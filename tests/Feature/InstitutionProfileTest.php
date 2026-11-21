<?php

namespace Tests\Feature;

use App\Models\InstitutionProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstitutionProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'government_name' => 'Pemerintah Kota Bandung',
            'institution_name' => 'Dinas Komunikasi dan Informatika',
            'address' => 'Jl. Wastukancana No. 2, Bandung 40117',
            'website' => 'diskominfo.bandung.go.id',
            'email' => 'diskominfo@bandung.go.id',
        ], $overrides);
    }

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $this->get('/profil-instansi')->assertRedirect(route('login'));
        $this->put('/profil-instansi', $this->payload())->assertRedirect(route('login'));
    }

    public function test_the_update_form_is_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profil-instansi')
            ->assertOk()
            ->assertSee('Profil Instansi')
            ->assertSee('Nama Pemerintahan')
            ->assertSee('Nama Instansi')
            ->assertSee('Alamat Instansi')
            ->assertSee('Alamat Website')
            ->assertSee('Alamat Email')
            ->assertSee('Logo Pemerintah')
            ->assertSee('Upload Logo');
    }

    public function test_the_institution_profile_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/profil-instansi', $this->payload(['website' => 'https://diskominfo.bandung.go.id']))
            ->assertRedirect(route('profil-instansi.edit'))
            ->assertSessionHas('status', 'Profil instansi berhasil diperbarui.');

        $profile = InstitutionProfile::current();

        $this->assertSame('Pemerintah Kota Bandung', $profile->government_name);
        $this->assertSame('Dinas Komunikasi dan Informatika', $profile->institution_name);
        $this->assertSame('Jl. Wastukancana No. 2, Bandung 40117', $profile->address);
        $this->assertSame('diskominfo.bandung.go.id', $profile->website);
        $this->assertSame('https://diskominfo.bandung.go.id', $profile->website_href);
        $this->assertSame('diskominfo@bandung.go.id', $profile->email);
        $this->assertSame(1, InstitutionProfile::count());
    }

    public function test_invalid_input_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/profil-instansi', [
                'government_name' => '',
                'institution_name' => '',
                'address' => '',
                'website' => 'bukan website',
                'email' => 'bukan-email',
            ])
            ->assertSessionHasErrors(['government_name', 'institution_name', 'address', 'website', 'email']);
    }

    public function test_the_government_logo_can_be_uploaded_and_replaced(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)->put('/profil-instansi', [
            ...$this->payload(),
            'logo' => UploadedFile::fake()->image('logo-lama.png', 300, 300),
        ]);

        $firstLogo = InstitutionProfile::current()->logo_path;

        $this->assertNotNull($firstLogo);
        Storage::disk('public')->assertExists($firstLogo);

        $this->actingAs($user)->put('/profil-instansi', [
            ...$this->payload(),
            'logo' => UploadedFile::fake()->image('logo-baru.png', 300, 300),
        ]);

        $secondLogo = InstitutionProfile::current()->logo_path;

        $this->assertNotSame($firstLogo, $secondLogo);
        Storage::disk('public')->assertMissing($firstLogo);
        Storage::disk('public')->assertExists($secondLogo);
    }

    public function test_the_logo_must_be_an_allowed_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/profil-instansi', [
                ...$this->payload(),
                'logo' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_the_sidebar_links_to_the_institution_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Profil Instansi')
            ->assertSee(route('profil-instansi.edit'), escape: false);
    }
}
