<?php

namespace Tests\Feature;

use App\Models\LetterClassification;
use App\Models\User;
use Database\Seeders\LetterClassificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LetterClassificationTest extends TestCase
{
    use RefreshDatabase;

    private function seedTree(): void
    {
        $root = LetterClassification::create([
            'scope' => 'pemda', 'code' => '000', 'name' => 'UMUM', 'level' => 1, 'sort_order' => 1,
        ]);

        $sub = LetterClassification::create([
            'scope' => 'pemda', 'code' => '000.1', 'name' => 'KETATAUSAHAAN DAN KERUMAHTANGGAAN',
            'parent_id' => $root->id, 'level' => 2, 'sort_order' => 2,
        ]);

        LetterClassification::create([
            'scope' => 'pemda', 'code' => '000.1.1', 'name' => 'Telekomunikasi',
            'parent_id' => $sub->id, 'level' => 3, 'sort_order' => 3,
        ]);

        LetterClassification::create([
            'scope' => 'pemda', 'code' => '000.1.2', 'name' => 'Perjalanan Dinas Dalam Negeri',
            'parent_id' => $sub->id, 'level' => 3, 'sort_order' => 4,
        ]);

        $kemendagriRoot = LetterClassification::create([
            'scope' => 'kemendagri', 'code' => '000', 'name' => 'UMUM', 'level' => 1, 'sort_order' => 5,
        ]);

        LetterClassification::create([
            'scope' => 'kemendagri', 'code' => '000.2', 'name' => 'KETATAUSAHAAN KEMENDAGRI',
            'parent_id' => $kemendagriRoot->id, 'level' => 2, 'sort_order' => 6,
        ]);
    }

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $this->get('/klasifikasi')->assertRedirect(route('login'));
    }

    public function test_the_classification_page_is_rendered_with_datatable_controls(): void
    {
        $this->seedTree();

        $this->actingAs(User::factory()->create())
            ->get('/klasifikasi')
            ->assertOk()
            ->assertSee('Klasifikasi Surat')
            ->assertSee('Permendagri Nomor 83 Tahun 2022')
            ->assertSee('Telekomunikasi')
            ->assertSee('data-table-search', escape: false)
            ->assertSee('data-table-per-page', escape: false)
            ->assertSee('25 data');
    }

    public function test_rows_are_indented_according_to_the_hierarchy_level(): void
    {
        $this->seedTree();

        $this->actingAs(User::factory()->create())
            ->get('/klasifikasi')
            ->assertSee('data-level="1"', escape: false)
            ->assertSee('data-level="2"', escape: false)
            ->assertSee('data-level="3"', escape: false)
            ->assertSee('padding-left: 2.5rem', escape: false);
    }

    public function test_search_filters_rows_and_keeps_their_ancestors(): void
    {
        $this->seedTree();

        $this->actingAs(User::factory()->create())
            ->get('/klasifikasi?q=telekomunikasi')
            ->assertOk()
            ->assertSee('Telekomunikasi')
            ->assertSee('000.1')
            ->assertSee('UMUM')
            ->assertDontSee('Perjalanan Dinas Dalam Negeri')
            ->assertSee('pencarian');
    }

    public function test_search_can_match_a_code(): void
    {
        $this->seedTree();

        $this->actingAs(User::factory()->create())
            ->get('/klasifikasi?q=000.1.2')
            ->assertOk()
            ->assertSee('Perjalanan Dinas Dalam Negeri')
            ->assertDontSee('Telekomunikasi');
    }

    public function test_pagination_slices_the_dataset(): void
    {
        foreach (range(1, 30) as $i) {
            LetterClassification::create([
                'scope' => 'pemda',
                'code' => str_pad((string) ($i * 10), 3, '0', STR_PAD_LEFT),
                'name' => 'Kategori Uji '.$i,
                'level' => 1,
                'sort_order' => $i,
            ]);
        }

        $user = User::factory()->create();

        $firstPage = $this->actingAs($user)->get('/klasifikasi?per_page=10');
        $firstPage->assertOk()->assertSee('1–10');
        $this->assertSame(10, substr_count($firstPage->getContent(), 'data-row'));

        $lastPage = $this->actingAs($user)->get('/klasifikasi?per_page=10&page=3');
        $lastPage->assertOk()->assertSee('21–30');
        $this->assertSame(10, substr_count($lastPage->getContent(), 'data-row'));
    }

    public function test_invalid_per_page_value_falls_back_to_the_default(): void
    {
        $this->seedTree();

        $this->actingAs(User::factory()->create())
            ->get('/klasifikasi?per_page=999')
            ->assertOk()
            ->assertSee('25 data');
    }

    public function test_ajax_requests_only_return_the_table_partial(): void
    {
        $this->seedTree();

        $this->actingAs(User::factory()->create())
            ->get('/klasifikasi', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('Telekomunikasi')
            ->assertDontSee('Klasifikasi Surat')
            ->assertDontSee('<html', escape: false);
    }

    public function test_the_scope_filter_switches_the_dataset(): void
    {
        $this->seedTree();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/klasifikasi')
            ->assertOk()
            ->assertSee('KETATAUSAHAAN DAN KERUMAHTANGGAAN')
            ->assertDontSee('KETATAUSAHAAN KEMENDAGRI');

        $this->actingAs($user)
            ->get('/klasifikasi?scope=kemendagri')
            ->assertOk()
            ->assertSee('KETATAUSAHAAN KEMENDAGRI')
            ->assertDontSee('Telekomunikasi');
    }

    public function test_the_seeder_imports_the_permendagri_dataset(): void
    {
        $this->seed(LetterClassificationSeeder::class);

        $total = LetterClassification::query()->count();
        $this->assertGreaterThan(3900, $total);

        $node = LetterClassification::query()
            ->where('scope', 'pemda')
            ->where('code', '000.1.1')
            ->first();

        $this->assertNotNull($node);
        $this->assertSame('Telekomunikasi', $node->name);
        $this->assertSame(3, $node->level);
        $this->assertSame('000.1', $node->parent?->code);
        $this->assertSame('000', $node->parent?->parent?->code);

        // Non-destruktif: menjalankan seeder ulang tidak menghapus/menggandakan data.
        $this->seed(LetterClassificationSeeder::class);

        $this->assertSame($total, LetterClassification::query()->count());
        $this->assertSame(3, $node->fresh()->level);
    }
}
