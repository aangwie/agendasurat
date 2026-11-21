<x-layouts.app title="Profil Instansi">
    @php
        $logoUrl = $profile->logo_url;
        $isConfigured = filled($profile->institution_name) || filled($profile->government_name);
    @endphp

    <div class="mx-auto max-w-5xl space-y-6">
        {{-- Ringkasan identitas instansi --}}
        <section
            class="relative overflow-hidden rounded-3xl bg-zinc-950 px-6 py-6 text-white shadow-lg shadow-zinc-900/10 sm:px-8">
            <div class="bg-grid-rose absolute inset-0 opacity-30" aria-hidden="true"></div>
            <div class="absolute -top-24 right-0 size-64 rounded-full bg-rose-600/25 blur-3xl" aria-hidden="true"></div>

            <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center">
                <div
                    class="grid size-20 shrink-0 place-items-center overflow-hidden rounded-2xl border border-white/10 bg-white/5 p-2.5 backdrop-blur">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="Logo {{ $profile->institution_name ?: 'instansi' }}"
                            class="size-full object-contain">
                    @else
                        <x-icon name="building-office" class="size-8 text-zinc-500" />
                    @endif
                </div>

                <div class="min-w-0">
                    <span class="badge bg-white/5 text-rose-200 ring-1 ring-white/10">
                        <x-icon name="shield-check" class="size-3.5" />
                        Identitas instansi
                    </span>

                    <h2 class="mt-2 truncate text-xl font-extrabold tracking-tight sm:text-2xl">
                        {{ $profile->institution_name ?: 'Instansi belum diatur' }}
                    </h2>

                    <p class="mt-1 truncate text-sm text-zinc-400">
                        {{ $profile->government_name ?: 'Lengkapi nama pemerintahan pada formulir di bawah.' }}
                    </p>

                    @if ($isConfigured)
                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-zinc-400">
                            @if ($profile->website)
                                <span class="inline-flex items-center gap-1.5">
                                    <x-icon name="globe-alt" class="size-3.5 text-zinc-500" />
                                    {{ $profile->website }}
                                </span>
                            @endif

                            @if ($profile->email)
                                <span class="inline-flex items-center gap-1.5">
                                    <x-icon name="at-symbol" class="size-3.5 text-zinc-500" />
                                    {{ $profile->email }}
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <form method="POST" action="{{ route('profil-instansi.update') }}" enctype="multipart/form-data"
            class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            @csrf
            @method('PUT')

            {{-- Data instansi --}}
            <section class="card p-5 sm:p-6 lg:col-span-2">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-zinc-900">Data Instansi</h3>
                        <p class="mt-1 text-xs text-zinc-500">
                            Perbarui identitas instansi yang dipakai pada kop surat dan halaman aplikasi.
                        </p>
                    </div>

                    <span class="badge shrink-0 bg-zinc-100 text-zinc-600">
                        <x-icon name="document-text" class="size-3.5" />
                        Update
                    </span>
                </div>

                @if ($errors->any())
                    <div class="mt-5 flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
                        role="alert">
                        <x-icon name="x-mark" class="mt-0.5 size-4 shrink-0 text-rose-500" />
                        <p>Ada {{ $errors->count() }} data yang perlu diperbaiki. Silakan periksa kembali formulir.</p>
                    </div>
                @endif

                <div class="mt-6 space-y-5">
                    <x-input-field name="government_name" label="Nama Pemerintahan" icon="building-office"
                        :value="$profile->government_name" placeholder="Contoh: Pemerintah Kota Bandung" maxlength="150" />

                    <x-input-field name="institution_name" label="Nama Instansi" icon="building-office"
                        :value="$profile->institution_name" placeholder="Contoh: Dinas Komunikasi dan Informatika"
                        maxlength="150" />

                    <div>
                        <label for="address" class="form-label">
                            <x-icon name="map-pin" class="size-4 text-zinc-400" />
                            Alamat Instansi
                            <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>

                        <textarea id="address" name="address" rows="3" required maxlength="500"
                            placeholder="Contoh: Jl. Wastukancana No. 2, Bandung 40117"
                            @error('address') aria-invalid="true" aria-describedby="address-error" @enderror
                            @class(['form-input', 'resize-y', 'form-input-error' => $errors->has('address')])>{{ old('address', $profile->address) }}</textarea>

                        <x-form-error field="address" />
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-input-field name="website" label="Alamat Website" icon="globe-alt" :value="$profile->website"
                            placeholder="diskominfo.go.id" inputmode="url" maxlength="150"
                            hint="Tanpa http:// atau https://" />

                        <x-input-field name="email" label="Alamat Email" icon="at-symbol" type="email"
                            :value="$profile->email" placeholder="diskominfo@bandung.go.id" inputmode="email"
                            maxlength="150" />
                    </div>
                </div>
            </section>

            {{-- Logo & aksi --}}
            <aside class="space-y-6">
                <section class="card p-5 sm:p-6">
                    <div class="flex items-center gap-2">
                        <x-icon name="photo" class="size-4 text-rose-500" />
                        <h3 class="text-sm font-bold tracking-wide text-zinc-900 uppercase">Logo Pemerintah</h3>
                    </div>

                    <div class="mt-5 rounded-2xl border-2 border-dashed border-zinc-200 bg-zinc-50/70 p-4 text-center transition focus-within:border-rose-400 focus-within:bg-rose-50/40 focus-within:ring-4 focus-within:ring-rose-500/10 hover:border-rose-300 hover:bg-rose-50/40">
                        <div class="mx-auto grid size-24 place-items-center overflow-hidden rounded-xl bg-white p-2 ring-1 ring-zinc-200">
                            <img id="logo-preview" src="{{ $logoUrl }}" alt="Pratinjau logo pemerintah"
                                class="size-full object-contain {{ $logoUrl ? '' : 'hidden' }}">

                            <span id="logo-placeholder" @class(['text-zinc-300', 'hidden' => filled($logoUrl)])>
                                <x-icon name="photo" class="size-8" />
                            </span>
                        </div>

                        <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"
                            class="sr-only" data-image-preview="logo-preview" data-image-placeholder="logo-placeholder"
                            data-image-filename="logo-filename"
                            @error('logo') aria-invalid="true" aria-describedby="logo-error" @enderror>

                        <label for="logo" class="btn btn-secondary mt-4 cursor-pointer">
                            <x-icon name="arrow-up-tray" class="size-4" />
                            {{ $logoUrl ? 'Ganti Logo' : 'Upload Logo' }}
                        </label>

                        <p class="mt-2 text-[11px] leading-relaxed text-zinc-400">
                            PNG, JPG, atau WEBP &middot; maks 2 MB
                            <br>Rasio 1:1 disarankan agar logo tidak terpotong.
                        </p>

                        <p id="logo-filename" class="mt-1.5 truncate text-xs font-medium text-zinc-600"></p>
                    </div>

                    <x-form-error field="logo" />

                    @if ($profile->logo_path)
                        <p class="mt-3 flex items-center gap-1.5 text-[11px] text-zinc-400">
                            <x-icon name="check-circle" class="size-3.5 text-emerald-500" />
                            Logo tersimpan. Unggah berkas baru untuk menggantinya.
                        </p>
                    @endif
                </section>

                <section class="card p-5 sm:p-6">
                    <h3 class="text-sm font-bold tracking-wide text-zinc-900 uppercase">Simpan Perubahan</h3>
                    <p class="mt-2 text-xs leading-relaxed text-zinc-500">
                        Data yang disimpan akan langsung dipakai pada kop surat dan halaman aplikasi.
                    </p>

                    <button type="submit" class="btn btn-primary mt-4 w-full">
                        <x-icon name="check-circle" class="size-4" />
                        Simpan Perubahan
                    </button>

                    <a href="{{ route('dashboard') }}" class="btn btn-secondary mt-2.5 w-full">
                        Batal
                    </a>
                </section>
            </aside>
        </form>
    </div>
</x-layouts.app>
