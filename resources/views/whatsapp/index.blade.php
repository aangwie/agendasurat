<x-layouts.app title="Whatsapp Gateway">
    <div class="space-y-6" data-whatsapp-gateway
        data-qr-url="{{ route('whatsapp.qr') }}"
        data-status-url="{{ route('whatsapp.status') }}"
        data-save-url="{{ route('whatsapp.update') }}"
        data-session-url="{{ route('whatsapp.session.save') }}"
        data-disconnect-url="{{ route('whatsapp.disconnect') }}"
        data-poll-interval="{{ $pollInterval }}">

        {{-- Ringkasan koneksi --}}
        <section
            class="relative overflow-hidden rounded-3xl bg-zinc-950 px-6 py-6 text-white shadow-lg shadow-zinc-900/10 sm:px-8">
            <div class="bg-grid-rose absolute inset-0 opacity-30" aria-hidden="true"></div>
            <div class="absolute -top-24 right-0 size-64 rounded-full bg-emerald-500/20 blur-3xl" aria-hidden="true">
            </div>

            <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-2xl">
                    <span class="badge bg-white/5 text-emerald-200 ring-1 ring-white/10">
                        <x-icon name="whatsapp" class="size-3.5" />
                        Administrator
                    </span>

                    <h2 class="mt-3 text-xl font-extrabold tracking-tight sm:text-2xl">WhatsApp Gateway</h2>

                    <p class="mt-1.5 text-sm leading-relaxed text-zinc-400">
                        Tautkan perangkat WhatsApp dengan memindai QR, simpan sesinya, lalu uji pengiriman pesan
                        langsung dari aplikasi.
                    </p>

                    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                        <span data-wa-status-badge class="badge bg-white/10 text-zinc-200 ring-1 ring-white/10">
                            <span data-wa-status-dot class="size-1.5 rounded-full bg-zinc-400"></span>
                            <span data-wa-status-label>Memeriksa status...</span>
                        </span>

                        <span class="badge bg-white/5 font-mono text-[11px] text-zinc-400 ring-1 ring-white/10">
                            <x-icon name="globe-alt" class="size-3.5" />
                            <span data-wa-gateway-label>{{ $baseUrl }}</span>
                        </span>

                        <span class="badge bg-white/5 font-mono text-[11px] text-zinc-400 ring-1 ring-white/10">
                            <x-icon name="key" class="size-3.5" />
                            <span data-wa-device-label>{{ $deviceId }}</span>
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" data-wa-refresh
                        class="btn border border-emerald-600 bg-emerald-500 py-2.5 text-white shadow-sm shadow-emerald-600/30 hover:bg-emerald-400">
                        <x-icon name="arrow-path" class="size-4" />
                        Muat QR
                    </button>

                    <a href="{{ route('whatsapp.test') }}"
                        class="btn border border-white/10 bg-white/10 py-2.5 text-white hover:bg-white/15">
                        <x-icon name="paper-airplane" class="size-4" />
                        Uji Pesan
                    </a>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            {{-- QR Code --}}
            <section class="card overflow-hidden xl:col-span-2">
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 px-5 py-4 sm:px-6">
                    <div class="flex items-start gap-3">
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100">
                            <x-icon name="qr-code" class="size-5" />
                        </span>

                        <div>
                            <h3 class="text-base font-bold text-zinc-900">Pindai QR</h3>
                            <p class="mt-0.5 text-xs text-zinc-500">
                                WhatsApp &gt; Perangkat Tertaut &gt; Tautkan perangkat.
                            </p>
                        </div>
                    </div>

                    <span data-wa-connection-badge class="badge bg-zinc-100 text-zinc-600">
                        <span data-wa-connection-dot class="size-1.5 rounded-full bg-zinc-400"></span>
                        <span data-wa-connection-label>Belum terhubung</span>
                    </span>
                </div>

                <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-[minmax(0,240px)_minmax(0,1fr)]">
                    <div>
                        <div data-wa-qr-box
                            class="relative mx-auto grid aspect-square w-full max-w-[240px] place-items-center overflow-hidden rounded-2xl border border-dashed border-zinc-300 bg-white p-3">

                            <img data-wa-qr-image alt="QR WhatsApp Gateway"
                                class="hidden h-full w-full rounded-xl object-contain">

                            <div data-wa-qr-placeholder class="absolute inset-0">
                                <div class="flex h-full flex-col items-center justify-center gap-2 px-4 text-center">
                                    <span
                                        class="grid size-12 place-items-center rounded-full bg-zinc-100 text-zinc-400">
                                        <x-icon name="qr-code" class="size-6" />
                                    </span>
                                    <p data-wa-qr-placeholder-title class="text-xs font-semibold text-zinc-600">QR belum dimuat</p>
                                    <p data-wa-qr-placeholder-text class="text-[11px] leading-relaxed text-zinc-400">
                                        Klik "Muat QR" untuk menampilkan kode QR.
                                    </p>
                                </div>
                            </div>

                            <div data-wa-qr-loading class="absolute inset-0 hidden">
                                <div class="flex h-full flex-col items-center justify-center gap-2 px-4 text-center">
                                    <svg class="size-8 animate-spin text-emerald-500" viewBox="0 0 24 24"
                                        fill="none" aria-hidden="true">
                                        <circle cx="12" cy="12" r="9" stroke="currentColor"
                                            stroke-opacity="0.25" stroke-width="3" />
                                        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3"
                                            stroke-linecap="round" />
                                    </svg>
                                    <p class="text-xs font-medium text-zinc-500">Menyiapkan QR...</p>
                                </div>
                            </div>
                        </div>

                        <p class="mt-3 text-center text-[11px] leading-relaxed text-zinc-400">
                            QR berlaku singkat dan diperbarui otomatis sampai perangkat tertaut.
                        </p>
                    </div>

                    <div class="flex flex-col gap-4">
                        <div class="rounded-2xl border border-zinc-200 bg-zinc-50/70 px-4 py-3">
                            <p class="text-[10px] font-semibold tracking-wide text-zinc-400 uppercase">Status koneksi</p>
                            <p data-wa-status-text aria-live="polite" class="mt-1 text-sm font-medium text-zinc-700">
                                Memeriksa status gateway...
                            </p>
                            <p class="mt-1 flex items-center gap-1.5 text-[11px] text-zinc-400">
                                <x-icon name="clock" class="size-3.5" />
                                <span>Terakhir dicek: <span data-wa-checked-at>&mdash;</span></span>
                            </p>
                        </div>

                        <ol class="space-y-2 text-xs leading-relaxed text-zinc-500">
                            <li class="flex gap-2">
                                <span
                                    class="grid size-5 shrink-0 place-items-center rounded-full bg-zinc-900 text-[10px] font-bold text-white">1</span>
                                <span>Buka WhatsApp di ponsel, lalu masuk ke
                                    <span class="font-semibold text-zinc-700">Perangkat Tertaut</span>.</span>
                            </li>
                            <li class="flex gap-2">
                                <span
                                    class="grid size-5 shrink-0 place-items-center rounded-full bg-zinc-900 text-[10px] font-bold text-white">2</span>
                                <span>Ketuk <span class="font-semibold text-zinc-700">Tautkan perangkat</span> dan
                                    arahkan kamera ke QR di samping.</span>
                            </li>
                            <li class="flex gap-2">
                                <span
                                    class="grid size-5 shrink-0 place-items-center rounded-full bg-zinc-900 text-[10px] font-bold text-white">3</span>
                                <span>Sesi tersimpan otomatis; gunakan
                                    <span class="font-semibold text-zinc-700">Simpan Sesi</span> untuk mencatat perangkat
                                    aktif.</span>
                            </li>
                        </ol>

                        <div class="mt-auto flex flex-col gap-2 sm:flex-row">
                            <button type="button" data-wa-reset class="btn btn-secondary flex-1">
                                <x-icon name="qr-code" class="size-4" />
                                Reset &amp; QR Baru
                            </button>

                            <button type="button" data-wa-disconnect
                                class="btn flex-1 border border-rose-200 bg-white text-rose-600 shadow-xs hover:bg-rose-50">
                                <x-icon name="x-mark" class="size-4" />
                                Putuskan
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Sesi --}}
            <section class="card p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-600/25">
                        <x-icon name="whatsapp" class="size-5" />
                    </span>

                    <div>
                        <h3 class="text-base font-bold text-zinc-900">Sesi Tersimpan</h3>
                        <p class="mt-0.5 text-xs leading-relaxed text-zinc-500">
                            Kredensial disimpan gateway (folder .baileys_auth); aplikasi mencatat perangkat aktif.
                        </p>
                    </div>
                </div>

                <dl class="mt-5 space-y-2.5 text-sm">
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-zinc-50 px-3 py-2.5">
                        <dt class="text-xs font-medium text-zinc-500">Nomor terhubung</dt>
                        <dd data-wa-session-phone class="font-mono text-xs font-semibold text-zinc-800">
                            {{ $session['phone_number'] ? '+'.$session['phone_number'] : 'Belum ada' }}
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-3 rounded-xl bg-zinc-50 px-3 py-2.5">
                        <dt class="text-xs font-medium text-zinc-500">Device ID</dt>
                        <dd data-wa-session-device class="max-w-[60%] truncate font-mono text-xs font-semibold text-zinc-800">
                            {{ $session['device_id'] ?? '—' }}
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-3 rounded-xl bg-zinc-50 px-3 py-2.5">
                        <dt class="text-xs font-medium text-zinc-500">Disimpan pada</dt>
                        <dd data-wa-session-saved-at class="font-mono text-xs font-semibold text-zinc-800">
                            {{ $session['saved_at'] ?? '—' }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-5 space-y-2">
                    <button type="button" data-wa-save-session class="btn btn-secondary w-full">
                        <x-icon name="key" class="size-4" />
                        Simpan Sesi
                    </button>

                    <a href="{{ route('whatsapp.test') }}" class="btn btn-primary w-full">
                        <x-icon name="paper-airplane" class="size-4" />
                        Uji Pesan WhatsApp
                    </a>
                </div>
            </section>
        </div>


        {{-- Pengaturan gateway --}}
        <section class="card p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-zinc-900 text-white">
                        <x-icon name="cog" class="size-5" />
                    </span>

                    <div>
                        <h3 class="text-base font-bold text-zinc-900">Pengaturan Gateway</h3>
                        <p class="mt-0.5 text-xs text-zinc-500">
                            URL bawaan: <span class="font-mono">{{ $defaultUrl }}</span>
                        </p>
                    </div>
                </div>

                <span class="badge bg-zinc-100 font-mono text-[11px] text-zinc-500">
                    API: <span data-wa-base-url>{{ $baseUrl }}</span>
                </span>
            </div>

            <form data-wa-settings-form class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto] lg:items-end">
                <div>
                    <label for="gateway_url" class="form-label">
                        <x-icon name="globe-alt" class="size-4 text-zinc-400" />
                        URL Gateway
                    </label>

                    <input id="gateway_url" name="gateway_url" type="text" value="{{ $gatewayUrl }}"
                        placeholder="{{ $defaultUrl }}" maxlength="255" required
                        class="form-input font-mono text-xs">

                    @error('gateway_url')
                        <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="device_id" class="form-label">
                        <x-icon name="key" class="size-4 text-zinc-400" />
                        Device ID
                    </label>

                    <input id="device_id" name="device_id" type="text" value="{{ $deviceId }}" maxlength="60"
                        required class="form-input font-mono text-xs">

                    @error('device_id')
                        <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">
                    <x-icon name="check-circle" class="size-4" />
                    Simpan
                </button>
            </form>

            <p class="mt-3 flex items-start gap-1.5 rounded-xl bg-zinc-50 px-3 py-2 text-[11px] leading-relaxed text-zinc-500">
                <x-icon name="sparkles" class="mt-px size-3.5 shrink-0 text-zinc-400" />
                <span>
                    URL boleh diisi lengkap dengan <span class="font-mono">/server.js</span>; aplikasi otomatis
                    memakai host-nya untuk memanggil endpoint gateway (<span class="font-mono">/qr</span>,
                    <span class="font-mono">/status</span>, <span class="font-mono">/send</span>).
                </span>
            </p>
        </section>

        {{-- Penerima notifikasi --}}
        <section class="card p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-xl bg-zinc-900 text-white">
                        <x-icon name="bell" class="size-5" />
                    </span>

                    <div>
                        <h3 class="text-base font-bold text-zinc-900">Penerima Notifikasi</h3>
                        <p class="mt-0.5 text-xs leading-relaxed text-zinc-500">
                            Administrator menerima pesan WhatsApp setiap ada surat keluar atau surat tugas baru.
                        </p>
                    </div>
                </div>

                <span @class([
                    'badge shrink-0',
                    'bg-emerald-50 text-emerald-700' => $notificationsEnabled,
                    'bg-zinc-100 text-zinc-600' => ! $notificationsEnabled,
                ])>
                    {{ $notificationsEnabled ? 'Notifikasi aktif' : 'Notifikasi nonaktif' }}
                </span>
            </div>

            <p class="mt-4 flex items-start gap-1.5 rounded-xl bg-amber-50 px-3 py-2 text-[11px] leading-relaxed text-amber-800">
                <x-icon name="shield-check" class="mt-px size-3.5 shrink-0 text-amber-500" />
                <span>
                    Nomor admin harus berbeda dengan nomor WhatsApp gateway
                    @if ($gatewayPhone)
                        (<span class="font-mono">+{{ $gatewayPhone }}</span>)
                    @endif
                    agar notifikasi tidak terkirim ke perangkat sendiri. Nomor yang sama otomatis dilewati.
                </span>
            </p>

            @if ($notificationRecipients->isEmpty())
                <p class="mt-4 rounded-xl bg-zinc-50 px-3 py-2.5 text-xs text-zinc-500">
                    Belum ada akun administrator. Tambahkan administrator terlebih dahulu pada menu Pengguna.
                </p>
            @else
                <ul class="mt-4 divide-y divide-zinc-100">
                    @foreach ($notificationRecipients as $recipient)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                            <span class="flex min-w-0 items-center gap-2.5">
                                <span
                                    class="grid size-8 shrink-0 place-items-center rounded-full bg-zinc-900 text-[10px] font-bold text-white">
                                    {{ $recipient['user']->initials }}
                                </span>

                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-zinc-800">
                                        {{ $recipient['user']->name }}
                                    </span>
                                    <span class="block truncate font-mono text-[11px] text-zinc-500">
                                        {{ $recipient['phone'] ? '+'.$recipient['phone'] : 'Tanpa nomor HP' }}
                                    </span>
                                </span>
                            </span>

                            @if ($recipient['is_gateway'])
                                <span class="badge bg-rose-50 text-rose-700">
                                    <x-icon name="x-mark" class="size-3.5" />
                                    Sama dengan nomor gateway
                                </span>
                            @elseif ($recipient['can_receive'])
                                <span class="badge bg-emerald-50 text-emerald-700">
                                    <x-icon name="check-circle" class="size-3.5" />
                                    Menerima notifikasi
                                </span>
                            @else
                                <span class="badge bg-zinc-100 text-zinc-600">
                                    <x-icon name="x-mark" class="size-3.5" />
                                    Nomor HP belum diisi
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <p class="mt-4 flex items-start gap-1.5 rounded-xl bg-zinc-50 px-3 py-2 text-[11px] leading-relaxed text-zinc-500">
                <x-icon name="sparkles" class="mt-px size-3.5 shrink-0 text-zinc-400" />
                <span>
                    Ubah nomor HP admin melalui menu
                    <a href="{{ route('pengguna.index') }}" class="font-semibold text-rose-600 hover:underline">Pengguna</a>.
                    Nomor disimpan dalam format <span class="font-mono">62...</span> saat notifikasi dikirim.
                </span>
            </p>
        </section>
    </div>
</x-layouts.app>

