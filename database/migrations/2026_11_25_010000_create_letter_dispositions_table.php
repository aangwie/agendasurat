<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('letter_dispositions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('incoming_letter_id')
                ->constrained('incoming_letters')
                ->cascadeOnDelete();

            $table->string('kode_bidang', 30);
            $table->string('bidang_name', 150);

            // Akun penerima disposisi (boleh kosong bila akun dihapus).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('phone', 30)->nullable();

            // sent | failed | skipped
            $table->string('status', 20);
            $table->text('message')->nullable();

            $table->foreignId('disposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['incoming_letter_id', 'kode_bidang']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_dispositions');
    }
};
