<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSessionIsNotIdle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SessionIdleLogoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Timestamp aktivitas terakhir beberapa menit yang lalu.
     */
    private function idleSince(int $minutes): int
    {
        return now()->subMinutes($minutes)->getTimestamp();
    }

    public function test_the_app_layout_exposes_the_idle_timeout_configuration(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('name="session-idle-timeout" content="600"', escape: false)
            ->assertSee('name="session-idle-warning" content="60"', escape: false)
            ->assertSee('name="session-keepalive-url" content="'.route('sesi.aktif').'"', escape: false)
            ->assertSee('name="session-idle-logout-url" content="'.route('logout.idle').'"', escape: false);
    }

    public function test_the_idle_timeout_follows_the_session_configuration(): void
    {
        config(['session.idle_timeout' => 5, 'session.idle_warning' => 30]);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('name="session-idle-timeout" content="300"', escape: false)
            ->assertSee('name="session-idle-warning" content="30"', escape: false);
    }

    public function test_login_starts_the_idle_timer(): void
    {
        $user = User::factory()->create(['password' => Hash::make('rahasia123')]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'rahasia123',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertNotNull(session(EnsureSessionIsNotIdle::SESSION_KEY));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_request_after_the_idle_limit_logs_the_user_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([EnsureSessionIsNotIdle::SESSION_KEY => $this->idleSince(11)])
            ->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Sesi Anda berakhir karena tidak ada aktivitas selama 10 menit. Silakan masuk kembali.');

        $this->assertGuest();
    }

    public function test_a_request_within_the_idle_limit_keeps_the_user_logged_in(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([EnsureSessionIsNotIdle::SESSION_KEY => $this->idleSince(9)])
            ->get('/dashboard')
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_each_request_refreshes_the_idle_timer(): void
    {
        $user = User::factory()->create();
        $stale = $this->idleSince(9);

        $this->actingAs($user)
            ->withSession([EnsureSessionIsNotIdle::SESSION_KEY => $stale])
            ->get('/dashboard')
            ->assertOk();

        // Waktu aktivitas diperbarui sehingga hitungan mulai dari sekarang.
        $this->assertGreaterThan($stale, (int) session(EnsureSessionIsNotIdle::SESSION_KEY));
    }

    public function test_ajax_requests_after_the_idle_limit_receive_a_json_response(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([EnsureSessionIsNotIdle::SESSION_KEY => $this->idleSince(11)])
            ->getJson('/surat-masuk')
            ->assertStatus(401)
            ->assertJson([
                'session_idle' => true,
                'login_url' => route('login'),
            ])
            ->assertJsonPath(
                'message',
                'Sesi Anda berakhir karena tidak ada aktivitas selama 10 menit. Silakan masuk kembali.',
            );

        $this->assertGuest();
    }

    public function test_the_csrf_heartbeat_does_not_extend_the_idle_timer(): void
    {
        $user = User::factory()->create();

        // Detak CSRF tetap dilayani (token segar) ...
        $this->actingAs($user)
            ->withSession([EnsureSessionIsNotIdle::SESSION_KEY => $this->idleSince(11)])
            ->getJson('/csrf-token')
            ->assertOk()
            ->assertJsonStructure(['token']);

        // ... tetapi tidak dihitung sebagai aktivitas, sehingga permintaan
        // berikutnya tetap berakhir logout.
        $this->get('/dashboard')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_the_keepalive_endpoint_resets_the_idle_timer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([EnsureSessionIsNotIdle::SESSION_KEY => $this->idleSince(9)])
            ->getJson(route('sesi.aktif'))
            ->assertOk()
            ->assertJson(['ok' => true, 'timeout' => 600, 'expires_in' => 600]);

        // Setelah sinyal aktivitas, permintaan berikutnya tidak logout.
        $this->get('/dashboard')->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_idle_logout_route_ends_the_session_and_shows_a_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('logout.idle'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Sesi Anda berakhir karena tidak ada aktivitas selama 10 menit. Silakan masuk kembali.');

        $this->assertGuest();
    }

    public function test_the_idle_logout_route_works_for_guests(): void
    {
        // Tautan dari hitungan mundur browser tetap aman dibuka walau sesi
        // sudah tidak valid (mis. dibuka di tab baru setelah waktu habis).
        $this->get(route('logout.idle'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');
    }

    public function test_the_idle_timeout_can_be_disabled(): void
    {
        config(['session.idle_timeout' => 0]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([EnsureSessionIsNotIdle::SESSION_KEY => $this->idleSince(120)])
            ->get('/dashboard')
            ->assertOk();

        // Meta tag tidak dirender ketika fitur dimatikan.
        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('name="session-idle-timeout"', escape: false);
    }

    public function test_the_login_page_does_not_render_the_idle_countdown(): void
    {
        // Halaman masuk memakai layout guest (tanpa hitungan mundur).
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('name="session-idle-timeout"', escape: false);
    }
}
