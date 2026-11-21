<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Klasifikasi surat mengikuti lampiran Permendagri Nomor 83 Tahun 2022
     * tentang Kode Klasifikasi Arsip (bagian Kemendagri dan Pemerintah Daerah).
     */
    public function up(): void
    {
        Schema::create('letter_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20);
            $table->string('code', 32);
            $table->text('name');
            $table->foreignId('parent_id')->nullable()->constrained('letter_classifications')->nullOnDelete();
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['scope', 'code']);
            $table->index(['scope', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_classifications');
    }
};
