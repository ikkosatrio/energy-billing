<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice pemakaian listrik.
 *
 * PENTING: seluruh angka — stand meter, tarif, persentase pajak — DI-SNAPSHOT
 * ke baris ini saat invoice digenerate. Saat mencetak, jangan pernah join ke
 * `tariff_rates` atau `customers` untuk mengambil angka, karena tarif dan data
 * pelanggan bisa berubah setelah invoice terbit. Kolom foreign key di sini
 * hanya untuk penelusuran, bukan sumber angka.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->unique();

            $table->foreignId('billing_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('power_meter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tariff_rate_id')->nullable()->constrained()->nullOnDelete();

            // --- Snapshot identitas pelanggan saat invoice terbit ---
            $table->string('customer_name');
            $table->text('customer_address')->nullable();
            $table->string('customer_npwp')->nullable();
            $table->string('meter_code')->nullable();
            $table->string('tariff_group_code')->nullable();

            // --- Periode tertagih ---
            $table->date('period_start');
            $table->date('period_end');

            // --- LWBP ---
            $table->decimal('stand_lwbp_start', 15, 2)->default(0);
            $table->decimal('stand_lwbp_end', 15, 2)->default(0);
            $table->decimal('kwh_lwbp', 15, 2)->default(0);
            $table->decimal('rate_lwbp', 15, 2)->default(0);
            $table->decimal('amount_lwbp', 18, 2)->default(0);

            // --- WBP ---
            $table->decimal('stand_wbp_start', 15, 2)->default(0);
            $table->decimal('stand_wbp_end', 15, 2)->default(0);
            $table->decimal('kwh_wbp', 15, 2)->default(0);
            $table->decimal('rate_wbp', 15, 2)->default(0);
            $table->decimal('amount_wbp', 18, 2)->default(0);

            // --- Komponen lain ---
            // Snapshot cara biaya beban dihitung beserta angkanya.
            $table->enum('biaya_beban_mode', ['flat', 'per_kva'])->default('flat');
            $table->decimal('daya_kva', 10, 2)->default(0);
            $table->decimal('rate_beban_per_kva', 15, 2)->default(0);
            $table->decimal('biaya_beban', 18, 2)->default(0);
            $table->decimal('biaya_admin', 18, 2)->default(0);

            $table->decimal('subtotal', 18, 2)->default(0);
            // Persentase disnapshot agar invoice lama tetap konsisten walau
            // tarif pajak di setting berubah.
            $table->decimal('ppj_percent', 6, 3)->default(0);
            $table->decimal('ppj_amount', 18, 2)->default(0);
            $table->decimal('ppn_percent', 6, 3)->default(0);
            $table->decimal('ppn_amount', 18, 2)->default(0);
            // Selisih pembulatan ke ratusan/ribuan terdekat; boleh negatif.
            $table->decimal('rounding', 18, 2)->default(0);
            // Koreksi manual oleh admin, boleh negatif.
            $table->decimal('adjustment', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);

            // --- Status & pembayaran ---
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('status', ['draft', 'issued', 'partial', 'paid', 'overdue', 'cancelled'])
                ->default('draft')->index();
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Satu pelanggan hanya boleh punya satu invoice per periode —
            // pengaman agar generate ulang tidak menghasilkan tagihan ganda.
            $table->unique(['billing_period_id', 'customer_id']);
            $table->index(['customer_id', 'status']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
