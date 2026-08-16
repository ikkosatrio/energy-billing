<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kuitansi (tanda terima) untuk tiap pembayaran yang masuk.
 *
 * Nomor kuitansi TIDAK diberikan saat pembayaran dicatat, melainkan saat
 * kuitansinya pertama kali diterbitkan. Alasannya masa tunggu kirim otomatis:
 * pembayaran yang salah input dan ditarik kembali dalam masa itu belum sempat
 * memakai nomor, sehingga deret nomor kuitansi tidak berlubang — sesuatu yang
 * dipersoalkan saat dokumennya dipakai untuk pembukuan.
 *
 * receipt_paid_total dan receipt_outstanding_after adalah snapshot saat
 * kuitansi terbit, bukan angka yang dihitung ulang tiap kali PDF dibuka.
 * Pembayaran bertanggal mundur yang diinput belakangan akan mengubah hasil
 * hitungan, dan dokumen yang sudah dipegang pelanggan tidak boleh berubah
 * isinya setelah dikirim.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->string('receipt_no')->nullable()->after('import_hash')->unique();
            $table->timestamp('receipt_issued_at')->nullable()->after('receipt_no');
            $table->timestamp('receipt_sent_at')->nullable()->after('receipt_issued_at');
            $table->decimal('receipt_paid_total', 18, 2)->nullable()->after('receipt_sent_at');
            $table->decimal('receipt_outstanding_after', 18, 2)->nullable()->after('receipt_paid_total');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropUnique(['receipt_no']);
            $table->dropColumn([
                'receipt_no',
                'receipt_issued_at',
                'receipt_sent_at',
                'receipt_paid_total',
                'receipt_outstanding_after',
            ]);
        });
    }
};
