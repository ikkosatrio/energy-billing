<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menghubungkan pembayaran ke batch asalnya, dan menjaga impor dari duplikat.
 *
 * `import_hash` adalah sidik jari satu baris berkas impor (invoice + tanggal +
 * jumlah + nomor referensi). Unique index di kolom ini membuat berkas mutasi
 * yang tanpa sengaja diunggah dua kali ditolak oleh database, bukan sekadar
 * oleh pemeriksaan di kode yang bisa lolos saat dua orang mengunggah bersamaan.
 *
 * Nullable: pembayaran yang dicatat manual satu per satu tidak punya sidik
 * jari, dan MySQL mengizinkan banyak NULL pada kolom unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->foreignId('payment_batch_id')->nullable()->after('recorded_by')
                ->constrained('payment_batches')->nullOnDelete();
            $table->string('import_hash', 64)->nullable()->after('payment_batch_id')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_batch_id');
            $table->dropUnique(['import_hash']);
            $table->dropColumn('import_hash');
        });
    }
};
