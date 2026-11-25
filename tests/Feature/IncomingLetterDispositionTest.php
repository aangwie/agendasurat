<?php

namespace Tests\Feature;

use App\Models\IncomingLetter;
use App\Models\LetterDisposition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncomingLetterDispositionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Device ID tetap agar tidak bergantung pada akhiran lingkungan.
        config([
            'whatsapp-gateway.device_suffix' => '',
            'whatsapp-gateway.notify_timeout' => 5,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'agenda_number' => '1700',
            'received_date' => now()->subDay()->format('Y-m-d'),
            'letter_date' => now()->subDays(3)->format('Y-m-d'),
            'letter_number' => '005/321/DISDIK/2026',
            'sender' => 'Dinas Pendidikan Provinsi',
            'subject' => 'Undangan Rapat Koordinasi Tata Usaha',
            'sifat' => ['segera'],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function manager(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Subbagian Umum dan Kepegawaian / Tata Usaha',
            'kode_bidang' => IncomingLetter::MANAGER_KODE_BIDANG,
            'phone' => '6281111111111',
        ], $attributes));
    }

    public function test_only_the_letter_manager_can_open_and_send_the_disposition(): void
    {
        $letter = IncomingLetter::create($this->payload());
        $reader = User::factory()->create(['is_admin' => false, 'kode_bidang' => '408.37.03']);

        $this->actingAs($reader)->getJson(route('surat-masuk.disposisi', $letter))->assertForbidden();
        $this->actingAs($reader)
            ->postJson(route('surat-masuk.disposisi.kirim', $letter), ['bidangs' => ['408.37.03']])
            ->assertForbidden();
    }

    public function test_the_disposition_popup_lists_bidangs_and_the_letter_attachment(): void
    {
        Storage::fake(IncomingLetter::ATTACHMENT_DISK);

        $manager = $this->manager();

        User::factory()->create([
            'name' => 'Bidang Pembinaan Sekolah Dasar (SD)',
            'kode_bidang' => '408.37.03',
            'phone' => '6283333333333',
        ]);

        User::factory()->create([
            'name' => 'Bidang Tanpa Nomor',
            'kode_bidang' => '408.37.02',
            'phone' => null,
        ]);

        $letter = IncomingLetter::create($this->payload());

        $this->actingAs($manager)
            ->post(route('surat-masuk.store'), $this->payload([
                'agenda_number' => '1701',
                'attachment' => UploadedFile::fake()->create('surat-edaran.pdf', 120, 'application/pdf'),
            ]))
            ->assertRedirect(route('surat-masuk.index'));

        $stored = IncomingLetter::query()->where('agenda_number', '1701')->firstOrFail();

        $this->assertTrue($stored->hasAttachment());
        $this->assertSame('surat-edaran.pdf', $stored->attachment_name);
        Storage::disk(IncomingLetter::ATTACHMENT_DISK)->assertExists((string) $stored->attachment_path);

        $response = $this->actingAs($manager)
            ->getJson(route('surat-masuk.disposisi', $stored))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('letter.agenda_number', '1701')
            ->assertJsonPath('attachment.available', true)
            ->assertJsonPath('attachment.name', 'surat-edaran.pdf');

        $bidangs = collect($response->json('bidangs'));

        $this->assertTrue($bidangs->contains(fn (array $row): bool => $row['kode_bidang'] === '408.37.03'
            && $row['can_receive'] === true
            && $row['phone'] === '6283333333333'));

        $this->assertTrue($bidangs->contains(fn (array $row): bool => $row['kode_bidang'] === '408.37.02'
            && $row['can_receive'] === false));

        // Lampiran lama tidak tersentuh saat surat lain dibuat.
        $this->assertTrue($letter->fresh()->hasAttachment() === false);
    }

    public function test_the_attachment_can_be_downloaded_by_logged_in_users(): void
    {
        Storage::fake(IncomingLetter::ATTACHMENT_DISK);

        $manager = $this->manager();

        $this->actingAs($manager)->post(route('surat-masuk.store'), $this->payload([
            'attachment' => UploadedFile::fake()->create('scan-surat.pdf', 60, 'application/pdf'),
        ]));

        $letter = IncomingLetter::query()->firstOrFail();

        $this->actingAs($manager)
            ->get(route('surat-masuk.lampiran', $letter))
            ->assertOk()
            ->assertDownload('scan-surat.pdf');
    }

    public function test_sending_the_disposition_notifies_each_selected_bidang_with_a_download_link(): void
    {
        Storage::fake(IncomingLetter::ATTACHMENT_DISK);
        config(['whatsapp-gateway.attachment_public_url' => 'https://agenda.example.go.id']);

        $manager = $this->manager();

        User::factory()->create([
            'name' => 'Bidang Pembinaan Sekolah Dasar (SD)',
            'kode_bidang' => '408.37.03',
            'phone' => '6283333333333',
        ]);

        User::factory()->create([
            'name' => 'Bidang Pembinaan SMP',
            'kode_bidang' => '408.37.04',
            'phone' => '0812-4444-5555',
        ]);

        User::factory()->create([
            'name' => 'Bidang Tanpa Nomor',
            'kode_bidang' => '408.37.02',
            'phone' => null,
        ]);

        $this->actingAs($manager)->post(route('surat-masuk.store'), $this->payload([
            'attachment' => UploadedFile::fake()->create('surat-edaran.pdf', 90, 'application/pdf'),
        ]));

        $letter = IncomingLetter::query()->firstOrFail();

        Http::fake([
            'gateway.billnesia.com/health' => Http::response(['status' => 'online']),
            'gateway.billnesia.com/send' => Http::response(['success' => true, 'messageId' => 'DISP-1']),
        ]);

        $this->actingAs($manager)
            ->postJson(route('surat-masuk.disposisi.kirim', $letter), [
                'bidangs' => ['408.37.03', '408.37.04', '408.37.02'],
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'sent' => 2,
                'failed' => 0,
                'skipped' => 1,
            ]);

        // Nomor dinormalisasi (08xx => 62xx) dan pesan memuat tautan unduhan.
        $letterId = (int) $letter->id;

        Http::assertSent(function (Request $request) use ($letterId): bool {
            if ($request->url() !== 'http://gateway.billnesia.com/send') {
                return false;
            }

            $message = (string) ($request['pesan'] ?? '');
            $recipient = (string) ($request['nomor_penerima'] ?? '');

            return in_array($recipient, ['6283333333333', '6281244445555'], true)
                && str_contains($message, 'Disposisi Surat Masuk')
                && str_contains($message, '1700')
                && str_contains($message, 'https://agenda.example.go.id/surat-masuk/'.$letterId.'/lampiran');
        });

        // Riwayat disposisi tersimpan lengkap dengan status.
        $this->assertDatabaseHas('letter_dispositions', [
            'incoming_letter_id' => $letter->id,
            'kode_bidang' => '408.37.03',
            'status' => LetterDisposition::STATUS_SENT,
        ]);

        $this->assertDatabaseHas('letter_dispositions', [
            'incoming_letter_id' => $letter->id,
            'kode_bidang' => '408.37.04',
            'status' => LetterDisposition::STATUS_SENT,
        ]);

        $this->assertDatabaseHas('letter_dispositions', [
            'incoming_letter_id' => $letter->id,
            'kode_bidang' => '408.37.02',
            'status' => LetterDisposition::STATUS_SKIPPED,
        ]);
    }

    public function test_the_disposition_requires_at_least_one_bidang(): void
    {
        $letter = IncomingLetter::create($this->payload());

        $this->actingAs($this->manager())
            ->postJson(route('surat-masuk.disposisi.kirim', $letter), ['bidangs' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bidangs']);
    }

    public function test_the_letter_page_shows_the_disposition_action_and_modal(): void
    {
        IncomingLetter::create($this->payload());

        $this->actingAs($this->manager())
            ->get('/surat-masuk')
            ->assertOk()
            ->assertSee('data-letter-disposisi', escape: false)
            ->assertSee('data-disposisi-modal', escape: false)
            ->assertSee('data-disposisi-form', escape: false)
            ->assertSee('data-letter-attachment', escape: false)
            ->assertSee('Kirim Disposisi');
    }
}
