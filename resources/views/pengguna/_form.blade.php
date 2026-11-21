{{-- Modal tambah/edit pengguna --}}
<div data-user-modal class="fixed inset-0 z-[70] hidden" aria-hidden="true">
    <div data-modal-backdrop class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm"></div>

    <div class="relative flex min-h-full items-end justify-center p-4 sm:items-center">
        <div data-modal-panel role="dialog" aria-modal="true" aria-labelledby="user-modal-title"
            class="max-h-[calc(100dvh-2rem)] w-full max-w-xl overflow-y-auto rounded-2xl bg-white shadow-2xl shadow-zinc-950/25">
            <div class="flex items-start justify-between gap-3 border-b border-zinc-100 px-5 py-4 sm:px-6">
                <div>
                    <h2 id="user-modal-title" data-modal-title class="text-base font-bold text-zinc-900">
                        Tambah Pengguna
                    </h2>
                    <p data-modal-description class="mt-0.5 text-xs text-zinc-500">
                        Buat akun untuk bidang/unit kerja.
                    </p>
                </div>

                <button type="button" data-modal-close aria-label="Tutup formulir"
                    class="grid size-9 shrink-0 place-items-center rounded-lg text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700">
                    <x-icon name="x-mark" class="size-5" />
                </button>
            </div>

            <form data-user-form method="POST" action="{{ route('pengguna.store') }}"
                data-store-action="{{ route('pengguna.store') }}"
                data-update-template="{{ route('pengguna.update', ['pengguna' => '__ID__']) }}">
                @csrf
                <input type="hidden" name="_method" value="" data-method-input disabled>

                <div class="space-y-4 px-5 py-5 sm:px-6">
                    <x-input-field name="name" label="Nama Bidang" icon="building-office"
                        placeholder="Contoh: Bidang Pendidikan Dasar" maxlength="150" />

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input-field name="email" label="Email" icon="at-symbol" type="email"
                            placeholder="bidang@instansi.go.id" inputmode="email" maxlength="150" />

                        <x-input-field name="phone" label="No HP" icon="user" placeholder="081234567890"
                            inputmode="tel" maxlength="30" />
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4">
                        <p class="flex items-center gap-1.5 text-[11px] font-semibold tracking-wide text-zinc-500 uppercase">
                            <x-icon name="lock" class="size-3.5" />
                            Keamanan Akun
                        </p>

                        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="password" class="form-label">
                                    Password
                                    <span class="text-rose-500" aria-hidden="true">*</span>
                                </label>

                                <div class="relative">
                                    <input id="password" name="password" type="password" autocomplete="new-password"
                                        data-password-input
                                        placeholder="Minimal 8 karakter"
                                        @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                                        @class(['form-input', 'pr-11', 'form-input-error' => $errors->has('password')])>

                                    <button type="button" data-password-toggle="password" aria-pressed="false"
                                        aria-label="Tampilkan password"
                                        class="absolute inset-y-0 right-0 grid w-11 place-items-center rounded-r-xl text-zinc-400 transition hover:text-zinc-700">
                                        <x-icon name="eye" data-icon-show class="size-5" />
                                        <x-icon name="eye-slash" data-icon-hide class="hidden size-5" />
                                    </button>
                                </div>

                                <p data-password-hint class="mt-1.5 hidden text-xs text-zinc-400">
                                    Kosongkan bila password tidak diubah.
                                </p>

                                <x-form-error field="password" />
                            </div>

                            <div>
                                <label for="password_confirmation" class="form-label">
                                    Konfirmasi Password
                                    <span class="text-rose-500" aria-hidden="true">*</span>
                                </label>

                                <input id="password_confirmation" name="password_confirmation" type="password"
                                    autocomplete="new-password" placeholder="Ulangi password"
                                    data-password-confirm
                                    @class(['form-input', 'form-input-error' => $errors->has('password')])>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-zinc-100 bg-zinc-50/70 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
                    <button type="button" data-modal-close class="btn btn-secondary">Batal</button>

                    <button type="submit" data-modal-submit class="btn btn-primary">
                        <x-icon name="check-circle" class="size-4" />
                        Simpan Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
