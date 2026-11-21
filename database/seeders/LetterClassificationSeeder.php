<?php

namespace Database\Seeders;

use App\Models\LetterClassification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LetterClassificationSeeder extends Seeder
{
    /**
     * Seed the letter classification hierarchy from the Permendagri 83/2022
     * attachment (bagian Kemendagri dan Pemerintah Daerah).
     */
    public function run(): void
    {
        $path = database_path('seeders/data/klasifikasi-arsip.json');

        if (! is_file($path)) {
            $this->command?->warn('Berkas klasifikasi-arsip.json tidak ditemukan, seeder dilewati.');

            return;
        }

        $rows = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($rows) {
            $now = now();
            $ids = [];

            foreach ([1, 2, 3, 4] as $level) {
                $batch = [];

                foreach ($rows as $row) {
                    if ($row['level'] !== $level) {
                        continue;
                    }

                    $parentId = $row['parent'] !== null
                        ? ($ids[$row['scope'].'|'.$row['parent']] ?? null)
                        : null;

                    $batch[] = [
                        'scope' => $row['scope'],
                        'code' => $row['code'],
                        'name' => $row['name'],
                        'parent_id' => $parentId,
                        'level' => $level,
                        'sort_order' => $row['order'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // Non-destruktif: perbarui baris yang sudah ada, tambahkan yang baru.
                // Tidak ada data yang dihapus.
                foreach (array_chunk($batch, 500) as $chunk) {
                    DB::table('letter_classifications')->upsert(
                        $chunk,
                        ['scope', 'code'],
                        ['name', 'parent_id', 'level', 'sort_order', 'updated_at'],
                    );
                }

                // Petakan ulang kode -> id agar level berikutnya dapat menemukan induknya.
                DB::table('letter_classifications')
                    ->select(['id', 'scope', 'code'])
                    ->orderBy('id')
                    ->get()
                    ->each(function ($record) use (&$ids) {
                        $ids[$record->scope.'|'.$record->code] = $record->id;
                    });
            }
        });

        $this->command?->info('Klasifikasi arsip: '.LetterClassification::query()->count().' baris dimuat.');
    }
}
