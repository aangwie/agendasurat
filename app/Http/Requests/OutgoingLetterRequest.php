<?php

namespace App\Http\Requests;

use App\Models\OutgoingLetter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OutgoingLetterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $letter = $this->route('outgoingLetter');

        if ($user === null) {
            return false;
        }

        return $letter === null || $letter->canBeManagedBy($user);
    }

    /**
     * Normalize the input before validation.
     */
    protected function prepareForValidation(): void
    {
        $letter = $this->route('outgoingLetter');
        $user = $this->user();
        $isUpdate = $letter instanceof OutgoingLetter;

        // Tahun, bulan, kode, subkode, dan bidang tidak dapat diubah saat edit.
        $year = $isUpdate ? (int) $letter->year : (int) $this->input('year');
        $month = $isUpdate ? (int) $letter->month : (int) $this->input('month');
        $code = $isUpdate ? (string) $letter->code : trim((string) $this->input('code'));
        $subCode = $isUpdate ? (string) $letter->sub_code : trim((string) $this->input('sub_code'));
        $bidang = $isUpdate ? $letter->bidang : $user?->name;

        // Kode bidang mengikuti akun pembuat. Surat lama memakai kode yang tersimpan,
        // dan bila kosong memakai kode bidang akun yang sedang mengedit.
        $kodeBidang = $isUpdate
            ? ($letter->kode_bidang ?: $user?->kode_bidang)
            : $user?->kode_bidang;

        $prefix = trim((string) $this->input('letter_prefix'));

        $this->merge([
            'year' => $year,
            'month' => $month,
            'code' => $code,
            'sub_code' => $subCode,
            'bidang' => $bidang,
            'kode_bidang' => $kodeBidang,
            'letter_prefix' => $prefix,
            // Nomor lengkap selalu disusun ulang di server, bukan dari input bebas.
            'letter_number' => OutgoingLetter::composeNumber(
                $prefix,
                $code,
                $subCode,
                (string) $kodeBidang,
                $year,
            ),
            'subject' => trim((string) $this->input('subject')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $letter = $this->route('outgoingLetter');

        $rules = [
            'letter_prefix' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9][A-Za-z0-9.\-]*$/',
            ],
            'letter_number' => ['required', 'string', 'max:100'],
            'subject' => ['required', 'string', 'max:255'],
            'letter_date' => ['required', 'date'],
            'bidang' => ['required', 'string', 'max:150'],
            'kode_bidang' => ['required', 'string', 'max:30'],
        ];

        if ($letter instanceof OutgoingLetter) {
            return $rules;
        }

        return $rules + [
            'year' => ['required', 'integer', 'between:'.OutgoingLetter::MIN_YEAR.','.OutgoingLetter::MAX_YEAR],
            'month' => ['required', 'integer', 'between:1,12'],
            'code' => [
                'required',
                'string',
                'size:2',
                Rule::in(OutgoingLetter::codesForMonth((int) $this->input('month'))),
            ],
            'sub_code' => [
                'required',
                'string',
                'size:2',
                Rule::in(OutgoingLetter::allSubCodes()),
                Rule::unique('outgoing_letters', 'sub_code')
                    ->where('year', (int) $this->input('year'))
                    ->where('code', (string) $this->input('code')),
            ],
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
            'letter_prefix.required' => 'Kode depan nomor surat wajib diisi.',
            'letter_prefix.max' => 'Kode depan maksimal 50 karakter.',
            'letter_prefix.regex' => 'Kode depan hanya boleh berisi huruf, angka, titik, dan tanda hubung. Contoh: 400.3.5.',
            'letter_number.required' => 'Nomor surat keluar belum dapat disusun.',
            'letter_number.max' => 'Nomor surat keluar maksimal 100 karakter.',
            'subject.required' => 'Perihal wajib diisi.',
            'subject.max' => 'Perihal maksimal 255 karakter.',
            'letter_date.required' => 'Tanggal surat wajib diisi.',
            'letter_date.date' => 'Tanggal surat tidak valid.',
            'year.required' => 'Tahun wajib dipilih.',
            'year.integer' => 'Tahun tidak valid.',
            'year.between' => 'Tahun harus antara '.OutgoingLetter::MIN_YEAR.' dan '.OutgoingLetter::MAX_YEAR.'.',
            'month.required' => 'Bulan wajib dipilih.',
            'month.integer' => 'Bulan tidak valid.',
            'month.between' => 'Bulan tidak valid.',
            'code.required' => 'Kode wajib dipilih.',
            'code.size' => 'Kode harus terdiri dari 2 digit.',
            'code.in' => 'Kode tersebut bukan milik bulan yang dipilih.',
            'sub_code.required' => 'Subkode wajib dipilih.',
            'sub_code.size' => 'Subkode harus terdiri dari 2 digit.',
            'sub_code.in' => 'Subkode harus antara 01 sampai 99.',
            'sub_code.unique' => 'Kode dan subkode tersebut sudah dipakai pada tahun ini.',
            'bidang.required' => 'Bidang wajib tersedia pada akun Anda.',
            'bidang.max' => 'Bidang maksimal 150 karakter.',
            'kode_bidang.required' => 'Akun Anda belum memiliki kode bidang. Hubungi administrator untuk melengkapinya.',
            'kode_bidang.max' => 'Kode bidang maksimal 30 karakter.',
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
            'letter_prefix' => 'kode depan nomor surat',
            'letter_number' => 'nomor surat keluar',
            'kode_bidang' => 'kode bidang',
            'subject' => 'perihal',
            'letter_date' => 'tanggal surat',
            'year' => 'tahun',
            'month' => 'bulan',
            'code' => 'kode',
            'sub_code' => 'subkode',
            'bidang' => 'bidang',
        ];
    }

    /**
     * Reopen the create/edit modal after a validation failure.
     */
    protected function failedValidation(Validator $validator): void
    {
        $letter = $this->route('outgoingLetter');

        session()->flash('open_form', $letter ? 'edit' : 'create');

        if ($letter) {
            session()->flash('open_form_id', $letter->id);
        }

        parent::failedValidation($validator);
    }
}
