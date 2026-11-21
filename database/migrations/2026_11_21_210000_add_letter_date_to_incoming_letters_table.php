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
     * Menambahkan tanggal surat (tanggal pada surat asli). Kolom ditambahkan
     * nullable lebih dulu, diisi dari tanggal diterima untuk data lama, lalu
     * dijadikan wajib agar aman pada database yang sudah berisi data.
     */
    public function up(): void
    {
        Schema::table('incoming_letters', function (Blueprint $table) {
            $table->date('letter_date')->nullable()->after('received_date');
        });

        DB::table('incoming_letters')
            ->whereNull('letter_date')
            ->update(['letter_date' => DB::raw('received_date')]);

        Schema::table('incoming_letters', function (Blueprint $table) {
            $table->date('letter_date')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incoming_letters', function (Blueprint $table) {
            $table->dropColumn('letter_date');
        });
    }
};
