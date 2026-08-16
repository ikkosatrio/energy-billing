<?php

namespace App\Http\Controllers;

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
 *   php artisan demo:heartbeat
 *   node scripts/capture-guide-screenshots.mjs
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

        return Pdf::loadView('guide.pdf', [
            'roles' => $roles,
            // Super Admin dikeluarkan dari tabel centang: ia selalu lolos lewat
            // Gate::before, jadi kolomnya akan penuh centang dan justru
            // mengaburkan perbedaan antar peran lain.
            'nonSuperRoles' => $roles->reject(fn (Role $role) => $role->slug === Role::SUPER_ADMIN)->values(),
            'permissionGroups' => Permission::orderBy('id')->get()->groupBy('group'),
            'totalPages' => count(config('menu', [])) > 0 ? $this->countMenuPages() : 0,
        ])->setPaper('a4');
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
