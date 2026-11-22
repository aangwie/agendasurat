<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class AgendaRequest extends FormRequest
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
        $this->merge([
            'place' => trim((string) $this->input('place')),
            'person_in_charge' => trim((string) $this->input('person_in_charge')),
            'participants' => trim((string) $this->input('participants')),
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
            'date' => ['required', 'date'],
            'place' => ['required', 'string', 'max:150'],
            'person_in_charge' => ['required', 'string', 'max:150'],
            'participants' => ['required', 'string', 'max:255'],
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
            'date.required' => 'Tanggal wajib diisi.',
            'date.date' => 'Tanggal tidak valid.',
            'place.required' => 'Tempat wajib diisi.',
            'place.max' => 'Tempat maksimal 150 karakter.',
            'person_in_charge.required' => 'Penanggung jawab wajib diisi.',
            'person_in_charge.max' => 'Penanggung jawab maksimal 150 karakter.',
            'participants.required' => 'Peserta wajib diisi.',
            'participants.max' => 'Peserta maksimal 255 karakter.',
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
            'date' => 'tanggal',
            'place' => 'tempat',
            'person_in_charge' => 'penanggung jawab',
            'participants' => 'peserta',
        ];
    }

    /**
     * Reopen the create/edit modal after a validation failure.
     */
    protected function failedValidation(Validator $validator): void
    {
        $agenda = $this->route('agenda');

        session()->flash('open_form', $agenda ? 'edit' : 'create');

        if ($agenda) {
            session()->flash('open_form_id', $agenda->id);
        }

        parent::failedValidation($validator);
    }
}
