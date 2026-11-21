@props(['title' => 'Dashboard'])

@php
    $user = auth()->user();

    $navigation = [
        [
            'label' => 'Utama',
            'items' => [
                ['title' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard'],
            ],
        ],
        [
            'label' => 'Manajemen Surat',
            'items' => [
                ['title' => 'Surat Masuk', 'icon' => 'inbox', 'route' => 'surat-masuk.index'],
                ['title' => 'Surat Keluar', 'icon' => 'paper-airplane', 'route' => 'surat-keluar.index'],
                ['title' => 'Surat Tugas', 'icon' => 'clipboard-document-list', 'route' => 'surat-tugas.index'],
                ['title' => 'Agenda', 'icon' => 'calendar', 'route' => 'agenda.index'],
                ['title' => 'Klasifikasi', 'icon' => 'folder', 'route' => 'klasifikasi.index'],
                ['title' => 'Laporan', 'icon' => 'chart-bar', 'route' => 'laporan.index'],
            ],
        ],
        [
            'label' => 'Sistem',
            // Menu sistem hanya untuk administrator.
            'adminOnly' => true,
            'items' => [
                ['title' => 'Profil Instansi', 'icon' => 'building-office', 'route' => 'profil-instansi.edit'],
                ['title' => 'Pengguna', 'icon' => 'users', 'route' => 'pengguna.index'],
                ['title' => 'Pengaturan', 'icon' => 'cog', 'route' => 'pengaturan.index'],
            ],
        ],
    ];

    $isAdmin = $user?->isAdmin() ?? false;

    // Sembunyikan grup menu sistem bagi pengguna selain admin.
    $navigation = array_values(array_filter(
        $navigation,
        fn (array $group): bool => ! ($group['adminOnly'] ?? false) || $isAdmin,
    ));

    $isActive = function (string $route): bool {
        if (request()->routeIs($route)) {
            return true;
        }

        $prefix = \Illuminate\Support\Str::beforeLast($route, '.index');

        return $prefix !== $route && request()->routeIs($prefix . '.*');
    };
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" data-sidebar-minimized="false">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#18181b">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} · {{ config('app.name', 'Agenda Surat') }}</title>

    {{-- Apply the saved sidebar state before first paint to avoid a layout flash. --}}
    <script>
        try {
            if (window.localStorage.getItem('agenda-surat:sidebar-minimized') === '1') {
                document.documentElement.setAttribute('data-sidebar-minimized', 'true');
            }
        } catch (error) {
            /* localStorage may be unavailable (e.g. private mode). */
        }
    </script>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-zinc-100 font-sans text-zinc-900 antialiased">
    <a href="#main-content"
        class="sr-only rounded-lg bg-white px-4 py-2 text-sm font-semibold text-zinc-900 shadow-lg focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[60]">
        Lewati ke konten utama
    </a>

    <div data-sidebar-backdrop
        class="pointer-events-none fixed inset-0 z-40 bg-zinc-950/60 opacity-0 backdrop-blur-sm transition-opacity duration-300 lg:hidden"
        aria-hidden="true"></div>

    <aside data-sidebar id="app-sidebar" aria-label="Navigasi utama"
        class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-white/5 bg-zinc-950 transition-transform duration-300 ease-out lg:w-[var(--sidebar-width)] lg:translate-x-0">
        <div data-sidebar-header
            class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-white/5 px-5">
            <x-logo class="min-w-0" />

            <button type="button" data-sidebar-close
                class="grid size-9 shrink-0 place-items-center rounded-lg text-zinc-400 transition hover:bg-white/5 hover:text-white focus-visible:outline-white lg:hidden"
                aria-label="Tutup menu navigasi">
                <x-icon name="x-mark" class="size-5" />
            </button>
        </div>

        <nav class="scrollbar-slim flex-1 space-y-6 overflow-y-auto px-3 py-5">
            @foreach ($navigation as $group)
                <div>
                    <div data-sidebar-divider class="mx-auto mb-3 hidden h-px w-8 bg-white/10" aria-hidden="true"></div>

                    <p data-sidebar-heading
                        class="px-3 pb-2 text-[10px] font-semibold tracking-[0.18em] text-zinc-600 uppercase">
                        {{ $group['label'] }}
                    </p>

                    <div class="space-y-1">
                        @foreach ($group['items'] as $item)
                            <x-nav-link :href="route($item['route'])" :icon="$item['icon']" :label="$item['title']"
                                :active="$isActive($item['route'])">
                                {{ $item['title'] }}
                            </x-nav-link>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div data-sidebar-footer class="shrink-0 space-y-3 border-t border-white/5 p-4">
            <div class="rounded-2xl border border-rose-500/20 bg-gradient-to-br from-rose-500/15 via-zinc-900 to-zinc-900 p-4">
                <div class="flex items-center gap-2 text-xs font-semibold text-rose-200">
                    <x-icon name="sparkles" class="size-4" />
                    Modul bertahap
                </div>
                <p class="mt-1.5 text-xs leading-relaxed text-zinc-400">
                    Surat masuk, surat keluar, surat tugas, dan agenda akan aktif pada tahap pengembangan berikutnya.
                </p>
            </div>

            <p class="px-1 text-[10px] font-medium tracking-wide text-zinc-600">
                Agenda Surat v1.0 &middot; Setup Awal
            </p>
        </div>
    </aside>
    <div data-app-shell class="flex min-h-dvh flex-col lg:pl-[var(--sidebar-width)]">
        <header class="sticky top-0 z-30 border-b border-zinc-200/80 bg-white/85 backdrop-blur-md">
            <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                <button type="button" data-sidebar-toggle aria-expanded="false" aria-controls="app-sidebar"
                    aria-label="Buka menu navigasi"
                    class="grid size-10 shrink-0 place-items-center rounded-xl border border-zinc-200 bg-white text-zinc-600 shadow-xs transition hover:bg-zinc-50 hover:text-zinc-900 lg:hidden">
                    <x-icon name="bars-3" class="size-5" />
                </button>

                <button type="button" data-sidebar-collapse aria-pressed="false" aria-controls="app-sidebar"
                    aria-label="Perkecil menu" title="Perkecil menu"
                    class="hidden size-10 shrink-0 place-items-center rounded-xl border border-zinc-200 bg-white text-zinc-600 shadow-xs transition hover:bg-zinc-50 hover:text-zinc-900 lg:grid">
                    <x-icon name="chevron-double-left" data-collapse-icon class="size-5" />
                    <x-icon name="chevron-double-right" data-expand-icon class="hidden size-5" />
                </button>

                <div class="min-w-0 flex-1">
                    <nav aria-label="Breadcrumb" class="hidden items-center gap-1.5 text-xs text-zinc-400 sm:flex">
                        <a href="{{ route('dashboard') }}" class="transition hover:text-zinc-600">Beranda</a>
                        <span aria-hidden="true">/</span>
                        <span class="font-medium text-zinc-500">{{ $title }}</span>
                    </nav>
                    <h1 class="truncate text-base font-bold tracking-tight text-zinc-900 sm:mt-0.5 sm:text-lg">
                        {{ $title }}
                    </h1>
                </div>

                <div class="relative" data-dropdown>
                    <button type="button" data-dropdown-toggle aria-haspopup="true" aria-expanded="false"
                        class="flex items-center gap-2.5 rounded-full border border-transparent p-1 pr-1 transition hover:border-zinc-200 hover:bg-zinc-50 sm:pr-2.5">
                        <span class="hidden min-w-0 text-right sm:block">
                            <span class="block max-w-[9.5rem] truncate text-sm font-semibold text-zinc-900">
                                {{ $user->name }}
                            </span>
                            <span class="block text-[11px] text-zinc-500">Tata Usaha</span>
                        </span>

                        <span
                            class="grid size-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-rose-500 to-rose-700 text-xs font-bold text-white shadow-sm shadow-rose-600/30 ring-2 ring-white">
                            {{ $user->initials }}
                        </span>

                        <x-icon name="chevron-down" class="hidden size-4 text-zinc-400 sm:block" />
                    </button>

                    <div data-dropdown-menu
                        class="absolute right-0 z-50 mt-2 hidden w-64 origin-top-right overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xl shadow-zinc-900/10">
                        <div class="flex items-center gap-3 border-b border-zinc-100 bg-zinc-50/70 px-4 py-3.5">
                            <span
                                class="grid size-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-rose-500 to-rose-700 text-sm font-bold text-white">
                                {{ $user->initials }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-zinc-900">{{ $user->name }}</p>
                                <p class="truncate text-xs text-zinc-500">{{ $user->email }}</p>
                            </div>
                        </div>

                        <div class="p-1.5">
                            <a href="{{ route('profil.show') }}"
                                class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900">
                                <x-icon name="user" class="size-4 text-zinc-400" />
                                Profil Saya
                            </a>

                            @if ($isAdmin)
                                <a href="{{ route('pengaturan.index') }}"
                                    class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900">
                                    <x-icon name="cog" class="size-4 text-zinc-400" />
                                    Pengaturan
                                </a>
                            @endif
                        </div>

                        <div class="border-t border-zinc-100 p-1.5">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-rose-600 transition hover:bg-rose-50">
                                    <x-icon name="logout" class="size-4" />
                                    Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main id="main-content" class="flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            @if (session('status'))
                <div data-toast role="status"
                    class="mb-5 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-xs transition duration-200">
                    <x-icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-emerald-500" />
                    <p class="flex-1">{{ session('status') }}</p>
                    <button type="button" data-toast-close aria-label="Tutup notifikasi"
                        class="rounded-md p-1 text-emerald-600 transition hover:bg-emerald-100">
                        <x-icon name="x-mark" class="size-4" />
                    </button>
                </div>
            @endif

            {{ $slot }}
        </main>

        <footer class="border-t border-zinc-200/70 px-4 py-4 text-xs text-zinc-500 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; {{ now()->year }} {{ config('app.name', 'Agenda Surat') }}. Seluruh hak dilindungi.</p>
                <p class="text-zinc-400">Versi 1.0 &middot; Setup Awal</p>
            </div>
        </footer>
    </div>
</body>
</html>

