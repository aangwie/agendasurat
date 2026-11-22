<x-layouts.app title="Pengaturan">
    <div class="space-y-6" data-settings
        data-action-url="{{ route('pengaturan.action') }}"
        data-save-url="{{ route('pengaturan.update') }}"
        data-backup-url="{{ route('pengaturan.backup') }}"
        data-import-url="{{ route('pengaturan.import') }}"
        data-log-url="{{ route('pengaturan.log') }}"
        data-log-clear-url="{{ route('pengaturan.log.clear') }}">

        {{-- Ringkasan sistem --}}
        <section
            class="relative overflow-hidden rounded-3xl bg-zinc-950 px-6 py-6 text-white shadow-lg shadow-zinc-900/10 sm:px-8">
            <div class="bg-grid-rose absolute inset-0 opacity-30" aria-hidden="true"></div>
            <div class="absolute -top-24 right-0 size-64 rounded-full bg-rose-600/25 blur-3xl" aria-hidden="true"></div>

            <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-xl">
                    <span class="badge bg-white/5 text-rose-200 ring-1 ring-white/10">
                        <x-icon name="cog" class="size-3.5" />
                        Administrator
                    </span>

                    <h2 class="mt-3 text-xl font-extrabold tracking-tight sm:text-2xl">Pengaturan Sistem</h2>

                    <p class="mt-1.5 text-sm leading-relaxed text-zinc-400">
                        Update kode ke GitHub, jalankan perintah artisan, serta backup dan import database dari satu
                        halaman.
                    </p>
                </div>

                <dl class="flex flex-wrap gap-3">
                    <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3 backdrop-blur">
                        <dt class="text-[10px] font-semibold tracking-wide text-zinc-400 uppercase">Database</dt>
                        <dd class="mt-0.5 font-mono text-sm font-bold">{{ $database['database'] }}</dd>
                        <dd class="text-[10px] text-zinc-500">{{ $database['driver'] }} &middot; {{ $database['size'] }}</dd>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3 backdrop-blur">
                        <dt class="text-[10px] font-semibold tracking-wide text-zinc-400 uppercase">Symlink</dt>
                        <dd class="mt-0.5 text-sm font-bold {{ $storageLinked ? 'text-emerald-400' : 'text-amber-400' }}">
                            {{ $storageLinked ? 'Aktif' : 'Belum dibuat' }}
                        </dd>
                        <dd class="text-[10px] text-zinc-500">public/storage</dd>
                    </div>
                </dl>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            {{-- Update GitHub --}}
            <section class="card p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-zinc-900 text-white">
                            <x-icon name="arrow-up-tray" class="size-5" />
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-zinc-900">Sinkronisasi GitHub</h3>
                            <p class="mt-0.5 text-xs text-zinc-500">Tarik perubahan terbaru dari repository.</p>
                        </div>
                    </div>

                    <span @class([
                        'badge shrink-0',
                        'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' => $github['token_set'],
                        'bg-amber-50 text-amber-700 ring-1 ring-amber-200' => ! $github['token_set'],
                    ])>
                        {{ $github['token_set'] ? 'Token tersimpan' : 'Token belum ada' }}
                    </span>
                </div>

                <form data-github-form class="mt-5 space-y-4">
                    {{-- Repository & branch dipakai dari pengaturan tersimpan (tidak ditampilkan). --}}
                    <input type="hidden" name="repo" value="{{ $github['repo'] }}">
                    <input type="hidden" name="branch" value="{{ $github['branch'] }}">

                    <div>
                        <label for="github_token" class="form-label">
                            <x-icon name="key" class="size-4 text-zinc-400" />
                            Token GitHub (PAT)
                        </label>
                        <input id="github_token" name="token" type="password" autocomplete="new-password"
                            placeholder="{{ $github['token_set'] ? $github['token_preview'] : 'github_pat_...' }}"
                            class="form-input font-mono">
                        <p class="mt-1.5 text-xs text-zinc-400">
                            {{ $github['token_set'] ? 'Kosongkan bila tidak ingin mengganti token.' : 'Token disimpan terenkripsi (AES-256).' }}
                        </p>
                    </div>

                    <div>
                        <label for="github_message" class="form-label">
                            <x-icon name="document-text" class="size-4 text-zinc-400" />
                            Pesan Commit
                        </label>
                        <input id="github_message" name="message" type="text"
                            value="chore: update dari halaman pengaturan" maxlength="200" class="form-input">
                    </div>

                    <p class="flex items-start gap-1.5 rounded-xl bg-zinc-50 px-3 py-2 text-[11px] leading-relaxed text-zinc-500">
                        <x-icon name="folder" class="mt-px size-3.5 shrink-0 text-zinc-400" />
                        <span>
                            Tujuan: <span class="font-mono">{{ $github['repo'] }}</span>
                            &middot; branch <span class="font-mono">{{ $github['branch'] }}</span>
                        </span>
                    </p>

                    <div class="flex flex-col gap-2 sm:flex-row">
                        <button type="submit" class="btn btn-secondary flex-1">
                            <x-icon name="check-circle" class="size-4" />
                            Simpan Token
                        </button>

                        <button type="button" data-verify="git-verify" class="btn btn-secondary flex-1">
                            <x-icon name="shield-check" class="size-4" />
                            Tes Token
                        </button>
                    </div>

                    <button type="button" data-run="git-pull"
                        class="flex w-full flex-col items-start gap-1 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-left transition hover:bg-emerald-100">
                        <span class="flex items-center gap-2 text-sm font-semibold text-emerald-700">
                            <x-icon name="arrow-down-tray" class="size-4" />
                            Pull dari GitHub
                        </span>
                        <span class="text-[11px] leading-relaxed text-emerald-700/80">
                            Ambil perubahan terbaru dari GitHub ke server ini.
                        </span>
                    </button>

                    <p class="flex items-start gap-1.5 rounded-xl bg-zinc-50 px-3 py-2 text-[11px] leading-relaxed text-zinc-500">
                        <x-icon name="sparkles" class="mt-px size-3.5 shrink-0 text-zinc-400" />
                        <span>
                            Alur: push dilakukan dari komputer pengembang, lalu
                            <span class="font-semibold text-zinc-700">Pull</span> di halaman ini, dan jalankan
                            migrasi bila ada perubahan database.
                        </span>
                    </p>
                </form>
            </section>

            {{-- Tools artisan --}}
            <section class="card p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-600 ring-1 ring-rose-100">
                        <x-icon name="sparkles" class="size-5" />
                    </span>
                    <div>
                        <h3 class="text-base font-bold text-zinc-900">Perintah Artisan</h3>
                        <p class="mt-0.5 text-xs text-zinc-500">Jalankan perintah perawatan aplikasi.</p>
                    </div>
                </div>

                <div class="mt-5 space-y-2.5">
                    <button type="button" data-run="migrate"
                        class="flex w-full items-center gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left transition hover:border-rose-300 hover:bg-rose-50/40">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-zinc-900 text-white">
                            <x-icon name="document-text" class="size-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-mono text-xs font-semibold text-zinc-900">php artisan migrate --force</span>
                            <span class="block text-[11px] text-zinc-500">Menjalankan migrasi database yang tertunda.</span>
                        </span>
                        <x-icon name="arrow-right" class="size-4 shrink-0 text-zinc-400" />
                    </button>

                    <button type="button" data-run="clear-cache"
                        class="flex w-full items-center gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left transition hover:border-rose-300 hover:bg-rose-50/40">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-amber-500 text-white">
                            <x-icon name="sparkles" class="size-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-mono text-xs font-semibold text-zinc-900">php artisan optimize:clear</span>
                            <span class="block text-[11px] text-zinc-500">Membersihkan cache config, route, view, dan event.</span>
                        </span>
                        <x-icon name="arrow-right" class="size-4 shrink-0 text-zinc-400" />
                    </button>

                    <button type="button" data-run="symlink"
                        class="flex w-full items-center gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-left transition hover:border-rose-300 hover:bg-rose-50/40">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-emerald-600 text-white">
                            <x-icon name="folder" class="size-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-mono text-xs font-semibold text-zinc-900">php artisan storage:link</span>
                            <span class="block text-[11px] text-zinc-500">Membuat symlink public/storage untuk logo instansi.</span>
                        </span>
                        <x-icon name="arrow-right" class="size-4 shrink-0 text-zinc-400" />
                    </button>
                </div>

                <div class="mt-5 rounded-xl bg-zinc-50 px-4 py-3 text-[11px] leading-relaxed text-zinc-500">
                    PHP: <span class="font-mono break-all">{{ $phpBinary }}</span>
                </div>
            </section>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            {{-- Backup database --}}
            <section class="card p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-900 ring-1 ring-blue-100">
                            <x-icon name="arrow-down-tray" class="size-5" />
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-zinc-900">Backup Database</h3>
                            <p class="mt-0.5 text-xs text-zinc-500">Ekspor seluruh tabel ke berkas <span class="font-mono">.sql</span>.</p>
                        </div>
                    </div>
                </div>

                <button type="button" data-backup class="btn btn-primary mt-5 w-full">
                    <x-icon name="arrow-down-tray" class="size-4" />
                    Buat Backup Sekarang
                </button>

                <div class="mt-4 space-y-2" data-backup-list>
                    @forelse ($backups as $backup)
                        <div class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white px-3 py-2.5">
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-zinc-100 text-zinc-500">
                                <x-icon name="document-text" class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-mono text-[11px] font-semibold text-zinc-800">{{ $backup['filename'] }}</p>
                                <p class="text-[10px] text-zinc-400">{{ $backup['created_at'] }}</p>
                            </div>
                            <a href="{{ route('pengaturan.backup.download', ['filename' => $backup['filename']]) }}"
                                class="shrink-0 rounded-lg px-2 py-1 text-[11px] font-semibold text-rose-600 transition hover:bg-rose-50">
                                Unduh
                            </a>
                        </div>
                    @empty
                        <p class="rounded-xl border border-dashed border-zinc-200 px-3 py-4 text-center text-xs text-zinc-400"
                            data-backup-empty>
                            Belum ada berkas backup.
                        </p>
                    @endforelse
                </div>
            </section>

            {{-- Import database --}}
            <section class="card p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-600 ring-1 ring-rose-100">
                        <x-icon name="arrow-up-tray" class="size-5" />
                    </span>
                    <div>
                        <h3 class="text-base font-bold text-zinc-900">Import Database</h3>
                        <p class="mt-0.5 text-xs text-zinc-500">Pulihkan data dari berkas backup <span class="font-mono">.sql</span>.</p>
                    </div>
                </div>

                <div class="mt-5 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-relaxed text-amber-800">
                    <x-icon name="shield-check" class="mt-0.5 size-4 shrink-0 text-amber-500" />
                    <p>
                        Import akan <span class="font-semibold">menimpa data yang ada</span>. Sistem otomatis membuat
                        backup pengaman sebelum proses berjalan.
                    </p>
                </div>

                <form data-import-form class="mt-4 space-y-3">
                    <div>
                        <label for="sql_file" class="form-label">
                            <x-icon name="folder" class="size-4 text-zinc-400" />
                            Berkas SQL
                        </label>
                        <input id="sql_file" name="sql" type="file" accept=".sql,text/plain"
                            class="form-input py-2.5 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                        <p class="mt-1.5 text-xs text-zinc-400">Maksimal 50 MB, format <span class="font-mono">.sql</span>.</p>
                    </div>

                    <button type="submit" class="btn w-full border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100">
                        <x-icon name="arrow-up-tray" class="size-4" />
                        Import Database
                    </button>
                </form>
            </section>
        </div>

        {{-- Terminal log --}}
        <section class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-800 bg-zinc-900 px-4 py-3">
                <div class="flex items-center gap-2.5">
                    <span class="flex items-center gap-1.5" aria-hidden="true">
                        <span class="size-2.5 rounded-full bg-rose-500"></span>
                        <span class="size-2.5 rounded-full bg-amber-400"></span>
                        <span class="size-2.5 rounded-full bg-emerald-500"></span>
                    </span>

                    <span class="font-mono text-xs font-semibold text-zinc-300">
                        terminal &mdash; deployment log
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <span data-terminal-status
                        class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-[10px] font-semibold text-zinc-300">
                        <span data-terminal-dot class="size-1.5 rounded-full bg-emerald-400"></span>
                        <span data-terminal-status-text>Siap</span>
                    </span>

                    <button type="button" data-log-refresh title="Muat ulang log"
                        class="rounded-lg p-1.5 text-zinc-400 transition hover:bg-white/10 hover:text-white">
                        <x-icon name="arrow-down-tray" class="size-4 rotate-180" />
                    </button>

                    <button type="button" data-log-clear title="Bersihkan log"
                        class="rounded-lg p-1.5 text-zinc-400 transition hover:bg-rose-500/20 hover:text-rose-300">
                        <x-icon name="trash" class="size-4" />
                    </button>
                </div>
            </div>

            <pre data-terminal
                class="scrollbar-slim max-h-80 min-h-[14rem] overflow-auto bg-zinc-950 px-4 py-3 font-mono text-[11.5px] leading-relaxed whitespace-pre-wrap text-zinc-300">{{ $logLines === [] ? '> Belum ada aktivitas. Jalankan salah satu aksi di atas.' : implode("\n", $logLines) }}</pre>

            <div
                class="flex flex-wrap items-center justify-between gap-2 border-t border-zinc-800 bg-zinc-900 px-4 py-2 text-[10px] text-zinc-500">
                <span>Log tersimpan di <span class="font-mono">storage/logs/deploy.log</span></span>
                <span data-terminal-time>{{ now()->format('H:i:s') }}</span>
            </div>
        </section>
    </div>
</x-layouts.app>

