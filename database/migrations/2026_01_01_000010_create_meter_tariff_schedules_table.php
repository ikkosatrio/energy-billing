<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal WBP/LWBP per meter — satu baris = satu periode waktu dalam sehari.
 *
 * Karena meter sudah mengirim register LWBP dan WBP secara terpisah, jadwal
 * ini TIDAK dipakai untuk membagi kWh. Fungsinya sebagai referensi konfigurasi
 * yang tersimpan di aplikasi: menampilkan tarif aktif saat ini, mewarnai chart,
 * dan menjadi acuan saat mencocokkan dengan setelan di perangkat.
 *
 * Aturan validasi (lihat halaman Jadwal WBP/LWBP):
 *   - maksimal 12 periode per meter
 *   - start_time & end_time kelipatan 15 menit
 *   - tidak ada start_time duplikat
 *   - total durasi seluruh periode tepat 24 jam
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_tariff_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('power_meter_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->time('start_time');
            // 24:00 disimpan sebagai 00:00 pada baris terakhir; urutan periode
            // ditentukan oleh kolom sequence, bukan perbandingan jam.
            $table->time('end_time');
            $table->enum('tariff_type', ['LWBP', 'WBP']);
            $table->timestamps();

            $table->unique(['power_meter_id', 'sequence']);
            $table->unique(['power_meter_id', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_tariff_schedules');
    }
};
