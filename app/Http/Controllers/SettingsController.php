<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Services\DeploymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SettingsController extends Controller
{
    public function __construct(private readonly DeploymentService $deployment) {}

    /**
     * Halaman pengaturan (GitHub, tools artisan, backup/import, terminal log).
     */
    public function index(): View
    {
        $token = AppSetting::getSecret('github_token');

        return view('pengaturan.index', [
            'github' => [
                'repo' => AppSetting::get('github_repo', 'github.com/aangwie/agendasurat'),
                'branch' => AppSetting::get('github_branch', 'main'),
                'token_set' => filled($token),
                'token_preview' => filled($token) ? substr($token, 0, 12).str_repeat('•', 8) : null,
            ],
            'backups' => $this->deployment->backups(),
            'database' => $this->deployment->databaseInfo(),
            'logLines' => $this->deployment->logLines(200),
            'phpBinary' => PHP_BINARY,
            'storageLinked' => File::exists(public_path('storage')),
        ]);
    }

    /**
     * Simpan pengaturan GitHub (token disimpan terenkripsi).
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'repo' => ['required', 'string', 'max:200'],
            'branch' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'token' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:200'],
        ], [], [
            'repo' => 'repository',
            'branch' => 'branch',
            'token' => 'token GitHub',
        ]);

        AppSetting::put('github_repo', trim($data['repo']));
        AppSetting::put('github_branch', trim($data['branch']));

        // Token hanya diganti bila diisi (agar tidak menimpa token tersimpan).
        if (filled($data['token'] ?? null)) {
            AppSetting::putSecret('github_token', trim($data['token']));
        }

        if (filled($data['message'] ?? null)) {
            AppSetting::put('commit_message', trim($data['message']));
        }

        $this->deployment->appendLog('=== '.now()->format('Y-m-d H:i:s').' | Pengaturan GitHub disimpan ===');

        return response()->json([
            'ok' => true,
            'message' => 'Pengaturan GitHub berhasil disimpan.',
            'token_set' => filled(AppSetting::getSecret('github_token')),
        ]);
    }

    /**
     * Jalankan aksi (update GitHub / migrate / clear cache / symlink).
     */
    public function action(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['git-push', 'git-verify', 'migrate', 'clear-cache', 'symlink'])],
            'message' => ['nullable', 'string', 'max:200'],
        ]);

        // Token dari form (bila baru diisi) atau token tersimpan.
        $token = filled($request->input('token'))
            ? trim((string) $request->input('token'))
            : (string) AppSetting::getSecret('github_token');

        $repo = (string) AppSetting::get('github_repo', 'github.com/aangwie/agendasurat');
        $branch = (string) AppSetting::get('github_branch', 'main');

        if ($data['action'] === 'git-verify') {
            $check = $this->deployment->verifyGitHubToken($token, $repo);

            $this->deployment->appendLog('=== '.now()->format('Y-m-d H:i:s').' | Verifikasi token GitHub ===');
            $this->deployment->appendLog($check['message']);

            return response()->json([
                'ok' => $check['ok'] && $check['can_push'],
                'log' => $check['message'],
                'hint' => $check['message'],
                'verification' => $check,
                'lines' => $this->deployment->logLines(200),
            ]);
        }

        $result = match ($data['action']) {
            'git-push' => $this->deployment->pushToGitHub(
                $token,
                $repo,
                $branch,
                (string) ($data['message'] ?? AppSetting::get('commit_message', 'chore: update dari halaman pengaturan')),
            ),
            'migrate' => $this->deployment->migrate(),
            'clear-cache' => $this->deployment->clearCache(),
            'symlink' => $this->deployment->storageLink(),
        };

        return response()->json([
            'ok' => $result['ok'],
            'log' => $result['log'],
            'hint' => $result['hint'] ?? null,
            'lines' => $this->deployment->logLines(200),
        ]);
    }

    /**
     * Log terminal (untuk polling).
     */
    public function log(): JsonResponse
    {
        return response()->json([
            'lines' => $this->deployment->logLines(200),
        ]);
    }

    /**
     * Kosongkan log terminal.
     */
    public function clearLog(): JsonResponse
    {
        $this->deployment->clearLog();

        return response()->json(['ok' => true, 'lines' => []]);
    }

    /**
     * Buat backup database.
     */
    public function backup(): JsonResponse
    {
        $result = $this->deployment->backupDatabase();

        return response()->json([
            'ok' => $result['ok'],
            'log' => $result['log'],
            'download_url' => isset($result['filename'])
                ? route('pengaturan.backup.download', ['filename' => $result['filename']])
                : null,
            'lines' => $this->deployment->logLines(200),
            'backups' => $this->deployment->backups(),
        ]);
    }

    /**
     * Unduh berkas backup.
     */
    public function downloadBackup(string $filename): BinaryFileResponse
    {
        abort_unless((bool) preg_match('/^[A-Za-z0-9._-]+\.sql$/', $filename), 404);

        $path = $this->deployment->backupDirectory().DIRECTORY_SEPARATOR.$filename;

        abort_unless(File::exists($path), 404);

        return response()->download($path);
    }

    /**
     * Import database dari berkas .sql yang diunggah.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'sql' => ['required', 'file', 'max:51200', 'mimes:sql,txt'],
        ], [
            'sql.required' => 'Berkas SQL wajib dipilih.',
            'sql.mimes' => 'Berkas harus berformat .sql.',
            'sql.max' => 'Ukuran berkas maksimal 50 MB.',
        ]);

        $temporary = $request->file('sql')->storeAs('imports', 'import-'.now()->format('YmdHis').'.sql', 'local');

        // Disk "local" berakar di storage/app/private (Laravel 11+).
        $absolutePath = Storage::disk('local')->path((string) $temporary);

        $result = $this->deployment->importDatabase($absolutePath);

        // Bersihkan berkas unggahan sementara setelah diproses.
        Storage::disk('local')->delete((string) $temporary);

        return response()->json([
            'ok' => $result['ok'],
            'log' => $result['log'],
            'lines' => $this->deployment->logLines(200),
        ]);
    }
}
