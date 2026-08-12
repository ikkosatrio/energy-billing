<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permission berbasis slug (mis. `invoice.generate`), menggantikan bitmask
 * 14 digit pada tabel `mpermit` milik sistem lama. Slug inilah yang dipakai
 * di config/menu.php dan pada pengecekan @can di Blade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // Modul pemilik permission, untuk mengelompokkan checkbox di
            // halaman Role & Hak Akses.
            $table->string('group')->default('general')->index();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
