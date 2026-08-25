<?php

namespace App\Http\Controllers;

use App\Models\CustomerUser;
use App\Models\Permission;
use App\Models\Role;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Buku Panduan pengguna dalam bentuk PDF.
 *
 * Sengaja dibuat ulang setiap kali dibuka, bukan disimpan sebagai berkas
 * statis: tabel hak akses di dalamnya dibaca langsung dari database, sehingga
 * peran atau izin yang ditambahkan kemudian ikut muncul tanpa ada yang perlu
 * memperbarui dokumennya secara manual.
 *
 * Tangkapan layarnya TIDAK ikut diperbarui otomatis — itu berkas gambar yang
 * dipotret terpisah. Bila tampilan aplikasi berubah, jalankan:
 *
 *   php artisan db:seed --class=DemoDataSeeder   (bila datanya belum ada)
 *   php artisan demo:heartbeat
 *   node scripts/capture-guide-screenshots.mjs
 *
 * demo:heartbeat harus lebih dulu: tanpa itu seluruh meter tampil offline dan
 * tangkapan layarnya terlihat seperti aplikasi yang rusak. Bidikan Portal
 * Pelanggan memakai akun portal yang dibuat DemoDataSeeder.
 */
class GuideController extends Controller
{
    /** Dibuka di tab baru untuk dibaca langsung. */
    public function show()
    {
        return $this->pdf()->stream('buku-panduan.pdf');
    }

    public function download()
    {
        return $this->pdf()->download($this->filename());
    }

    private function pdf()
    {
        $roles = Role::with('permissions:id')->orderBy('id')->get();
        $permissions = Permission::orderBy('id')->get();

        /*
         * Peran dan izin dipisah per guard.
         *
         * Sejak ada portal pelanggan, tabel `roles` memuat dua audiens
         * sekaligus. Digabung dalam satu tabel centang, peran staf mendapat
         * kolom kosong untuk seluruh izin portal dan sebaliknya — pembacanya
         * melihat lebih banyak tanda hubung daripada centang, dan justru sulit
         * menyimpulkan siapa boleh apa.
         */
        $staffRoles = $roles->where('guard', 'web')->values();
        $portalRoles = $roles->where('guard', CustomerUser::GUARD)->values();

        return Pdf::loadView('guide.pdf', [
            'staffRoles' => $staffRoles,
            'portalRoles' => $portalRoles,
            // Super Admin dikeluarkan dari tabel centang: ia selalu lolos lewat
            // Gate::before, jadi kolomnya akan penuh centang dan justru
            // mengaburkan perbedaan antar peran lain.
            'nonSuperRoles' => $staffRoles->reject(fn (Role $role) => $role->slug === Role::SUPER_ADMIN)->values(),
            'permissionGroups' => $permissions->where('guard', 'web')->groupBy('group'),
            'portalPermissionGroups' => $permissions->where('guard', CustomerUser::GUARD)->groupBy('group'),
            'totalPages' => count(config('menu', [])) > 0 ? $this->countMenuPages() : 0,
            'totalPortalPages' => $this->countPortalMenuPages(),
        ])->setPaper('a4');
    }

    /** Jumlah halaman portal pelanggan yang terdaftar di sidebar portal. */
    private function countPortalMenuPages(): int
    {
        $total = 0;

        foreach (config('portal-menu', []) as $group) {
            $total += isset($group['items']) ? count($group['items']) : 1;
        }

        return $total;
    }

    /** Jumlah halaman yang benar-benar terdaftar di sidebar. */
    private function countMenuPages(): int
    {
        $total = 0;

        foreach (config('menu', []) as $group) {
            $total += isset($group['items']) ? count($group['items']) : 1;
        }

        return $total;
    }

    private function filename(): string
    {
        return 'buku-panduan-'.str(setting('app_name', 'energy-billing'))->slug().'.pdf';
    }
}
