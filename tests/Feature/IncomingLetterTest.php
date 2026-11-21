<?php

namespace Tests\Feature;

use App\Models\IncomingLetter;
use App\Models\InstitutionProfile;
use App\Models\User;
use Database\Seeders\IncomingLetterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncomingLetterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'agenda_number' => 'SM/2026/001',
            'received_date' => now()->subDay()->format('Y-m-d'),
            'letter_date' => now()->subDays(3)->format('Y-m-d'),
            'letter_number' => '005/123/DISKOMINFO/2026',
            'sender' => 'Dinas Pendidikan Provinsi',
            'subject' => 'Undangan Rapat Koordinasi Tata Usaha',
            'sifat' => ['segera'],
        ], $overrides);
    }

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $this->get('/surat-masuk')->assertRedirect(route('login'));
        $this->post('/surat-masuk', $this->payload())->assertRedirect(route('login'));
    }

    public function test_the_datatable_is_rendered_with_all_columns_and_actions(): void
    {
        IncomingLetter::create($this->payload());

        $this->actingAs(User::factory()->create())
            ->get('/surat-masuk')
            ->assertOk()
            ->assertSee('Nomor Agenda')
            ->assertSee('Tanggal Diterima')
            ->assertSee('Tanggal Surat')
            ->assertSee('Nomor Surat')
            ->assertSee('Asal Surat')
            ->assertSee('Perihal')
            ->assertSee('SM/2026/001')
            ->assertSee('data-table-search', escape: false)
            ->assertSee('data-table-per-page', escape: false)
            ->assertSee('10 data')
            ->assertSee('data-letter-edit', escape: false)
            ->assertSee('data-confirm-delete', escape: false)
            ->assertSee('data-letter-modal', escape: false)
            ->assertSee('/cetak', escape: false);
    }

    public function test_an_incoming_letter_can_be_stored(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/surat-masuk', $this->payload())
            ->assertRedirect(route('surat-masuk.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('incoming_letters', [
            'agenda_number' => 'SM/2026/001',
            'letter_number' => '005/123/DISKOMINFO/2026',
            'sender' => 'Dinas Pendidikan Provinsi',
        ]);

        $this->assertSame(
            now()->subDays(3)->format('Y-m-d'),
            IncomingLetter::query()->first()->letter_date?->format('Y-m-d'),
        );

        $this->assertSame(['segera'], IncomingLetter::query()->first()->sifat);
    }

    public function test_sifat_supports_multiple_checkboxes_and_can_be_cleared(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/surat-masuk', $this->payload([
            'sifat' => ['segera', 'rahasia'],
        ]));

        $letter = IncomingLetter::query()->first();

        $this->assertSame(['segera', 'rahasia'], $letter->sifat);
        $this->assertSame(['Segera/Penting', 'Rahasia'], $letter->sifatLabels());

        // Seluruh centang dilepas saat update -> sifat harus ikut terhapus.
        $this->actingAs($user)->put("/surat-masuk/{$letter->id}", $this->payload(['sifat' => []]));

        $this->assertSame([], $letter->fresh()->sifat);
    }

    public function test_sifat_must_use_the_available_options(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/surat-masuk', $this->payload(['sifat' => ['biasa']]))
            ->assertSessionHasErrors('sifat.0');
    }

    public function test_the_sifat_badges_and_printed_label_are_shown(): void
    {
        $letter = IncomingLetter::create($this->payload(['sifat' => ['segera', 'terbuka']]));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/surat-masuk')
            ->assertOk()
            ->assertSee('Sifat')
            ->assertSee('Segera/Penting')
            ->assertSee('Terbuka');

        $this->actingAs($user)
            ->get("/surat-masuk/{$letter->id}/cetak")
            ->assertOk()
            ->assertSee('Sifat')
            ->assertSee('Segera/Penting')
            ->assertSee('Terbuka')
            ->assertSee('data-sifat="segera"', escape: false)
            ->assertSee('data-checked="true"', escape: false)
            ->assertSee('data-sifat="rahasia"', escape: false);
    }

    public function test_the_form_fields_follow_the_requested_order(): void
    {
        $content = $this->actingAs(User::factory()->create())
            ->get('/surat-masuk')
            ->assertOk()
            ->getContent();

        // Ambil hanya bagian formulir (modal) agar tidak tertukar dengan header tabel.
        $formStart = strpos($content, 'data-letter-form');
        $this->assertNotFalse($formStart);

        $form = substr($content, $formStart, strpos($content, '</form>', $formStart) - $formStart);

        $positions = [];

        foreach (['Tanggal Surat', 'Nomor Surat', 'Asal Surat', 'Perihal', 'Sifat Surat', 'Tanggal Diterima', 'Nomor Agenda'] as $label) {
            $matched = preg_match('/>\s*'.preg_quote($label, '/').'\s*</', $form, $match, PREG_OFFSET_CAPTURE);

            $this->assertSame(1, $matched, "Label {$label} tidak ditemukan pada formulir.");
            $positions[$label] = $match[0][1];
        }

        $this->assertSame(
            array_keys($positions),
            collect($positions)->sort()->keys()->all(),
            'Urutan field formulir tidak sesuai permintaan.',
        );

        // Ketiga pilihan sifat tersedia sebagai checkbox.
        $this->assertStringContainsString('name="sifat[]" value="segera"', $form);
        $this->assertStringContainsString('name="sifat[]" value="terbuka"', $form);
        $this->assertStringContainsString('name="sifat[]" value="rahasia"', $form);
    }

    public function test_storing_requires_all_fields(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/surat-masuk', [])
            ->assertSessionHasErrors([
                'agenda_number',
                'received_date',
                'letter_date',
                'letter_number',
                'sender',
                'subject',
            ])
            ->assertSessionHas('open_form', 'create');
    }

    public function test_the_letter_date_is_required_and_cannot_be_in_the_future(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/surat-masuk', $this->payload(['letter_date' => now()->addWeek()->format('Y-m-d')]))
            ->assertSessionHasErrors('letter_date');
    }

    public function test_the_letter_date_is_shown_in_indonesian_format(): void
    {
        IncomingLetter::create($this->payload([
            'received_date' => '2026-08-14',
            'letter_date' => '2026-08-12',
        ]));

        $user = User::factory()->create();

        // Tabel datatable.
        $this->actingAs($user)
            ->get('/surat-masuk')
            ->assertOk()
            ->assertSee('Tanggal Surat')
            ->assertSee('12 Agustus 2026');

        // Lembar disposisi: tanggal surat tampil dengan format Indonesia.
        $content = $this->actingAs($user)
            ->get('/surat-masuk/'.IncomingLetter::query()->value('id').'/cetak')
            ->assertOk()
            ->assertSee('12 Agustus 2026')
            ->getContent();

        $this->assertStringContainsString('Tanggal Surat', $content);
        $this->assertLessThan(
            strpos($content, 'Tanggal Surat'),
            strpos($content, 'Nomor Surat'),
            'Nomor surat tampil sebelum tanggal surat sesuai lembar disposisi.',
        );
    }

    public function test_the_agenda_number_must_be_unique(): void
    {
        IncomingLetter::create($this->payload());

        $this->actingAs(User::factory()->create())
            ->post('/surat-masuk', $this->payload(['letter_number' => '999/999/XXX/2026']))
            ->assertSessionHasErrors('agenda_number');
    }

    public function test_the_received_date_cannot_be_in_the_future(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/surat-masuk', $this->payload(['received_date' => now()->addWeek()->format('Y-m-d')]))
            ->assertSessionHasErrors('received_date');
    }

    public function test_an_incoming_letter_can_be_updated(): void
    {
        $letter = IncomingLetter::create($this->payload());

        $this->actingAs(User::factory()->create())
            ->put("/surat-masuk/{$letter->id}", $this->payload([
                'sender' => 'Inspektorat Daerah',
                'subject' => 'Permohonan Data Rekapitulasi Surat',
            ]))
            ->assertRedirect(route('surat-masuk.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('incoming_letters', [
            'id' => $letter->id,
            'sender' => 'Inspektorat Daerah',
            'subject' => 'Permohonan Data Rekapitulasi Surat',
        ]);
    }

    public function test_an_incoming_letter_can_be_deleted(): void
    {
        $letter = IncomingLetter::create($this->payload());

        $this->actingAs(User::factory()->create())
            ->delete("/surat-masuk/{$letter->id}")
            ->assertRedirect(route('surat-masuk.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('incoming_letters', ['id' => $letter->id]);
    }

    public function test_search_matches_agenda_number_sender_and_subject(): void
    {
        IncomingLetter::create($this->payload());
        IncomingLetter::create($this->payload([
            'agenda_number' => 'SM/2026/002',
            'sender' => 'Kecamatan Sukamaju',
            'subject' => 'Laporan Bulanan Arsip',
        ]));

        $user = User::factory()->create();

        $this->actingAs($user)->get('/surat-masuk?q=SM/2026/002')
            ->assertOk()->assertSee('SM/2026/002')->assertDontSee('SM/2026/001');

        $this->actingAs($user)->get('/surat-masuk?q=sukamaju')
            ->assertOk()->assertSee('Kecamatan Sukamaju')->assertDontSee('Dinas Pendidikan Provinsi');

        $this->actingAs($user)->get('/surat-masuk?q=undangan')
            ->assertOk()->assertSee('Undangan Rapat Koordinasi Tata Usaha')->assertDontSee('Laporan Bulanan Arsip');
    }

    public function test_pagination_uses_a_slice_of_ten_by_default(): void
    {
        foreach (range(1, 12) as $i) {
            IncomingLetter::create($this->payload([
                'agenda_number' => 'SM/2026/'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'received_date' => now()->subDays($i)->format('Y-m-d'),
            ]));
        }

        $user = User::factory()->create();

        $first = $this->actingAs($user)->get('/surat-masuk');
        $first->assertOk()->assertSee('1–10');
        $this->assertSame(10, substr_count($first->getContent(), 'data-row'));

        $second = $this->actingAs($user)->get('/surat-masuk?page=2');
        $second->assertOk()->assertSee('11–12');
        $this->assertSame(2, substr_count($second->getContent(), 'data-row'));
    }

    public function test_invalid_per_page_value_falls_back_to_ten(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/surat-masuk?per_page=500')
            ->assertOk()
            ->assertSee('10 data');
    }

    public function test_ajax_requests_only_return_the_table_partial(): void
    {
        IncomingLetter::create($this->payload());

        $this->actingAs(User::factory()->create())
            ->get('/surat-masuk', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('SM/2026/001')
            ->assertDontSee('<html', escape: false)
            ->assertDontSee('Tambah Surat Masuk');
    }

    public function test_the_print_sheet_shows_the_letter_and_institution_header(): void
    {
        InstitutionProfile::current()->update([
            'government_name' => 'Pemerintah Kota Bandung',
            'institution_name' => 'Dinas Komunikasi dan Informatika',
            'address' => 'Jl. Wastukancana No. 2, Bandung',
            'website' => 'diskominfo.bandung.go.id',
            'email' => 'diskominfo@bandung.go.id',
        ]);

        $letter = IncomingLetter::create($this->payload());

        $this->actingAs(User::factory()->create(['name' => 'Budi Santoso']))
            ->get("/surat-masuk/{$letter->id}/cetak")
            ->assertOk()
            ->assertSee('LEMBAR DISPOSISI')
            ->assertSee('Surat Dari')
            ->assertSee('Diterima Tanggal')
            ->assertSee('Nomor Agenda')
            ->assertSee('Diteruskan Kepada')
            ->assertSee('Dengan hormat harap')
            ->assertSee('Catatan :')
            ->assertSee('Pemerintah Kota Bandung')
            ->assertSee('Dinas Komunikasi dan Informatika')
            ->assertSee('Jl. Wastukancana No. 2, Bandung')
            ->assertSee('diskominfo.bandung.go.id')
            ->assertSee('diskominfo@bandung.go.id')
            ->assertSee('SM/2026/001')
            ->assertSee('Undangan Rapat Koordinasi Tata Usaha');
    }

    public function test_the_print_sheet_takes_the_kop_and_logo_from_the_institution_profile(): void
    {
        Storage::fake('public');

        $logo = UploadedFile::fake()->image('logo-pemkab.png', 200, 200)->store('logos', 'public');

        InstitutionProfile::current()->update([
            'government_name' => 'Pemerintah Kabupaten Pacitan',
            'institution_name' => 'Dinas Pendidikan',
            'address' => 'Jl. Dewi Sartika No. 17 Pacitan',
            'website' => 'www.dindik.pacitankab.go.id',
            'email' => 'dindik@pacitankab.go.id',
            'logo_path' => $logo,
        ]);

        $letter = IncomingLetter::create($this->payload());

        $response = $this->actingAs(User::factory()->create())
            ->get("/surat-masuk/{$letter->id}/cetak")
            ->assertOk()
            ->assertSee('Pemerintah Kabupaten Pacitan')
            ->assertSee('Dinas Pendidikan')
            ->assertSee('Jl. Dewi Sartika No. 17 Pacitan')
            ->assertSee('www.dindik.pacitankab.go.id')
            ->assertSee('dindik@pacitankab.go.id');

        // Logo yang diunggah lewat menu Profil Instansi dipakai pada kop cetak.
        $response->assertSee('src="/storage/'.$logo.'?v=', escape: false);
        $response->assertSee('alt="Logo Dinas Pendidikan"', escape: false);
    }

    public function test_the_sidebar_logo_and_name_come_from_the_institution_profile(): void
    {
        Storage::fake('public');

        $logo = UploadedFile::fake()->image('logo.png', 120, 120)->store('logos', 'public');

        InstitutionProfile::current()->update([
            'government_name' => 'Pemerintah Kota Bandung',
            'institution_name' => 'Dinas Komunikasi dan Informatika',
            'logo_path' => $logo,
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dinas Komunikasi dan Informatika')
            ->assertSee('Pemerintah Kota Bandung')
            ->assertSee('src="/storage/'.$logo.'?v=', escape: false);
    }

    public function test_the_kop_font_sizes_follow_the_requested_sizes(): void
    {
        InstitutionProfile::current()->update([
            'government_name' => 'Pemerintah Kabupaten Pacitan',
            'institution_name' => 'Dinas Pendidikan',
            'address' => 'Jl. Dewi Sartika No. 17 Pacitan',
            'website' => 'www.dindik.pacitankab.go.id',
            'email' => 'dindik@pacitankab.go.id',
        ]);

        $letter = IncomingLetter::create($this->payload());

        $content = $this->actingAs(User::factory()->create())
            ->get("/surat-masuk/{$letter->id}/cetak")
            ->assertOk()
            ->getContent();

        // Nama pemerintahan 14pt, nama instansi 16pt, alamat/website/email 12pt.
        $this->assertMatchesRegularExpression(
            '/<p class="text-\[14pt\][^"]*">\s*Pemerintah Kabupaten Pacitan/',
            $content,
        );
        $this->assertMatchesRegularExpression(
            '/<p class="text-\[16pt\][^"]*">\s*Dinas Pendidikan/',
            $content,
        );
        $this->assertMatchesRegularExpression(
            '/<p class="mt-0\.5 text-\[12pt\]">\s*Jl\. Dewi Sartika No\. 17 Pacitan/',
            $content,
        );
        $this->assertMatchesRegularExpression('/<p class="text-\[12pt\]">/', $content);
        $this->assertStringContainsString('dindik.pacitankab.go.id', $content);
        $this->assertStringContainsString('dindik@pacitankab.go.id', $content);
    }

    public function test_the_colon_cells_have_no_vertical_borders(): void
    {
        $letter = IncomingLetter::create($this->payload());

        $content = $this->actingAs(User::factory()->create())
            ->get("/surat-masuk/{$letter->id}/cetak")
            ->assertOk()
            ->getContent();

        // Tanda ":" memakai kelas cell-colon tanpa garis kiri/kanan.
        $this->assertStringContainsString('.disposisi .cell-colon {', $content);
        $this->assertMatchesRegularExpression('/\.disposisi \.cell-colon \{[^}]*border-left: 0;/s', $content);
        $this->assertMatchesRegularExpression('/\.disposisi \.cell-colon \{[^}]*border-right: 0;/s', $content);
        $this->assertMatchesRegularExpression('/\.disposisi \.cell-label \{[^}]*border-right: 0;/s', $content);
        $this->assertMatchesRegularExpression('/\.disposisi \.cell-value \{[^}]*border-left: 0;/s', $content);

        // Semua baris "Surat Dari" s.d. "Hal" memakai kelas tersebut
        // (2+2 baris dua kolom, lalu Tanggal Surat, Sifat, dan Hal).
        $this->assertSame(7, substr_count($content, 'class="cell-colon'));
        $this->assertSame(7, substr_count($content, 'class="cell-label'));
        $this->assertSame(7, substr_count($content, 'class="cell-value"'));
        $this->assertStringNotContainsString('class="cell-center">:', $content);
    }

    public function test_the_left_right_divider_sits_near_the_middle(): void
    {
        $letter = IncomingLetter::create($this->payload());

        $content = $this->actingAs(User::factory()->create())
            ->get("/surat-masuk/{$letter->id}/cetak")
            ->assertOk()
            ->getContent();

        preg_match_all('/<col style="width: (\d+)mm">/', $content, $matches);
        $widths = array_map('intval', $matches[1]);

        $this->assertSame([24, 2, 80, 27, 2, 59], $widths);
        $this->assertSame(194, array_sum($widths));

        // Garis tegak sebelum "Diterima Tanggal" berada di ±55% lebar lembar.
        $divider = $widths[0] + $widths[1] + $widths[2];
        $ratio = $divider / array_sum($widths);

        $this->assertGreaterThan(0.52, $ratio);
        $this->assertLessThan(0.58, $ratio);
    }

    public function test_the_colgroup_keeps_the_sheet_width(): void
    {
        $letter = IncomingLetter::create($this->payload());

        $content = $this->actingAs(User::factory()->create())
            ->get("/surat-masuk/{$letter->id}/cetak")
            ->assertOk()
            ->getContent();

        // Enam kolom dengan total selebar lembar (194mm).
        preg_match_all('/<col style="width: (\d+)mm">/', $content, $matches);
        $widths = array_map('intval', $matches[1]);

        $this->assertCount(6, $widths);
        $this->assertSame(194, array_sum($widths));
    }

    public function test_the_right_side_colons_are_shifted_slightly_right(): void
    {
        $letter = IncomingLetter::create($this->payload());

        $content = $this->actingAs(User::factory()->create())
            ->get("/surat-masuk/{$letter->id}/cetak")
            ->assertOk()
            ->getContent();

        // Hanya titik dua sisi kanan (Diterima Tanggal & Nomor Agenda) yang digeser.
        $this->assertSame(2, substr_count($content, 'class="cell-colon cell-colon-right"'));
        $this->assertSame(5, substr_count($content, 'class="cell-colon"'));

        $this->assertMatchesRegularExpression(
            '/\.disposisi \.cell-colon-right \{[^}]*padding-left: 1\.5mm;[^}]*padding-right: 0\.3mm;/s',
            $content,
        );

        // Penyesuaian manual font-size tidak berubah.
        $this->assertMatchesRegularExpression('/\.disposisi \.cell-label \{[^}]*font-size:\s*9pt;/s', $content);
        $this->assertMatchesRegularExpression('/\.disposisi \.cell-value \{[^}]*font-size:\s*9pt;/s', $content);
    }

    public function test_the_print_sheet_uses_half_a4_on_white_paper(): void
    {
        $letter = IncomingLetter::create($this->payload());

        $this->actingAs(User::factory()->create())
            ->get("/surat-masuk/{$letter->id}/cetak")
            ->assertOk()
            ->assertSee('size: A4 portrait;', escape: false)
            ->assertSee('width: 194mm;', escape: false)
            ->assertSee('min-height: 148mm;', escape: false)
            ->assertSee('min-height: 170mm;', escape: false)
            ->assertSee('background: #fff;', escape: false);
    }

    public function test_the_catatan_area_is_tall_enough_for_handwriting(): void
    {
        $letter = IncomingLetter::create($this->payload());

        $this->actingAs(User::factory()->create())
            ->get("/surat-masuk/{$letter->id}/cetak")
            ->assertOk()
            ->assertSee('catatan-area', escape: false)
            ->assertSee('min-height: 170mm;', escape: false);
    }

    public function test_the_sample_seeder_is_non_destructive(): void
    {
        $existing = IncomingLetter::create($this->payload(['agenda_number' => 'SM/KEEP/001']));

        $this->seed(IncomingLetterSeeder::class);
        $firstRun = IncomingLetter::query()->count();

        $this->seed(IncomingLetterSeeder::class);

        // Data lama tetap ada dan seeder ulang tidak menggandakan baris.
        $this->assertSame($firstRun, IncomingLetter::query()->count());
        $this->assertDatabaseHas('incoming_letters', [
            'id' => $existing->id,
            'agenda_number' => 'SM/KEEP/001',
        ]);
    }

    public function test_the_agenda_number_suggestion_increments(): void
    {
        $year = now()->year;

        $this->assertSame("SM/{$year}/001", IncomingLetter::suggestAgendaNumber());

        IncomingLetter::create($this->payload(['agenda_number' => "SM/{$year}/007"]));

        $this->assertSame("SM/{$year}/008", IncomingLetter::suggestAgendaNumber());
    }
}
