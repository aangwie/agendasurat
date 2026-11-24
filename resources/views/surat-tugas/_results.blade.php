@php
    $first = $letters->firstItem() ?? 0;
    $last = $letters->lastItem() ?? 0;
    $total = $letters->total();
    $current = $letters->currentPage();
    $lastPage = max(1, $letters->lastPage());
    $authUser = auth()->user();

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
        surat tugas
        @if ($search !== '')
            <span class="text-zinc-400">&middot; pencarian “{{ $search }}”</span>
        @endif
    </p>

    <p class="text-zinc-400">Halaman {{ $current }} dari {{ $lastPage }}</p>
</div>

<div class="overflow-x-auto">
    <table class="w-full min-w-[84rem] text-left text-sm">
        <caption class="sr-only">Daftar surat tugas</caption>

        <thead class="border-b border-zinc-100 bg-white text-[10px] tracking-wider text-zinc-500 uppercase">
            <tr>
                <th scope="col" class="w-14 px-4 py-3 font-semibold">No.</th>
                <th scope="col" class="w-24 px-4 py-3 font-semibold">Tahun</th>
                <th scope="col" class="w-32 px-4 py-3 font-semibold">Bulan</th>
                <th scope="col" class="w-20 px-4 py-3 font-semibold">Kode</th>
                <th scope="col" class="w-24 px-4 py-3 font-semibold">Subkode</th>
                <th scope="col" class="w-56 px-4 py-3 font-semibold">Nomor Surat Tugas</th>
                <th scope="col" class="px-4 py-3 font-semibold">Perihal</th>
                <th scope="col" class="w-36 px-4 py-3 font-semibold">Tanggal Surat</th>
                <th scope="col" class="w-64 px-4 py-3 font-semibold">Bidang</th>
                <th scope="col" class="w-44 px-4 py-3 font-semibold">Dibuat Oleh</th>
                <th scope="col" class="w-28 px-4 py-3 text-center font-semibold">Aksi</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-zinc-100">
            @forelse ($letters as $letter)
                <tr data-row class="transition hover:bg-amber-50/40">
                    <td class="px-4 py-3 align-top font-mono text-[11px] text-zinc-400">
                        {{ $first + $loop->index }}
                    </td>

                    <td class="px-4 py-3 align-top">
                        <span class="font-mono text-[13px] font-semibold whitespace-nowrap text-zinc-800">
                            {{ $letter->year }}
                        </span>
                    </td>

                    <td class="px-4 py-3 align-top whitespace-nowrap text-zinc-600">
                        {{ $letter->monthLabel() }}
                    </td>

                    <td class="px-4 py-3 align-top">
                        <span
                            class="inline-flex rounded-md bg-zinc-900 px-2 py-0.5 font-mono text-[12px] font-semibold text-white">
                            {{ $letter->code }}
                        </span>
                    </td>

                    <td class="px-4 py-3 align-top">
                        <span
                            class="inline-flex rounded-md bg-amber-50 px-2 py-0.5 font-mono text-[12px] font-semibold text-amber-700 ring-1 ring-amber-200">
                            {{ $letter->sub_code }}
                        </span>
                    </td>

                    <td class="px-4 py-3 align-top">
                        <span class="font-mono text-[12px] text-zinc-700">{{ $letter->letter_number }}</span>
                    </td>

                    <td class="px-4 py-3 align-top text-zinc-600">
                        <p class="line-clamp-2 max-w-[24rem]">{{ $letter->subject }}</p>
                    </td>

                    <td class="px-4 py-3 align-top whitespace-nowrap text-zinc-600">
                        {{ $letter->letterDateLabel() }}
                    </td>

                    <td class="px-4 py-3 align-top">
                        <span class="font-medium text-zinc-800">{{ $letter->bidang }}</span>

                        @if ($letter->kode_bidang)
                            <span
                                class="mt-1 block w-fit rounded-md bg-zinc-100 px-2 py-0.5 font-mono text-[11px] font-semibold text-zinc-600 ring-1 ring-zinc-200">
                                {{ $letter->kode_bidang }}
                            </span>
                        @endif
                    </td>

                    <td class="px-4 py-3 align-top">
                        <p class="max-w-[11rem] truncate font-medium text-zinc-700">{{ $letter->user?->name }}</p>
                        <p class="text-[11px] text-zinc-400">
                            {{ $letter->created_at?->locale('id')->translatedFormat('d M Y, H:i') }}
                        </p>
                    </td>

                    <td class="px-4 py-3 align-top">
                        <div class="flex items-center justify-center gap-1">
                            @include('surat-tugas._actions', ['letter' => $letter, 'authUser' => $authUser])
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="px-6 py-14 text-center">
                        <span class="mx-auto grid size-12 place-items-center rounded-full bg-zinc-100 text-zinc-400">
                            <x-icon name="clipboard-document-list" class="size-6" />
                        </span>

                        <p class="mt-3 text-sm font-semibold text-zinc-900">
                            {{ $search !== '' ? 'Surat tidak ditemukan' : 'Belum ada surat tugas' }}
                        </p>

                        <p class="mt-1 text-xs text-zinc-500">
                            @if ($search !== '')
                                Tidak ada data yang cocok dengan “{{ $search }}”.
                            @else
                                Mulai catat surat tugas pertama Anda.
                            @endif
                        </p>

                        @if ($search !== '')
                            <a href="{{ route('surat-tugas.index', ['per_page' => $perPage]) }}"
                                class="btn btn-secondary mt-4" data-table-reset>
                                <x-icon name="x-mark" class="size-4" />
                                Bersihkan pencarian
                            </a>
                        @else
                            <button type="button" class="btn btn-primary mt-4" data-task-open="create">
                                <x-icon name="plus" class="size-4" />
                                Tambah Surat Tugas
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

    <nav aria-label="Navigasi halaman surat tugas" class="flex flex-wrap items-center gap-1">
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
                    class="inline-flex size-9 items-center justify-center rounded-lg border border-amber-600 bg-amber-600 text-xs font-bold text-white">
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

