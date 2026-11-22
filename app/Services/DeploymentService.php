<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

class DeploymentService
{
    /**
     * Lokasi berkas log yang ditampilkan pada terminal halaman Pengaturan.
     */
    public function logPath(): string
    {
        return storage_path('logs/deploy.log');
    }

    /**
     * Ambil baris log terakhir.
     *
     * @return list<string>
     */
    public function logLines(int $limit = 300): array
    {
        $path = $this->logPath();

        if (! File::exists($path)) {
            return [];
        }

        $lines = preg_split('/\R/', (string) File::get($path)) ?: [];

        return array_values(array_slice(array_filter($lines, fn ($line) => $line !== ''), -$limit));
    }

    /**
     * Tulis satu blok log.
     */
    public function appendLog(string $text): void
    {
        File::ensureDirectoryExists(dirname($this->logPath()));

        File::append($this->logPath(), $text."\n");
    }

    /**
     * Kosongkan log terminal.
     */
    public function clearLog(): void
    {
        File::put($this->logPath(), '');
    }

    /**
     * Jalankan perintah tanpa shell (aman dari injeksi) dan kembalikan output.
     *
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     * @return array{output: string, exitCode: int}
     */
    public function runProcess(array $command, int $timeout = 120, array $env = [], ?string $cwd = null): array
    {
        // Pengaman: jangan pernah mengeksekusi perintah saat fitur dinonaktifkan
        // (mis. pada lingkungan pengujian) agar repository/database tidak berubah.
        if (! config('surat.deployment.commands_enabled', true)) {
            return [
                'output' => '(perintah dinonaktifkan: DEPLOY_COMMANDS_ENABLED=false)',
                'exitCode' => 0,
            ];
        }

        $process = new Process($command, $cwd ?? base_path(), $this->processEnv($env));
        $process->setTimeout($timeout);
        $process->run();

        $output = trim($process->getOutput()."\n".$process->getErrorOutput());

        return [
            'output' => $output === '' ? '(tidak ada output)' : $output,
            'exitCode' => $process->getExitCode() ?? 1,
        ];
    }

    /**
     * Environment untuk proses: non-interaktif + tanpa prompt kredensial.
     *
     * @param  array<string, string>  $env
     * @return array<string, string>
     */
    private function processEnv(array $env = []): array
    {
        $base = [
            'GIT_TERMINAL_PROMPT' => '0',
            'GCM_INTERACTIVE' => 'never',
            'GIT_ASKPASS' => '',
            'LC_ALL' => 'C',
        ];

        // Pertahankan PATH & SystemRoot agar binary Windows tetap ditemukan.
        foreach (['PATH', 'SystemRoot', 'SYSTEMROOT', 'COMSPEC', 'HOME', 'USERPROFILE', 'APPDATA', 'TEMP', 'TMP'] as $key) {
            $value = getenv($key);

            if ($value !== false) {
                $base[$key] = $value;
            }
        }

        return array_merge($base, $env);
    }

    /**
     * Cari binary (mis. mysqldump) pada PATH atau lokasi XAMPP umum.
     */
    public function resolveBinary(string $name): ?string
    {
        $process = new Process([$name, '--version'], base_path(), $this->processEnv());
        $process->setTimeout(10);
        $process->run();

        if ($process->isSuccessful()) {
            return $name;
        }

        $candidates = [
            'C:/xampp/mysql/bin/'.$name.'.exe',
            'C:/xampp/mysql/bin/'.$name,
            'C:/laragon/bin/mysql/mysql-8.0/bin/'.$name.'.exe',
            'C:/wamp64/bin/mysql/mysql8.0.31/bin/'.$name.'.exe',
        ];

        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Periksa token GitHub: akun pemilik token + izin tulis ke repository.
     *
     * @return array{ok: bool, can_push: bool, login: ?string, default_branch: ?string, scopes: ?string, message: string}
     */
    public function verifyGitHubToken(string $token, string $repo): array
    {
        $slug = $this->repositorySlug($repo);

        if ($slug === null) {
            return [
                'ok' => false, 'can_push' => false, 'login' => null,
                'default_branch' => null, 'scopes' => null,
                'message' => 'Format repository tidak dikenali. Gunakan bentuk github.com/owner/repo.',
            ];
        }

        $headers = ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'AgendaSurat'];

        try {
            $user = Http::withToken($token)->withHeaders($headers)->timeout(15)
                ->get('https://api.github.com/user');

            if ($user->status() === 401) {
                return [
                    'ok' => false, 'can_push' => false, 'login' => null,
                    'default_branch' => null, 'scopes' => null,
                    'message' => 'Token tidak valid atau kedaluwarsa (401 Bad credentials). Buat token baru.',
                ];
            }

            if (! $user->successful()) {
                return [
                    'ok' => false, 'can_push' => false, 'login' => null,
                    'default_branch' => null, 'scopes' => null,
                    'message' => 'Gagal menghubungi GitHub API (HTTP '.$user->status().').',
                ];
            }

            $login = (string) $user->json('login');
            $scopes = $user->header('x-oauth-scopes');

            $repository = Http::withToken($token)->withHeaders($headers)->timeout(15)
                ->get('https://api.github.com/repos/'.$slug);

            if ($repository->status() === 404) {
                return [
                    'ok' => false, 'can_push' => false, 'login' => $login,
                    'default_branch' => null, 'scopes' => $scopes,
                    'message' => "Token milik {$login} tidak memiliki akses ke repository {$slug} (404). "
                        .'Pada fine-grained token, pilih repository ini di "Repository access".',
                ];
            }

            if (! $repository->successful()) {
                return [
                    'ok' => false, 'can_push' => false, 'login' => $login,
                    'default_branch' => null, 'scopes' => $scopes,
                    'message' => 'Gagal membaca repository '.$slug.' (HTTP '.$repository->status().').',
                ];
            }

            $permissions = $repository->json('permissions');
            $canPush = is_array($permissions) ? (bool) ($permissions['push'] ?? false) : false;
            $defaultBranch = $repository->json('default_branch');

            if (! $canPush) {
                return [
                    'ok' => true, 'can_push' => false, 'login' => $login,
                    'default_branch' => is_string($defaultBranch) ? $defaultBranch : null,
                    'scopes' => $scopes,
                    'message' => "Token dikenali sebagai {$login}, tetapi TIDAK punya izin tulis ke {$slug}. "
                        .'Aktifkan permission "Contents: Read and write" (fine-grained) '
                        .'atau scope "repo" (classic token).',
                ];
            }

            return [
                'ok' => true, 'can_push' => true, 'login' => $login,
                'default_branch' => is_string($defaultBranch) ? $defaultBranch : null,
                'scopes' => $scopes,
                'message' => "Token valid: {$login} memiliki izin tulis ke {$slug}.",
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false, 'can_push' => false, 'login' => null,
                'default_branch' => null, 'scopes' => null,
                'message' => 'Tidak dapat menghubungi GitHub: '.$exception->getMessage(),
            ];
        }
    }

    /**
     * Update repository ke GitHub memakai Personal Access Token.
     *
     * @return array{ok: bool, log: string}
     */
    public function pushToGitHub(string $token, string $repo, string $branch, string $message): array
    {
        $this->appendLog('=== '.now()->format('Y-m-d H:i:s').' | Update ke GitHub ===');

        $repo = trim($repo);
        $branch = trim($branch) ?: 'main';
        $message = trim($message) ?: 'chore: update dari halaman pengaturan';

        if ($token === '' || $repo === '') {
            $this->appendLog('GAGAL: token GitHub dan repository wajib diisi.');

            return ['ok' => false, 'log' => 'Token GitHub dan repository wajib diisi.'];
        }

        // 1. Verifikasi token lebih dulu agar penyebab 403/401 langsung jelas.
        $check = $this->verifyGitHubToken($token, $repo);
        $this->appendLog('Verifikasi token: '.$check['message']);

        $log = ['Verifikasi token: '.$check['message']];

        // Perintah git tidak dijalankan bila dinonaktifkan (mis. pada pengujian).
        if (! config('surat.deployment.commands_enabled', true)) {
            $this->appendLog('Perintah git dilewati (DEPLOY_COMMANDS_ENABLED=false).');

            return [
                'ok' => false,
                'log' => implode("\n", $log),
                'hint' => $check['can_push']
                    ? 'Perintah git dinonaktifkan pada environment ini.'
                    : $check['message'],
            ];
        }

        if (! $check['ok'] || ! $check['can_push']) {
            $this->appendLog('PERINGATAN: token tidak dapat dipakai untuk push.');

            // Jalur cadangan: commit lalu push melalui remote "origin".
            if (! $this->commitChanges($message, $log)) {
                return ['ok' => false, 'log' => implode("\n", $log), 'hint' => $check['message']];
            }

            if ($this->pushToOrigin($branch, $log) === true) {
                $this->appendLog('SELESAI: berhasil di-push melalui remote origin.');

                return [
                    'ok' => true,
                    'log' => implode("\n", $log),
                    'hint' => $check['message'].' Push dilakukan melalui remote origin (kredensial server).',
                ];
            }

            return ['ok' => false, 'log' => implode("\n", $log), 'hint' => $check['message']];
        }

        // 2. Commit perubahan.
        if (! $this->commitChanges($message, $log)) {
            return ['ok' => false, 'log' => implode("\n", $log)];
        }

        $remote = $this->authenticatedRemote($token, $repo);
        $push = $this->runProcess(['git', 'push', $remote, 'HEAD:'.$branch], 180);
        $masked = $this->maskSecrets($push['output'], [$token]);
        $maskedRemote = $this->maskSecrets($remote, [$token]);

        $log[] = '$ git push '.$maskedRemote.' HEAD:'.$branch;
        $log[] = $masked;

        $this->appendLog('$ git push '.$maskedRemote.' HEAD:'.$branch);
        $this->appendLog($masked);

        if ($push['exitCode'] === 0) {
            $this->appendLog('SELESAI: berhasil di-push.');

            return ['ok' => true, 'log' => implode("\n", $log)];
        }

        // 4. Push via token gagal: diagnosis + jalur cadangan remote "origin".
        $diagnosis = $this->diagnosePushFailure($masked);
        $this->appendLog('GAGAL: '.$diagnosis);

        if ($this->pushToOrigin($branch, $log) === true) {
            $this->appendLog('SELESAI: berhasil di-push melalui remote origin.');

            return [
                'ok' => true,
                'log' => implode("\n", $log),
                'hint' => 'Push berhasil melalui remote origin (kredensial tersimpan di server).',
            ];
        }

        return ['ok' => false, 'log' => implode("\n", $log), 'hint' => $diagnosis];
    }

    /**
     * Commit seluruh perubahan. Mengembalikan false bila gagal.
     *
     * @param  list<string>  $log
     */
    private function commitChanges(string $message, array &$log): bool
    {
        $steps = [
            ['git', 'add', '-A'],
            [
                'git',
                '-c', 'user.name=Agenda Surat',
                '-c', 'user.email=noreply@agendasurat.local',
                'commit', '-m', $message,
            ],
        ];

        foreach ($steps as $step) {
            $result = $this->runProcess($step);
            $log[] = '$ '.implode(' ', $step);
            $log[] = $result['output'];
            $this->appendLog('$ '.implode(' ', $step));
            $this->appendLog($result['output']);

            // "nothing to commit" bukan kegagalan: tetap lanjut push.
            if ($result['exitCode'] !== 0 && ! str_contains(strtolower($result['output']), 'nothing to commit')) {
                $this->appendLog('GAGAL pada langkah: '.implode(' ', $step));

                return false;
            }
        }

        return true;
    }

    /**
     * Push melalui remote "origin" (kredensial tersimpan di server).
     * Mengembalikan null bila remote origin tidak tersedia.
     *
     * @param  list<string>  $log
     */
    private function pushToOrigin(string $branch, array &$log): ?bool
    {
        if (! config('surat.deployment.origin_fallback', true)) {
            $this->appendLog('Jalur cadangan origin dinonaktifkan (DEPLOY_ORIGIN_FALLBACK=false).');

            return null;
        }

        if (! $this->hasRemote('origin')) {
            return null;
        }

        $this->appendLog('Mencoba jalur cadangan: git push origin HEAD:'.$branch);

        $result = $this->runProcess(['git', 'push', 'origin', 'HEAD:'.$branch], 180);
        $log[] = '$ git push origin HEAD:'.$branch.' (cadangan)';
        $log[] = $result['output'];
        $this->appendLog('$ git push origin HEAD:'.$branch.' (cadangan)');
        $this->appendLog($result['output']);

        return $result['exitCode'] === 0;
    }

    /**
     * Terjemahkan kegagalan push menjadi panduan yang bisa ditindaklanjuti.
     */
    public function diagnosePushFailure(string $output): string
    {
        $lower = strtolower($output);

        return match (true) {
            str_contains($lower, 'denied to'), str_contains($lower, '403') => 'Token tidak punya izin tulis ke repository. '
                .'Buat fine-grained token dengan permission "Contents: Read and write" '
                .'dan pastikan repository ini dipilih pada "Repository access".',
            str_contains($lower, 'bad credentials'), str_contains($lower, '401') => 'Token tidak valid atau kedaluwarsa. Buat token baru.',
            str_contains($lower, 'repository not found'), str_contains($lower, '404') => 'Repository tidak ditemukan atau token tidak diberi akses ke repository tersebut.',
            str_contains($lower, 'protected') => 'Branch dilindungi (protected branch). Push langsung ke branch ini ditolak.',
            str_contains($lower, 'non-fast-forward'), str_contains($lower, 'rejected') => 'Remote memiliki commit yang belum ada di server. Tarik (pull) perubahan terlebih dahulu.',
            str_contains($lower, 'terminal prompts disabled') => 'Kredensial tidak tersedia. Isi token GitHub terlebih dahulu.',
            default => 'Push ditolak. Periksa detail pada log di atas.',
        };
    }

    /**
     * Apakah remote dengan nama tertentu terdaftar?
     */
    private function hasRemote(string $name): bool
    {
        $result = $this->runProcess(['git', 'remote']);

        return collect(preg_split('/\R/', $result['output']))
            ->map(fn ($line) => trim((string) $line))
            ->contains($name);
    }

    /**
     * Ambil bentuk "owner/repo" dari berbagai format input.
     */
    public function repositorySlug(string $repo): ?string
    {
        $repo = preg_replace('#^https?://#i', '', trim($repo)) ?? '';
        $repo = preg_replace('#\.git$#i', '', $repo) ?? '';
        $repo = preg_replace('#^(www\.)?github\.com/#i', '', $repo) ?? '';
        $repo = trim($repo, '/');

        return preg_match('#^[\w.-]+/[\w.-]+$#', $repo) === 1 ? $repo : null;
    }

    /**
     * Susun URL remote dengan token (https://x-access-token:TOKEN@github.com/owner/repo.git).
     */
    private function authenticatedRemote(string $token, string $repo): string
    {
        $slug = $this->repositorySlug($repo) ?? preg_replace('#\.git$#i', '', trim($repo));

        return 'https://x-access-token:'.rawurlencode($token).'@github.com/'.$slug.'.git';
    }

    /**
     * Sembunyikan nilai rahasia pada output.
     *
     * @param  list<string>  $secrets
     */
    public function maskSecrets(string $text, array $secrets): string
    {
        foreach ($secrets as $secret) {
            if (filled($secret)) {
                $text = str_replace($secret, '***', $text);
            }
        }

        return $text;
    }

    /**
     * Jalankan migrasi database.
     *
     * @return array{ok: bool, log: string}
     */
    public function migrate(): array
    {
        return $this->runLoggedAction(
            'php artisan migrate --force',
            [PHP_BINARY, 'artisan', 'migrate', '--force'],
            300,
        );
    }

    /**
     * Bersihkan cache aplikasi.
     *
     * @return array{ok: bool, log: string}
     */
    public function clearCache(): array
    {
        return $this->runLoggedAction(
            'php artisan optimize:clear',
            [PHP_BINARY, 'artisan', 'optimize:clear'],
            120,
        );
    }

    /**
     * Buat symlink public/storage.
     *
     * @return array{ok: bool, log: string}
     */
    public function storageLink(): array
    {
        return $this->runLoggedAction(
            'php artisan storage:link',
            [PHP_BINARY, 'artisan', 'storage:link'],
            60,
        );
    }

    /**
     * @param  list<string>  $command
     * @return array{ok: bool, log: string}
     */
    private function runLoggedAction(string $label, array $command, int $timeout): array
    {
        $this->appendLog('=== '.now()->format('Y-m-d H:i:s').' | '.$label.' ===');

        $result = $this->runProcess($command, $timeout);

        $this->appendLog($result['output']);
        $this->appendLog($result['exitCode'] === 0 ? 'SELESAI (kode 0).' : 'GAGAL (kode '.$result['exitCode'].').');

        return [
            'ok' => $result['exitCode'] === 0,
            'log' => $result['output'],
        ];
    }

    /**
     * Direktori penyimpanan berkas backup.
     */
    public function backupDirectory(): string
    {
        return storage_path('app/backups');
    }

    /**
     * Buat backup database (mysqldump) dan kembalikan path berkasnya.
     *
     * @return array{ok: bool, log: string, path?: string, filename?: string}
     */
    public function backupDatabase(): array
    {
        $this->appendLog('=== '.now()->format('Y-m-d H:i:s').' | Backup database ===');

        $dump = $this->resolveBinary('mysqldump');

        if ($dump === null) {
            $this->appendLog('GAGAL: mysqldump tidak ditemukan (pastikan MySQL/XAMPP terpasang).');

            return ['ok' => false, 'log' => 'mysqldump tidak ditemukan.'];
        }

        File::ensureDirectoryExists($this->backupDirectory());

        $filename = 'backup-'.now()->format('Ymd-His').'.sql';
        $path = $this->backupDirectory().DIRECTORY_SEPARATOR.$filename;

        $command = [
            $dump,
            '--host='.config('database.connections.mysql.host'),
            '--port='.config('database.connections.mysql.port'),
            '--user='.config('database.connections.mysql.username'),
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--routines',
            '--result-file='.$path,
            config('database.connections.mysql.database'),
        ];

        $result = $this->runProcess($command, 600, [
            'MYSQL_PWD' => (string) config('database.connections.mysql.password'),
        ]);

        if ($result['exitCode'] !== 0 || ! File::exists($path)) {
            $this->appendLog('GAGAL: '.$result['output']);

            return ['ok' => false, 'log' => $result['output']];
        }

        $size = File::size($path);
        $this->appendLog('Berkas: '.$filename.' ('.$this->humanSize($size).')');
        $this->appendLog('SELESAI: backup dibuat.');

        return [
            'ok' => true,
            'log' => 'Backup dibuat: '.$filename.' ('.$this->humanSize($size).')',
            'path' => $path,
            'filename' => $filename,
        ];
    }

    /**
     * Import database dari berkas .sql (backup otomatis dibuat lebih dulu).
     *
     * @return array{ok: bool, log: string}
     */
    public function importDatabase(string $sqlPath): array
    {
        $this->appendLog('=== '.now()->format('Y-m-d H:i:s').' | Import database ===');

        if (! File::exists($sqlPath)) {
            return ['ok' => false, 'log' => 'Berkas SQL tidak ditemukan.'];
        }

        // Pengaman: selalu buat backup sebelum menimpa data.
        $safety = $this->backupDatabase();
        $this->appendLog(
            $safety['ok']
                ? 'Backup pengaman dibuat: '.($safety['filename'] ?? '-')
                : 'PERINGATAN: backup pengaman gagal.',
        );

        $mysql = $this->resolveBinary('mysql');

        if ($mysql === null) {
            $this->appendLog('GAGAL: mysql client tidak ditemukan.');

            return ['ok' => false, 'log' => 'mysql client tidak ditemukan.'];
        }

        $process = new Process([
            $mysql,
            '--host='.config('database.connections.mysql.host'),
            '--port='.config('database.connections.mysql.port'),
            '--user='.config('database.connections.mysql.username'),
            config('database.connections.mysql.database'),
        ], base_path(), $this->processEnv([
            'MYSQL_PWD' => (string) config('database.connections.mysql.password'),
        ]));

        $process->setTimeout(900);
        $process->setInput(fopen($sqlPath, 'r'));
        $process->run();

        $output = trim($process->getOutput()."\n".$process->getErrorOutput());
        $ok = $process->isSuccessful();

        $this->appendLog($output === '' ? '(tidak ada output)' : $output);
        $this->appendLog($ok ? 'SELESAI: import berhasil.' : 'GAGAL: import gagal.');

        return ['ok' => $ok, 'log' => $output === '' ? 'Import selesai.' : $output];
    }

    /**
     * Daftar berkas backup terbaru.
     *
     * @return list<array{filename: string, size: int, created_at: string}>
     */
    public function backups(): array
    {
        $directory = $this->backupDirectory();

        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn ($file) => strtolower($file->getExtension()) === 'sql')
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->take(10)
            ->map(fn ($file) => [
                'filename' => $file->getFilename(),
                'size' => $file->getSize(),
                'created_at' => date('d M Y H:i', $file->getMTime()),
            ])
            ->values()
            ->all();
    }

    /**
     * Ringkasan database (untuk ditampilkan di halaman).
     *
     * @return array{driver: string, database: string, size: string}
     */
    public function databaseInfo(): array
    {
        $connection = config('database.default');
        $database = (string) config('database.connections.'.$connection.'.database');

        $size = '—';

        try {
            $row = DB::selectOne(
                'select round(sum(data_length + index_length) / 1024 / 1024, 2) as size
                 from information_schema.tables where table_schema = ?',
                [$database],
            );

            if ($row !== null && $row->size !== null) {
                $size = $row->size.' MB';
            }
        } catch (\Throwable) {
            // Abaikan: sebagian driver tidak mendukung information_schema.
        }

        return ['driver' => $connection, 'database' => $database, 'size' => $size];
    }

    /**
     * Ukuran berkas dalam format ramah.
     */
    public function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
