<?php

namespace App\Http\Requests;

use App\Models\IncomingLetter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncomingLetterRequest extends FormRequest
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
            'agenda_number' => trim((string) $this->input('agenda_number')),
            'letter_number' => trim((string) $this->input('letter_number')),
            'sender' => trim((string) $this->input('sender')),
            'subject' => trim((string) $this->input('subject')),
            // Checkbox: pastikan selalu berupa array agar nilai lama ikut terhapus
            // ketika seluruh centang dilepas.
            'sifat' => array_values(array_unique(array_filter(
                (array) $this->input('sifat', []),
                fn ($value) => is_string($value) && $value !== '',
            ))),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $letter = $this->route('incomingLetter');

        return [
            'agenda_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('incoming_letters', 'agenda_number')->ignore($letter?->id),
            ],
            'received_date' => ['required', 'date', 'before_or_equal:today'],
            'letter_date' => ['required', 'date', 'before_or_equal:today'],
            'letter_number' => ['required', 'string', 'max:100'],
            'sender' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
            'sifat' => ['nullable', 'array'],
            'sifat.*' => ['string', Rule::in(array_keys(IncomingLetter::SIFAT_OPTIONS))],
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
            'agenda_number.required' => 'Nomor agenda wajib diisi.',
            'agenda_number.unique' => 'Nomor agenda tersebut sudah dipakai surat lain.',
            'agenda_number.max' => 'Nomor agenda maksimal 50 karakter.',
            'received_date.required' => 'Tanggal diterima wajib diisi.',
            'received_date.date' => 'Tanggal diterima tidak valid.',
            'received_date.before_or_equal' => 'Tanggal diterima tidak boleh melebihi hari ini.',
            'letter_date.required' => 'Tanggal surat wajib diisi.',
            'letter_date.date' => 'Tanggal surat tidak valid.',
            'letter_date.before_or_equal' => 'Tanggal surat tidak boleh melebihi hari ini.',
            'letter_number.required' => 'Nomor surat wajib diisi.',
            'letter_number.max' => 'Nomor surat maksimal 100 karakter.',
            'sender.required' => 'Asal surat wajib diisi.',
            'sender.max' => 'Asal surat maksimal 150 karakter.',
            'subject.required' => 'Perihal wajib diisi.',
            'subject.max' => 'Perihal maksimal 255 karakter.',
            'sifat.array' => 'Sifat surat tidak valid.',
            'sifat.*.in' => 'Pilihan sifat surat tidak dikenali.',
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
            'agenda_number' => 'nomor agenda',
            'received_date' => 'tanggal diterima',
            'letter_date' => 'tanggal surat',
            'letter_number' => 'nomor surat',
            'sender' => 'asal surat',
            'subject' => 'perihal',
            'sifat' => 'sifat surat',
        ];
    }

    /**
     * Reopen the create/edit modal after a validation failure.
     */
    protected function failedValidation(Validator $validator): void
    {
        $letter = $this->route('incomingLetter');

        session()->flash('open_form', $letter ? 'edit' : 'create');

        if ($letter) {
            session()->flash('open_form_id', $letter->id);
        }

        parent::failedValidation($validator);
    }
}
