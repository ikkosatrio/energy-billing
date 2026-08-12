<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris = satu bulan tagihan. Invoice selalu menempel ke sebuah periode,
 * sehingga rekap dan penutupan buku dilakukan per periode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_periods', function (Blueprint $table) {
            $table->id();
            // Format YYYY-MM, mis. "2026-08".
            $table->string('code', 7)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('cut_off_date');
            // open      — periode berjalan, belum digenerate
            // generated — invoice sudah dibuat, masih boleh diulang
            // closed    — dikunci, invoice tidak boleh diubah lagi
            $table->enum('status', ['open', 'generated', 'closed'])->default('open')->index();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_periods');
    }
};
