@php
    $first = $agendas->firstItem() ?? 0;
    $last = $agendas->lastItem() ?? 0;
    $total = $agendas->total();
    $current = $agendas->currentPage();
    $lastPage = max(1, $agendas->lastPage());

    $pages = [];
    for ($i = 1; $i <= $lastPage; $i++) {
        if ($i === 1 || $i === $lastPage || abs($i - $current) <= 1) {
            $pages[] = $i;
        } elseif (end($pages) !== '...') {
            $pages[] = '...';
        }
    }
@endphp

<div
    class="flex flex-col gap-1 border-b border-zinc-100 bg-zinc-50/60 px-4 py-3 text-xs text-zinc-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
    <p>
        Menampilkan
        <span class="font-semibold text-zinc-900">{{ $first }}–{{ $last }}</span>
        dari
        <span class="font-semibold text-zinc-900">{{ number_format($total, 0, ',', '.') }}</span>
        agenda
        @if ($search !== '')
            <span class="text-zinc-400">&middot; pencarian “{{ $search }}”</span>
        @endif
    </p>

    <p class="text-zinc-400">Halaman {{ $current }} dari {{ $lastPage }}</p>
</div>

<div class="overflow-x-auto">
    <table class="w-full min-w-[52rem] text-left text-sm">
        <caption class="sr-only">Daftar agenda kegiatan</caption>

        <thead class="border-b border-zinc-100 bg-white text-[10px] tracking-wider text-zinc-500 uppercase">
            <tr>
                <th scope="col" class="w-14 px-4 py-3 font-semibold">No.</th>
                <th scope="col" class="w-40 px-4 py-3 font-semibold">Tanggal</th>
                <th scope="col" class="px-4 py-3 font-semibold">Tempat</th>
                <th scope="col" class="px-4 py-3 font-semibold">Penanggung Jawab</th>
                <th scope="col" class="px-4 py-3 font-semibold">Peserta</th>
                <th scope="col" class="w-28 px-4 py-3 text-center font-semibold">Aksi</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-zinc-100">
            @forelse ($agendas as $agenda)
                <tr data-row class="transition hover:bg-rose-50/40">
                    <td class="px-4 py-3 align-top font-mono text-[11px] text-zinc-400">
                        {{ $first + $loop->index }}
                    </td>

                    <td class="px-4 py-3 align-top whitespace-nowrap">
                        <span class="font-semibold text-zinc-900">
                            {{ $agenda->date?->locale('id')->translatedFormat('j F Y') }}
                        </span>
                        <span class="mt-0.5 block text-[11px] text-zinc-400">
                            {{ $agenda->date?->locale('id')->translatedFormat('l') }}
                        </span>
                    </td>

                    <td class="px-4 py-3 align-top text-zinc-700">
                        <p class="line-clamp-2 max-w-[16rem]">{{ $agenda->place }}</p>
                    </td>

                    <td class="px-4 py-3 align-top">
                        <span class="font-medium text-zinc-800">{{ $agenda->person_in_charge }}</span>
                    </td>

                    <td class="px-4 py-3 align-top text-zinc-600">
                        <p class="line-clamp-2 max-w-[18rem]">{{ $agenda->participants }}</p>
                    </td>

                    <td class="px-4 py-3 align-top">
                        <div class="flex items-center justify-center gap-1">
                            @include('agenda._actions', ['agenda' => $agenda])
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-14 text-center">
                        <span class="mx-auto grid size-12 place-items-center rounded-full bg-zinc-100 text-zinc-400">
                            <x-icon name="calendar" class="size-6" />
                        </span>

                        <p class="mt-3 text-sm font-semibold text-zinc-900">
                            {{ $search !== '' ? 'Agenda tidak ditemukan' : 'Belum ada agenda' }}
                        </p>

                        <p class="mt-1 text-xs text-zinc-500">
                            @if ($search !== '')
                                Tidak ada data yang cocok dengan “{{ $search }}”.
                            @else
                                Tambahkan agenda kegiatan pertama.
                            @endif
                        </p>

                        @if ($search !== '')
                            <a href="{{ route('agenda.index', ['per_page' => $perPage]) }}"
                                class="btn btn-secondary mt-4" data-table-reset>
                                <x-icon name="x-mark" class="size-4" />
                                Bersihkan pencarian
                            </a>
                        @else
                            <button type="button" class="btn btn-primary mt-4" data-agenda-open="create">
                                <x-icon name="plus" class="size-4" />
                                Tambah Agenda
                            </button>
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div
    class="flex flex-col gap-3 border-t border-zinc-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
    <p class="text-xs text-zinc-400">
        Menampilkan {{ $first }}–{{ $last }} dari {{ number_format($total, 0, ',', '.') }} baris
    </p>

    <nav aria-label="Navigasi halaman agenda" class="flex flex-wrap items-center gap-1">
        @if ($agendas->onFirstPage())
            <span aria-disabled="true"
                class="inline-flex h-9 items-center rounded-lg border border-zinc-200 bg-zinc-50 px-2.5 text-xs font-semibold text-zinc-300">
                Sebelumnya
            </span>
        @else
            <a href="{{ $agendas->previousPageUrl() }}" rel="prev" data-table-page="{{ $current - 1 }}"
                class="inline-flex h-9 items-center rounded-lg border border-zinc-200 bg-white px-2.5 text-xs font-semibold text-zinc-600 transition hover:border-zinc-300 hover:bg-zinc-50">
                Sebelumnya
            </a>
        @endif

        @foreach ($pages as $page)
            @if ($page === '...')
                <span class="inline-flex size-9 items-center justify-center text-xs text-zinc-400"
                    aria-hidden="true">&hellip;</span>
            @elseif ($page === $current)
                <span aria-current="page"
                    class="inline-flex size-9 items-center justify-center rounded-lg border border-rose-600 bg-rose-600 text-xs font-bold text-white">
                    {{ $page }}
                </span>
            @else
                <a href="{{ $agendas->url($page) }}" data-table-page="{{ $page }}"
                    aria-label="Halaman {{ $page }}"
                    class="inline-flex size-9 items-center justify-center rounded-lg border border-zinc-200 bg-white text-xs font-semibold text-zinc-600 transition hover:border-zinc-300 hover:bg-zinc-50">
                    {{ $page }}
                </a>
            @endif
        @endforeach

        @if ($agendas->hasMorePages())
            <a href="{{ $agendas->nextPageUrl() }}" rel="next" data-table-page="{{ $current + 1 }}"
                class="inline-flex h-9 items-center rounded-lg border border-zinc-200 bg-white px-2.5 text-xs font-semibold text-zinc-600 transition hover:border-zinc-300 hover:bg-zinc-50">
                Berikutnya
            </a>
        @else
            <span aria-disabled="true"
                class="inline-flex h-9 items-center rounded-lg border border-zinc-200 bg-zinc-50 px-2.5 text-xs font-semibold text-zinc-300">
                Berikutnya
            </span>
        @endif
    </nav>
</div>

