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
     * Menambahkan perihal dan tanggal surat keluar. Kolom ditambahkan nullable
     * lebih dulu, diisi untuk data lama, lalu dijadikan wajib agar aman pada
     * database yang sudah berisi data. Non-destruktif: tidak menghapus data.
     */
    public function up(): void
    {
        Schema::table('outgoing_letters', function (Blueprint $table) {
            $table->string('subject', 255)->nullable()->after('letter_number');
            $table->date('letter_date')->nullable()->after('subject');
        });

        DB::table('outgoing_letters')
            ->whereNull('subject')
            ->update(['subject' => DB::raw('letter_number')]);

        DB::table('outgoing_letters')
            ->whereNull('letter_date')
            ->update(['letter_date' => DB::raw('DATE(created_at)')]);

        Schema::table('outgoing_letters', function (Blueprint $table) {
            $table->string('subject', 255)->nullable(false)->change();
            $table->date('letter_date')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outgoing_letters', function (Blueprint $table) {
            $table->dropColumn(['subject', 'letter_date']);
        });
    }
};
