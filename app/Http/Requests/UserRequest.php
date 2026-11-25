<?php

namespace App\Http\Requests;

use App\Services\WhatsappNotifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Normalize the input before validation.
     */
    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/[\s\-().]/', '', (string) $this->input('phone'));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'alias' => trim((string) $this->input('alias')) ?: null,
            'kode_bidang' => trim((string) $this->input('kode_bidang')) ?: null,
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'phone' => $phone === '' ? null : $phone,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('pengguna');
        $isUpdate = $user !== null;

        return [
            'name' => ['required', 'string', 'max:150'],
            'alias' => ['nullable', 'string', 'max:30'],
            'kode_bidang' => [
                'nullable',
                'string',
                'max:30',
                'regex:/^[0-9]{1,4}(\.[0-9]{1,4}){0,3}$/',
                Rule::unique('users', 'kode_bidang')->ignore($user?->id),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'phone' => [
                'nullable',
                'string',
                'regex:/^(\+62|62|0)8[0-9]{7,13}$/',
                // Nomor administrator tidak boleh sama dengan nomor WhatsApp gateway,
                // karena notifikasi akan dikirim ke perangkat itu sendiri.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (blank($value) || ! $this->boolean('is_admin')) {
                        return;
                    }

                    $notifier = app(WhatsappNotifier::class);
                    $gatewayPhone = $notifier->gatewayPhone();

                    if ($gatewayPhone !== null && $notifier->normalizePhone((string) $value) === $gatewayPhone) {
                        $fail('Nomor HP administrator tidak boleh sama dengan nomor WhatsApp gateway (+'.$gatewayPhone.').');
                    }
                },
            ],
            'password' => $isUpdate
                ? ['nullable', 'string', Password::min(8), 'confirmed']
                : ['required', 'string', Password::min(8), 'confirmed'],
            'is_admin' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom validation messages in Indonesian.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama bidang wajib diisi.',
            'name.max' => 'Nama bidang maksimal 150 karakter.',
            'alias.max' => 'Alias maksimal 30 karakter.',
            'kode_bidang.max' => 'Kode bidang maksimal 30 karakter.',
            'kode_bidang.regex' => 'Kode bidang tidak valid. Contoh: 408.37.06.',
            'kode_bidang.unique' => 'Kode bidang tersebut sudah dipakai pengguna lain.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email tersebut sudah dipakai pengguna lain.',
            'phone.regex' => 'No HP tidak valid. Contoh: 081234567890.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }

    /**
     * Get custom attribute names in Indonesian.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama bidang',
            'alias' => 'alias',
            'kode_bidang' => 'kode bidang',
            'email' => 'email',
            'phone' => 'no hp',
            'password' => 'password',
        ];
    }

    /**
     * Reopen the create/edit modal after a validation failure.
     */
    protected function failedValidation(Validator $validator): void
    {
        $user = $this->route('pengguna');

        session()->flash('open_form', $user ? 'edit' : 'create');

        if ($user) {
            session()->flash('open_form_id', $user->id);
        }

        parent::failedValidation($validator);
    }
}
