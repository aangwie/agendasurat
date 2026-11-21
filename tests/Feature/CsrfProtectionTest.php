<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function registerThrowingRoute(): void
    {
        Route::middleware('web')->get('/__test-419', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });
    }

    public function test_the_csrf_token_endpoint_returns_a_fresh_token(): void
    {
        $response = $this->getJson('/csrf-token')->assertOk();

        $token = $response->json('token');

        $this->assertIsString($token);
        $this->assertSame(csrf_token(), $token);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_the_csrf_token_endpoint_is_available_for_guests(): void
    {
        // Halaman login juga memakai endpoint ini.
        $this->getJson('/csrf-token')->assertOk()->assertJsonStructure(['token']);
    }

    public function test_the_csrf_token_endpoint_keeps_the_session_alive(): void
    {
        $first = $this->getJson('/csrf-token')->json('token');

        // Request berikutnya memakai sesi yang sama (token tidak berubah),
        // dan sesi tetap hidup sehingga tidak memicu 419.
        $this->getJson('/csrf-token')
            ->assertOk()
            ->assertJson(['token' => $first]);
    }

    public function test_a_token_mismatch_renders_the_friendly_419_page(): void
    {
        $this->registerThrowingRoute();

        $response = $this->get('/__test-419');

        $response->assertStatus(419);
        $response->assertSee('Sesi Anda Telah Berakhir');
        $response->assertSee('Muat Ulang Sekarang');
        $response->assertSee('Batalkan');
    }

    public function test_a_token_mismatch_returns_json_for_ajax_requests(): void
    {
        $this->registerThrowingRoute();

        $this->getJson('/__test-419')
            ->assertStatus(419)
            ->assertJson(['csrf_expired' => true])
            ->assertJsonPath('message', 'Sesi Anda telah berakhir. Halaman akan dimuat ulang.');
    }

    public function test_every_page_exposes_a_csrf_meta_tag_for_the_js_refresh(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('name="csrf-token"', escape: false);
    }
}
