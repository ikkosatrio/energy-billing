<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pelanggan = penyewa gudang. Satu pelanggan memakai tepat satu power meter,
 * karena itu power_meter_id dibuat unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('pic_name')->nullable();
            $table->string('npwp')->nullable();

            // Satu meter hanya boleh dipakai satu pelanggan. Nullable supaya
            // pelanggan bisa didaftarkan sebelum meternya terpasang.
            $table->foreignId('power_meter_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('tariff_group_id')->nullable()->constrained()->nullOnDelete();

            // Daya tersambung — informatif, dan menjadi pengali bila mode
            // biaya beban di-set 'per_kva'.
            $table->decimal('daya_kva', 10, 2)->default(0);
            $table->enum('biaya_beban_mode', ['flat', 'per_kva'])->default('flat');
            // Dipakai bila mode 'flat'.
            $table->decimal('biaya_beban', 18, 2)->default(0);

            // Override tanggal generate invoice. NULL = ikut tanggal default
            // global di setting sistem (billing_cut_off_day).
            $table->unsignedTinyInteger('billing_day')->nullable();

            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
