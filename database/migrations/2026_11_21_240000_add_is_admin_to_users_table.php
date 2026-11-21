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
     * Menambah penanda admin. Non-destruktif: hanya menambah kolom, lalu akun
     * terlama (akun administrator awal) ditandai sebagai admin agar akses
     * menu Profil Instansi, Pengguna, dan Pengaturan tidak hilang.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('phone');
        });

        $oldestId = DB::table('users')->min('id');

        if ($oldestId !== null) {
            DB::table('users')->where('id', $oldestId)->update(['is_admin' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
