{{-- Modal tambah/edit surat masuk --}}
@php
    $letterDateValue = old('letter_date', now()->format('Y-m-d'));

    $letterDatePreview = rescue(
        fn () => \Illuminate\Support\Carbon::parse($letterDateValue)->locale('id')->translatedFormat('j F Y'),
        '—',
        false,
    );
@endphp

<div data-letter-modal class="fixed inset-0 z-[70] hidden" aria-hidden="true">
    <div data-modal-backdrop class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm"></div>

    <div class="relative flex min-h-full items-end justify-center p-4 sm:items-center">
        <div data-modal-panel role="dialog" aria-modal="true" aria-labelledby="letter-modal-title"
            class="max-h-[calc(100dvh-2rem)] w-full max-w-xl overflow-y-auto rounded-2xl bg-white shadow-2xl shadow-zinc-950/25">
            <div class="flex items-start justify-between gap-3 border-b border-zinc-100 px-5 py-4 sm:px-6">
                <div>
                    <h2 id="letter-modal-title" data-modal-title class="text-base font-bold text-zinc-900">
                        Tambah Surat Masuk
                    </h2>
                    <p data-modal-description class="mt-0.5 text-xs text-zinc-500">
                        Lengkapi data surat yang diterima instansi.
                    </p>
                </div>

                <button type="button" data-modal-close aria-label="Tutup formulir"
                    class="grid size-9 shrink-0 place-items-center rounded-lg text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700">
                    <x-icon name="x-mark" class="size-5" />
                </button>
            </div>

            <form data-letter-form method="POST" action="{{ route('surat-masuk.store') }}"
                data-store-action="{{ route('surat-masuk.store') }}"
                data-update-template="{{ route('surat-masuk.update', ['incomingLetter' => '__ID__']) }}">
                @csrf
                <input type="hidden" name="_method" value="" data-method-input disabled>

                <div class="space-y-4 px-5 py-5 sm:px-6">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="letter_date" class="form-label">
                                <x-icon name="calendar" class="size-4 text-zinc-400" />
                                Tanggal Surat
                                <span class="text-rose-500" aria-hidden="true">*</span>
                            </label>

                            <input id="letter_date" name="letter_date" type="date" value="{{ $letterDateValue }}"
                                required data-date-display="letter-date-preview"
                                @error('letter_date') aria-invalid="true" aria-describedby="letter_date-error" @enderror
                                @class(['form-input', 'form-input-error' => $errors->has('letter_date')])>

                            <p class="mt-1.5 text-xs text-zinc-400">
                                Tampil sebagai
                                <span id="letter-date-preview"
                                    class="font-semibold text-zinc-700">{{ $letterDatePreview }}</span>
                            </p>

                            <p class="mt-1 text-xs text-zinc-400">
                                Boleh diisi tanggal yang akan datang bila surat baru akan diterbitkan.
                            </p>

                            <x-form-error field="letter_date" />
                        </div>

                        <x-input-field name="letter_number" label="Nomor Surat" icon="inbox"
                            placeholder="Nomor surat dari instansi pengirim" maxlength="100" />
                    </div>

                    <x-input-field name="sender" label="Asal Surat" icon="building-office"
                        placeholder="Nama instansi pengirim" maxlength="150" />

                    <div>
                        <label for="subject" class="form-label">
                            <x-icon name="document-text" class="size-4 text-zinc-400" />
                            Perihal
                            <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>

                        <textarea id="subject" name="subject" rows="3" required maxlength="255"
                            placeholder="Ringkasan isi surat"
                            @error('subject') aria-invalid="true" aria-describedby="subject-error" @enderror
                            @class(['form-input', 'resize-y', 'form-input-error' => $errors->has('subject')])>{{ old('subject') }}</textarea>

                        <x-form-error field="subject" />
                    </div>

                    {{-- Sifat surat (checkbox) --}}
                    <fieldset>
                        <legend class="form-label">
                            <x-icon name="shield-check" class="size-4 text-zinc-400" />
                            Sifat Surat
                        </legend>

                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            @foreach (\App\Models\IncomingLetter::SIFAT_OPTIONS as $value => $label)
                                <label data-sifat-option
                                    class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-700 transition hover:border-zinc-300 hover:bg-zinc-50 has-checked:border-rose-400 has-checked:bg-rose-50/60 has-checked:text-rose-700 has-checked:ring-1 has-checked:ring-rose-200">
                                    <input type="checkbox" name="sifat[]" value="{{ $value }}"
                                        data-sifat-checkbox @checked(in_array($value, old('sifat', []), true))
                                        class="size-4 shrink-0 rounded border-zinc-300 accent-rose-600">

                                    <span class="font-medium">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>

                        <p class="mt-1.5 text-xs text-zinc-400">
                            Boleh pilih lebih dari satu. Kosongkan bila sifat surat biasa.
                        </p>

                        @error('sifat')
                            <p role="alert" class="mt-2 flex items-start gap-1.5 text-xs font-medium text-rose-600">
                                <x-icon name="x-mark" class="mt-px size-3.5 shrink-0" />
                                {{ $message }}
                            </p>
                        @enderror

                        @error('sifat.*')
                            <p role="alert" class="mt-2 flex items-start gap-1.5 text-xs font-medium text-rose-600">
                                <x-icon name="x-mark" class="mt-px size-3.5 shrink-0" />
                                {{ $message }}
                            </p>
                        @enderror
                    </fieldset>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input-field name="received_date" label="Tanggal Diterima" icon="calendar" type="date"
                            :value="now()->format('Y-m-d')" maxlength="10" />

                        <x-input-field name="agenda_number" label="Nomor Agenda" icon="document-text"
                            type="number" inputmode="numeric" min="1" step="1"
                            :value="$suggestedAgendaNumber" placeholder="1600"
                            hint="Berupa digit angka. Disarankan otomatis dari nomor agenda terbesar saat ini + 1." />
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-zinc-100 bg-zinc-50/70 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
                    <button type="button" data-modal-close class="btn btn-secondary">
                        Batal
                    </button>

                    <button type="submit" data-modal-submit class="btn btn-primary">
                        <x-icon name="check-circle" class="size-4" />
                        Simpan Surat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
