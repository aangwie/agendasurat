<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\OutgoingLetter;
use App\Models\TaskLetter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Nomor WhatsApp gateway yang "terhubung" pada sesi tersimpan.
     */
    private const GATEWAY_PHONE = '6281111111111';

    protected function setUp(): void
    {
        parent::setUp();

        // Notifikasi dimatikan pada phpunit.xml; aktifkan khusus untuk test ini.
        config(['whatsapp-gateway.notify_letters' => true]);

        // Device ID tetap agar tidak bergantung pada akhiran lingkungan (APP_ENV).
        config(['whatsapp-gateway.device_suffix' => '']);

        AppSetting::put('whatsapp_session_device', 'agenda-surat');
        AppSetting::put('whatsapp_session_phone', self::GATEWAY_PHONE);
    }

    /**
     * Akun bidang beserta kode bidang, seperti akun hasil BidangUserSeeder.
     */
    private function bidangUser(): User
    {
        return User::factory()->create([
            'name' => 'Bidang PTK',
            'kode_bidang' => '408.37.06',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function outgoingPayload(): array
    {
        return [
            'year' => 2026,
            'month' => 1,
            'code' => '00',
            'sub_code' => '01',
            'letter_prefix' => '421.2',
            'subject' => 'Undangan Rapat Koordinasi',
            'letter_date' => '2026-01-12',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskPayload(): array
    {
        return [
            'year' => 2026,
            'month' => 1,
            'code' => '00',
            'sub_code' => '01',
            'letter_prefix' => '421.2',
            'subject' => 'Penugasan Pendampingan Sekolah',
            'letter_date' => '2026-01-12',
        ];
    }

    /**
     * Siapkan dua admin: satu nomor berbeda, satu nomor sama dengan gateway,
     * ditambah admin tanpa nomor dan pengguna biasa.
     *
     * @return array{recipient: User, gateway: User, without_phone: User, regular: User}
     */
    private function admins(): array
    {
        return [
            'recipient' => User::factory()->admin()->create(['phone' => '0812-2222-2222']),
            'gateway' => User::factory()->admin()->create(['phone' => '0811-1111-1111']),
            'without_phone' => User::factory()->admin()->create(['phone' => null]),
            'regular' => User::factory()->create(['phone' => '0813-3333-3333']),
        ];
    }

    public function test_creating_an_outgoing_letter_notifies_admins_but_not_the_gateway_number(): void
    {
        Http::fake([
            'gateway.billnesia.com/health' => Http::response(['status' => 'ok']),
            'gateway.billnesia.com/send' => Http::response(['success' => true, 'messageId' => 'MSG-1']),
        ]);

        $this->admins();

        $this->actingAs($this->bidangUser())
            ->post('/surat-keluar', $this->outgoingPayload())
            ->assertRedirect(route('surat-keluar.index'));

        $this->assertSame(1, OutgoingLetter::query()->count());

        // Hanya admin dengan nomor berbeda yang menerima notifikasi.
        $sends = Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/send'));
        $this->assertCount(1, $sends);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://gateway.billnesia.com/send'
                && $request['device_id'] === 'agenda-surat'
                && $request['nomor_penerima'] === '6281222222222'
                && str_contains((string) $request['pesan'], 'Surat Keluar Baru')
                && str_contains((string) $request['pesan'], '421.2/0001/408.37.06/2026')
                && str_contains((string) $request['pesan'], 'Undangan Rapat Koordinasi');
        });

        $status = (string) session('status');

        $this->assertStringContainsString('berhasil ditambahkan', $status);
        $this->assertStringContainsString('terkirim ke 1 admin', $status);
        $this->assertStringContainsString('dilewati', $status);

        // Nomor gateway & pengguna biasa tidak pernah dikirimi pesan.
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/send')
            && in_array((string) ($request['nomor_penerima'] ?? ''), [self::GATEWAY_PHONE, '6281333333333'], true));
    }

    public function test_creating_a_task_letter_notifies_admins(): void
    {
        Http::fake([
            'gateway.billnesia.com/health' => Http::response(['status' => 'ok']),
            'gateway.billnesia.com/send' => Http::response(['success' => true, 'messageId' => 'MSG-2']),
        ]);

        $this->admins();

        $this->actingAs($this->bidangUser())
            ->post('/surat-tugas', $this->taskPayload())
            ->assertRedirect(route('surat-tugas.index'));

        $this->assertSame(1, TaskLetter::query()->count());

        $sends = Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/send'));
        $this->assertCount(1, $sends);

        Http::assertSent(function (Request $request): bool {
            return str_ends_with($request->url(), '/send')
                && $request['nomor_penerima'] === '6281222222222'
                && str_contains((string) $request['pesan'], 'Surat Tugas Baru')
                && str_contains((string) $request['pesan'], 'Penugasan Pendampingan Sekolah');
        });
    }

    public function test_a_gateway_failure_does_not_prevent_the_letter_from_being_saved(): void
    {
        Http::fake([
            'gateway.billnesia.com/health' => Http::response(['status' => 'ok']),
            'gateway.billnesia.com/send' => Http::response(['error' => 'Device not connected'], 500),
        ]);

        $this->admins();

        $this->actingAs($this->bidangUser())
            ->post('/surat-keluar', $this->outgoingPayload())
            ->assertRedirect(route('surat-keluar.index'));

        $this->assertSame(1, OutgoingLetter::query()->count());
        $this->assertStringContainsString('tidak terkirim', (string) session('status'));
    }

    public function test_letter_notifications_can_be_disabled(): void
    {
        config(['whatsapp-gateway.notify_letters' => false]);

        Http::fake();

        $this->admins();

        $this->actingAs($this->bidangUser())
            ->post('/surat-keluar', $this->outgoingPayload())
            ->assertRedirect(route('surat-keluar.index'));

        $this->assertSame(1, OutgoingLetter::query()->count());
        Http::assertNothingSent();
        $this->assertStringContainsString('dinonaktifkan', (string) session('status'));
    }

    public function test_an_admin_phone_may_not_equal_the_gateway_number(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['phone' => '081299999999']);

        $this->actingAs($admin)
            ->put(route('pengguna.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'phone' => '0811-1111-1111',
                'password' => '',
                'password_confirmation' => '',
                'is_admin' => '1',
            ])
            ->assertSessionHasErrors('phone');

        $this->assertSame('081299999999', $target->fresh()->phone);
    }

    public function test_an_unreachable_gateway_skips_the_notification_without_blocking_the_letter(): void
    {
        Http::fake([
            'gateway.billnesia.com/health' => Http::response(['status' => 'error'], 500),
        ]);

        $this->admins();

        $this->actingAs($this->bidangUser())
            ->post('/surat-keluar', $this->outgoingPayload())
            ->assertRedirect(route('surat-keluar.index'));

        $this->assertSame(1, OutgoingLetter::query()->count());
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/send'));
        $this->assertStringContainsString('gateway tidak dapat dihubungi', (string) session('status'));
    }

    public function test_the_gateway_health_is_checked_before_sending(): void
    {
        Http::fake([
            'gateway.billnesia.com/health' => Http::response(['status' => 'ok', 'engine' => 'Baileys']),
            'gateway.billnesia.com/send' => Http::response(['success' => true]),
        ]);

        $this->admins();

        $this->actingAs($this->bidangUser())->post('/surat-keluar', $this->outgoingPayload());

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/health'));
    }

    public function test_the_gateway_page_marks_admins_that_use_the_gateway_number(): void
    {
        $this->admins();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/whatsapp-gateway')
            ->assertOk()
            ->assertSee('Penerima Notifikasi')
            ->assertSee('Sama dengan nomor gateway')
            ->assertSee('Menerima notifikasi')
            ->assertSee('Nomor HP belum diisi');
    }
}
