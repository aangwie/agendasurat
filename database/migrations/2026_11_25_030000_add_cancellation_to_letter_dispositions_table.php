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
        Schema::table('letter_dispositions', function (Blueprint $table) {
            // Pembatalan disposisi yang salah kirim.
            $table->timestamp('cancelled_at')->nullable()->after('sent_at');
            $table->string('cancel_reason', 255)->nullable()->after('cancelled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('letter_dispositions', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancel_reason']);
        });
    }
};
