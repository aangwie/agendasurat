@php
    $first = $users->firstItem() ?? 0;
    $last = $users->lastItem() ?? 0;
    $total = $users->total();
    $current = $users->currentPage();
    $lastPage = max(1, $users->lastPage());

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
        pengguna
        @if ($search !== '')
            <span class="text-zinc-400">&middot; pencarian “{{ $search }}”</span>
        @endif
    </p>

    <p class="text-zinc-400">Halaman {{ $current }} dari {{ $lastPage }}</p>
</div>

<div class="overflow-x-auto">
    <table class="w-full min-w-[52rem] text-left text-sm">
        <caption class="sr-only">Daftar pengguna aplikasi</caption>

        <thead class="border-b border-zinc-100 bg-white text-[10px] tracking-wider text-zinc-500 uppercase">
            <tr>
                <th scope="col" class="w-14 px-4 py-3 font-semibold">No.</th>
                <th scope="col" class="px-4 py-3 font-semibold">Nama Bidang</th>
                <th scope="col" class="px-4 py-3 font-semibold">Email</th>
                <th scope="col" class="w-44 px-4 py-3 font-semibold">No HP</th>
                <th scope="col" class="w-28 px-4 py-3 text-center font-semibold">Aksi</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-zinc-100">
            @forelse ($users as $user)
                <tr data-row class="transition hover:bg-rose-50/40">
                    <td class="px-4 py-3 align-top font-mono text-[11px] text-zinc-400">
                        {{ $first + $loop->index }}
                    </td>

                    <td class="px-4 py-3 align-top">
                        <div class="flex items-center gap-3">
                            <span
                                class="grid size-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-rose-500 to-rose-700 text-[11px] font-bold text-white">
                                {{ $user->initials }}
                            </span>

                            <div class="min-w-0">
                                <p class="truncate font-semibold text-zinc-900">{{ $user->name }}</p>
                                @if (auth()->id() === $user->id)
                                    <span class="badge mt-1 bg-rose-50 text-rose-600 ring-1 ring-rose-100">
                                        Akun Anda
                                    </span>
                                @endif
                            </div>
                        </div>
                    </td>

                    <td class="px-4 py-3 align-top">
                        <span class="text-zinc-600">{{ $user->email }}</span>
                    </td>

                    <td class="px-4 py-3 align-top whitespace-nowrap">
                        <span class="font-mono text-[12px] text-zinc-600">{{ $user->phone ?: '—' }}</span>
                    </td>

                    <td class="px-4 py-3 align-top">
                        <div class="flex items-center justify-center gap-1">
                            @include('pengguna._actions', ['user' => $user])
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-6 py-14 text-center">
                        <span class="mx-auto grid size-12 place-items-center rounded-full bg-zinc-100 text-zinc-400">
                            <x-icon name="users" class="size-6" />
                        </span>

                        <p class="mt-3 text-sm font-semibold text-zinc-900">
                            {{ $search !== '' ? 'Pengguna tidak ditemukan' : 'Belum ada pengguna' }}
                        </p>

                        <p class="mt-1 text-xs text-zinc-500">
                            @if ($search !== '')
                                Tidak ada data yang cocok dengan “{{ $search }}”.
                            @else
                                Tambahkan akun pengguna pertama.
                            @endif
                        </p>

                        @if ($search !== '')
                            <a href="{{ route('pengguna.index', ['per_page' => $perPage]) }}"
                                class="btn btn-secondary mt-4" data-table-reset>
                                <x-icon name="x-mark" class="size-4" />
                                Bersihkan pencarian
                            </a>
                        @else
                            <button type="button" class="btn btn-primary mt-4" data-user-open="create">
                                <x-icon name="plus" class="size-4" />
                                Tambah Pengguna
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

    <nav aria-label="Navigasi halaman pengguna" class="flex flex-wrap items-center gap-1">
        @if ($users->onFirstPage())
            <span aria-disabled="true"
                class="inline-flex h-9 items-center rounded-lg border border-zinc-200 bg-zinc-50 px-2.5 text-xs font-semibold text-zinc-300">
                Sebelumnya
            </span>
        @else
            <a href="{{ $users->previousPageUrl() }}" rel="prev" data-table-page="{{ $current - 1 }}"
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
                <a href="{{ $users->url($page) }}" data-table-page="{{ $page }}"
                    aria-label="Halaman {{ $page }}"
                    class="inline-flex size-9 items-center justify-center rounded-lg border border-zinc-200 bg-white text-xs font-semibold text-zinc-600 transition hover:border-zinc-300 hover:bg-zinc-50">
                    {{ $page }}
                </a>
            @endif
        @endforeach

        @if ($users->hasMorePages())
            <a href="{{ $users->nextPageUrl() }}" rel="next" data-table-page="{{ $current + 1 }}"
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
