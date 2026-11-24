<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel surat tugas. Setiap tahun memiliki kode bulanan (00-36) dan
     * subkode 01-99. Kombinasi tahun + kode + subkode tidak boleh dipakai dua
     * kali, sehingga nomor yang sudah terbit tidak dapat dipakai kembali.
     * Non-destruktif: hanya membuat tabel baru.
     */
    public function up(): void
    {
        Schema::create('task_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('code', 2);
            $table->string('sub_code', 2);
            $table->string('letter_prefix', 50);
            $table->string('kode_bidang', 30);
            $table->string('letter_number', 100);
            $table->string('subject', 255);
            $table->date('letter_date');
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
        Schema::dropIfExists('task_letters');
    }
};
