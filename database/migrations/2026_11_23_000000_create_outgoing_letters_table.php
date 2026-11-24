<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel surat keluar. Setiap tahun memiliki kode bulanan (00-59) dan
     * subkode 01-99. Kombinasi tahun + kode + subkode tidak boleh dipakai dua kali.
     * Non-destruktif: hanya membuat tabel baru.
     */
    public function up(): void
    {
        Schema::create('outgoing_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('code', 2);
            $table->string('sub_code', 2);
            $table->string('letter_number', 100);
            $table->string('bidang', 150);
            $table->timestamps();

            $table->unique(['year', 'code', 'sub_code']);
            $table->index(['year', 'month']);
            $table->index('bidang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outgoing_letters');
    }
};
