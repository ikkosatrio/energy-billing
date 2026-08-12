<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setting sistem: identitas aplikasi, aturan billing, integrasi IoT.
 * Menggantikan tabel `applications`/`company`/`application_settings` di
 * database `apps` milik OEE App. Dibaca lewat helper setting().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            // Menentukan cara SettingService meng-cast value saat dibaca.
            $table->enum('type', ['string', 'number', 'boolean', 'json'])->default('string');
            // Untuk mengelompokkan field di halaman Setting.
            $table->string('group')->default('general')->index();
            $table->string('label')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
