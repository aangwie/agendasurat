<?php

namespace Database\Factories;

use App\Models\IncomingLetter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomingLetter>
 */
class IncomingLetterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agenda_number' => (string) fake()->unique()->numberBetween(1, 9999),
            'received_date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'letter_date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'letter_number' => fake()->bothify('###/####/DISKOMINFO/'.now()->year),
            'sender' => fake()->randomElement([
                'Dinas Pendidikan Provinsi',
                'Bagian Umum Sekretariat Daerah',
                'Kecamatan Sukamaju',
                'Inspektorat Daerah',
                'Arsip Daerah',
                'Badan Perencanaan Pembangunan Daerah',
            ]),
            'subject' => fake()->randomElement([
                'Undangan Rapat Koordinasi Tata Usaha',
                'Permohonan Data Rekapitulasi Surat',
                'Pemberitahuan Perubahan Jadwal Kegiatan',
                'Laporan Bulanan Arsip Surat Dinas',
                'Balasan Undangan Forum Koordinasi',
            ]),
            'sifat' => fake()->randomElements(['segera', 'terbuka', 'rahasia'], fake()->numberBetween(0, 2)),
        ];
    }
}
