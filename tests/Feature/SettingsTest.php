<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\DeploymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_the_settings_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/pengaturan')
            ->assertOk()
            ->assertSee('Pengaturan Sistem')
            ->assertSee('Sinkronisasi GitHub')
            ->assertSee('Pull dari GitHub')
            ->assertSee('Perintah Artisan')
            ->assertSee('Backup Database')
            ->assertSee('Import Database')
            ->assertSee('terminal')
            ->assertSee('data-settings', escape: false);

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/pengaturan')
            ->assertForbidden();
    }

    public function test_github_settings_are_saved_and_the_token_is_encrypted(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->putJson('/pengaturan', [
                'repo' => 'github.com/aangwie/agendasurat',
                'branch' => 'main',
                'token' => 'github_pat_rahasia123',
                'message' => 'chore: uji simpan',
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'token_set' => true]);

        $this->assertSame('github.com/aangwie/agendasurat', AppSetting::get('github_repo'));
        $this->assertSame('main', AppSetting::get('github_branch'));
        $this->assertSame('chore: uji simpan', AppSetting::get('commit_message'));

        // Token tersimpan terenkripsi (bukan plaintext) dan bisa dibaca kembali.
        $raw = AppSetting::query()->where('key', 'github_token')->value('value');
        $this->assertNotSame('github_pat_rahasia123', $raw);
        $this->assertSame('github_pat_rahasia123', AppSetting::getSecret('github_token'));
    }

    public function test_an_existing_token_is_not_overwritten_when_the_field_is_empty(): void
    {
        AppSetting::putSecret('github_token', 'github_pat_lama');

        $this->actingAs(User::factory()->admin()->create())
            ->putJson('/pengaturan', [
                'repo' => 'github.com/aangwie/agendasurat',
                'branch' => 'main',
                'token' => '',
            ])
            ->assertOk();

        $this->assertSame('github_pat_lama', AppSetting::getSecret('github_token'));
    }

    public function test_the_page_never_exposes_the_full_token(): void
    {
        AppSetting::putSecret('github_token', 'github_pat_sangatrahasia');

        $response = $this->actingAs(User::factory()->admin()->create())->get('/pengaturan');

        $response->assertOk();
        $this->assertStringNotContainsString('github_pat_sangatrahasia', $response->getContent());
        $response->assertSee('Token tersimpan');
    }

    public function test_the_settings_page_only_shows_the_token_field(): void
    {
        $content = $this->actingAs(User::factory()->admin()->create())
            ->get('/pengaturan')
            ->assertOk()
            ->getContent();

        // Input token tetap ada.
        $this->assertStringContainsString('id="github_token"', $content);
        $this->assertStringContainsString('name="token"', $content);

        // Repository & branch tidak lagi berupa input yang dapat diedit.
        $this->assertStringNotContainsString('id="github_repo"', $content);
        $this->assertStringNotContainsString('id="github_branch"', $content);
        $this->assertStringNotContainsString('name="repo" type="text"', $content);
        $this->assertStringNotContainsString('name="branch" type="text"', $content);

        // Nilainya tetap dikirim (hidden) dan ditampilkan sebagai info tujuan.
        $this->assertStringContainsString('type="hidden" name="repo"', $content);
        $this->assertStringContainsString('type="hidden" name="branch"', $content);
        $this->assertStringContainsString('Tujuan:', $content);
    }

    public function test_saving_with_hidden_repository_and_branch_keeps_them(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson('/pengaturan', [
                'repo' => 'github.com/aangwie/agendasurat',
                'branch' => 'main',
                'token' => 'github_pat_baru',
            ])
            ->assertOk();

        $this->assertSame('github.com/aangwie/agendasurat', AppSetting::get('github_repo'));
        $this->assertSame('main', AppSetting::get('github_branch'));
        $this->assertSame('github_pat_baru', AppSetting::getSecret('github_token'));
    }

    public function test_a_token_without_read_permission_is_reported_clearly(): void
    {
        Http::fake([
            'api.github.com/user' => Http::response(['login' => 'aangwie'], 200, ['x-oauth-scopes' => 'read:user']),
            'api.github.com/repos/aangwie/agendasurat' => Http::response([
                'default_branch' => 'main',
                'permissions' => ['admin' => false, 'push' => false, 'pull' => false],
            ], 200),
        ]);

        $result = app(DeploymentService::class)->verifyGitHubToken('github_pat_x', 'github.com/aangwie/agendasurat');

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['can_push']);
        $this->assertSame('aangwie', $result['login']);
        $this->assertStringContainsString('TIDAK punya izin baca', $result['message']);
        $this->assertStringContainsString('Contents: Read', $result['message']);
    }

    public function test_a_valid_token_with_read_permission_passes_verification(): void
    {
        Http::fake([
            'api.github.com/user' => Http::response(['login' => 'aangwie'], 200),
            'api.github.com/repos/aangwie/agendasurat' => Http::response([
                'default_branch' => 'main',
                'permissions' => ['admin' => true, 'push' => true, 'pull' => true],
            ], 200),
        ]);

        $result = app(DeploymentService::class)->verifyGitHubToken('github_pat_x', 'github.com/aangwie/agendasurat');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['can_push']);
        $this->assertSame('main', $result['default_branch']);
    }

    public function test_an_invalid_token_is_reported(): void
    {
        Http::fake([
            'api.github.com/user' => Http::response(['message' => 'Bad credentials'], 401),
        ]);

        $result = app(DeploymentService::class)->verifyGitHubToken('salah', 'github.com/aangwie/agendasurat');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('401', $result['message']);
    }

    public function test_a_repository_without_access_is_reported(): void
    {
        Http::fake([
            'api.github.com/user' => Http::response(['login' => 'aangwie'], 200),
            'api.github.com/repos/aangwie/agendasurat' => Http::response(['message' => 'Not Found'], 404),
        ]);

        $result = app(DeploymentService::class)->verifyGitHubToken('github_pat_x', 'github.com/aangwie/agendasurat');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('404', $result['message']);
    }

    public function test_a_pull_without_commands_enabled_is_rejected(): void
    {
        $result = app(DeploymentService::class)->pullFromGitHub('', 'github.com/aangwie/agendasurat', 'main');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('dinonaktifkan', $result['log']);
    }

    public function test_the_pull_failure_diagnosis_maps_common_errors(): void
    {
        $service = app(DeploymentService::class);

        $this->assertStringContainsString(
            'belum di-commit',
            $service->diagnosePullFailure('error: Your local changes to the following files would be overwritten'),
        );

        $this->assertStringContainsString(
            'berbeda',
            $service->diagnosePullFailure('fatal: refusing to merge unrelated histories'),
        );

        $this->assertStringContainsString(
            'konflik',
            $service->diagnosePullFailure('CONFLICT (content): Merge conflict in app/Models/User.php'),
        );

        $this->assertStringContainsString(
            'tidak valid',
            $service->diagnosePullFailure('fatal: Authentication failed: Bad credentials'),
        );
    }

    public function test_only_whitelisted_actions_are_accepted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/pengaturan/aksi', ['action' => 'rm -rf /'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('action');
    }

    public function test_the_log_endpoint_returns_and_clears_lines(): void
    {
        $service = app(DeploymentService::class);
        $service->clearLog();
        $service->appendLog('baris uji pertama');
        $service->appendLog('baris uji kedua');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/pengaturan/log')
            ->assertOk()
            ->assertJsonFragment(['baris uji pertama']);

        $this->actingAs($admin)
            ->deleteJson('/pengaturan/log')
            ->assertOk()
            ->assertJson(['ok' => true, 'lines' => []]);

        $this->assertSame([], $service->logLines());
    }

    public function test_backup_download_rejects_path_traversal(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/pengaturan/backup/..%2F..%2F.env/unduh')
            ->assertNotFound();
    }

    public function test_the_service_masks_secrets_in_output(): void
    {
        $service = app(DeploymentService::class);

        $masked = $service->maskSecrets(
            'https://x-access-token:github_pat_abc123@github.com/aangwie/agendasurat.git',
            ['github_pat_abc123'],
        );

        $this->assertStringNotContainsString('github_pat_abc123', $masked);
        $this->assertStringContainsString('***', $masked);
    }

    public function test_the_backup_directory_is_inside_storage_and_gitignored(): void
    {
        $service = app(DeploymentService::class);

        $this->assertSame(storage_path('app/backups'), $service->backupDirectory());
        $this->assertStringContainsString('storage', $service->backupDirectory());

        // Folder backup berada di storage/app yang di-ignore git.
        $this->assertTrue(File::exists(storage_path('app/.gitignore')));
        $this->assertStringContainsString('*', (string) File::get(storage_path('app/.gitignore')));
    }
}
