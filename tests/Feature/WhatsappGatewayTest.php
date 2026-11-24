<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_the_gateway_and_test_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/whatsapp-gateway')
            ->assertOk()
            ->assertSee('WhatsApp Gateway')
            ->assertSee('Pindai QR')
            ->assertSee('Simpan Sesi')
            ->assertSee('data-whatsapp-gateway', escape: false)
            ->assertSee('data-wa-qr-image', escape: false)
            ->assertSee('data-wa-refresh', escape: false)
            ->assertSee('data-wa-reset', escape: false)
            ->assertSee('data-wa-disconnect', escape: false)
            ->assertSee('data-wa-save-session', escape: false)
            ->assertSee('data-wa-settings-form', escape: false)
            ->assertSee('data-wa-session-phone', escape: false);

        $this->actingAs($admin)
            ->get('/whatsapp-gateway/uji-pesan')
            ->assertOk()
            ->assertSee('Uji Pesan WhatsApp')
            ->assertSee('Kirim Pesan Uji')
            ->assertSee('data-whatsapp-test', escape: false)
            ->assertSee('data-wa-test-form', escape: false)
            ->assertSee('data-wa-submit', escape: false)
            ->assertSee('data-wa-history', escape: false);

        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/whatsapp-gateway')->assertForbidden();
        $this->actingAs($user)->get('/whatsapp-gateway/uji-pesan')->assertForbidden();
        $this->actingAs($user)->postJson('/whatsapp-gateway/uji-pesan', [])->assertForbidden();
    }

    public function test_the_gateway_menu_is_visible_only_for_admin_users(): void
    {
        $adminContent = $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-label="Whatsapp Gateway"', $adminContent);
        $this->assertStringContainsString('data-label="Pengguna"', $adminContent);

        $userContent = $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('data-label="Whatsapp Gateway"', $userContent);
    }

    public function test_the_default_gateway_url_is_used_when_nothing_is_saved(): void
    {
        Http::fake([
            'gateway.billnesia.com/status/*' => Http::response(['status' => 'disconnected']),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/whatsapp-gateway/status')
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'status' => 'disconnected',
                'qr' => null,
            ]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://gateway.billnesia.com/status/agenda-surat');
    }

    public function test_gateway_settings_are_saved_and_server_js_suffix_is_stripped(): void
    {
        Http::fake([
            'gateway.billnesia.com/status/*' => Http::response(['status' => 'disconnected']),
        ]);

        $admin = User::factory()->admin()->create();

        // Sesi lama pada device sebelumnya.
        AppSetting::put('whatsapp_session_device', 'agenda-surat');
        AppSetting::put('whatsapp_session_phone', '6281234567890');

        $this->actingAs($admin)
            ->putJson('/whatsapp-gateway', [
                'gateway_url' => 'http://gateway.billnesia.com/server.js',
                'device_id' => 'agenda-utama',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'base_url' => 'http://gateway.billnesia.com',
                'device_id' => 'agenda-utama',
            ]);

        $this->assertSame('http://gateway.billnesia.com/server.js', AppSetting::get('whatsapp_gateway_url'));
        $this->assertSame('agenda-utama', AppSetting::get('whatsapp_gateway_device'));

        // Device berubah: sesi lama dibersihkan.
        $this->assertNull(AppSetting::get('whatsapp_session_device'));
        $this->assertNull(AppSetting::get('whatsapp_session_phone'));

        $this->actingAs($admin)->getJson('/whatsapp-gateway/status')->assertOk();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://gateway.billnesia.com/status/agenda-utama');
    }

    public function test_invalid_gateway_settings_are_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->putJson('/whatsapp-gateway', [
                'gateway_url' => 'bukan-url',
                'device_id' => 'spasi tidak boleh',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['gateway_url', 'device_id']);
    }

    public function test_the_qr_endpoint_returns_the_qr_from_the_gateway(): void
    {
        Http::fake([
            'gateway.billnesia.com/qr/*' => Http::response([
                'status' => 'pending',
                'qr' => 'data:image/png;base64,QR-UJI',
            ]),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/whatsapp-gateway/qr')
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'status' => 'pending',
                'qr' => 'data:image/png;base64,QR-UJI',
            ]);
    }

    public function test_a_connected_device_is_saved_automatically(): void
    {
        Http::fake([
            'gateway.billnesia.com/qr/*' => Http::response([
                'status' => 'connected',
                'phone_number' => '6281234567890',
            ]),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/whatsapp-gateway/qr')
            ->assertOk()
            ->assertJsonPath('session.phone_number', '6281234567890');

        $this->assertSame('6281234567890', AppSetting::get('whatsapp_session_phone'));
        $this->assertSame('agenda-surat', AppSetting::get('whatsapp_session_device'));
    }

    public function test_the_test_message_endpoint_normalizes_the_phone_and_forwards_to_the_gateway(): void
    {
        Http::fake([
            'gateway.billnesia.com/send' => Http::response(['success' => true, 'messageId' => 'MSG-1']),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/whatsapp-gateway/uji-pesan', [
                'recipient' => '0812-3456-7890',
                'message' => 'Halo dari Agenda Surat',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'message_id' => 'MSG-1',
                'recipient' => '6281234567890',
            ]);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://gateway.billnesia.com/send'
                && $request['device_id'] === 'agenda-surat'
                && $request['nomor_penerima'] === '6281234567890'
                && $request['pesan'] === 'Halo dari Agenda Surat';
        });
    }

    public function test_a_gateway_error_is_reported_without_throwing(): void
    {
        Http::fake([
            'gateway.billnesia.com/send' => Http::response(['error' => 'Device not connected'], 500),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/whatsapp-gateway/uji-pesan', [
                'recipient' => '6281234567890',
                'message' => 'Uji gagal',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => false,
                'message' => 'Device not connected',
            ]);
    }

    public function test_the_test_message_endpoint_validates_the_input(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/whatsapp-gateway/uji-pesan', [
                'recipient' => 'abc',
                'message' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipient', 'message']);
    }

    public function test_disconnecting_clears_the_saved_session(): void
    {
        AppSetting::put('whatsapp_session_device', 'agenda-surat');
        AppSetting::put('whatsapp_session_phone', '6281234567890');
        AppSetting::put('whatsapp_session_saved_at', now()->format('Y-m-d H:i:s'));

        Http::fake([
            'gateway.billnesia.com/disconnect/*' => Http::response(['success' => true, 'message' => 'Disconnected']),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/whatsapp-gateway/disconnect')
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'session' => [
                    'device_id' => null,
                    'phone_number' => null,
                    'saved_at' => null,
                ],
            ]);

        $this->assertNull(AppSetting::get('whatsapp_session_phone'));
    }
}
