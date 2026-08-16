<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kondisi terakhir tiap perangkat — satu baris per meter, ditimpa terus,
 * tanpa riwayat.
 *
 * Isinya sama seperti payload pembacaan, ditambah informasi perangkat (kekuatan
 * sinyal, IP, MAC, versi firmware). Bedanya dengan `meter_readings`: yang itu
 * dicatat sebagai riwayat untuk dasar tagihan, yang ini hanya menjawab
 * "sekarang kondisinya bagaimana".
 *
 * Dipisah dari `power_meters` karena dua tabel ini punya sifat berbeda:
 * `power_meters` adalah konfigurasi yang jarang berubah dan setiap
 * perubahannya tercatat di log aktivitas, sedangkan baris di sini ditimpa
 * setiap menit oleh gateway. Menggabungkannya membuat setiap kiriman status
 * terbaca seperti perubahan konfigurasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('power_meter_statuses', function (Blueprint $table) {
            // Satu meter satu baris; id meter sekaligus menjadi primary key.
            $table->foreignId('power_meter_id')->primary()->constrained()->cascadeOnDelete();

            // --- Informasi perangkat ---
            $table->smallInteger('signal_dbm')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('mac_address', 17)->nullable();
            $table->string('firmware_version', 50)->nullable();

            // --- Kondisi kelistrikan terakhir ---
            $table->decimal('stand_lwbp', 15, 2)->nullable();
            $table->decimal('stand_wbp', 15, 2)->nullable();
            $table->decimal('active_power_kw', 12, 2)->nullable();
            $table->decimal('voltage_r', 8, 2)->nullable();
            $table->decimal('voltage_s', 8, 2)->nullable();
            $table->decimal('voltage_t', 8, 2)->nullable();
            $table->decimal('current_r', 10, 2)->nullable();
            $table->decimal('current_s', 10, 2)->nullable();
            $table->decimal('current_t', 10, 2)->nullable();
            $table->decimal('power_factor', 5, 3)->nullable();
            $table->decimal('frequency', 6, 2)->nullable();

            // Waktu menurut perangkat, dan waktu kiriman itu diterima server.
            $table->timestamp('read_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('power_meter_statuses');
    }
};
