<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateInstitutionProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Normalize the website so users can type "diskominfo.go.id" without a scheme.
     */
    protected function prepareForValidation(): void
    {
        $website = trim((string) $this->input('website'));

        $this->merge([
            'website' => $website === '' ? null : Str::of($website)->replaceMatches('#^https?://#i', '')->value(),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'government_name' => ['required', 'string', 'max:150'],
            'institution_name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:500'],
            'website' => ['required', 'string', 'max:150', 'regex:/^(?!-)(?:[a-z0-9-]+\.)+[a-z]{2,}(?::\d{2,5})?(?:\/[^\s]*)?$/i'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
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
            'government_name.required' => 'Nama pemerintahan wajib diisi.',
            'government_name.max' => 'Nama pemerintahan maksimal 150 karakter.',
            'institution_name.required' => 'Nama instansi wajib diisi.',
            'institution_name.max' => 'Nama instansi maksimal 150 karakter.',
            'address.required' => 'Alamat instansi wajib diisi.',
            'address.max' => 'Alamat instansi maksimal 500 karakter.',
            'website.required' => 'Alamat website wajib diisi.',
            'website.regex' => 'Alamat website tidak valid. Contoh: diskominfo.go.id',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'logo.image' => 'Berkas logo harus berupa gambar.',
            'logo.mimes' => 'Logo harus berformat PNG, JPG, JPEG, atau WEBP.',
            'logo.max' => 'Ukuran logo maksimal 2 MB.',
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
            'government_name' => 'nama pemerintahan',
            'institution_name' => 'nama instansi',
            'address' => 'alamat instansi',
            'website' => 'alamat website',
            'email' => 'alamat email',
            'logo' => 'logo pemerintah',
        ];
    }
}
