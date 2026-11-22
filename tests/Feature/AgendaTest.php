<?php

namespace Tests\Feature;

use App\Models\Agenda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'date' => now()->addDay()->format('Y-m-d'),
            'place' => 'Ruang Rapat Utama',
            'person_in_charge' => 'Budi Santoso',
            'participants' => 'Seluruh Kepala Bidang dan staf Tata Usaha',
        ], $overrides);
    }

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $this->get('/agenda')->assertRedirect(route('login'));
        $this->post('/agenda', $this->payload())->assertRedirect(route('login'));
    }

    public function test_the_agenda_table_is_rendered_with_actions(): void
    {
        Agenda::factory()->create([
            'date' => '2026-08-12',
            'place' => 'Aula Dinas Pendidikan',
            'person_in_charge' => 'Siti Aminah',
            'participants' => 'Kepala Sekolah SD',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/agenda')
            ->assertOk()
            ->assertSee('Tanggal')
            ->assertSee('Tempat')
            ->assertSee('Penanggung Jawab')
            ->assertSee('Peserta')
            ->assertSee('12 Agustus 2026')
            ->assertSee('Aula Dinas Pendidikan')
            ->assertSee('Siti Aminah')
            ->assertSee('data-table-search', escape: false)
            ->assertSee('10 data')
            ->assertSee('data-agenda-edit', escape: false)
            ->assertSee('data-confirm-delete', escape: false)
            ->assertSee('data-agenda-modal', escape: false);
    }

    public function test_an_agenda_can_be_created(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/agenda', $this->payload())
            ->assertRedirect(route('agenda.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('agendas', [
            'place' => 'Ruang Rapat Utama',
            'person_in_charge' => 'Budi Santoso',
            'participants' => 'Seluruh Kepala Bidang dan staf Tata Usaha',
        ]);
    }

    public function test_creating_requires_all_fields(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/agenda', [])
            ->assertSessionHasErrors(['date', 'place', 'person_in_charge', 'participants'])
            ->assertSessionHas('open_form', 'create');
    }

    public function test_an_agenda_can_be_updated(): void
    {
        $agenda = Agenda::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put("/agenda/{$agenda->id}", $this->payload([
                'place' => 'Gedung Karya Dharma',
                'participants' => 'Panitia Kegiatan',
            ]))
            ->assertRedirect(route('agenda.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('agendas', [
            'id' => $agenda->id,
            'place' => 'Gedung Karya Dharma',
            'participants' => 'Panitia Kegiatan',
        ]);
    }

    public function test_an_agenda_can_be_deleted(): void
    {
        $agenda = Agenda::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete("/agenda/{$agenda->id}")
            ->assertRedirect(route('agenda.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('agendas', ['id' => $agenda->id]);
    }

    public function test_search_filters_by_place_person_and_participants(): void
    {
        Agenda::factory()->create(['place' => 'Aula Dinas', 'person_in_charge' => 'Budi', 'participants' => 'Kepala Sekolah']);
        Agenda::factory()->create(['place' => 'Gedung Karya', 'person_in_charge' => 'Siti', 'participants' => 'Panitia']);

        $user = User::factory()->create();

        $this->actingAs($user)->get('/agenda?q=karya')
            ->assertOk()->assertSee('Gedung Karya')->assertDontSee('Aula Dinas');

        $this->actingAs($user)->get('/agenda?q=siti')
            ->assertOk()->assertSee('Siti')->assertDontSee('Budi');

        $this->actingAs($user)->get('/agenda?q=panitia')
            ->assertOk()->assertSee('Panitia')->assertDontSee('Kepala Sekolah');
    }

    public function test_pagination_slices_the_dataset(): void
    {
        Agenda::factory()->count(12)->create();

        $user = User::factory()->create();

        $first = $this->actingAs($user)->get('/agenda');
        $first->assertOk()->assertSee('1–10');
        $this->assertSame(10, substr_count($first->getContent(), 'data-row'));

        $second = $this->actingAs($user)->get('/agenda?page=2');
        $second->assertOk();
        $this->assertSame(2, substr_count($second->getContent(), 'data-row'));
    }

    public function test_ajax_requests_only_return_the_table_partial(): void
    {
        Agenda::factory()->create(['place' => 'Aula Dinas']);

        $this->actingAs(User::factory()->create())
            ->get('/agenda', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('Aula Dinas')
            ->assertDontSee('<html', escape: false)
            ->assertDontSee('Tambah Agenda');
    }
}
