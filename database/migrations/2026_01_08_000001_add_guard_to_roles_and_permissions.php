<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memisahkan role & permission staf dari role & permission portal pelanggan.
 *
 * Tabelnya sengaja tidak diduplikasi: satu kosakata otorisasi untuk seluruh
 * aplikasi berarti halaman "Role & Hak Akses" yang sudah ada tetap dipakai,
 * dan `Gate::before` tetap satu jalur. Kolom `guard` inilah yang mencegah
 * permission staf (mis. `user.delete`) muncul sebagai pilihan saat menyusun
 * role pelanggan, dan sebaliknya.
 *
 * Default 'web' supaya seluruh baris yang sudah ada tetap terbaca sebagai
 * milik staf tanpa perlu disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('guard', 20)->default('web')->after('slug')->index();
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->string('guard', 20)->default('web')->after('slug')->index();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex(['guard']);
            $table->dropColumn('guard');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex(['guard']);
            $table->dropColumn('guard');
        });
    }
};
