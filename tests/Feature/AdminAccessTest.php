<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function adminRoutes(): array
    {
        return [
            'profil-instansi.edit',
            'pengguna.index',
            'pengaturan.index',
        ];
    }

    public function test_non_admin_users_cannot_access_admin_pages(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        foreach ($this->adminRoutes() as $route) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertForbidden();
        }
    }

    public function test_non_admin_users_cannot_modify_admin_resources(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $target = User::factory()->create();

        $this->actingAs($user)
            ->post(route('pengguna.store'), [])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('pengguna.update', $target), [])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('pengguna.destroy', $target))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('profil-instansi.update'), [])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_admin_users_can_access_admin_pages(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ($this->adminRoutes() as $route) {
            $this->actingAs($admin)
                ->get(route($route))
                ->assertOk();
        }
    }

    public function test_the_system_menus_are_hidden_for_non_admin_users(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $content = $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        // Menu umum tetap tampil.
        $this->assertStringContainsString('data-label="Surat Masuk"', $content);
        $this->assertStringContainsString('data-label="Klasifikasi"', $content);

        // Menu sistem disembunyikan (sidebar & dropdown profil).
        $this->assertStringNotContainsString('data-label="Profil Instansi"', $content);
        $this->assertStringNotContainsString('data-label="Pengguna"', $content);
        $this->assertStringNotContainsString('data-label="Pengaturan"', $content);
        $this->assertStringNotContainsString(route('pengaturan.index'), $content);
        $this->assertStringNotContainsString(route('pengguna.index'), $content);
        $this->assertStringNotContainsString(route('profil-instansi.edit'), $content);
    }

    public function test_the_system_menus_are_visible_for_admin_users(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-label="Profil Instansi"', escape: false)
            ->assertSee('data-label="Pengguna"', escape: false)
            ->assertSee('data-label="Pengaturan"', escape: false);
    }

    public function test_admin_can_grant_admin_rights_to_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)
            ->put(route('pengguna.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'phone' => $target->phone,
                'password' => '',
                'password_confirmation' => '',
                'is_admin' => '1',
            ])
            ->assertRedirect(route('pengguna.index'));

        $this->assertTrue($target->fresh()->isAdmin());

        $this->actingAs($target->fresh())
            ->get(route('pengguna.index'))
            ->assertOk();
    }

    public function test_admin_cannot_revoke_their_own_admin_rights(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('pengguna.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'phone' => $admin->phone,
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('pengguna.index'))
            ->assertSessionHasErrors('is_admin');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_an_admin_can_delete_another_admin_but_not_a_non_admin_actor(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $regular = User::factory()->create(['is_admin' => false]);

        // Administrator boleh menghapus administrator lain.
        $this->actingAs($admin)
            ->delete(route('pengguna.destroy', $otherAdmin))
            ->assertRedirect(route('pengguna.index'));

        $this->assertDatabaseMissing('users', ['id' => $otherAdmin->id]);

        // Pengguna biasa tetap tidak boleh menghapus siapa pun.
        $this->actingAs($regular)
            ->delete(route('pengguna.destroy', $admin))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
