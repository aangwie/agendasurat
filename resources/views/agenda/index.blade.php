<x-layouts.app title="Agenda">
    <div class="space-y-6" data-datatable="server" data-endpoint="{{ route('agenda.index') }}"
        data-open-form="{{ $openForm }}" data-open-form-id="{{ $openFormId }}">

        {{-- Ringkasan --}}
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <article class="card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold tracking-wide text-zinc-500 uppercase">Total Agenda</p>
                        <p class="mt-1.5 text-3xl font-extrabold tracking-tight text-zinc-900">
                            {{ number_format($totalAgendas, 0, ',', '.') }}
                        </p>
                        <p class="mt-1 text-xs text-zinc-400">seluruh kegiatan tercatat</p>
                    </div>
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-600 ring-1 ring-rose-100">
                        <x-icon name="calendar" class="size-5" />
                    </span>
                </div>
            </article>

            <article class="card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold tracking-wide text-zinc-500 uppercase">Agenda Mendatang</p>
                        <p class="mt-1.5 text-3xl font-extrabold tracking-tight text-zinc-900">
                            {{ number_format($upcomingAgendas, 0, ',', '.') }}
                        </p>
                        <p class="mt-1 text-xs text-zinc-400">hari ini dan setelahnya</p>
                    </div>
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-zinc-900 text-white">
                        <x-icon name="clock" class="size-5" />
                    </span>
                </div>
            </article>

            <article class="card flex items-center p-5">
                <button type="button" data-agenda-open="create"
                    class="btn btn-primary w-full py-3">
                    <x-icon name="plus" class="size-4" />
                    Tambah Agenda
                </button>
            </article>
        </section>

        {{-- Toolbar --}}
        <section class="card p-4 sm:p-5">
            <form method="GET" action="{{ route('agenda.index') }}" data-table-form
                class="flex flex-col gap-2 sm:flex-row">
                <div class="relative flex-1">
                    <x-icon name="magnifying-glass"
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400" />

                    <input type="search" name="q" value="{{ $search }}" data-table-search
                        placeholder="Cari tempat, penanggung jawab, atau peserta..."
                        aria-label="Cari agenda" class="form-input py-2.5 pr-10 pl-10">

                    <span data-table-spinner
                        class="pointer-events-none absolute top-1/2 right-3 hidden -translate-y-1/2 text-rose-500">
                        <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.25" stroke-width="3" />
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3"
                                stroke-linecap="round" />
                        </svg>
                    </span>
                </div>

                <select name="per_page" data-table-per-page aria-label="Jumlah data per halaman"
                    class="form-input py-2.5 sm:w-36">
                    @foreach ($perPageOptions as $option)
                        <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }} data</option>
                    @endforeach
                </select>

                <button type="submit" class="btn btn-primary py-2.5">Cari</button>
            </form>
        </section>

        {{-- Tabel --}}
        <section class="card overflow-hidden">
            <div id="agenda-results" data-table-results aria-live="polite" aria-busy="false"
                class="transition-opacity duration-200">
                @include('agenda._results')
            </div>
        </section>

        @include('agenda._form')
    </div>
</x-layouts.app>
