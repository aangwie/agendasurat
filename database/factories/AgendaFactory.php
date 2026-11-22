<?php

namespace Database\Factories;

use App\Models\Agenda;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agenda>
 */
class AgendaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'place' => fake()->randomElement([
                'Ruang Rapat Utama',
                'Aula Dinas Pendidikan',
                'Ruang Kepala Bidang',
                'Gedung Karya Dharma',
            ]),
            'person_in_charge' => fake()->name(),
            'participants' => fake()->randomElement([
                'Seluruh Kepala Bidang dan staf Tata Usaha',
                'Kepala Sekolah SD se-Kecamatan',
                'Pengawas dan Koordinator Wilayah',
                'Panitia Kegiatan',
            ]),
        ];
    }
}
