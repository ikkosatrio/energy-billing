<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perangkat power meter di lapangan. Gateway mengirim pembacaan ke endpoint
 * API dengan `device_key` sebagai token identifikasi perangkat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('power_meters', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('serial_no')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('location')->nullable();
            // Token rahasia yang dipakai gateway saat push pembacaan.
            $table->string('device_key', 64)->unique();
            // CT ratio sebagai teks (mis. "800/5") untuk ditampilkan, dan
            // pengali numerik yang benar-benar dipakai saat menghitung kWh.
            $table->string('ct_ratio')->nullable();
            $table->decimal('multiplier', 10, 4)->default(1);
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active')->index();
            $table->date('installed_at')->nullable();
            // Diperbarui tiap kali gateway push; dipakai menentukan
            // online/offline di halaman monitoring.
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('power_meters');
    }
};
