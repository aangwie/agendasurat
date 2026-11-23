<x-layouts.app title="Surat Masuk">
    <div class="space-y-6" data-datatable="server" data-endpoint="{{ route('surat-masuk.index') }}"
        data-open-form="{{ $openForm }}" data-open-form-id="{{ $openFormId }}">
        {{-- Ringkasan --}}
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <article class="card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold tracking-wide text-zinc-500 uppercase">Total Surat Masuk</p>
                        <p class="mt-1.5 text-3xl font-extrabold tracking-tight text-zinc-900">
                            {{ number_format($totalLetters, 0, ',', '.') }}
                        </p>
                        <p class="mt-1 text-xs text-zinc-400">seluruh surat tercatat</p>
                    </div>
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-600 ring-1 ring-rose-100">
                        <x-icon name="inbox" class="size-5" />
                    </span>
                </div>
            </article>

            <article class="card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold tracking-wide text-zinc-500 uppercase">Bulan Ini</p>
                        <p class="mt-1.5 text-3xl font-extrabold tracking-tight text-zinc-900">
                            {{ number_format($monthLetters, 0, ',', '.') }}
                        </p>
                        <p class="mt-1 text-xs text-zinc-400">{{ now()->locale('id')->translatedFormat('F Y') }}</p>
                    </div>
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-zinc-100 text-zinc-600">
                        <x-icon name="calendar" class="size-5" />
                    </span>
                </div>
            </article>

            <article class="card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold tracking-wide text-zinc-500 uppercase">Nomor Agenda Berikutnya</p>
                        <p class="mt-1.5 font-mono text-3xl font-extrabold tracking-tight text-rose-600">
                            {{ $suggestedAgendaNumber }}
                        </p>
                        <p class="mt-1 text-xs text-zinc-400">nomor terbesar saat ini + 1</p>
                    </div>
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-zinc-900 text-white">
                        <x-icon name="document-text" class="size-5" />
                    </span>
                </div>
            </article>
        </section>

        {{-- Toolbar --}}
        <section class="card p-4 sm:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <form method="GET" action="{{ route('surat-masuk.index') }}" data-table-form
                    class="flex flex-1 flex-col gap-2 sm:flex-row lg:max-w-2xl">
                    <div class="relative flex-1">
                        <x-icon name="magnifying-glass"
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400" />

                        <input type="search" name="q" value="{{ $search }}" data-table-search
                            placeholder="Cari nomor agenda, nomor surat, asal, atau perihal..."
                            aria-label="Cari surat masuk" class="form-input py-2.5 pr-10 pl-10">

                        <span data-table-spinner
                            class="pointer-events-none absolute top-1/2 right-3 hidden -translate-y-1/2 text-rose-500">
                            <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.25"
                                    stroke-width="3" />
                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3"
                                    stroke-linecap="round" />
                            </svg>
                        </span>
                    </div>

                    <select name="per_page" data-table-per-page aria-label="Jumlah data per halaman"
                        class="form-input py-2.5 sm:w-36">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected($perPage === $option)>
                                {{ $option }} data
                            </option>
                        @endforeach
                    </select>

                    <button type="submit" class="btn btn-primary py-2.5">Cari</button>
                </form>

                @if ($canManage)
                    <button type="button" data-letter-open="create" class="btn btn-primary">
                        <x-icon name="plus" class="size-4" />
                        Tambah Surat Masuk
                    </button>
                @else
                    <p
                        class="flex items-start gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs font-medium text-zinc-600">
                        <x-icon name="lock" class="mt-px size-3.5 shrink-0" />
                        Anda hanya dapat melihat dan mencetak surat masuk.
                    </p>
                @endif
            </div>
        </section>

        {{-- Tabel --}}
        <section class="card overflow-hidden">
            <div id="surat-masuk-results" data-table-results aria-live="polite" aria-busy="false"
                class="transition-opacity duration-200">
                @include('surat-masuk._results')
            </div>
        </section>

        @if ($canManage)
            @include('surat-masuk._form')
        @endif
    </div>
</x-layouts.app>
