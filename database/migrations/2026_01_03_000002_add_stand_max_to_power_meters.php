<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Angka maksimum register meter sebelum berputar kembali ke nol.
 *
 * Tanpa nilai ini, aplikasi tidak bisa membedakan meter yang di-reset teknisi
 * (mulai lagi dari nol) dengan register yang penuh lalu berputar. Keduanya
 * sama-sama terlihat sebagai stand yang mundur, padahal pemakaiannya berbeda:
 * pada rollover masih ada sisa pemakaian antara pembacaan terakhir dan titik
 * putar yang seharusnya ikut ditagih.
 *
 * Diisi apa adanya dari spesifikasi meter, mis. 999999.99 untuk register 6
 * digit. Dibiarkan kosong bila tidak diketahui — stand mundur akan dianggap
 * reset biasa, sama seperti perilaku sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('power_meters', function (Blueprint $table) {
            $table->decimal('stand_max', 15, 2)->nullable()->after('multiplier');
        });
    }

    public function down(): void
    {
        Schema::table('power_meters', function (Blueprint $table) {
            $table->dropColumn('stand_max');
        });
    }
};
