<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akun login portal pelanggan — sengaja tabel tersendiri, bukan baris di
 * `users`.
 *
 * `users` adalah tabel staf: setiap query User Management, log aktivitas, dan
 * dropdown "dibuat oleh" mengasumsikan seluruh barisnya orang dalam. Menaruh
 * pihak luar di sana berarti setiap query itu harus ingat mengecualikannya,
 * dan satu yang terlupa membuat akun pelanggan muncul di panel admin.
 *
 * Dengan tabel dan guard terpisah, akun portal secara struktural tidak bisa
 * menyentuh panel admin — bukan karena ada permission yang menahannya, tapi
 * karena tidak ada jalannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            // Boleh kosong: pelanggan kecil sering tidak punya email khusus,
            // dan login memakai username. Tetap unique bila diisi supaya
            // nanti bisa dipakai sebagai identitas login alternatif.
            $table->string('email')->nullable()->unique();
            $table->string('password');
            // Role portal (baris `roles` dengan guard 'customer'). Nullable
            // supaya akun tanpa role tetap tersimpan — tanpa role ia tidak
            // punya permission apa pun, jadi aman sebagai default.
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            // Siapa (staf) yang membuat akun ini — jejak untuk audit, karena
            // akun portal hanya bisa dibuat dari panel admin.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_users');
    }
};
