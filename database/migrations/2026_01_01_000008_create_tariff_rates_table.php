<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harga per kWh untuk sebuah golongan tarif pada rentang tanggal tertentu.
 *
 * Mengubah tarif = MENAMBAH baris baru dengan effective_from baru, lalu
 * menutup baris lama dengan effective_to. Baris lama tidak pernah diubah,
 * sehingga invoice periode sebelumnya tetap bisa ditelusuri tarifnya.
 *
 * Tarif yang berlaku pada tanggal T:
 *   effective_from <= T AND (effective_to IS NULL OR effective_to >= T)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_group_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate_lwbp', 15, 2);
            $table->decimal('rate_wbp', 15, 2);
            // Dipakai hanya bila customer memilih mode biaya beban per kVA.
            $table->decimal('rate_beban_per_kva', 15, 2)->default(0);
            $table->date('effective_from');
            // NULL = masih berlaku sampai sekarang.
            $table->date('effective_to')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['tariff_group_id', 'effective_from', 'effective_to'], 'tariff_rates_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_rates');
    }
};
