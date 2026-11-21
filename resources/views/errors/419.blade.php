<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#18181b">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Sesi Berakhir (419) · {{ config('app.name', 'Agenda Surat') }}</title>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="grid min-h-dvh place-items-center bg-zinc-100 px-4 py-10 font-sans text-zinc-900 antialiased">
    <div class="w-full max-w-lg">
        <div class="card overflow-hidden">
            <div class="relative bg-zinc-950 px-6 py-7 text-white">
                <div class="bg-grid-rose absolute inset-0 opacity-30" aria-hidden="true"></div>
                <div class="absolute -top-16 right-0 size-48 rounded-full bg-rose-600/25 blur-3xl" aria-hidden="true">
                </div>

                <div class="relative flex items-start gap-4">
                    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-white/10 text-rose-300 ring-1 ring-white/15">
                        <x-icon name="shield-check" class="size-6" />
                    </span>

                    <div>
                        <span class="badge bg-white/5 text-rose-200 ring-1 ring-white/10">Error 419</span>
                        <h1 class="mt-2 text-xl font-extrabold tracking-tight sm:text-2xl">Sesi Anda Telah Berakhir</h1>
                        <p class="mt-1 text-sm leading-relaxed text-zinc-400">
                            Halaman terlalu lama dibiarkan terbuka atau sesi login berakhir, sehingga token keamanan
                            tidak lagi valid.
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-5 p-6">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 text-sm leading-relaxed text-zinc-600">
                    <p class="flex items-start gap-2">
                        <x-icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                        Muat ulang halaman ini, lalu ulangi pengiriman formulir. Data yang sudah diisi biasanya masih
                        tersimpan pada isian browser.
                    </p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <button type="button" data-reload-now class="btn btn-primary flex-1">
                        <x-icon name="arrow-right" class="size-4 rotate-180" />
                        Muat Ulang Sekarang
                    </button>

                    @auth
                        <a href="{{ route('dashboard') }}" class="btn btn-secondary flex-1">
                            <x-icon name="home" class="size-4" />
                            Ke Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-secondary flex-1">
                            <x-icon name="lock" class="size-4" />
                            Masuk Kembali
                        </a>
                    @endauth
                </div>

                <p class="text-center text-xs text-zinc-400">
                    Halaman akan dimuat ulang otomatis dalam
                    <span data-countdown class="font-semibold text-zinc-600">8</span> detik.
                    <button type="button" data-cancel-reload
                        class="font-semibold text-rose-600 underline underline-offset-2 hover:text-rose-700">
                        Batalkan
                    </button>
                </p>
            </div>
        </div>

        <p class="mt-4 text-center text-xs text-zinc-400">
            Jika masalah berulang, pastikan cookie browser aktif dan hindari membuka aplikasi dari dua alamat berbeda
            (mis. <span class="font-mono">localhost</span> dan <span class="font-mono">127.0.0.1</span>).
        </p>
    </div>

    <script>
        (function () {
            var seconds = 8;
            var countdown = document.querySelector('[data-countdown]');
            var timer = window.setInterval(function () {
                seconds -= 1;
                if (countdown) {
                    countdown.textContent = String(Math.max(0, seconds));
                }
                if (seconds <= 0) {
                    window.clearInterval(timer);
                    window.location.reload();
                }
            }, 1000);

            document.querySelector('[data-reload-now]')?.addEventListener('click', function () {
                window.location.reload();
            });

            document.querySelector('[data-cancel-reload]')?.addEventListener('click', function () {
                window.clearInterval(timer);
                if (countdown) {
                    countdown.textContent = '0';
                }
            });
        })();
    </script>
</body>

</html>
