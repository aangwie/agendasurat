<?php

use Database\Seeders\BidangUserSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan kode bidang (kode register, mis. 408.37.06) pada tabel users.
     * Non-destruktif: hanya menambah kolom nullable dan mengisi kode untuk akun
     * bidang yang sudah ada; data pengguna tidak diubah/dihapus.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('kode_bidang', 30)->nullable()->after('name');
        });

        // Isi kode bidang untuk akun bidang yang sudah ada (berdasarkan email
        // yang dipakai BidangUserSeeder), hanya bila kolomnya masih kosong.
        foreach (BidangUserSeeder::BIDANG as $bidang) {
            $suffix = Str::afterLast($bidang['code'], '.');
            $email = $bidang['slug'].'.'.$suffix.'@'.BidangUserSeeder::DOMAIN;

            DB::table('users')
                ->where('email', $email)
                ->whereNull('kode_bidang')
                ->update(['kode_bidang' => $bidang['code']]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('kode_bidang');
        });
    }
};
