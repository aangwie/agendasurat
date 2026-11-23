<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambah komponen nomor surat keluar: kode depan (prefix) dan kode bidang.
     * Nomor lengkap disusun sebagai: kode depan / kode+subkode / kode bidang / tahun.
     * Non-destruktif: hanya menambah kolom nullable dan mengisi kode depan/kode
     * bidang dari nomor lama yang sudah mengikuti format tersebut.
     */
    public function up(): void
    {
        Schema::table('outgoing_letters', function (Blueprint $table) {
            $table->string('letter_prefix', 50)->nullable()->after('sub_code');
            $table->string('kode_bidang', 30)->nullable()->after('letter_prefix');
        });

        // Isi kode depan & kode bidang dari nomor lama yang sudah berbentuk
        // prefix/kode+subkode/kode bidang/tahun (4 bagian dipisah garis miring).
        DB::table('outgoing_letters')
            ->select(['id', 'letter_number'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $parts = explode('/', (string) $row->letter_number);

                    if (count($parts) !== 4) {
                        continue;
                    }

                    DB::table('outgoing_letters')
                        ->where('id', $row->id)
                        ->update([
                            'letter_prefix' => $parts[0],
                            'kode_bidang' => $parts[2],
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outgoing_letters', function (Blueprint $table) {
            $table->dropColumn(['letter_prefix', 'kode_bidang']);
        });
    }
};
