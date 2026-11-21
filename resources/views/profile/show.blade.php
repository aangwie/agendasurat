<x-layouts.app title="Profil Saya">
    <div class="mx-auto max-w-4xl space-y-6">
        <section class="card overflow-hidden">
            <div class="relative h-28 bg-zinc-950 sm:h-32">
                <div class="bg-grid-rose absolute inset-0 opacity-30" aria-hidden="true"></div>
                <div class="absolute -top-16 right-6 size-52 rounded-full bg-rose-600/25 blur-3xl" aria-hidden="true">
                </div>
            </div>

            <div class="px-5 pb-6 sm:px-6">
                <div class="-mt-10 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div class="flex items-end gap-4">
                        <span
                            class="grid size-20 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-rose-500 to-rose-700 text-2xl font-extrabold text-white shadow-lg shadow-rose-600/30 ring-4 ring-white">
                            {{ $user->initials }}
                        </span>

                        <div class="min-w-0 pb-1">
                            <h2 class="truncate text-xl font-extrabold tracking-tight text-zinc-900">
                                {{ $user->name }}
                            </h2>
                            <p class="truncate text-sm text-zinc-500">{{ $user->email }}</p>
                        </div>
                    </div>

                    <span class="badge w-fit bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                        <span class="size-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                        Akun aktif
                    </span>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
            <section class="card p-5 sm:p-6 lg:col-span-3">
                <div class="flex items-center gap-2">
                    <x-icon name="user" class="size-4 text-rose-500" />
                    <h3 class="text-sm font-bold tracking-wide text-zinc-900 uppercase">Informasi Akun</h3>
                </div>

                <dl class="mt-5 divide-y divide-zinc-100">
                    <div class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <dt class="text-sm text-zinc-500">Nama Bidang</dt>
                        <dd class="text-sm font-semibold text-zinc-900">{{ $user->name }}</dd>
                    </div>
                    <div class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <dt class="text-sm text-zinc-500">Email</dt>
                        <dd class="text-sm font-semibold break-all text-zinc-900">{{ $user->email }}</dd>
                    </div>
                    <div class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <dt class="text-sm text-zinc-500">ID pengguna</dt>
                        <dd class="font-mono text-sm font-semibold text-zinc-900">
                            #{{ str_pad((string) $user->id, 4, '0', STR_PAD_LEFT) }}
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <dt class="text-sm text-zinc-500">No HP</dt>
                        <dd class="font-mono text-sm font-semibold text-zinc-900">
                            {{ $user->phone ?: '—' }}
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <dt class="text-sm text-zinc-500">Terdaftar sejak</dt>
                        <dd class="text-sm font-semibold text-zinc-900">
                            {{ $user->created_at?->locale('id')->translatedFormat('j F Y') ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <dt class="text-sm text-zinc-500">Status email</dt>
                        <dd>
                            @if ($user->email_verified_at)
                                <span class="badge bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                                    Terverifikasi
                                </span>
                            @else
                                <span class="badge bg-amber-50 text-amber-700 ring-1 ring-amber-200">
                                    Belum terverifikasi
                                </span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="card flex flex-col p-5 sm:p-6 lg:col-span-2">
                <div class="flex items-center gap-2">
                    <x-icon name="shield-check" class="size-4 text-rose-500" />
                    <h3 class="text-sm font-bold tracking-wide text-zinc-900 uppercase">Keamanan</h3>
                </div>

                <div class="mt-5 rounded-xl border border-zinc-200 bg-zinc-50/70 p-4">
                    <p class="flex items-center gap-1.5 text-xs font-semibold text-zinc-500">
                        <x-icon name="key" class="size-3.5" />
                        Kata sandi
                    </p>
                    <p class="mt-1 font-mono text-lg tracking-widest text-zinc-900">
                        &bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;
                    </p>
                </div>

                <p class="mt-4 text-xs leading-relaxed text-zinc-500">
                    Pembaruan profil dan kata sandi akan tersedia pada tahap pengembangan berikutnya. Hubungi
                    administrator apabila Anda perlu mengubah data akun.
                </p>

                <div class="mt-auto space-y-2.5 pt-6">
                    <a href="{{ route('dashboard') }}" class="btn btn-secondary w-full">
                        <x-icon name="home" class="size-4" />
                        Kembali ke Dashboard
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="btn w-full border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100">
                            <x-icon name="logout" class="size-4" />
                            Keluar dari akun
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
