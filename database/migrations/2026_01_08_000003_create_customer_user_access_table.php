<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pelanggan mana yang boleh dilihat oleh satu akun portal.
 *
 * Ini yang menjawab "data siapa", sementara permission menjawab "boleh
 * melakukan apa" — keduanya wajib dan tidak saling menggantikan. Punya
 * `portal.invoice.view` tidak membuat sebuah akun bisa membuka invoice
 * pelanggan lain; baris di tabel inilah yang membatasinya.
 *
 * Satu akun boleh menunjuk beberapa pelanggan (mis. satu grup usaha dengan
 * beberapa cabang). Power meter tidak perlu ikut dicatat di sini: satu
 * pelanggan memakai tepat satu meter (`customers.power_meter_id` unique),
 * jadi daftar meter selalu bisa diturunkan dari daftar pelanggannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_user_access', function (Blueprint $table) {
            $table->foreignId('customer_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Tanpa baris ganda: satu akun tidak bisa "dua kali" mengakses
            // pelanggan yang sama, dan itu jadi kunci primernya sekaligus.
            $table->primary(['customer_user_id', 'customer_id']);

            // Arah sebaliknya — "akun mana saja yang bisa melihat pelanggan
            // ini" — dipakai halaman admin saat menghapus pelanggan.
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_user_access');
    }
};
