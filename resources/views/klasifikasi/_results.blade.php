@use('App\Models\LetterClassification')

@php
    $first = $paginator->firstItem() ?? 0;
    $last = $paginator->lastItem() ?? 0;
    $total = $paginator->total();
    $current = $paginator->currentPage();
    $lastPage = max(1, $paginator->lastPage());

    $pages = [];
    for ($i = 1; $i <= $lastPage; $i++) {
        if ($i === 1 || $i === $lastPage || abs($i - $current) <= 1) {
            $pages[] = $i;
        } elseif (end($pages) !== '...') {
            $pages[] = '...';
        }
    }

    $canHighlight = $search !== '' && preg_match('/^[\p{L}\p{N}\s.\-\/()]+$/u', $search) === 1;
    $highlight = function (string $text) use ($search, $canHighlight): string {
        $escaped = e($text);

        if (! $canHighlight) {
            return $escaped;
        }

        return preg_replace(
            '/('.preg_quote(e($search), '/').')/iu',
            '<mark class="rounded bg-rose-100 px-0.5 text-rose-700">$1</mark>',
            $escaped,
        ) ?? $escaped;
    };
@endphp

<div
    class="flex flex-col gap-1 border-b border-zinc-100 bg-zinc-50/60 px-4 py-3 text-xs text-zinc-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
    <p>
        Menampilkan
        <span class="font-semibold text-zinc-900">{{ $first }}–{{ $last }}</span>
        dari
        <span class="font-semibold text-zinc-900">{{ number_format($total, 0, ',', '.') }}</span>
        klasifikasi
        @if ($search !== '')
            <span class="text-zinc-400">&middot; pencarian “{{ $search }}”</span>
        @endif
    </p>

    <p class="text-zinc-400">Halaman {{ $current }} dari {{ $lastPage }}</p>
</div>

<div class="overflow-x-auto">
    <table class="w-full min-w-[48rem] text-left text-sm">
        <caption class="sr-only">Hierarki kode klasifikasi arsip Permendagri 83 Tahun 2022</caption>

        <thead class="border-b border-zinc-100 bg-white text-[10px] tracking-wider text-zinc-500 uppercase">
            <tr>
                <th scope="col" class="w-14 px-4 py-3 font-semibold">No.</th>
                <th scope="col" class="w-32 px-4 py-3 font-semibold">Kode</th>
                <th scope="col" class="px-4 py-3 font-semibold">Uraian Klasifikasi</th>
                <th scope="col" class="w-28 px-4 py-3 font-semibold">Tingkat</th>
                <th scope="col" class="hidden w-32 px-4 py-3 font-semibold md:table-cell">Induk</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-zinc-100">
            @forelse ($paginator->items() as $row)
                <tr data-row data-level="{{ $row['level'] }}"
                    @class([
                        'transition hover:bg-rose-50/40',
                        'bg-zinc-50/60' => ! $row['matched'],
                    ])>
                    <td class="px-4 py-2.5 align-top font-mono text-[11px] text-zinc-400">
                        {{ $first + $loop->index }}
                    </td>

                    <td class="px-4 py-2.5 align-top">
                        <span @class([
                            'font-mono text-[13px] font-semibold whitespace-nowrap',
                            'text-zinc-900' => $row['level'] === 1,
                            'text-rose-600' => $row['level'] === 2,
                            'text-zinc-600' => $row['level'] >= 3,
                        ])>{!! $highlight($row['code']) !!}</span>
                    </td>

                    <td class="px-4 py-2.5 align-top">
                        <div class="flex items-start gap-2" style="padding-left: {{ ($row['level'] - 1) * 1.25 }}rem">
                            <span @class([
                                'mt-0.5 grid size-5 shrink-0 place-items-center rounded',
                                'bg-rose-100 text-rose-600' => $row['level'] === 1,
                                'bg-zinc-100 text-zinc-500' => $row['level'] === 2,
                                'bg-transparent text-zinc-300' => $row['level'] >= 3,
                            ])>
                                <x-icon :name="$row['level'] <= 2 ? 'folder' : 'document-text'" class="size-3.5" />
                            </span>

                            <span @class([
                                'leading-relaxed',
                                'font-bold tracking-wide text-zinc-900 uppercase' => $row['level'] === 1,
                                'font-semibold text-zinc-800' => $row['level'] === 2,
                                'text-zinc-600' => $row['level'] >= 3,
                                'opacity-70' => ! $row['matched'],
                            ])>{!! $highlight($row['name']) !!}</span>
                        </div>
                    </td>

                    <td class="px-4 py-2.5 align-top">
                        <span @class([
                            'badge whitespace-nowrap',
                            'bg-zinc-900 text-white' => $row['level'] === 1,
                            'bg-rose-50 text-rose-600 ring-1 ring-rose-100' => $row['level'] === 2,
                            'bg-zinc-100 text-zinc-600' => $row['level'] === 3,
                            'bg-white text-zinc-500 ring-1 ring-zinc-200' => $row['level'] >= 4,
                        ])>
                            {{ LetterClassification::LEVEL_SHORT_LABELS[$row['level']] ?? 'Kode' }}
                        </span>
                    </td>

                    <td class="hidden px-4 py-2.5 align-top font-mono text-[11px] text-zinc-400 md:table-cell">
                        {{ $row['parent'] ?? '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-6 py-14 text-center">
                        <span class="mx-auto grid size-12 place-items-center rounded-full bg-zinc-100 text-zinc-400">
                            <x-icon name="magnifying-glass" class="size-6" />
                        </span>

                        <p class="mt-3 text-sm font-semibold text-zinc-900">Klasifikasi tidak ditemukan</p>
                        <p class="mt-1 text-xs text-zinc-500">
                            Tidak ada kode atau uraian yang cocok dengan
                            <span class="font-semibold text-zinc-700">“{{ $search }}”</span>.
                        </p>

                        <a href="{{ route('klasifikasi.index', ['scope' => $scope, 'per_page' => $perPage]) }}"
                            class="btn btn-secondary mt-4" data-table-reset>
                            <x-icon name="x-mark" class="size-4" />
                            Bersihkan pencarian
                        </a>
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

    <nav aria-label="Navigasi halaman klasifikasi" class="flex flex-wrap items-center gap-1">
        @if ($paginator->onFirstPage())
            <span aria-disabled="true"
                class="inline-flex h-9 items-center rounded-lg border border-zinc-200 bg-zinc-50 px-2.5 text-xs font-semibold text-zinc-300">
                Sebelumnya
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" data-table-page="{{ $current - 1 }}"
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
                <a href="{{ $paginator->url($page) }}" data-table-page="{{ $page }}"
                    aria-label="Halaman {{ $page }}"
                    class="inline-flex size-9 items-center justify-center rounded-lg border border-zinc-200 bg-white text-xs font-semibold text-zinc-600 transition hover:border-zinc-300 hover:bg-zinc-50">
                    {{ $page }}
                </a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" data-table-page="{{ $current + 1 }}"
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
