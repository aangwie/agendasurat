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
            'alias' => 'SD',
            'kode_bidang' => '408.37.03',
            'phone' => '6283333333333',
        ]);

        User::factory()->create([
            'name' => 'Bidang Tanpa Nomor',
            'kode_bidang' => '408.37.02',
            'phone' => null,
        ]);

        $letter = IncomingLetter::create($this->payload(['agenda_number' => '1701']));

        // Lampiran diunggah dari popup disposisi, bukan dari form surat.
        $this->actingAs($manager)
            ->post(route('surat-masuk.lampiran.unggah', $letter), [
                'attachment' => UploadedFile::fake()->create('surat-edaran.pdf', 120, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('attachment.available', true)
            ->assertJsonPath('attachment.name', 'surat-edaran.pdf');

        $stored = $letter->fresh();

        $this->assertTrue($stored->hasAttachment());
        $this->assertSame('surat-edaran.pdf', $stored->attachment_name);
        Storage::disk(IncomingLetter::ATTACHMENT_DISK)->assertExists((string) $stored->attachment_path);

        $response = $this->actingAs($manager)
            ->getJson(route('surat-masuk.disposisi', $stored))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('letter.agenda_number', '1701')
            ->assertJsonPath('attachment.available', true)
            ->assertJsonPath('attachment.name', 'surat-edaran.pdf')
            ->assertJsonStructure(['attachment' => ['upload_url', 'delete_url']]);

        $bidangs = collect($response->json('bidangs'));

        $this->assertTrue($bidangs->contains(fn (array $row): bool => $row['kode_bidang'] === '408.37.03'
            && $row['can_receive'] === true
            && $row['alias'] === 'SD'
            && $row['phone'] === '6283333333333'));

        $this->assertTrue($bidangs->contains(fn (array $row): bool => $row['kode_bidang'] === '408.37.02'
            && $row['can_receive'] === false));
    }

    public function test_the_letter_form_no_longer_accepts_an_attachment(): void
    {
        Storage::fake(IncomingLetter::ATTACHMENT_DISK);

        $manager = $this->manager();

        $this->actingAs($manager)->post(route('surat-masuk.store'), $this->payload([
            'attachment' => UploadedFile::fake()->create('ignored.pdf', 20, 'application/pdf'),
        ]))->assertRedirect(route('surat-masuk.index'));

        $letter = IncomingLetter::query()->firstOrFail();

        // Berkas tidak disimpan karena lampiran hanya diunggah dari disposisi.
        $this->assertFalse($letter->hasAttachment());
        $this->assertCount(0, Storage::disk(IncomingLetter::ATTACHMENT_DISK)->files('incoming-letters'));

        $this->actingAs($manager)
            ->get('/surat-masuk')
            ->assertOk()
            ->assertDontSee('data-letter-attachment', escape: false);
    }

    public function test_the_attachment_can_be_downloaded_by_logged_in_users(): void
    {
        Storage::fake(IncomingLetter::ATTACHMENT_DISK);

        $manager = $this->manager();
        $letter = IncomingLetter::create($this->payload());

        $this->actingAs($manager)->post(route('surat-masuk.lampiran.unggah', $letter), [
            'attachment' => UploadedFile::fake()->create('scan-surat.pdf', 60, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $this->actingAs($manager)
            ->get(route('surat-masuk.lampiran', $letter))
            ->assertOk()
            ->assertDownload('scan-surat.pdf');
    }

    public function test_the_attachment_can_be_replaced_and_removed_from_the_disposition_popup(): void
    {
        Storage::fake(IncomingLetter::ATTACHMENT_DISK);

        $manager = $this->manager();
        $letter = IncomingLetter::create($this->payload());

        $this->actingAs($manager)
            ->post(route('surat-masuk.lampiran.unggah', $letter), [
                'attachment' => UploadedFile::fake()->create('lama.pdf', 10, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $firstPath = (string) $letter->fresh()->attachment_path;

        $this->actingAs($manager)
            ->post(route('surat-masuk.lampiran.unggah', $letter), [
                'attachment' => UploadedFile::fake()->create('baru.pdf', 12, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('attachment.name', 'baru.pdf');

        Storage::disk(IncomingLetter::ATTACHMENT_DISK)->assertMissing($firstPath);

        $this->actingAs($manager)
            ->deleteJson(route('surat-masuk.lampiran.hapus', $letter))
            ->assertOk()
            ->assertJsonPath('attachment.available', false);

        $this->assertFalse($letter->fresh()->hasAttachment());
        $this->assertCount(0, Storage::disk(IncomingLetter::ATTACHMENT_DISK)->files('incoming-letters'));
    }

    public function test_uploading_an_unsupported_attachment_is_rejected(): void
    {
        Storage::fake(IncomingLetter::ATTACHMENT_DISK);

        $letter = IncomingLetter::create($this->payload());

        $this->actingAs($this->manager())
            ->post(route('surat-masuk.lampiran.unggah', $letter), [
                'attachment' => UploadedFile::fake()->create('skrip.exe', 10, 'application/x-msdownload'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['attachment']);
    }

    public function test_only_the_letter_manager_can_upload_the_attachment(): void
    {
        Storage::fake(IncomingLetter::ATTACHMENT_DISK);

        $letter = IncomingLetter::create($this->payload());
        $reader = User::factory()->create(['is_admin' => false, 'kode_bidang' => '408.37.03']);

        $this->actingAs($reader)
            ->post(route('surat-masuk.lampiran.unggah', $letter), [
                'attachment' => UploadedFile::fake()->create('scan.pdf', 10, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();
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

        $letter = IncomingLetter::create($this->payload());

        $this->actingAs($manager)->post(route('surat-masuk.lampiran.unggah', $letter), [
            'attachment' => UploadedFile::fake()->create('surat-edaran.pdf', 90, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

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

    public function test_the_letter_table_shows_the_disposition_targets_under_the_letter_number(): void
    {
        $manager = $this->manager();
        $letter = IncomingLetter::create($this->payload());

        // Riwayat lama (gagal) lalu terkirim: yang ditampilkan adalah status terbaru.
        LetterDisposition::create([
            'incoming_letter_id' => $letter->id,
            'kode_bidang' => '408.37.03',
            'bidang_name' => 'Bidang Pembinaan Sekolah Dasar (SD)',
            'status' => LetterDisposition::STATUS_FAILED,
        ]);

        LetterDisposition::create([
            'incoming_letter_id' => $letter->id,
            'kode_bidang' => '408.37.03',
            'bidang_name' => 'Bidang Pembinaan Sekolah Dasar (SD)',
            'status' => LetterDisposition::STATUS_SENT,
            'sent_at' => now(),
        ]);

        LetterDisposition::create([
            'incoming_letter_id' => $letter->id,
            'kode_bidang' => '408.37.05',
            'bidang_name' => 'Bidang Pembinaan PAUD dan PNF',
            'status' => LetterDisposition::STATUS_SKIPPED,
        ]);

        $response = $this->actingAs($manager)->get('/surat-masuk')->assertOk();

        $html = $response->getContent();

        // Badge tujuan disposisi berada pada sel nomor surat.
        $this->assertStringContainsString('data-letter-dispositions-cell', $html);
        $this->assertStringContainsString('data-letter-dispositions', $html);
        $this->assertStringContainsString('data-disposition-bidang="408.37.03"', $html);
        $this->assertStringContainsString('data-disposition-bidang="408.37.05"', $html);
        $this->assertStringContainsString('Bidang Pembinaan Sekolah Dasar (SD)', $html);
        $this->assertStringContainsString('Bidang Pembinaan PAUD dan PNF', $html);

        // Status terbaru yang dipakai (bukan riwayat gagal sebelumnya).
        $this->assertStringContainsString('Disposisi: Bidang Pembinaan Sekolah Dasar (SD) — Terkirim', $html);

        // Parsial AJAX juga memuat badge yang sama.
        $ajax = $this->actingAs($manager)
            ->get('/surat-masuk', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertStringContainsString('data-disposition-bidang="408.37.03"', $ajax->getContent());
    }

    public function test_the_letter_table_prefers_the_bidang_alias_over_the_full_name(): void
    {
        $manager = $this->manager();

        $bidang = User::factory()->create([
            'name' => 'Bidang Pendidik dan Tenaga Kependidikan (PTK)',
            'alias' => 'PTK',
            'kode_bidang' => '408.37.06',
            'phone' => '6286666666666',
        ]);

        $letter = IncomingLetter::create($this->payload());

        LetterDisposition::create([
            'incoming_letter_id' => $letter->id,
            'kode_bidang' => '408.37.06',
            'bidang_name' => $bidang->name,
            'user_id' => $bidang->id,
            'status' => LetterDisposition::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $html = $this->actingAs($manager)->get('/surat-masuk')->assertOk()->getContent();

        $this->assertStringContainsString('data-disposition-bidang="408.37.06"', $html);
        $this->assertStringContainsString('<span class="truncate">PTK</span>', $html);
    }

    public function test_a_disposition_can_be_cancelled_and_disappears_from_the_table(): void
    {
        $manager = $this->manager();

        $bidang = User::factory()->create([
            'name' => 'Bidang Pembinaan Sekolah Menengah Pertama (SMP)',
            'alias' => 'SMP',
            'kode_bidang' => '408.37.04',
            'phone' => '6284444444444',
        ]);

        $letter = IncomingLetter::create($this->payload());

        $sent = LetterDisposition::create([
            'incoming_letter_id' => $letter->id,
            'kode_bidang' => '408.37.04',
            'bidang_name' => $bidang->name,
            'user_id' => $bidang->id,
            'phone' => '6284444444444',
            'status' => LetterDisposition::STATUS_SENT,
            'sent_at' => now(),
        ]);

        // Sebelum dibatalkan: badge tampil di tabel.
        $before = $this->actingAs($manager)->get('/surat-masuk')->assertOk()->getContent();
        $this->assertStringContainsString('data-disposition-bidang="408.37.04"', $before);

        Http::fake();

        $this->actingAs($manager)
            ->deleteJson(route('surat-masuk.disposisi.batal', $letter), [
                'kode_bidang' => '408.37.04',
                'reason' => 'Salah bidang, seharusnya PTK',
                'notify' => false,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('cancelled.kode_bidang', '408.37.04');

        // Riwayat lama tetap ada, pembatalan dicatat sebagai baris baru.
        $this->assertDatabaseHas('letter_dispositions', [
            'id' => $sent->id,
            'status' => LetterDisposition::STATUS_SENT,
        ]);

        $this->assertDatabaseHas('letter_dispositions', [
            'incoming_letter_id' => $letter->id,
            'kode_bidang' => '408.37.04',
            'status' => LetterDisposition::STATUS_CANCELLED,
            'cancel_reason' => 'Salah bidang, seharusnya PTK',
        ]);

        // Tanpa pemberitahuan WA karena notify=false.
        Http::assertNothingSent();

        // Badge tujuan disposisi hilang dari tabel.
        $after = $this->actingAs($manager)->get('/surat-masuk')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-disposition-bidang="408.37.04"', $after);

        // Popup tidak lagi menawarkan pembatalan untuk bidang tersebut.
        $popup = $this->actingAs($manager)
            ->getJson(route('surat-masuk.disposisi', $letter))
            ->assertOk()
            ->json('bidangs');

        $row = collect($popup)->firstWhere('kode_bidang', '408.37.04');

        $this->assertNull($row['last_status']);
        $this->assertNull($row['disposition_id']);
    }

    public function test_cancelling_sends_a_whatsapp_notice_when_requested(): void
    {
        $manager = $this->manager();

        $bidang = User::factory()->create([
            'name' => 'Bidang Pembinaan Sekolah Menengah Pertama (SMP)',
            'alias' => 'SMP',
            'kode_bidang' => '408.37.04',
            'phone' => '0812-5555-6666',
        ]);

        $letter = IncomingLetter::create($this->payload());

        LetterDisposition::create([
            'incoming_letter_id' => $letter->id,
            'kode_bidang' => '408.37.04',
            'bidang_name' => $bidang->name,
            'user_id' => $bidang->id,
            'phone' => '6281255556666',
            'status' => LetterDisposition::STATUS_SENT,
            'sent_at' => now(),
        ]);

        Http::fake([
            'gateway.billnesia.com/health' => Http::response(['status' => 'online']),
            'gateway.billnesia.com/send' => Http::response(['success' => true, 'messageId' => 'CANCEL-1']),
        ]);

        $this->actingAs($manager)
            ->deleteJson(route('surat-masuk.disposisi.batal', $letter), [
                'kode_bidang' => '408.37.04',
                'reason' => 'Salah bidang, seharusnya PTK',
                'notify' => true,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('notification.ok', true);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'http://gateway.billnesia.com/send') {
                return false;
            }

            $message = (string) ($request['pesan'] ?? '');

            return $request['nomor_penerima'] === '6281255556666'
                && str_contains($message, 'Pembatalan Disposisi Surat Masuk')
                && str_contains($message, 'Salah bidang, seharusnya PTK')
                && str_contains($message, 'tidak ditindaklanjuti');
        });
    }

    public function test_cancelling_requires_an_active_disposition(): void
    {
        $letter = IncomingLetter::create($this->payload());

        $this->actingAs($this->manager())
            ->deleteJson(route('surat-masuk.disposisi.batal', $letter), [
                'kode_bidang' => '408.37.06',
                'notify' => false,
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_only_the_letter_manager_can_cancel_a_disposition(): void
    {
        $letter = IncomingLetter::create($this->payload());

        $reader = User::factory()->create(['is_admin' => false, 'kode_bidang' => '408.37.03']);

        $this->actingAs($reader)
            ->deleteJson(route('surat-masuk.disposisi.batal', $letter), [
                'kode_bidang' => '408.37.04',
                'notify' => false,
            ])
            ->assertForbidden();
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
            ->assertSee('data-disposisi-attachment-input', escape: false)
            ->assertSee('data-disposisi-attachment-upload', escape: false)
            ->assertSee('Unggah Lampiran')
            ->assertSee('data-disposisi-cancel-section', escape: false)
            ->assertSee('data-disposisi-cancel-list', escape: false)
            ->assertSee('Batalkan Disposisi yang Salah')
            ->assertSee('Kirim Disposisi');
    }
}
