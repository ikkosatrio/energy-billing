<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembayaran invoice. Satu invoice boleh punya banyak baris (cicilan);
 * `invoices.paid_amount` adalah jumlah seluruh baris di sini dan status
 * invoice diturunkan darinya (partial / paid).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->enum('method', ['transfer', 'cash', 'other'])->default('transfer');
            $table->string('reference_no')->nullable();
            // Path bukti transfer di disk 'public'.
            $table->string('proof_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['invoice_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
