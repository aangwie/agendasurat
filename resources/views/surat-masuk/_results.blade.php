@php
    $first = $letters->firstItem() ?? 0;
    $last = $letters->lastItem() ?? 0;
    $total = $letters->total();
    $current = $letters->currentPage();
    $lastPage = max(1, $letters->lastPage());

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
        surat masuk
        @if ($search !== '')
            <span class="text-zinc-400">&middot; pencarian “{{ $search }}”</span>
        @endif
    </p>

    <p class="text-zinc-400">Halaman {{ $current }} dari {{ $lastPage }}</p>
</div>

<div class="overflow-x-auto">
    <table class="w-full min-w-[62rem] text-left text-sm">
        <caption class="sr-only">Daftar surat masuk</caption>

        <thead class="border-b border-zinc-100 bg-white text-[10px] tracking-wider text-zinc-500 uppercase">
            <tr>
                <th scope="col" class="w-14 px-4 py-3 font-semibold">No.</th>
                <th scope="col" class="w-40 px-4 py-3 font-semibold">Nomor Agenda</th>
                <th scope="col" class="w-36 px-4 py-3 font-semibold">Tanggal Diterima</th>
                <th scope="col" class="w-40 px-4 py-3 font-semibold">Tanggal Surat</th>
                <th scope="col" class="w-44 px-4 py-3 font-semibold">Nomor Surat</th>
                <th scope="col" class="w-52 px-4 py-3 font-semibold">Asal Surat</th>
                <th scope="col" class="px-4 py-3 font-semibold">Perihal</th>
                <th scope="col" class="w-40 px-4 py-3 font-semibold">Sifat</th>
                <th scope="col" class="w-32 px-4 py-3 text-center font-semibold">Aksi</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-zinc-100">
            @forelse ($letters as $letter)
                <tr data-row class="transition hover:bg-rose-50/40">
                    <td class="px-4 py-3 align-top font-mono text-[11px] text-zinc-400">
                        {{ $first + $loop->index }}
                    </td>

                    <td class="px-4 py-3 align-top">
                        <span class="font-mono text-[13px] font-semibold whitespace-nowrap text-rose-600">
                            {{ $letter->agenda_number }}
                        </span>
                    </td>

                    <td class="px-4 py-3 align-top whitespace-nowrap text-zinc-600">
                        {{ $letter->received_date?->locale('id')->translatedFormat('d M Y') }}
                    </td>

                    <td class="px-4 py-3 align-top whitespace-nowrap text-zinc-600">
                        {{ $letter->letter_date?->locale('id')->translatedFormat('j F Y') }}
                    </td>

                    <td class="px-4 py-3 align-top">
                        <span class="font-mono text-[12px] text-zinc-600">{{ $letter->letter_number }}</span>
                    </td>

                    <td class="px-4 py-3 align-top">
                        <span class="font-medium text-zinc-800">{{ $letter->sender }}</span>
                    </td>

                    <td class="px-4 py-3 align-top text-zinc-600">
                        <p class="line-clamp-2 max-w-[24rem]">{{ $letter->subject }}</p>
                    </td>

                    <td class="px-4 py-3 align-top">
                        @php
                            $sifat = $letter->sifat ?? [];
                            $sifatStyles = [
                                'segera' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
                                'terbuka' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
                                'rahasia' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
                            ];
                        @endphp

                        @if ($sifat === [])
                            <span class="text-xs text-zinc-300">—</span>
                        @else
                            <div class="flex flex-wrap gap-1">
                                @foreach (\App\Models\IncomingLetter::SIFAT_OPTIONS as $value => $label)
                                    @if (in_array($value, $sifat, true))
                                        <span class="badge whitespace-nowrap {{ $sifatStyles[$value] ?? 'bg-zinc-100 text-zinc-600' }}">
                                            {{ $label }}
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </td>

                    <td class="px-4 py-3 align-top">
                        <div class="flex items-center justify-center gap-1">
                            @include('surat-masuk._actions', ['letter' => $letter])
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-6 py-14 text-center">
                        <span class="mx-auto grid size-12 place-items-center rounded-full bg-zinc-100 text-zinc-400">
                            <x-icon name="inbox" class="size-6" />
                        </span>

                        <p class="mt-3 text-sm font-semibold text-zinc-900">
                            {{ $search !== '' ? 'Surat tidak ditemukan' : 'Belum ada surat masuk' }}
                        </p>

                        <p class="mt-1 text-xs text-zinc-500">
                            @if ($search !== '')
                                Tidak ada data yang cocok dengan “{{ $search }}”.
                            @elseif ($canManage)
                                Mulai catat surat masuk pertama Anda.
                            @else
                                Belum ada surat masuk yang tercatat.
                            @endif
                        </p>

                        @if ($search !== '')
                            <a href="{{ route('surat-masuk.index', ['per_page' => $perPage]) }}"
                                class="btn btn-secondary mt-4" data-table-reset>
                                <x-icon name="x-mark" class="size-4" />
                                Bersihkan pencarian
                            </a>
                        @elseif ($canManage)
                            <button type="button" class="btn btn-primary mt-4" data-letter-open="create">
                                <x-icon name="plus" class="size-4" />
                                Tambah Surat Masuk
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

    <nav aria-label="Navigasi halaman surat masuk" class="flex flex-wrap items-center gap-1">
        @if ($letters->onFirstPage())
            <span aria-disabled="true"
                class="inline-flex h-9 items-center rounded-lg border border-zinc-200 bg-zinc-50 px-2.5 text-xs font-semibold text-zinc-300">
                Sebelumnya
            </span>
        @else
            <a href="{{ $letters->previousPageUrl() }}" rel="prev" data-table-page="{{ $current - 1 }}"
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
                <a href="{{ $letters->url($page) }}" data-table-page="{{ $page }}"
                    aria-label="Halaman {{ $page }}"
                    class="inline-flex size-9 items-center justify-center rounded-lg border border-zinc-200 bg-white text-xs font-semibold text-zinc-600 transition hover:border-zinc-300 hover:bg-zinc-50">
                    {{ $page }}
                </a>
            @endif
        @endforeach

        @if ($letters->hasMorePages())
            <a href="{{ $letters->nextPageUrl() }}" rel="next" data-table-page="{{ $current + 1 }}"
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
