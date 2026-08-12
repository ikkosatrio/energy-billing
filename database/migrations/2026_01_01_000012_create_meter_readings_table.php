<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembacaan mentah power meter. Gateway push ke endpoint API setiap menit,
 * jadi tabel ini tumbuh paling cepat: ±525.000 baris per meter per tahun.
 *
 * `stand_lwbp` dan `stand_wbp` adalah angka KUMULATIF meter (bukan selisih).
 * Tagihan dihitung dari stand awal & akhir periode, sehingga data yang bolong
 * di tengah periode tidak merusak perhitungan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('power_meter_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');

            // Stand kumulatif — dasar perhitungan tagihan.
            $table->decimal('stand_lwbp', 15, 2)->default(0);
            $table->decimal('stand_wbp', 15, 2)->default(0);

            // Besaran sesaat untuk halaman real-time; boleh kosong bila meter
            // tidak mengirimnya.
            $table->decimal('active_power_kw', 12, 2)->nullable();
            $table->decimal('voltage_r', 8, 2)->nullable();
            $table->decimal('voltage_s', 8, 2)->nullable();
            $table->decimal('voltage_t', 8, 2)->nullable();
            $table->decimal('current_r', 10, 2)->nullable();
            $table->decimal('current_s', 10, 2)->nullable();
            $table->decimal('current_t', 10, 2)->nullable();
            $table->decimal('power_factor', 5, 3)->nullable();
            $table->decimal('frequency', 6, 2)->nullable();

            $table->enum('source', ['api', 'manual'])->default('api');
            // Payload asli dari gateway, untuk menelusuri anomali.
            $table->json('raw_payload')->nullable();
            $table->timestamp('created_at')->nullable();

            // Menolak push ganda untuk timestamp yang sama, sekaligus menjadi
            // index utama query rentang waktu per meter.
            $table->unique(['power_meter_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_readings');
    }
};
