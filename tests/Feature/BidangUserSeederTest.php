<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\BidangUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BidangUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_one_account_per_bidang_with_the_expected_emails(): void
    {
        $this->seed(BidangUserSeeder::class);

        $expected = [
            'umum.01@dindikpct.go.id',
            'sekretariat.02@dindikpct.go.id',
            'sd.03@dindikpct.go.id',
            'smp.04@dindikpct.go.id',
            'paud.05@dindikpct.go.id',
            'ptk.06@dindikpct.go.id',
        ];

        $this->assertSame(6, User::query()->count());

        foreach ($expected as $email) {
            $this->assertDatabaseHas('users', ['email' => $email, 'is_admin' => false]);
        }
    }

    public function test_all_accounts_use_the_same_password(): void
    {
        $this->seed(BidangUserSeeder::class);

        foreach (User::query()->get() as $user) {
            $this->assertTrue(
                Hash::check('@Dewisartika17', $user->password),
                "Password akun {$user->email} tidak sesuai.",
            );
        }
    }

    public function test_the_suffix_comes_from_the_last_two_digits_of_the_register_code(): void
    {
        $this->seed(BidangUserSeeder::class);

        foreach (BidangUserSeeder::BIDANG as $bidang) {
            $suffix = substr($bidang['code'], -2);

            $this->assertDatabaseHas('users', [
                'email' => $bidang['slug'].'.'.$suffix.'@dindikpct.go.id',
                'name' => $bidang['name'],
            ]);
        }
    }

    public function test_it_is_non_destructive_when_run_twice(): void
    {
        $other = User::factory()->create(['email' => 'lain@dindikpct.go.id']);

        $this->seed(BidangUserSeeder::class);
        $this->seed(BidangUserSeeder::class);

        $this->assertSame(7, User::query()->count());
        $this->assertDatabaseHas('users', ['id' => $other->id, 'email' => 'lain@dindikpct.go.id']);
    }

    public function test_a_bidang_account_can_login(): void
    {
        $this->seed(BidangUserSeeder::class);

        $response = $this->post('/login', [
            'email' => 'ptk.06@dindikpct.go.id',
            'password' => '@Dewisartika17',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
    }

    public function test_a_bidang_account_cannot_access_admin_menus(): void
    {
        $this->seed(BidangUserSeeder::class);

        $user = User::query()->where('email', 'ptk.06@dindikpct.go.id')->firstOrFail();

        $this->actingAs($user)
            ->get('/pengguna')
            ->assertForbidden();
    }
}
