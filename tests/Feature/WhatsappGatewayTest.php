<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\WhatsappGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsappGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Uji memakai device ID tetap (tanpa akhiran lingkungan) agar tidak
        // bergantung pada APP_ENV. Perilaku akhiran diuji secara khusus.
        config(['whatsapp-gateway.device_suffix' => '']);
    }

    public function test_the_device_id_is_scoped_to_the_environment_to_avoid_shared_sessions(): void
    {
        // Tanpa konfigurasi eksplisit, akhiran mengikuti APP_ENV (bukan production).
        config(['whatsapp-gateway.device_suffix' => null, 'app.env' => 'local']);

        $gateway = app(WhatsappGatewayService::class);

        $this->assertSame('agenda-surat', $gateway->baseDeviceId());
        $this->assertSame('local', $gateway->deviceSuffix());
        $this->assertSame('agenda-surat-local', $gateway->deviceId());
    }

    public function test_production_keeps_the_plain_device_id(): void
    {
        config(['whatsapp-gateway.device_suffix' => null, 'app.env' => 'production']);

        $gateway = app(WhatsappGatewayService::class);

        $this->assertSame('', $gateway->deviceSuffix());
        $this->assertSame('agenda-surat', $gateway->deviceId());
    }

    public function test_a_stored_base_device_id_is_still_scoped(): void
    {
        // Nilai lama yang sudah tersimpan di database (mis. "agenda-surat").
        AppSetting::put('whatsapp_gateway_device', 'agenda-surat');

        config(['whatsapp-gateway.device_suffix' => 'local']);

        $this->assertSame('agenda-surat-local', app(WhatsappGatewayService::class)->deviceId());
    }

    public function test_an_explicitly_customised_device_id_is_kept(): void
    {
        AppSetting::put('whatsapp_gateway_device', 'agenda-bidang-tik');

        config(['whatsapp-gateway.device_suffix' => 'local']);

        $this->assertSame('agenda-bidang-tik', app(WhatsappGatewayService::class)->deviceId());
    }

    public function test_disconnecting_only_targets_the_device_of_this_installation(): void
    {
        config(['whatsapp-gateway.device_suffix' => 'local']);

        Http::fake([
            'gateway.billnesia.com/disconnect/*' => Http::response(['success' => true]),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/whatsapp-gateway/disconnect')
            ->assertOk()
            ->assertJson(['ok' => true]);

        // Sesi milik hosting ("agenda-surat") tidak boleh tersentuh.
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://gateway.billnesia.com/disconnect/agenda-surat-local');
        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'http://gateway.billnesia.com/disconnect/agenda-surat');
    }

    public function test_the_gateway_page_warns_when_the_device_id_is_shared(): void
    {
        // Akhiran dimatikan pada lingkungan non-production -> sesi berbagi.
        config(['whatsapp-gateway.device_suffix' => '']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/whatsapp-gateway')
            ->assertOk()
            ->assertSee('dipakai bersama oleh semua lingkungan')
            ->assertSee('WHATSAPP_GATEWAY_DEVICE_SUFFIX');
    }

    public function test_the_gateway_page_shows_the_environment_suffix_without_warning(): void
    {
        config(['whatsapp-gateway.device_suffix' => 'local']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/whatsapp-gateway')
            ->assertOk()
            ->assertSee('agenda-surat-local')
            ->assertDontSee('dipakai bersama oleh semua lingkungan');
    }

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

    public function test_the_test_page_shows_the_attachment_field(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/whatsapp-gateway/uji-pesan')
            ->assertOk()
            ->assertSee('data-wa-attachment', escape: false)
            ->assertSee('data-wa-attachment-preview', escape: false)
            ->assertSee('data-wa-attachment-clear', escape: false)
            ->assertSee('Lampiran')
            ->assertSee('maksimal 8 MB.');
    }

    public function test_the_test_page_warns_when_the_attachment_url_is_local(): void
    {
        config(['app.url' => 'http://localhost', 'whatsapp-gateway.attachment_public_url' => null]);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/whatsapp-gateway/uji-pesan')
            ->assertOk()
            ->assertSee('WHATSAPP_GATEWAY_PUBLIC_URL')
            ->assertSee('storage:link');
    }

    public function test_the_test_page_hides_the_warning_when_a_public_url_is_configured(): void
    {
        config(['whatsapp-gateway.attachment_public_url' => 'https://agenda.example.go.id']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/whatsapp-gateway/uji-pesan')
            ->assertOk()
            ->assertDontSee('Request failed with status code 404');
    }

    public function test_an_image_attachment_is_forwarded_to_the_media_endpoint(): void
    {
        Storage::fake('public');
        config(['whatsapp-gateway.attachment_public_url' => 'https://agenda.example.go.id']);

        Http::fake([
            'gateway.billnesia.com/send-media' => Http::response(['success' => true, 'messageId' => 'MEDIA-1']),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/whatsapp-gateway/uji-pesan', [
                'recipient' => '0812-3456-7890',
                'message' => 'Lampiran surat tugas',
                'attachment' => UploadedFile::fake()->create('bukti.png', 120, 'image/png'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'message_id' => 'MEDIA-1',
                'recipient' => '6281234567890',
                'via' => 'media',
                'attachment' => [
                    'name' => 'bukti.png',
                    'mime' => 'image/png',
                    'size' => 122880,
                ],
            ]);

        // Kontrak gateway: device_id, recipient, type, url/file_path, caption.
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://gateway.billnesia.com/send-media'
                && $request->isJson()
                && $request['device_id'] === 'agenda-surat'
                && $request['recipient'] === '6281234567890'
                && $request['type'] === 'image'
                && $request['caption'] === 'Lampiran surat tugas'
                && str_contains((string) $request['url'], 'https://agenda.example.go.id/storage/whatsapp/attachments/')
                && $request['file_path'] !== '';
        });

        $this->assertCount(1, Storage::disk('public')->files('whatsapp/attachments'));
    }

    public function test_a_pdf_attachment_is_sent_as_a_download_link(): void
    {
        Storage::fake('public');
        config(['whatsapp-gateway.attachment_public_url' => 'https://agenda.example.go.id']);

        Http::fake([
            'gateway.billnesia.com/send-media' => Http::response(
                ['error' => 'Invalid media type. Supported: image, video, audio'],
                400,
            ),
            'gateway.billnesia.com/send' => Http::response(['success' => true, 'messageId' => 'LINK-1']),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/whatsapp-gateway/uji-pesan', [
                'recipient' => '6281234567890',
                'message' => 'Surat tugas',
                'attachment' => UploadedFile::fake()->create('surat-tugas.pdf', 120, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'message_id' => 'LINK-1',
                'via' => 'link',
                'attachment' => ['name' => 'surat-tugas.pdf'],
            ]);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'http://gateway.billnesia.com/send') {
                return false;
            }

            $message = (string) ($request['pesan'] ?? '');

            return ($request['nomor_penerima'] ?? null) === '6281234567890'
                && str_contains($message, 'Surat tugas')
                && str_contains($message, 'Lampiran: surat-tugas.pdf')
                && str_contains($message, 'https://agenda.example.go.id/storage/whatsapp/attachments/');
        });
    }

    public function test_the_message_is_optional_when_an_attachment_is_present(): void
    {
        Storage::fake('public');
        config(['whatsapp-gateway.attachment_public_url' => 'https://agenda.example.go.id']);

        Http::fake([
            'gateway.billnesia.com/send-media' => Http::response(['success' => true]),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/whatsapp-gateway/uji-pesan', [
                'recipient' => '6281234567890',
                'attachment' => UploadedFile::fake()->create('foto.jpg', 20, 'image/jpeg'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['ok' => true, 'via' => 'media', 'attachment' => ['name' => 'foto.jpg']]);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://gateway.billnesia.com/send-media'
                && $request['type'] === 'image'
                && $request['caption'] === '';
        });
    }

    public function test_an_unsupported_attachment_type_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/whatsapp-gateway/uji-pesan', [
                'recipient' => '6281234567890',
                'message' => 'Lampiran',
                'attachment' => UploadedFile::fake()->create('skrip.exe', 10, 'application/x-msdownload'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['attachment']);
    }

    public function test_an_attachment_with_a_localhost_url_is_rejected_before_calling_the_gateway(): void
    {
        Storage::fake('public');
        config(['app.url' => 'http://localhost', 'whatsapp-gateway.attachment_public_url' => null]);

        Http::fake();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->post('/whatsapp-gateway/uji-pesan', [
                'recipient' => '6281234567890',
                'attachment' => UploadedFile::fake()->create('bukti.png', 20, 'image/png'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('ok', false);

        $message = (string) $response->json('message');

        $this->assertStringContainsString('hanya bisa diakses dari server ini', $message);
        $this->assertStringContainsString('WHATSAPP_GATEWAY_PUBLIC_URL', $message);

        // Gateway tidak boleh dipanggil dan berkas tidak boleh tertinggal.
        Http::assertNothingSent();
        $this->assertCount(0, Storage::disk('public')->files('whatsapp/attachments'));
    }

    public function test_a_gateway_download_failure_is_explained_in_indonesian(): void
    {
        Storage::fake('public');
        config(['whatsapp-gateway.attachment_public_url' => 'https://agenda.example.go.id']);

        Http::fake([
            'gateway.billnesia.com/send-media' => Http::response(['error' => 'Request failed with status code 404'], 500),
        ]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->post('/whatsapp-gateway/uji-pesan', [
                'recipient' => '6281234567890',
                'attachment' => UploadedFile::fake()->create('bukti.png', 20, 'image/png'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('ok', false);

        $message = (string) $response->json('message');

        $this->assertStringContainsString('Gateway gagal mengunduh lampiran dari', $message);
        $this->assertStringContainsString('storage:link', $message);
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
