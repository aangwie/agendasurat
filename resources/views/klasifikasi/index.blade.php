@use('App\Models\LetterClassification')

<x-layouts.app title="Klasifikasi">
    <div class="space-y-6" data-datatable data-endpoint="{{ route('klasifikasi.index') }}">
        {{-- Ringkasan & sumber data --}}
        <section class="card overflow-hidden">
            <div class="relative border-b border-zinc-100 bg-zinc-950 px-5 py-6 text-white sm:px-6">
                <div class="bg-grid-rose absolute inset-0 opacity-30" aria-hidden="true"></div>
                <div class="absolute -top-20 right-0 size-56 rounded-full bg-rose-600/25 blur-3xl" aria-hidden="true">
                </div>

                <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="max-w-2xl">
                        <span class="badge bg-white/5 text-rose-200 ring-1 ring-white/10">
                            <x-icon name="document-text" class="size-3.5" />
                            Permendagri Nomor 83 Tahun 2022
                        </span>

                        <h2 class="mt-3 text-xl font-extrabold tracking-tight sm:text-2xl">
                            Klasifikasi Surat
                        </h2>

                        <p class="mt-1.5 text-sm leading-relaxed text-zinc-400">
                            Hierarki kode klasifikasi arsip sesuai lampiran Permendagri 83/2022 tentang Kode
                            Klasifikasi Arsip di Lingkungan Kementerian Dalam Negeri dan Pemerintah Daerah.
                        </p>
                    </div>

                    <dl class="flex shrink-0 gap-3">
                        <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3 backdrop-blur">
                            <dt class="text-[10px] font-semibold tracking-wide text-zinc-400 uppercase">Total Kode</dt>
                            <dd class="mt-0.5 text-2xl font-extrabold">{{ number_format($totalAll, 0, ',', '.') }}</dd>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3 backdrop-blur">
                            <dt class="text-[10px] font-semibold tracking-wide text-zinc-400 uppercase">Kedalaman</dt>
                            <dd class="mt-0.5 text-2xl font-extrabold">
                                4 <span class="text-sm font-medium text-zinc-400">level</span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Tab lingkup & toolbar --}}
            <div class="flex flex-col gap-3 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="inline-flex w-full rounded-xl bg-zinc-100 p-1 sm:w-auto" role="tablist"
                    aria-label="Lingkup klasifikasi">
                    @foreach (LetterClassification::SCOPE_LABELS as $key => $label)
                        <a href="{{ route('klasifikasi.index', ['scope' => $key, 'per_page' => $perPage]) }}"
                            data-table-scope="{{ $key }}" aria-selected="{{ $scope === $key ? 'true' : 'false' }}"
                            @class([
                                'flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold transition sm:flex-none sm:text-sm',
                                'bg-white text-zinc-900 shadow-sm' => $scope === $key,
                                'text-zinc-500 hover:text-zinc-800' => $scope !== $key,
                            ])>
                            {{ $label }}
                            <span @class([
                                'rounded-full px-1.5 py-0.5 text-[10px] font-bold',
                                'bg-rose-50 text-rose-600' => $scope === $key,
                                'bg-zinc-200/70 text-zinc-500' => $scope !== $key,
                            ])>{{ number_format($counts[$key] ?? 0, 0, ',', '.') }}</span>
                        </a>
                    @endforeach
                </div>

                <form method="GET" action="{{ route('klasifikasi.index') }}" data-table-form
                    class="flex flex-1 flex-col gap-2 sm:flex-row lg:max-w-xl">
                    <input type="hidden" name="scope" value="{{ $scope }}" data-table-scope-input>

                    <div class="relative flex-1">
                        <x-icon name="magnifying-glass"
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400" />

                        <input type="search" name="q" value="{{ $search }}" data-table-search
                            placeholder="Cari kode atau uraian klasifikasi..." aria-label="Cari klasifikasi"
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
                            <option value="{{ $option }}" @selected($perPage === $option)>
                                {{ $option }} data
                            </option>
                        @endforeach
                    </select>

                    <button type="submit" class="btn btn-primary py-2.5">
                        Cari
                    </button>
                </form>
            </div>
        </section>

        {{-- Tabel --}}
        <section class="card overflow-hidden">
            <div id="klasifikasi-results" data-table-results aria-live="polite" aria-busy="false"
                class="transition-opacity duration-200">
                @include('klasifikasi._results')
            </div>
        </section>

        <p class="px-1 text-xs leading-relaxed text-zinc-400">
            Sumber: Lampiran Peraturan Menteri Dalam Negeri Nomor 83 Tahun 2022 tentang Kode Klasifikasi Arsip di
            Lingkungan Kementerian Dalam Negeri dan Pemerintah Daerah.
        </p>
    </div>
</x-layouts.app>

