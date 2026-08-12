<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Golongan tarif ala PLN (I-3 / TR, B-3 / TR, I-4 / TM, dst).
 * Harga per kWh-nya tidak di sini, melainkan di `tariff_rates` yang punya
 * masa berlaku — supaya perubahan tarif tidak menimpa tarif lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_groups');
    }
};
