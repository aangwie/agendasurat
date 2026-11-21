<x-layouts.guest title="Masuk">
    <div class="grid min-h-dvh lg:grid-cols-[1.05fr_1fr]">
        {{-- Brand panel --}}
        <section class="relative hidden overflow-hidden bg-zinc-950 lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div class="bg-grid-rose absolute inset-0 opacity-40" aria-hidden="true"></div>
            <div class="absolute -top-24 -right-16 size-96 rounded-full bg-rose-600/20 blur-3xl" aria-hidden="true">
            </div>
            <div class="absolute -bottom-32 -left-24 size-96 rounded-full bg-rose-900/30 blur-3xl" aria-hidden="true">
            </div>

            <div class="relative">
                <x-logo />
            </div>

            <div class="relative max-w-lg">
                <span class="badge bg-white/5 text-rose-200 ring-1 ring-white/10">
                    <x-icon name="sparkles" class="size-3.5" />
                    Tata kelola surat modern
                </span>

                <p class="mt-6 text-4xl leading-[1.1] font-extrabold tracking-tight text-white">
                    Kelola surat &amp; agenda kantor
                    <span class="text-rose-400">tanpa tumpukan kertas.</span>
                </p>

                <p class="mt-4 max-w-md text-sm leading-relaxed text-zinc-400">
                    Catat surat masuk dan keluar, atur agenda, dan temukan kembali arsip dalam hitungan detik.
                </p>

                <ul class="mt-8 space-y-3 text-sm text-zinc-300">
                    @foreach (['Pencatatan surat masuk & keluar yang rapi', 'Agenda kegiatan dan disposisi terpusat', 'Arsip digital mudah dicari dan aman'] as $feature)
                        <li class="flex items-start gap-3">
                            <span
                                class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-rose-500/15 text-rose-300">
                                <x-icon name="check-circle" class="size-4" />
                            </span>
                            {{ $feature }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="relative flex items-center justify-between text-xs text-zinc-500">
                <p>&copy; {{ now()->year }} {{ config('app.name', 'Agenda Surat') }}</p>
                <p class="flex items-center gap-1.5">
                    <x-icon name="shield-check" class="size-4 text-zinc-600" />
                    Aman &amp; terenkripsi
                </p>
            </div>
        </section>

        {{-- Login form --}}
        <section class="flex flex-col justify-center bg-zinc-100 px-5 py-10 sm:px-10 lg:bg-white lg:px-16">
            <div class="mx-auto w-full max-w-md">
                <div class="lg:hidden">
                    <x-logo tone="dark" />
                </div>

                <div class="mt-10 lg:mt-0">
                    <p class="text-[11px] font-bold tracking-[0.18em] text-rose-600 uppercase">Selamat datang kembali</p>
                    <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-zinc-900 sm:text-3xl">
                        Masuk ke akun Anda
                    </h1>
                    <p class="mt-2 text-sm leading-relaxed text-zinc-500">
                        Gunakan email dan kata sandi yang diberikan administrator untuk mengakses dashboard.
                    </p>
                </div>

                @if (session('status'))
                    <div
                        class="mt-6 flex items-start gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        <x-icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                        <p>{{ session('status') }}</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5" novalidate>
                    @csrf

                    <div>
                        <label for="email" class="form-label">
                            <x-icon name="mail" class="size-4 text-zinc-400" />
                            Email
                        </label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                            autocomplete="username" inputmode="email" placeholder="nama@instansi.go.id"
                            @class(['form-input', 'form-input-error' => $errors->has('email')])
                            @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>

                        @error('email')
                            <p id="email-error" role="alert"
                                class="mt-2 flex items-start gap-1.5 text-xs font-medium text-rose-600">
                                <x-icon name="x-mark" class="mt-px size-3.5 shrink-0" />
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="form-label">
                            <x-icon name="lock" class="size-4 text-zinc-400" />
                            Kata Sandi
                        </label>
                        <div class="relative">
                            <input id="password" name="password" type="password" required
                                autocomplete="current-password" placeholder="Masukkan kata sandi"
                                @class(['form-input', 'pr-12', 'form-input-error' => $errors->has('password')])
                                @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>

                            <button type="button" data-password-toggle="password" aria-pressed="false"
                                aria-label="Tampilkan kata sandi"
                                class="absolute inset-y-0 right-0 grid w-12 place-items-center rounded-r-xl text-zinc-400 transition hover:text-zinc-700">
                                <x-icon name="eye" data-icon-show class="size-5" />
                                <x-icon name="eye-slash" data-icon-hide class="hidden size-5" />
                            </button>
                        </div>

                        @error('password')
                            <p id="password-error" role="alert"
                                class="mt-2 flex items-start gap-1.5 text-xs font-medium text-rose-600">
                                <x-icon name="x-mark" class="mt-px size-3.5 shrink-0" />
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <label for="remember" class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-600">
                            <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))
                                class="size-4 rounded border-zinc-300 accent-rose-600">
                            Ingat saya
                        </label>

                        <span class="text-xs text-zinc-400">Lupa sandi? Hubungi admin</span>
                    </div>

                    <button type="submit" class="btn btn-primary w-full py-3">
                        Masuk
                        <x-icon name="arrow-right" class="size-4" />
                    </button>
                </form>

                @if (app()->environment('local'))
                    <div class="mt-8 rounded-xl border border-dashed border-zinc-300 bg-zinc-50/80 p-4">
                        <p class="flex items-center gap-1.5 text-[11px] font-bold tracking-wide text-zinc-500 uppercase">
                            <x-icon name="key" class="size-3.5" />
                            Akun demo
                        </p>
                        <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs text-zinc-600">
                            <dt class="font-medium text-zinc-400">Email</dt>
                            <dd class="font-mono">admin@agendasurat.test</dd>
                            <dt class="font-medium text-zinc-400">Sandi</dt>
                            <dd class="font-mono">password</dd>
                        </dl>
                    </div>
                @endif

                <p class="mt-8 text-center text-xs text-zinc-400">
                    Dengan masuk, Anda menyetujui kebijakan penggunaan internal instansi.
                </p>
            </div>
        </section>
    </div>
</x-layouts.guest>
