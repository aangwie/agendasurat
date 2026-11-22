{{-- Modal tambah/edit agenda --}}
<div data-agenda-modal class="fixed inset-0 z-[70] hidden" aria-hidden="true">
    <div data-modal-backdrop class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm"></div>

    <div class="relative flex min-h-full items-end justify-center p-4 sm:items-center">
        <div data-modal-panel role="dialog" aria-modal="true" aria-labelledby="agenda-modal-title"
            class="max-h-[calc(100dvh-2rem)] w-full max-w-xl overflow-y-auto rounded-2xl bg-white shadow-2xl shadow-zinc-950/25">
            <div class="flex items-start justify-between gap-3 border-b border-zinc-100 px-5 py-4 sm:px-6">
                <div>
                    <h2 id="agenda-modal-title" data-modal-title class="text-base font-bold text-zinc-900">
                        Tambah Agenda
                    </h2>
                    <p data-modal-description class="mt-0.5 text-xs text-zinc-500">
                        Catat kegiatan yang akan dilaksanakan.
                    </p>
                </div>

                <button type="button" data-modal-close aria-label="Tutup formulir"
                    class="grid size-9 shrink-0 place-items-center rounded-lg text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700">
                    <x-icon name="x-mark" class="size-5" />
                </button>
            </div>

            <form data-agenda-form method="POST" action="{{ route('agenda.store') }}"
                data-store-action="{{ route('agenda.store') }}"
                data-update-template="{{ route('agenda.update', ['agenda' => '__ID__']) }}">
                @csrf
                <input type="hidden" name="_method" value="" data-method-input disabled>

                <div class="space-y-4 px-5 py-5 sm:px-6">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="date" class="form-label">
                                <x-icon name="calendar" class="size-4 text-zinc-400" />
                                Tanggal
                                <span class="text-rose-500" aria-hidden="true">*</span>
                            </label>

                            <input id="date" name="date" type="date" value="{{ old('date', now()->format('Y-m-d')) }}"
                                required data-date-display="agenda-date-preview"
                                @error('date') aria-invalid="true" aria-describedby="date-error" @enderror
                                @class(['form-input', 'form-input-error' => $errors->has('date')])>

                            <p class="mt-1.5 text-xs text-zinc-400">
                                Tampil sebagai
                                <span id="agenda-date-preview" class="font-semibold text-zinc-700">—</span>
                            </p>

                            <x-form-error field="date" />
                        </div>

                        <x-input-field name="person_in_charge" label="Penanggung Jawab" icon="user"
                            placeholder="Nama penanggung jawab" maxlength="150" />
                    </div>

                    <x-input-field name="place" label="Tempat" icon="map-pin"
                        placeholder="Contoh: Ruang Rapat Utama" maxlength="150" />

                    <div>
                        <label for="participants" class="form-label">
                            <x-icon name="users" class="size-4 text-zinc-400" />
                            Peserta
                            <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>

                        <textarea id="participants" name="participants" rows="3" required maxlength="255"
                            placeholder="Contoh: Seluruh Kepala Bidang dan staf Tata Usaha"
                            @error('participants') aria-invalid="true" aria-describedby="participants-error" @enderror
                            @class(['form-input', 'resize-y', 'form-input-error' => $errors->has('participants')])>{{ old('participants') }}</textarea>

                        <x-form-error field="participants" />
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-zinc-100 bg-zinc-50/70 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
                    <button type="button" data-modal-close class="btn btn-secondary">Batal</button>

                    <button type="submit" data-modal-submit class="btn btn-primary">
                        <x-icon name="check-circle" class="size-4" />
                        Simpan Agenda
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
