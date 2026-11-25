<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Bidang Pendidikan Dasar',
            'kode_bidang' => '408.37.03',
            'email' => 'dikdas@instansi.go.id',
            'phone' => '081234567890',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $overrides);
    }

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $this->get('/pengguna')->assertRedirect(route('login'));
        $this->post('/pengguna', $this->payload())->assertRedirect(route('login'));
    }

    public function test_the_user_datatable_is_rendered_with_actions(): void
    {
        User::factory()->create(['name' => 'Bidang Umum', 'kode_bidang' => '408.37.01', 'phone' => '0811111111']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/pengguna')
            ->assertOk()
            ->assertSee('Nama Bidang')
            ->assertSee('Kode Bidang')
            ->assertSee('No HP')
            ->assertSee('Bidang Umum')
            ->assertSee('408.37.01')
            ->assertSee('0811111111')
            ->assertSee('data-table-search', escape: false)
            ->assertSee('data-table-per-page', escape: false)
            ->assertSee('10 data')
            ->assertSee('data-user-edit', escape: false)
            ->assertSee('data-confirm-delete', escape: false)
            ->assertSee('data-user-modal', escape: false);
    }

    public function test_a_user_can_be_created(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/pengguna', $this->payload())
            ->assertRedirect(route('pengguna.index'))
            ->assertSessionHas('status');

        $user = User::query()->where('email', 'dikdas@instansi.go.id')->first();

        $this->assertNotNull($user);
        $this->assertSame('Bidang Pendidikan Dasar', $user->name);
        $this->assertSame('408.37.03', $user->kode_bidang);
        $this->assertSame('081234567890', $user->phone);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_the_kode_bidang_is_optional(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/pengguna', $this->payload(['kode_bidang' => '']))
            ->assertRedirect(route('pengguna.index'));

        $user = User::query()->where('email', 'dikdas@instansi.go.id')->first();

        $this->assertNotNull($user);
        $this->assertNull($user->kode_bidang);
    }

    public function test_the_kode_bidang_must_use_the_register_format(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/pengguna', $this->payload(['kode_bidang' => 'bidang SD']))
            ->assertSessionHasErrors('kode_bidang');

        $this->assertDatabaseMissing('users', ['email' => 'dikdas@instansi.go.id']);
    }

    public function test_the_kode_bidang_must_be_unique(): void
    {
        User::factory()->create(['kode_bidang' => '408.37.03']);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/pengguna', $this->payload())
            ->assertSessionHasErrors('kode_bidang');
    }

    public function test_the_kode_bidang_can_be_updated(): void
    {
        $user = User::factory()->create(['kode_bidang' => '408.37.01']);

        $this->actingAs(User::factory()->admin()->create())
            ->put("/pengguna/{$user->id}", $this->payload([
                'email' => $user->email,
                'kode_bidang' => '408.37.02',
                'password' => '',
                'password_confirmation' => '',
            ]))
            ->assertRedirect(route('pengguna.index'));

        $this->assertSame('408.37.02', $user->fresh()->kode_bidang);
    }

    public function test_the_alias_is_optional_and_falls_back_to_the_full_name(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/pengguna', $this->payload(['alias' => '']))
            ->assertRedirect(route('pengguna.index'));

        $user = User::query()->where('email', 'dikdas@instansi.go.id')->first();

        $this->assertNotNull($user);
        $this->assertNull($user->alias);
        $this->assertSame('Bidang Pendidikan Dasar', $user->bidangLabel());
    }

    public function test_an_alias_can_be_created_updated_and_shown_on_the_table(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/pengguna', $this->payload([
                'name' => 'Bidang Pendidik dan Tenaga Kependidikan (PTK)',
                'alias' => 'PTK',
            ]))
            ->assertRedirect(route('pengguna.index'));

        $user = User::query()->where('email', 'dikdas@instansi.go.id')->firstOrFail();

        $this->assertSame('PTK', $user->alias);
        $this->assertSame('PTK', $user->bidangLabel());

        $this->actingAs($admin)
            ->put("/pengguna/{$user->id}", $this->payload([
                'name' => 'Bidang Pendidik dan Tenaga Kependidikan (PTK)',
                'alias' => 'PTK Baru',
                'email' => $user->email,
                'password' => '',
                'password_confirmation' => '',
            ]))
            ->assertRedirect(route('pengguna.index'));

        $this->assertSame('PTK Baru', $user->fresh()->alias);

        $this->actingAs($admin)
            ->get('/pengguna')
            ->assertOk()
            ->assertSee('Alias')
            ->assertSee('PTK Baru')
            ->assertSee('data-user-alias', escape: false);
    }

    public function test_the_alias_is_limited_to_thirty_characters(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/pengguna', $this->payload(['alias' => str_repeat('A', 31)]))
            ->assertSessionHasErrors('alias');
    }

    public function test_creating_requires_all_fields(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/pengguna', [])
            ->assertSessionHasErrors(['name', 'email', 'password'])
            ->assertSessionHas('open_form', 'create');
    }

    public function test_the_phone_number_is_optional(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/pengguna', $this->payload(['phone' => '']))
            ->assertRedirect(route('pengguna.index'));

        $user = User::query()->where('email', 'dikdas@instansi.go.id')->first();

        $this->assertNotNull($user);
        $this->assertNull($user->phone);
    }

    public function test_the_password_must_be_confirmed_and_long_enough(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/pengguna', $this->payload([
                'password' => 'pendek',
                'password_confirmation' => 'beda',
            ]))
            ->assertSessionHasErrors('password');
    }

    public function test_the_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'dikdas@instansi.go.id']);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/pengguna', $this->payload())
            ->assertSessionHasErrors('email');
    }

    public function test_the_phone_number_must_be_valid(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/pengguna', $this->payload(['phone' => '12345']))
            ->assertSessionHasErrors('phone');
    }

    public function test_a_user_can_be_updated_without_changing_the_password(): void
    {
        $user = User::factory()->create(['password' => 'password-lama']);
        $oldPassword = $user->password;

        $this->actingAs(User::factory()->admin()->create())
            ->put("/pengguna/{$user->id}", $this->payload([
                'email' => $user->email,
                'name' => 'Bidang Sosial',
                'phone' => '089876543210',
                'password' => '',
                'password_confirmation' => '',
            ]))
            ->assertRedirect(route('pengguna.index'))
            ->assertSessionHas('status');

        $user->refresh();

        $this->assertSame('Bidang Sosial', $user->name);
        $this->assertSame('089876543210', $user->phone);
        $this->assertSame($oldPassword, $user->password);
    }

    public function test_a_user_can_be_updated_with_a_new_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put("/pengguna/{$user->id}", $this->payload([
                'email' => $user->email,
                'password' => 'password-baru',
                'password_confirmation' => 'password-baru',
            ]))
            ->assertRedirect(route('pengguna.index'));

        $this->assertTrue(Hash::check('password-baru', $user->fresh()->password));
    }

    public function test_a_user_can_be_deleted(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete("/pengguna/{$user->id}")
            ->assertRedirect(route('pengguna.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_a_user_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete("/pengguna/{$admin->id}")
            ->assertRedirect(route('pengguna.index'))
            ->assertSessionHasErrors('pengguna');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_search_filters_by_name_email_and_phone(): void
    {
        User::factory()->create([
            'name' => 'Bidang Umum',
            'kode_bidang' => '408.37.01',
            'email' => 'umum@instansi.go.id',
            'phone' => '0811111111',
        ]);

        User::factory()->create([
            'name' => 'Bidang Sosial',
            'kode_bidang' => '408.37.09',
            'email' => 'sosial@instansi.go.id',
            'phone' => '0822222222',
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/pengguna?q=sosial')
            ->assertOk()->assertSee('Bidang Sosial')->assertDontSee('Bidang Umum');

        $this->actingAs($admin)->get('/pengguna?q=0811111111')
            ->assertOk()->assertSee('Bidang Umum')->assertDontSee('Bidang Sosial');

        $this->actingAs($admin)->get('/pengguna?q=408.37.09')
            ->assertOk()->assertSee('Bidang Sosial')->assertDontSee('Bidang Umum');
    }

    public function test_pagination_slices_the_dataset(): void
    {
        User::factory()->count(12)->create();

        $admin = User::factory()->admin()->create();

        $first = $this->actingAs($admin)->get('/pengguna');
        $first->assertOk()->assertSee('1–10');
        $this->assertSame(10, substr_count($first->getContent(), 'data-row'));

        $second = $this->actingAs($admin)->get('/pengguna?page=2');
        $second->assertOk();
        $this->assertSame(3, substr_count($second->getContent(), 'data-row'));
    }

    public function test_ajax_requests_only_return_the_table_partial(): void
    {
        User::factory()->create(['name' => 'Bidang Umum']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/pengguna', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('Bidang Umum')
            ->assertDontSee('<html', escape: false)
            ->assertDontSee('Tambah Pengguna');
    }
}
