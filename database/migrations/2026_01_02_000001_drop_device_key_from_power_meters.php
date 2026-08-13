<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membuang rahasia per perangkat.
 *
 * Gateway kini mengidentifikasi meter lewat `meter_id` biasa — nilainya
 * tampil di halaman Power Meter dan bisa dilihat kapan saja, tidak seperti
 * device_key yang hanya muncul sekali saat dibuat. Autentikasinya dipindah ke
 * satu API token global di setting sistem (key `api_token`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('power_meters', function (Blueprint $table) {
            $table->dropUnique('power_meters_device_key_unique');
            $table->dropColumn('device_key');
        });
    }

    public function down(): void
    {
        Schema::table('power_meters', function (Blueprint $table) {
            // Kolom lama tidak bisa dipulihkan isinya; baris yang ada diberi
            // key acak agar constraint unique tetap terpenuhi.
            $table->string('device_key', 64)->nullable()->unique()->after('location');
        });
    }
};
