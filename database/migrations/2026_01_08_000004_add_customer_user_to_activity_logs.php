<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pelaku dari portal pelanggan dicatat di kolom sendiri.
 *
 * `activity_logs.user_id` adalah foreign key ke `users` (tabel staf), jadi
 * menaruh id akun portal di sana bukan cuma salah baca — melanggar constraint
 * dan membuat log gagal ditulis. Dua kolom terpisah membuat pertanyaan "ini
 * dilakukan staf atau pelanggan?" terjawab dari barisnya sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('customer_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_user_id');
        });
    }
};
