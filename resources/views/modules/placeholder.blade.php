<x-layouts.app :title="$title">
    <div class="mx-auto max-w-4xl space-y-6">
        <section class="card relative overflow-hidden p-6 sm:p-8">
            <div class="absolute -top-20 -right-10 size-56 rounded-full bg-rose-100/70 blur-3xl" aria-hidden="true">
            </div>

            <div class="relative flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-4">
                    <span
                        class="grid size-14 shrink-0 place-items-center rounded-2xl bg-rose-50 text-rose-600 ring-1 ring-rose-100">
                        <x-icon :name="$icon" class="size-7" />
                    </span>

                    <div class="min-w-0">
                        <span class="badge bg-zinc-100 text-zinc-600">
                            <span class="size-1.5 rounded-full bg-rose-500" aria-hidden="true"></span>
                            Segera hadir
                        </span>

                        <h2 class="mt-2 text-xl font-extrabold tracking-tight text-zinc-900 sm:text-2xl">
                            {{ $title }}
                        </h2>

                        <p class="mt-2 max-w-xl text-sm leading-relaxed text-zinc-500">
                            {{ $description }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="relative mt-6 flex flex-wrap gap-2.5">
                <a href="{{ route('dashboard') }}" class="btn btn-primary">
                    <x-icon name="home" class="size-4" />
                    Kembali ke Dashboard
                </a>

                <a href="{{ route('profil.show') }}" class="btn btn-secondary">
                    <x-icon name="user" class="size-4" />
                    Profil Saya
                </a>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-3" aria-label="Rencana fitur modul">
            @foreach ($features as $feature)
                <article class="card p-5">
                    <span
                        class="grid size-9 place-items-center rounded-xl bg-zinc-900 text-white shadow-sm">
                        <x-icon name="check-circle" class="size-4" />
                    </span>
                    <p class="mt-3 text-sm font-semibold text-zinc-900">{{ $feature }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-zinc-500">
                        Direncanakan pada tahap pengembangan berikutnya.
                    </p>
                </article>
            @endforeach
        </section>

        <section class="card p-5 sm:p-6">
            <div class="flex items-center gap-2">
                <x-icon name="sparkles" class="size-4 text-rose-500" />
                <h3 class="text-sm font-bold tracking-wide text-zinc-900 uppercase">Tahapan Pengembangan</h3>
            </div>

            <ol class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                @foreach ([
                    ['step' => '01', 'title' => 'Skema data', 'desc' => 'Perancangan tabel dan relasi modul.'],
                    ['step' => '02', 'title' => 'Antarmuka', 'desc' => 'Form input, daftar, dan filter data.'],
                    ['step' => '03', 'title' => 'Pengujian', 'desc' => 'Uji alur kerja dan peluncuran modul.'],
                ] as $stage)
                    <li class="relative rounded-xl border border-zinc-200 bg-zinc-50/60 p-4">
                        <span class="font-mono text-[11px] font-bold text-rose-500">{{ $stage['step'] }}</span>
                        <p class="mt-1 text-sm font-semibold text-zinc-900">{{ $stage['title'] }}</p>
                        <p class="mt-1 text-xs leading-relaxed text-zinc-500">{{ $stage['desc'] }}</p>
                    </li>
                @endforeach
            </ol>

            <p class="mt-5 rounded-xl border border-dashed border-zinc-200 p-3 text-xs leading-relaxed text-zinc-500">
                Halaman ini adalah penanda modul. Setup awal saat ini fokus pada autentikasi, layout, dan dashboard.
            </p>
        </section>
    </div>
</x-layouts.app>
