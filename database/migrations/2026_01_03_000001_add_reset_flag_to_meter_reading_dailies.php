<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menandai hari yang mengandung reset meter.
 *
 * Sebelumnya agregat harian hanya menyimpan angka pemakaian tanpa jejak bahwa
 * meter sempat di-reset — laporan yang membacanya jadi tidak bisa membedakan
 * "hari ini pemakaiannya memang rendah" dari "angkanya tidak bisa dipercaya".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meter_reading_dailies', function (Blueprint $table) {
            $table->unsignedSmallInteger('reset_count')->default(0)->after('reading_count');
        });
    }

    public function down(): void
    {
        Schema::table('meter_reading_dailies', function (Blueprint $table) {
            $table->dropColumn('reset_count');
        });
    }
};
