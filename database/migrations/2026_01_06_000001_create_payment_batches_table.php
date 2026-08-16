<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris per operasi pembayaran massal — baik "tandai lunas" dari daftar
 * invoice maupun impor berkas.
 *
 * Ada supaya operasi yang menyentuh puluhan invoice sekaligus bisa dibatalkan
 * sebagai satu kesatuan. Tanpa ini, salah pilih 100 invoice berarti menghapus
 * 100 pembayaran satu per satu — dan itu hanya bisa dilakukan super admin,
 * sementara yang menjalankan penagihan harian adalah staff.
 *
 * Batch yang dibatalkan tidak dihapus: barisnya tetap tinggal dengan
 * reverted_at terisi, sehingga jejak "pernah ada 100 pembayaran lalu ditarik
 * kembali" tidak hilang dari catatan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_batches', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['bulk', 'import']);
            // Nama berkas untuk impor; null untuk bulk dari daftar invoice.
            $table->string('source')->nullable();
            $table->unsignedInteger('payment_count')->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reverted_at')->nullable();
            $table->foreignId('reverted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_batches');
    }
};
