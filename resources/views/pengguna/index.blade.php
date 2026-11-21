<x-layouts.app title="Pengguna">
    <div class="space-y-6" data-datatable="server" data-endpoint="{{ route('pengguna.index') }}"
        data-open-form="{{ $openForm }}" data-open-form-id="{{ $openFormId }}">
        {{-- Ringkasan --}}
        <section class="card p-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-zinc-900 text-white">
                        <x-icon name="users" class="size-5" />
                    </span>

                    <div>
                        <h2 class="text-base font-bold text-zinc-900">Manajemen Pengguna</h2>
                        <p class="mt-0.5 text-xs text-zinc-500">
                            Total <span class="font-semibold text-zinc-700">{{ number_format($totalUsers, 0, ',', '.') }}</span>
                            akun terdaftar
                        </p>
                    </div>
                </div>

                @error('pengguna')
                    <p role="alert"
                        class="flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">
                        <x-icon name="x-mark" class="mt-px size-3.5 shrink-0" />
                        {{ $message }}
                    </p>
                @enderror
            </div>
        </section>

        {{-- Toolbar --}}
        <section class="card p-4 sm:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <form method="GET" action="{{ route('pengguna.index') }}" data-table-form
                    class="flex flex-1 flex-col gap-2 sm:flex-row lg:max-w-2xl">
                    <div class="relative flex-1">
                        <x-icon name="magnifying-glass"
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400" />

                        <input type="search" name="q" value="{{ $search }}" data-table-search
                            placeholder="Cari nama bidang, email, atau no HP..." aria-label="Cari pengguna"
                            class="form-input py-2.5 pr-10 pl-10">

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
                            <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }} data</option>
                        @endforeach
                    </select>

                    <button type="submit" class="btn btn-primary py-2.5">Cari</button>
                </form>

                <button type="button" data-user-open="create" class="btn btn-primary">
                    <x-icon name="plus" class="size-4" />
                    Tambah Pengguna
                </button>
            </div>
        </section>

        {{-- Tabel --}}
        <section class="card overflow-hidden">
            <div id="pengguna-results" data-table-results aria-live="polite" aria-busy="false"
                class="transition-opacity duration-200">
                @include('pengguna._results')
            </div>
        </section>

        @include('pengguna._form')
    </div>
</x-layouts.app>
