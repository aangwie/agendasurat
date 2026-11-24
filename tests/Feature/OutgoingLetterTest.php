<?php

namespace Tests\Feature;

use App\Models\OutgoingLetter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutgoingLetterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'year' => 2026,
            'month' => 1,
            'code' => '00',
            'sub_code' => '01',
            'letter_prefix' => '421.2',
            'subject' => 'Undangan Rapat Koordinasi',
            'letter_date' => '2026-01-12',
        ], $overrides);
    }

    /**
     * Akun bidang beserta kode bidang, seperti akun hasil BidangUserSeeder.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function bidangUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Bidang PTK',
            'kode_bidang' => '408.37.06',
        ], $attributes));
    }

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $this->get('/surat-keluar')->assertRedirect(route('login'));
        $this->post('/surat-keluar', $this->payload())->assertRedirect(route('login'));
    }

    public function test_the_outgoing_letter_table_is_rendered_with_datatable_controls(): void
    {
        $user = $this->bidangUser(['name' => 'Bidang PTK']);

        OutgoingLetter::factory()->create([
            'user_id' => $user->id,
            'year' => 2026,
            'month' => 1,
            'code' => '00',
            'sub_code' => '01',
            'letter_prefix' => '421.2',
            'kode_bidang' => '408.37.06',
            'letter_number' => '421.2/0001/408.37.06/2026',
            'subject' => 'Undangan Rapat Koordinasi',
            'letter_date' => '2026-08-12',
            'bidang' => 'Bidang PTK',
        ]);

        $this->actingAs($user)
            ->get('/surat-keluar')
            ->assertOk()
            ->assertSee('Tahun')
            ->assertSee('Bulan')
            ->assertSee('Subkode')
            ->assertSee('Nomor Surat Keluar')
            ->assertSee('Perihal')
            ->assertSee('Tanggal Surat')
            ->assertSee('421.2/0001/408.37.06/2026')
            ->assertSee('Undangan Rapat Koordinasi')
            ->assertSee('12 Agustus 2026')
            ->assertSee('Bidang PTK')
            ->assertSee('data-table-search', escape: false)
            ->assertSee('10 data')
            ->assertSee('data-outgoing-modal', escape: false)
            ->assertSee('name="letter_prefix"', escape: false)
            ->assertSee('data-outgoing-number-preview', escape: false)
            ->assertSee('name="subject"', escape: false)
            ->assertSee('name="letter_date"', escape: false)
            ->assertSee('data-date-display="outgoing-letter-date-preview"', escape: false)
            ->assertSee('data-outgoing-edit', escape: false)
            ->assertSee('data-confirm-delete', escape: false);
    }

    public function test_the_letter_number_is_composed_from_prefix_code_bidang_and_year(): void
    {
        $user = $this->bidangUser(['kode_bidang' => '408.37.06']);

        $this->actingAs($user)
            ->post('/surat-keluar', $this->payload([
                'year' => 2026,
                'month' => 2,
                'code' => '05',
                'sub_code' => '56',
                'letter_prefix' => '400.3.5',
            ]))
            ->assertRedirect(route('surat-keluar.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('outgoing_letters', [
            'user_id' => $user->id,
            'letter_prefix' => '400.3.5',
            'code' => '05',
            'sub_code' => '56',
            'kode_bidang' => '408.37.06',
            'letter_number' => '400.3.5/0556/408.37.06/2026',
        ]);
    }

    public function test_a_letter_can_be_created_and_takes_the_bidang_from_the_user(): void
    {
        $user = $this->bidangUser([
            'name' => 'Bidang Pendidik dan Tenaga Kependidikan (PTK)',
            'kode_bidang' => '408.37.06',
        ]);

        $this->actingAs($user)
            ->post('/surat-keluar', $this->payload())
            ->assertRedirect(route('surat-keluar.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('outgoing_letters', [
            'user_id' => $user->id,
            'year' => 2026,
            'month' => 1,
            'code' => '00',
            'sub_code' => '01',
            'letter_prefix' => '421.2',
            'kode_bidang' => '408.37.06',
            'letter_number' => '421.2/0001/408.37.06/2026',
            'subject' => 'Undangan Rapat Koordinasi',
            'bidang' => 'Bidang Pendidik dan Tenaga Kependidikan (PTK)',
        ]);

        $letter = OutgoingLetter::query()->firstOrFail();

        $this->assertSame('2026-01-12', $letter->letter_date->toDateString());
        $this->assertSame('12 Januari 2026', $letter->letterDateLabel());
    }

    public function test_the_letter_prefix_is_required(): void
    {
        $user = $this->bidangUser();

        $this->actingAs($user)
            ->post('/surat-keluar', $this->payload(['letter_prefix' => '']))
            ->assertSessionHasErrors('letter_prefix');

        $this->assertSame(0, OutgoingLetter::query()->count());
    }

    public function test_the_letter_prefix_rejects_slashes_and_spaces(): void
    {
        $user = $this->bidangUser();

        $this->actingAs($user)
            ->post('/surat-keluar', $this->payload(['letter_prefix' => '400/3']))
            ->assertSessionHasErrors('letter_prefix');

        $this->actingAs($user)
            ->post('/surat-keluar', $this->payload(['letter_prefix' => '400 3']))
            ->assertSessionHasErrors('letter_prefix');

        $this->assertSame(0, OutgoingLetter::query()->count());
    }

    public function test_a_user_without_kode_bidang_cannot_create_a_letter(): void
    {
        $user = User::factory()->create(['kode_bidang' => null]);

        $this->actingAs($user)
            ->post('/surat-keluar', $this->payload())
            ->assertSessionHasErrors('kode_bidang');

        $this->assertSame(0, OutgoingLetter::query()->count());
    }

    public function test_an_administrator_can_choose_the_bidang_of_the_letter(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Administrator']);
        $bidang = $this->bidangUser(['name' => 'Bidang SMP', 'kode_bidang' => '408.37.04']);

        $this->actingAs($admin)
            ->post('/surat-keluar', $this->payload(['bidang_user_id' => $bidang->id]))
            ->assertRedirect(route('surat-keluar.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('outgoing_letters', [
            'user_id' => $admin->id,
            'kode_bidang' => '408.37.04',
            'letter_number' => '421.2/0001/408.37.04/2026',
            'bidang' => 'Bidang SMP',
        ]);
    }

    public function test_an_administrator_must_choose_a_bidang_when_creating_a_letter(): void
    {
        $admin = User::factory()->admin()->create();
        $this->bidangUser();

        $this->actingAs($admin)
            ->post('/surat-keluar', $this->payload())
            ->assertSessionHasErrors('bidang_user_id');

        $this->assertSame(0, OutgoingLetter::query()->count());
    }

    public function test_an_administrator_cannot_choose_a_bidang_without_kode_bidang(): void
    {
        $admin = User::factory()->admin()->create();
        $withoutCode = User::factory()->create(['name' => 'Bidang Tanpa Kode', 'kode_bidang' => null]);

        $this->actingAs($admin)
            ->post('/surat-keluar', $this->payload(['bidang_user_id' => $withoutCode->id]))
            ->assertSessionHasErrors('bidang_user_id');

        $this->assertSame(0, OutgoingLetter::query()->count());
    }

    public function test_the_bidang_picker_is_only_available_to_administrators(): void
    {
        $bidang = $this->bidangUser(['name' => 'Bidang PTK', 'kode_bidang' => '408.37.06']);

        $this->actingAs($bidang)
            ->get('/surat-keluar')
            ->assertOk()
            ->assertDontSee('data-outgoing-bidang-select', escape: false);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/surat-keluar')
            ->assertOk()
            ->assertSee('data-outgoing-bidang-select', escape: false)
            ->assertSee('name="bidang_user_id"', escape: false)
            ->assertSee('data-kode-bidang="408.37.06"', escape: false)
            ->assertSee('Bidang PTK');
    }

    public function test_a_non_administrator_cannot_choose_another_bidang(): void
    {
        $user = $this->bidangUser(['name' => 'Bidang PTK', 'kode_bidang' => '408.37.06']);
        $other = $this->bidangUser(['name' => 'Bidang SMP', 'kode_bidang' => '408.37.04']);

        $this->actingAs($user)
            ->post('/surat-keluar', $this->payload(['bidang_user_id' => $other->id]))
            ->assertRedirect(route('surat-keluar.index'));

        $this->assertDatabaseHas('outgoing_letters', [
            'bidang' => 'Bidang PTK',
            'kode_bidang' => '408.37.06',
            'letter_number' => '421.2/0001/408.37.06/2026',
        ]);
    }


    public function test_the_subject_and_letter_date_are_required(): void
    {
        $user = $this->bidangUser();

        $this->actingAs($user)
            ->post('/surat-keluar', $this->payload(['subject' => '', 'letter_date' => '']))
            ->assertSessionHasErrors(['subject', 'letter_date']);

        $this->assertSame(0, OutgoingLetter::query()->count());
    }

    public function test_the_letter_date_may_be_in_the_future(): void
    {
        $user = $this->bidangUser();
        $futureDate = now()->addDays(30)->toDateString();

        $this->actingAs($user)
            ->post('/surat-keluar', $this->payload(['letter_date' => $futureDate]))
            ->assertRedirect(route('surat-keluar.index'))
            ->assertSessionHasNoErrors();

        $letter = OutgoingLetter::query()->firstOrFail();

        $this->assertSame($futureDate, $letter->letter_date->toDateString());
    }

    public function test_the_letter_date_can_be_updated_to_a_future_date(): void
    {
        $user = $this->bidangUser();

        $letter = OutgoingLetter::factory()->create([
            'user_id' => $user->id,
            'letter_date' => now()->toDateString(),
        ]);

        $futureDate = now()->addMonths(2)->toDateString();

        $this->actingAs($user)
            ->put("/surat-keluar/{$letter->id}", [
                'letter_prefix' => '400.3.5',
                'subject' => 'Surat Untuk Diterbitkan Nanti',
                'letter_date' => $futureDate,
            ])
            ->assertRedirect(route('surat-keluar.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame($futureDate, $letter->fresh()->letter_date->toDateString());
    }

    public function test_a_used_code_and_sub_code_cannot_be_used_again_in_the_same_year(): void
    {
        $user = $this->bidangUser();

        OutgoingLetter::factory()->create([
            'user_id' => $user->id,
            'year' => 2026,
            'month' => 1,
            'code' => '00',
            'sub_code' => '01',
        ]);

        $this->actingAs($user)
            ->post('/surat-keluar', $this->payload(['letter_prefix' => 'SK-002']))
            ->assertSessionHasErrors('sub_code');

        $this->assertSame(1, OutgoingLetter::query()->count());
    }

    public function test_the_same_code_and_sub_code_can_be_used_in_another_year(): void
    {
        $user = $this->bidangUser();

        OutgoingLetter::factory()->create([
            'user_id' => $user->id,
            'year' => 2025,
            'month' => 1,
            'code' => '00',
            'sub_code' => '01',
        ]);

        $this->actingAs($user)
            ->post('/surat-keluar', $this->payload())
            ->assertRedirect(route('surat-keluar.index'));

        $this->assertSame(2, OutgoingLetter::query()->count());
    }

    public function test_the_code_must_belong_to_the_selected_month(): void
    {
        $user = $this->bidangUser();

        $this->actingAs($user)
            ->post('/surat-keluar', $this->payload(['month' => 1, 'code' => '05']))
            ->assertSessionHasErrors('code');

        $this->assertSame(0, OutgoingLetter::query()->count());
    }

    public function test_each_month_has_five_archive_codes(): void
    {
        $this->assertSame(['00', '01', '02', '03', '04'], OutgoingLetter::codesForMonth(1));
        $this->assertSame(['05', '06', '07', '08', '09'], OutgoingLetter::codesForMonth(2));
        $this->assertSame(['55', '56', '57', '58', '59'], OutgoingLetter::codesForMonth(12));

        $this->assertCount(60, OutgoingLetter::allCodes());
        $this->assertSame('00', OutgoingLetter::allCodes()[0]);
        $this->assertSame('59', OutgoingLetter::allCodes()[59]);

        $this->assertSame(1, OutgoingLetter::monthForCode('00'));
        $this->assertSame(1, OutgoingLetter::monthForCode('04'));
        $this->assertSame(2, OutgoingLetter::monthForCode('05'));
        $this->assertSame(2, OutgoingLetter::monthForCode('09'));
        $this->assertSame(12, OutgoingLetter::monthForCode('59'));
    }

    public function test_the_creator_can_update_the_letter_prefix(): void
    {
        $user = $this->bidangUser(['name' => 'Bidang SD', 'kode_bidang' => '408.37.04']);

        $letter = OutgoingLetter::factory()->create([
            'user_id' => $user->id,
            'year' => 2026,
            'month' => 1,
            'code' => '00',
            'sub_code' => '01',
            'letter_prefix' => '421.2',
            'kode_bidang' => '408.37.04',
            'letter_number' => '421.2/0001/408.37.04/2026',
            'subject' => 'Perihal Lama',
            'bidang' => 'Bidang SD',
        ]);

        $this->actingAs($user)
            ->put("/surat-keluar/{$letter->id}", [
                'year' => 2027,
                'month' => 5,
                'code' => '12',
                'sub_code' => '99',
                'letter_prefix' => '400.3.5',
                'subject' => 'Laporan Bulanan',
                'letter_date' => '2026-01-20',
            ])
            ->assertRedirect(route('surat-keluar.index'));

        $this->assertDatabaseHas('outgoing_letters', [
            'id' => $letter->id,
            'year' => 2026,
            'month' => 1,
            'code' => '00',
            'sub_code' => '01',
            'letter_prefix' => '400.3.5',
            'kode_bidang' => '408.37.04',
            'letter_number' => '400.3.5/0001/408.37.04/2026',
            'subject' => 'Laporan Bulanan',
            'bidang' => 'Bidang SD',
        ]);

        $this->assertSame('2026-01-20', $letter->fresh()->letter_date->toDateString());
    }

    public function test_an_administrator_can_update_another_users_letter(): void
    {
        $admin = User::factory()->admin()->create();
        $letter = OutgoingLetter::factory()->create([
            'letter_prefix' => '421.2',
            'subject' => 'Perihal Lama',
        ]);

        $this->actingAs($admin)
            ->put("/surat-keluar/{$letter->id}", [
                'letter_prefix' => '400.1.2',
                'subject' => 'Surat Tugas',
                'letter_date' => '2026-02-03',
            ])
            ->assertRedirect(route('surat-keluar.index'));

        $this->assertDatabaseHas('outgoing_letters', [
            'id' => $letter->id,
            'letter_prefix' => '400.1.2',
            'kode_bidang' => $letter->kode_bidang,
            'letter_number' => OutgoingLetter::composeNumber(
                '400.1.2',
                $letter->code,
                $letter->sub_code,
                (string) $letter->kode_bidang,
                (int) $letter->year,
            ),
            'subject' => 'Surat Tugas',
        ]);
    }

    public function test_another_bidang_cannot_update_or_delete_the_letter(): void
    {
        $creator = User::factory()->create(['name' => 'Bidang PTK']);
        $other = User::factory()->create(['name' => 'Bidang SMP']);

        $letter = OutgoingLetter::factory()->create(['user_id' => $creator->id]);

        $this->actingAs($other)
            ->put("/surat-keluar/{$letter->id}", ['letter_prefix' => 'SK-OTHER'])
            ->assertForbidden();

        $this->actingAs($other)
            ->delete("/surat-keluar/{$letter->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('outgoing_letters', ['id' => $letter->id]);
    }

    public function test_only_the_creator_and_admin_see_the_action_buttons(): void
    {
        $creator = User::factory()->create(['name' => 'Bidang PTK']);
        $other = User::factory()->create(['name' => 'Bidang SMP']);

        $letter = OutgoingLetter::factory()->create(['user_id' => $creator->id]);

        $this->actingAs($creator)
            ->get('/surat-keluar')
            ->assertSee('data-outgoing-edit="'.$letter->id.'"', escape: false);

        $this->actingAs($other)
            ->get('/surat-keluar')
            ->assertDontSee('data-outgoing-edit="'.$letter->id.'"', escape: false)
            ->assertSee('Hanya admin dan pembuat surat yang dapat mengubah');

        $this->actingAs(User::factory()->admin()->create())
            ->get('/surat-keluar')
            ->assertSee('data-outgoing-edit="'.$letter->id.'"', escape: false);
    }

    public function test_the_creator_can_delete_the_letter(): void
    {
        $creator = User::factory()->create();
        $letter = OutgoingLetter::factory()->create(['user_id' => $creator->id]);

        $this->actingAs($creator)
            ->delete("/surat-keluar/{$letter->id}")
            ->assertRedirect(route('surat-keluar.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('outgoing_letters', ['id' => $letter->id]);
    }

    public function test_search_filters_by_letter_number_prefix_bidang_and_creator(): void
    {
        $first = $this->bidangUser(['name' => 'Bidang PTK', 'kode_bidang' => '408.37.06']);
        $second = $this->bidangUser(['name' => 'Bidang SD', 'kode_bidang' => '408.37.04']);

        OutgoingLetter::factory()->create([
            'user_id' => $first->id,
            'year' => 2026,
            'month' => 1,
            'code' => '00',
            'sub_code' => '01',
            'letter_prefix' => '421.2',
            'kode_bidang' => '408.37.06',
            'letter_number' => '421.2/0001/408.37.06/2026',
            'subject' => 'Undangan Rapat Koordinasi',
            'bidang' => 'Bidang PTK',
        ]);

        OutgoingLetter::factory()->create([
            'user_id' => $second->id,
            'year' => 2026,
            'month' => 2,
            'code' => '05',
            'sub_code' => '01',
            'letter_prefix' => '400.3.5',
            'kode_bidang' => '408.37.04',
            'letter_number' => '400.3.5/0501/408.37.04/2026',
            'subject' => 'Laporan Kegiatan Sekolah',
            'bidang' => 'Bidang SD',
        ]);

        $this->actingAs($first)->get('/surat-keluar?q=PTK')
            ->assertOk()
            ->assertSee('421.2/0001/408.37.06/2026')
            ->assertDontSee('400.3.5/0501/408.37.04/2026');

        $this->actingAs($first)->get('/surat-keluar?q=400.3.5')
            ->assertOk()
            ->assertSee('400.3.5/0501/408.37.04/2026')
            ->assertDontSee('421.2/0001/408.37.06/2026');

        $this->actingAs($first)->get('/surat-keluar?q=Kegiatan')
            ->assertOk()
            ->assertSee('400.3.5/0501/408.37.04/2026')
            ->assertDontSee('421.2/0001/408.37.06/2026');
    }

    public function test_pagination_slices_the_dataset_with_a_minimum_of_ten_rows(): void
    {
        $user = $this->bidangUser();

        foreach (range(0, 11) as $index) {
            $month = 1 + ($index % 6);

            OutgoingLetter::factory()->create([
                'user_id' => $user->id,
                'year' => 2026 + intdiv($index, 6),
                'month' => $month,
                'code' => OutgoingLetter::codesForMonth($month)[0],
                'sub_code' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            ]);
        }

        $first = $this->actingAs($user)->get('/surat-keluar');
        $first->assertOk()->assertSee('1–10');
        $this->assertSame(10, substr_count($first->getContent(), 'data-row'));

        $second = $this->actingAs($user)->get('/surat-keluar?page=2');
        $second->assertOk();
        $this->assertSame(2, substr_count($second->getContent(), 'data-row'));
    }

    public function test_ajax_requests_only_return_the_table_partial(): void
    {
        $user = $this->bidangUser();

        OutgoingLetter::factory()->create([
            'user_id' => $user->id,
            'year' => 2026,
            'month' => 1,
            'code' => '00',
            'sub_code' => '01',
            'letter_prefix' => '421.2',
            'kode_bidang' => '408.37.06',
            'letter_number' => '421.2/0001/408.37.06/2026',
        ]);

        $this->actingAs($user)
            ->get('/surat-keluar', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('421.2/0001/408.37.06/2026')
            ->assertDontSee('<html', escape: false)
            ->assertDontSee('Tambah Surat Keluar');
    }
}
