<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agregasi harian dari `meter_readings`, diisi oleh scheduled job.
 *
 * Tanpa tabel ini, chart bulanan dan tahunan harus memindai jutaan baris
 * pembacaan mentah. Kolom stand awal/akhir disimpan apa adanya supaya
 * pemakaian harian bisa dihitung ulang dan diaudit kapan saja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_reading_dailies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('power_meter_id')->constrained()->cascadeOnDelete();
            $table->date('date');

            $table->decimal('stand_lwbp_start', 15, 2)->default(0);
            $table->decimal('stand_lwbp_end', 15, 2)->default(0);
            $table->decimal('stand_wbp_start', 15, 2)->default(0);
            $table->decimal('stand_wbp_end', 15, 2)->default(0);

            // Selisih stand akhir dikurangi stand awal hari itu.
            $table->decimal('kwh_lwbp', 15, 2)->default(0);
            $table->decimal('kwh_wbp', 15, 2)->default(0);

            $table->decimal('peak_kw', 12, 2)->nullable();
            $table->timestamp('peak_at')->nullable();
            // Jumlah pembacaan yang masuk hari itu — dipakai mendeteksi hari
            // dengan data tidak lengkap (idealnya 1440 pada interval 1 menit).
            $table->unsignedSmallInteger('reading_count')->default(0);
            $table->timestamps();

            $table->unique(['power_meter_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_reading_dailies');
    }
};
