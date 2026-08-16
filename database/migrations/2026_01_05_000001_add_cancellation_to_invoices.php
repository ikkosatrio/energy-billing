<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak pembatalan invoice.
 *
 * Sebelumnya pembatalan hanya mengubah kolom status, sehingga dokumen yang
 * sudah beredar ke pelanggan tidak bisa menjelaskan dirinya sendiri: PDF-nya
 * tetap terlihat seperti tagihan yang sah. Tiga kolom ini membuat pembatalan
 * bisa dicetak di dokumen dan ditelusuri siapa yang melakukannya.
 *
 * Alasan sengaja opsional — pembatalan draft yang belum pernah beredar sering
 * tidak perlu penjelasan, dan memaksa mengisi hanya melahirkan alasan asal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('paid_at');
            $table->text('cancel_reason')->nullable()->after('cancelled_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancel_reason')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancel_reason']);
        });
    }
};
