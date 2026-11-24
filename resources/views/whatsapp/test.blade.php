<x-layouts.app title="Uji Pesan WhatsApp">
    <div class="space-y-6" data-whatsapp-test
        data-send-url="{{ route('whatsapp.test.send') }}"
        data-status-url="{{ route('whatsapp.status') }}"
        data-session-phone="{{ $session['phone_number'] }}">

        {{-- Ringkasan --}}
        <section
            class="relative overflow-hidden rounded-3xl bg-zinc-950 px-6 py-6 text-white shadow-lg shadow-zinc-900/10 sm:px-8">
            <div class="bg-grid-rose absolute inset-0 opacity-30" aria-hidden="true"></div>
            <div class="absolute -top-24 right-0 size-64 rounded-full bg-emerald-500/20 blur-3xl" aria-hidden="true">
            </div>

            <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-2xl">
                    <span class="badge bg-white/5 text-emerald-200 ring-1 ring-white/10">
                        <x-icon name="paper-airplane" class="size-3.5" />
                        Uji Pengiriman
                    </span>

                    <h2 class="mt-3 text-xl font-extrabold tracking-tight sm:text-2xl">Uji Pesan WhatsApp</h2>

                    <p class="mt-1.5 text-sm leading-relaxed text-zinc-400">
                        Kirim pesan percobaan melalui gateway untuk memastikan perangkat sudah tertaut dan pesan
                        terkirim dengan benar.
                    </p>

                    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                        <span class="badge bg-white/5 font-mono text-[11px] text-zinc-400 ring-1 ring-white/10">
                            <x-icon name="globe-alt" class="size-3.5" />
                            {{ $baseUrl }}
                        </span>

                        <span class="badge bg-white/5 font-mono text-[11px] text-zinc-400 ring-1 ring-white/10">
                            <x-icon name="key" class="size-3.5" />
                            {{ $deviceId }}
                        </span>

                        <span data-wa-test-connection class="badge bg-white/10 text-zinc-200 ring-1 ring-white/10">
                            <span data-wa-test-dot class="size-1.5 rounded-full bg-zinc-400"></span>
                            <span data-wa-test-label>Memeriksa koneksi...</span>
                        </span>
                    </div>
                </div>

                <a href="{{ route('whatsapp.index') }}"
                    class="btn border border-white/10 bg-white/10 py-2.5 text-white hover:bg-white/15">
                    <x-icon name="qr-code" class="size-4" />
                    Halaman QR &amp; Sesi
                </a>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            {{-- Form uji --}}
            <section class="card p-5 sm:p-6 xl:col-span-2">
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-600 ring-1 ring-rose-100">
                        <x-icon name="paper-airplane" class="size-5" />
                    </span>

                    <div>
                        <h3 class="text-base font-bold text-zinc-900">Formulir Pesan Uji</h3>
                        <p class="mt-0.5 text-xs text-zinc-500">
                            Pesan dikirim memakai device <span class="font-mono">{{ $deviceId }}</span>.
                        </p>
                    </div>
                </div>

                <form data-wa-test-form class="mt-5 space-y-4">
                    <div>
                        <label for="recipient" class="form-label">
                            <x-icon name="at-symbol" class="size-4 text-zinc-400" />
                            Nomor Tujuan
                        </label>

                        <input id="recipient" name="recipient" type="tel" list="wa-recipients"
                            value="{{ $prefillRecipient }}" placeholder="08xxxxxxxxxx" autocomplete="off" required
                            class="form-input font-mono">

                        <datalist id="wa-recipients">
                            @foreach ($users as $user)
                                <option value="{{ $user->phone }}">
                                    {{ $user->name }}@if ($user->kode_bidang)
                                        &middot; {{ $user->kode_bidang }}
                                    @endif
                                </option>
                            @endforeach
                        </datalist>

                        <p class="mt-1.5 text-[11px] text-zinc-400">
                            Format 08xx, 62xx, atau +62xx. Nomor dinormalisasi otomatis sebelum dikirim.
                        </p>

                        @error('recipient')
                            <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <label for="message" class="form-label">
                                <x-icon name="document-text" class="size-4 text-zinc-400" />
                                Isi Pesan
                            </label>

                            <span data-wa-counter class="text-[11px] font-medium text-zinc-400">0 / 4096</span>
                        </div>

                        <textarea id="message" name="message" rows="6" maxlength="4096" required
                            placeholder="Tulis pesan uji di sini..." class="form-input resize-y">{{ old('message', 'Uji coba pengiriman pesan dari Agenda Surat.') }}</textarea>

                        @error('message')
                            <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" data-wa-submit class="btn btn-primary">
                            <x-icon name="paper-airplane" class="size-4" />
                            Kirim Pesan Uji
                        </button>

                        @if ($session['phone_number'])
                            <button type="button" data-wa-fill-self class="btn btn-secondary">
                                <x-icon name="whatsapp" class="size-4" />
                                Isi Nomor Saya
                            </button>
                        @endif
                    </div>
                </form>

                <div data-wa-result class="mt-5 hidden rounded-2xl border px-4 py-3" role="status" aria-live="polite">
                    <p data-wa-result-title class="flex items-center gap-2 text-sm font-semibold"></p>
                    <p data-wa-result-text class="mt-1 text-xs leading-relaxed"></p>
                </div>
            </section>

            {{-- Status & kontak cepat --}}
            <section class="card p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-600/25">
                        <x-icon name="whatsapp" class="size-5" />
                    </span>

                    <div>
                        <h3 class="text-base font-bold text-zinc-900">Status Perangkat</h3>
                        <p class="mt-0.5 text-xs text-zinc-500">Pastikan perangkat tertaut sebelum mengirim.</p>
                    </div>
                </div>

                <dl class="mt-4 space-y-2.5 text-sm">
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-zinc-50 px-3 py-2.5">
                        <dt class="text-xs font-medium text-zinc-500">Koneksi</dt>
                        <dd data-wa-test-status class="text-xs font-semibold text-zinc-700">&mdash;</dd>
                    </div>

                    <div class="flex items-center justify-between gap-3 rounded-xl bg-zinc-50 px-3 py-2.5">
                        <dt class="text-xs font-medium text-zinc-500">Nomor aktif</dt>
                        <dd class="font-mono text-xs font-semibold text-zinc-800">
                            {{ $session['phone_number'] ? '+'.$session['phone_number'] : 'Belum ada' }}
                        </dd>
                    </div>
                </dl>

                @if ($users->isNotEmpty())
                    <div class="mt-5">
                        <p class="text-[10px] font-semibold tracking-wide text-zinc-400 uppercase">Kontak cepat</p>

                        <div class="scrollbar-slim mt-2 max-h-56 space-y-1 overflow-y-auto pr-1">
                            @foreach ($users as $user)
                                <button type="button" data-wa-pick-phone="{{ $user->phone }}"
                                    class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left transition hover:bg-zinc-50">
                                    <span class="min-w-0">
                                        <span class="block truncate text-xs font-semibold text-zinc-700">
                                            {{ $user->name }}
                                        </span>
                                        <span class="block truncate text-[11px] text-zinc-400">
                                            {{ $user->kode_bidang ?: 'Tanpa kode bidang' }}
                                        </span>
                                    </span>

                                    <span class="shrink-0 font-mono text-[11px] text-zinc-500">{{ $user->phone }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="mt-5 rounded-xl bg-zinc-50 px-3 py-2 text-[11px] leading-relaxed text-zinc-500">
                        Belum ada pengguna dengan nomor HP. Isi nomor tujuan secara manual.
                    </p>
                @endif
            </section>
        </div>

        {{-- Riwayat pengujian (client-side) --}}
        <section class="card p-5 sm:p-6">
            <div class="flex items-start gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-zinc-900 text-white">
                    <x-icon name="clock" class="size-5" />
                </span>

                <div>
                    <h3 class="text-base font-bold text-zinc-900">Riwayat Pengujian</h3>
                    <p class="mt-0.5 text-xs text-zinc-500">Hanya tersimpan selama halaman ini terbuka.</p>
                </div>
            </div>

            <ol data-wa-history class="mt-4 space-y-2">
                <li data-wa-history-empty
                    class="rounded-xl bg-zinc-50 px-3 py-2.5 text-xs text-zinc-500">
                    Belum ada pesan uji yang dikirim.
                </li>
            </ol>
        </section>
    </div>
</x-layouts.app>
