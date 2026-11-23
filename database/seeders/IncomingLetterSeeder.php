<?php

namespace Database\Seeders;

use App\Models\IncomingLetter;
use Illuminate\Database\Seeder;

class IncomingLetterSeeder extends Seeder
{
    /**
     * Seed contoh surat masuk untuk mencoba datatable (opsional).
     *
     * Jalankan manual: php artisan db:seed --class=IncomingLetterSeeder
     * Seeder ini tidak dipanggil oleh DatabaseSeeder.
     */
    public function run(): void
    {
        $sifatOptions = array_keys(IncomingLetter::SIFAT_OPTIONS);
        $created = 0;

        // Non-destruktif: hanya menambah data contoh yang belum ada.
        // Data surat yang sudah tersimpan tidak pernah dihapus/ditimpa.
        foreach (range(1, 28) as $i) {
            $agendaNumber = (string) $i;

            if (IncomingLetter::query()->where('agenda_number', $agendaNumber)->exists()) {
                continue;
            }

            IncomingLetter::factory()->create([
                'agenda_number' => $agendaNumber,
                'received_date' => now()->subDays(28 - $i)->format('Y-m-d'),
                'sifat' => fake()->randomElements($sifatOptions, fake()->numberBetween(0, 2)),
            ]);

            $created++;
        }

        $this->command?->info('Contoh surat masuk: '.$created.' baris baru dibuat (total '.IncomingLetter::query()->count().' baris).');
    }
}
