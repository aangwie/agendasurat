<x-layouts.app title="Dashboard">
    @php
        $statusStyles = [
            'Baru' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
            'Diproses' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
            'Selesai' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
            'Diarsipkan' => 'bg-zinc-100 text-zinc-600 ring-1 ring-zinc-200',
        ];
    @endphp

    <div class="space-y-6">
        {{-- Sapaan --}}
        <section class="relative overflow-hidden rounded-3xl bg-zinc-950 px-6 py-7 text-white shadow-lg shadow-zinc-900/10 sm:px-8">
            <div class="bg-grid-rose absolute inset-0 opacity-30" aria-hidden="true"></div>
            <div class="absolute -top-24 right-0 size-72 rounded-full bg-rose-600/25 blur-3xl" aria-hidden="true"></div>

            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-xl">
                    <p class="flex items-center gap-2 text-[11px] font-semibold tracking-[0.18em] text-rose-300 uppercase">
                        <x-icon name="calendar" class="size-4" />
                        {{ $today }}
                    </p>

                    <h2 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">
                        {{ $greeting }}, {{ auth()->user()->name }} 👋
                    </h2>

                    <p class="mt-2 text-sm leading-relaxed text-zinc-400">
                        Berikut ringkasan surat dan agenda Anda. Semua administrasi surat kantor bisa dipantau dari satu
                        dashboard.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <a href="{{ route('agenda.index') }}"
                        class="btn border border-white/15 bg-white/5 text-white hover:bg-white/10">
                        <x-icon name="calendar" class="size-4" />
                        Lihat Agenda
                    </a>
                </div>
            </div>
        </section>

        {{-- Statistik --}}
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan surat">
            @php
                $accentClasses = [
                    'rose' => 'bg-rose-50 text-rose-600 ring-rose-100',
                    'blue' => 'bg-blue-50 text-blue-900 ring-blue-100',
                    'amber' => 'bg-amber-50 text-amber-600 ring-amber-100',
                    'zinc' => 'bg-zinc-100 text-zinc-700 ring-zinc-200',
                ];
            @endphp

            @foreach ($stats as $index => $stat)
                <article class="card animate-fade-up p-5 transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
                    style="animation-delay: {{ $index * 70 }}ms" data-stat="{{ $stat['accent'] }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold tracking-wide text-zinc-500 uppercase">
                                {{ $stat['label'] }}
                            </p>
                            <p class="mt-2 text-3xl font-extrabold tracking-tight text-zinc-900">
                                {{ number_format($stat['value'], 0, ',', '.') }}
                            </p>
                        </div>

                        <span
                            class="grid size-11 shrink-0 place-items-center rounded-xl ring-1 {{ $accentClasses[$stat['accent']] ?? $accentClasses['zinc'] }}">
                            <x-icon :name="$stat['icon']" class="size-5" />
                        </span>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                        <span @class([
                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold',
                            'bg-rose-50 text-rose-600' => $stat['trend'] === 'up',
                            'bg-emerald-50 text-emerald-600' => $stat['trend'] === 'down',
                            'bg-zinc-100 text-zinc-600' => $stat['trend'] === 'neutral',
                        ])>
                            <x-icon
                                :name="match ($stat['trend']) {
                                    'up' => 'trending-up',
                                    'down' => 'trending-down',
                                    default => 'check-circle',
                                }"
                                class="size-3.5" />
                            {{ $stat['delta'] }}
                        </span>
                        <span class="text-zinc-400">{{ $stat['hint'] }}</span>
                    </div>
                </article>
            @endforeach
        </section>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            {{-- Grafik volume surat --}}
            <section class="card p-5 sm:p-6 xl:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-zinc-900">Volume Surat</h3>
                        <p class="mt-1 text-xs text-zinc-500">Surat masuk, surat keluar, dan surat tugas</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-4 text-xs text-zinc-500">
                        @foreach ($letterVolume as $item)
                            <span class="flex items-center gap-1.5">
                                <span class="size-2.5 rounded-full"
                                    style="background-color: {{ $item['color'] }}" aria-hidden="true"></span>
                                {{ str_replace('Surat ', '', $item['label']) }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <div class="mt-8 flex h-56 items-end justify-around gap-4 sm:gap-8">
                    @foreach ($letterVolume as $item)
                        <div class="group flex h-full w-full max-w-24 flex-col items-center justify-end gap-2">
                            <span class="text-xs font-bold text-zinc-700">{{ number_format($item['value'], 0, ',', '.') }}</span>

                            <a href="{{ $item['route'] }}" class="flex w-full flex-1 items-end justify-center"
                                title="{{ $item['label'] }}: {{ number_format($item['value'], 0, ',', '.') }} surat">
                                <div class="w-full max-w-14 rounded-t-lg shadow-sm transition duration-300 group-hover:opacity-85"
                                    style="height: {{ max(6, (int) round($item['value'] / $maxVolume * 100)) }}%; background-color: {{ $item['color'] }}"
                                    data-bar="{{ $item['label'] }}" data-color="{{ $item['color'] }}"></div>
                            </a>

                            <span class="text-center text-[11px] font-semibold text-zinc-500">{{ $item['label'] }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-zinc-50 px-4 py-3">
                    <p class="text-xs text-zinc-500">
                        Total <span class="font-semibold text-zinc-900">{{ number_format($totalLetters, 0, ',', '.') }} surat</span>
                        tercatat. Data Surat Keluar &amp; Surat Tugas akan terisi otomatis setelah modulnya aktif.
                    </p>
                    <a href="{{ route('laporan.index') }}"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-rose-600 transition hover:text-rose-700">
                        Lihat laporan
                        <x-icon name="arrow-right" class="size-3.5" />
                    </a>
                </div>
            </section>

            {{-- Agenda hari ini --}}
            <section class="card flex flex-col p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-zinc-900">Agenda Hari Ini</h3>
                        <p class="mt-1 text-xs text-zinc-500">{{ $today }}</p>
                    </div>

                    <span class="badge bg-rose-50 text-rose-600 ring-1 ring-rose-100">
                        {{ count($agendas) }} kegiatan
                    </span>
                </div>

                <ol class="mt-6 flex-1 space-y-4">
                    @foreach ($agendas as $agenda)
                        <li class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <span
                                    class="grid size-10 shrink-0 place-items-center rounded-full bg-rose-50 text-[10px] font-bold text-rose-600 ring-1 ring-rose-100">
                                    {{ $agenda['time'] }}
                                </span>
                                @unless ($loop->last)
                                    <span class="mt-1 w-px flex-1 bg-zinc-200" aria-hidden="true"></span>
                                @endunless
                            </div>

                            <div class="min-w-0 pb-1">
                                <p class="text-sm font-semibold text-zinc-900">{{ $agenda['title'] }}</p>
                                <p class="mt-1 flex items-center gap-1.5 text-xs text-zinc-500">
                                    <x-icon name="building-office" class="size-3.5 shrink-0 text-zinc-400" />
                                    {{ $agenda['place'] }}
                                </p>
                                <span class="badge mt-2 bg-zinc-100 text-zinc-600">{{ $agenda['tag'] }}</span>
                            </div>
                        </li>
                    @endforeach
                </ol>

                <a href="{{ route('agenda.index') }}"
                    class="mt-6 inline-flex items-center justify-center gap-1.5 rounded-xl border border-zinc-200 px-4 py-2.5 text-xs font-semibold text-zinc-700 transition hover:border-zinc-300 hover:bg-zinc-50">
                    Lihat semua agenda
                    <x-icon name="arrow-right" class="size-3.5" />
                </a>
            </section>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            {{-- Surat terbaru --}}
            <section class="card overflow-hidden xl:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 p-5 sm:p-6">
                    <div>
                        <h3 class="text-base font-bold text-zinc-900">Surat Terbaru</h3>
                        <p class="mt-1 text-xs text-zinc-500">Lima surat terakhir yang dicatat</p>
                    </div>

                    <a href="{{ route('surat-masuk.index') }}"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-zinc-200 px-3.5 py-2 text-xs font-semibold text-zinc-700 transition hover:border-zinc-300 hover:bg-zinc-50">
                        Lihat semua
                        <x-icon name="arrow-right" class="size-3.5" />
                    </a>
                </div>

                {{-- Tabel untuk layar sedang ke atas --}}
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50/80 text-[10px] tracking-wider text-zinc-500 uppercase">
                            <tr>
                                <th scope="col" class="px-5 py-3 font-semibold sm:px-6">Surat</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Asal / Tujuan</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Tanggal</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($recentLetters as $letter)
                                <tr class="transition hover:bg-zinc-50/70">
                                    <td class="px-5 py-4 sm:px-6">
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="grid size-9 shrink-0 place-items-center rounded-lg bg-zinc-100 text-zinc-500">
                                                <x-icon :name="$letter['direction'] === 'Masuk' ? 'inbox' : 'paper-airplane'"
                                                    class="size-4" />
                                            </span>
                                            <div class="min-w-0">
                                                <p class="max-w-[22rem] truncate font-semibold text-zinc-900">
                                                    {{ $letter['subject'] }}
                                                </p>
                                                <p class="mt-0.5 font-mono text-[11px] text-zinc-400">
                                                    {{ $letter['number'] }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-zinc-600">{{ $letter['party'] }}</td>
                                    <td class="px-5 py-4 whitespace-nowrap text-zinc-500">{{ $letter['date'] }}</td>
                                    <td class="px-5 py-4">
                                        <span class="badge {{ $statusStyles[$letter['status']] ?? 'bg-zinc-100 text-zinc-600' }}">
                                            {{ $letter['status'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Daftar kartu untuk ponsel --}}
                <ul class="divide-y divide-zinc-100 md:hidden">
                    @foreach ($recentLetters as $letter)
                        <li class="p-4">
                            <div class="flex items-start gap-3">
                                <span
                                    class="grid size-9 shrink-0 place-items-center rounded-lg bg-zinc-100 text-zinc-500">
                                    <x-icon :name="$letter['direction'] === 'Masuk' ? 'inbox' : 'paper-airplane'"
                                        class="size-4" />
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-zinc-900">{{ $letter['subject'] }}</p>
                                    <p class="mt-0.5 font-mono text-[11px] text-zinc-400">{{ $letter['number'] }}</p>

                                    <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-zinc-500">
                                        <span class="truncate">{{ $letter['party'] }}</span>
                                        <span aria-hidden="true">&middot;</span>
                                        <span>{{ $letter['date'] }}</span>
                                    </div>

                                    <span
                                        class="badge mt-2 {{ $statusStyles[$letter['status']] ?? 'bg-zinc-100 text-zinc-600' }}">
                                        {{ $letter['status'] }}
                                    </span>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- Status surat --}}
            <section class="card p-5 sm:p-6">
                <div>
                    <h3 class="text-base font-bold text-zinc-900">Status Surat</h3>
                    <p class="mt-1 text-xs text-zinc-500">Distribusi status bulan ini</p>
                </div>

                <div class="mt-6 flex items-center justify-center">
                    <div class="relative grid size-40 place-items-center rounded-full"
                        style="background: {{ $donutGradient }}" role="img"
                        aria-label="Distribusi status surat: {{ collect($statusBreakdown)->map(fn ($item) => $item['label'] . ' ' . $item['value'] . '%')->implode(', ') }}">
                        <div class="grid size-[7.25rem] place-items-center rounded-full bg-white text-center">
                            <div>
                                <p class="text-2xl font-extrabold tracking-tight text-zinc-900">100</p>
                                <p class="text-[10px] font-semibold tracking-wide text-zinc-400 uppercase">Total surat</p>
                            </div>
                        </div>
                    </div>
                </div>

                <ul class="mt-6 space-y-3">
                    @foreach ($statusBreakdown as $item)
                        <li class="flex items-center gap-3">
                            <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $item['color'] }}"
                                aria-hidden="true"></span>
                            <span class="flex-1 text-sm text-zinc-600">{{ $item['label'] }}</span>
                            <span class="text-sm font-semibold text-zinc-900">{{ $item['value'] }}%</span>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-5 rounded-xl bg-zinc-50 p-3 text-xs leading-relaxed text-zinc-500">
                    Angka pada dashboard masih berupa data contoh sampai modul surat tersedia.
                </p>
            </section>
        </div>
    </div>
</x-layouts.app>
