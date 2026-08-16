<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis sambungan meter: 1 phase atau 3 phase.
 *
 * Bukan sekadar keterangan — meter 1 phase memang hanya punya satu jalur
 * tegangan dan arus, sehingga kolom S dan T disembunyikan di monitoring dan
 * laporan. Tanpa ini, tanda strip pada kolom S/T ambigu: tidak jelas apakah
 * datanya tidak terkirim atau memang tidak ada.
 *
 * Default 3 phase, mengikuti sambungan gudang pada umumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('power_meters', function (Blueprint $table) {
            $table->enum('phase', ['1', '3'])->default('3')->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('power_meters', function (Blueprint $table) {
            $table->dropColumn('phase');
        });
    }
};
