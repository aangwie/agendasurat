<?php

namespace Tests\Feature;

use App\Models\IncomingLetter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Volume Surat')
            ->assertSee('Surat Terbaru')
            ->assertSee('Surat Tugas')
            ->assertSee('data-sidebar-collapse', escape: false)
            ->assertDontSee('Surat Baru');
    }

    public function test_the_dashboard_has_no_catat_surat_button(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Catat Surat');
    }

    public function test_the_stat_cards_show_incoming_outgoing_task_and_total(): void
    {
        IncomingLetter::factory()->count(3)->create();

        $content = $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        foreach (['Surat Masuk', 'Surat Keluar', 'Surat Tugas', 'Total Surat'] as $label) {
            $this->assertStringContainsString($label, $content);
        }

        // Surat masuk memakai data nyata, surat keluar & tugas masih 0.
        $this->assertMatchesRegularExpression('/data-stat="rose"[\s\S]*?>\s*3\s*</', $content);
        $this->assertStringContainsString('data-stat="blue"', $content);
        $this->assertStringContainsString('data-stat="amber"', $content);
        $this->assertStringContainsString('data-stat="zinc"', $content);
    }

    public function test_the_chart_shows_three_bars_with_the_requested_colors(): void
    {
        IncomingLetter::factory()->count(2)->create();

        $content = $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        // Tiga bar: masuk (merah), keluar (biru laut), tugas (kuning tua).
        $this->assertSame(3, substr_count($content, 'data-bar='));
        $this->assertStringContainsString('data-bar="Surat Masuk" data-color="#e11d48"', $content);
        $this->assertStringContainsString('data-bar="Surat Keluar" data-color="#1e3a8a"', $content);
        $this->assertStringContainsString('data-bar="Surat Tugas" data-color="#ca8a04"', $content);
    }

    public function test_the_sidebar_module_pages_can_be_rendered(): void
    {
        // Menu sistem (Pengguna/Pengaturan) hanya untuk administrator.
        $user = User::factory()->admin()->create();

        $routes = [
            'surat-masuk.index',
            'surat-keluar.index',
            'surat-tugas.index',
            'agenda.index',
            'klasifikasi.index',
            'laporan.index',
            'pengguna.index',
            'pengaturan.index',
            'profil.show',
        ];

        foreach ($routes as $route) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertOk();
        }
    }
}
