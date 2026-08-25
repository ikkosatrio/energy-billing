<?php

namespace Database\Seeders;

use App\Models\CustomerUser;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Slug permission di sini adalah slug yang sama yang dipakai config/menu.php
 * dan direktif @can di Blade. Menambah permission baru cukup di array ini,
 * lalu jalankan ulang seeder (aman diulang — memakai updateOrCreate).
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * group => [slug => label]
     */
    private const PERMISSIONS = [
        'Monitoring' => [
            'monitoring.view' => 'Lihat monitoring & riwayat energi',
        ],
        'Billing' => [
            'invoice.view' => 'Lihat invoice',
            'invoice.generate' => 'Generate invoice periode',
            'invoice.update' => 'Ubah invoice',
            'invoice.delete' => 'Hapus / batalkan invoice',
            'invoice.reopen' => 'Buka kembali invoice yang dibatalkan',
            'invoice.send' => 'Kirim invoice ke pelanggan',
            'payment.view' => 'Lihat pembayaran',
            'payment.create' => 'Catat pembayaran',
            'payment.bulk' => 'Pembayaran massal & impor berkas',
            'payment.receipt' => 'Terbitkan & kirim kuitansi',
            // Dipisah dari payment.bulk: menarik kembali pembayaran yang
            // kuitansinya sudah dipegang pelanggan bukan koreksi biasa.
            'payment.force_revert' => 'Batalkan batch yang kuitansinya sudah terkirim',
            'payment.delete' => 'Hapus pembayaran',
        ],
        'Master Data' => [
            'customer.view' => 'Lihat pelanggan',
            'customer.create' => 'Tambah pelanggan',
            'customer.update' => 'Ubah pelanggan',
            'customer.delete' => 'Hapus pelanggan',
            'meter.view' => 'Lihat power meter',
            'meter.create' => 'Tambah power meter',
            'meter.update' => 'Ubah power meter',
            'meter.delete' => 'Hapus power meter',
        ],
        'Tarif' => [
            'tariff.view' => 'Lihat golongan & tarif',
            'tariff.create' => 'Tambah tarif',
            'tariff.update' => 'Ubah tarif & jadwal WBP/LWBP',
            'tariff.delete' => 'Hapus tarif',
        ],
        'Report' => [
            'report.view' => 'Lihat report',
            'report.export' => 'Export report Excel/PDF',
            'reading.purge' => 'Hapus data mentah lama di luar jadwal otomatis',
        ],
        'Sistem' => [
            'setting.manage' => 'Kelola setting aplikasi',
            'user.view' => 'Lihat user',
            'user.create' => 'Tambah user',
            'user.update' => 'Ubah user',
            'user.delete' => 'Hapus user',
            'role.view' => 'Lihat role',
            'role.manage' => 'Kelola role & hak akses',
            'activity_log.view' => 'Lihat log aktivitas',
            'reading.wipe_trial' => 'Hapus data mentah & agregat harian per rentang tanggal (data uji coba)',
            'customer_user.view' => 'Lihat akun portal pelanggan',
            'customer_user.manage' => 'Kelola akun portal pelanggan & pelanggan yang diaksesnya',
        ],
    ];

    /**
     * Permission portal pelanggan (guard 'customer').
     *
     * Dipisah dari PERMISSIONS di atas supaya tidak pernah bisa dipilih saat
     * menyusun role staf, dan sebaliknya — pemisahnya kolom `guard`, bukan
     * kedisiplinan operator.
     *
     * Slug diberi awalan `portal.` karena `permissions.slug` unique lintas
     * guard. Awalan itu sekaligus membuat @can di Blade portal terbaca jelas
     * bukan permission staf.
     *
     * group => [slug => label]
     */
    private const PORTAL_PERMISSIONS = [
        'Portal Pelanggan' => [
            'portal.monitoring.view' => 'Lihat monitoring real-time & riwayat energi',
            'portal.usage.view' => 'Lihat rekap pemakaian kWh',
            'portal.invoice.view' => 'Lihat & unduh invoice',
            'portal.payment.view' => 'Lihat pembayaran & unduh kuitansi',
        ],
    ];

    /**
     * Role portal bawaan. Slug berawalan `portal-` agar tidak bertabrakan
     * dengan role staf pada indeks unique `roles.slug`.
     */
    private const PORTAL_ROLES = [
        'portal-penuh' => [
            'name' => 'Pelanggan (Penuh)',
            'description' => 'Melihat monitoring, rekap pemakaian, invoice, dan kuitansi pembayaran.',
            'permissions' => [
                'portal.monitoring.view',
                'portal.usage.view',
                'portal.invoice.view',
                'portal.payment.view',
            ],
        ],
        // Untuk pihak yang perlu memantau pemakaian tapi tidak berkepentingan
        // dengan nominal tagihan — mis. teknisi gedung atau manajer operasional.
        'portal-pantau' => [
            'name' => 'Pelanggan (Pantau Saja)',
            'description' => 'Hanya monitoring dan rekap pemakaian kWh, tanpa invoice dan nominal tagihan.',
            'permissions' => [
                'portal.monitoring.view',
                'portal.usage.view',
            ],
        ],
    ];

    /**
     * Role bawaan beserta permission-nya.
     * super-admin sengaja tidak diberi daftar: User::hasPermission()
     * meloloskannya tanpa perlu baris di permission_role.
     */
    private const ROLES = [
        'super-admin' => [
            'name' => 'Super Admin',
            'description' => 'Akses penuh: setting sistem, user, tarif, billing, hapus data.',
            'permissions' => [],
        ],
        'billing-staff' => [
            'name' => 'Billing Staff',
            'description' => 'Generate & kirim invoice, catat pembayaran, kelola pelanggan. Tidak bisa mengubah tarif.',
            'permissions' => [
                'monitoring.view',
                'invoice.view', 'invoice.generate', 'invoice.update', 'invoice.send',
                'payment.view', 'payment.create', 'payment.bulk', 'payment.receipt',
                'customer.view', 'customer.create', 'customer.update',
                'meter.view',
                'tariff.view',
                'report.view', 'report.export',
            ],
        ],
        'teknisi' => [
            'name' => 'Teknisi',
            'description' => 'Kelola power meter dan jadwal WBP/LWBP, serta melihat monitoring.',
            'permissions' => [
                'monitoring.view',
                'meter.view', 'meter.create', 'meter.update',
                'tariff.view', 'tariff.update',
                'customer.view',
                'report.view',
            ],
        ],
        'viewer' => [
            'name' => 'Viewer',
            'description' => 'Hanya melihat monitoring, invoice, dan report tanpa bisa mengubah apa pun.',
            'permissions' => [
                'monitoring.view',
                'invoice.view',
                'payment.view',
                'customer.view',
                'meter.view',
                'tariff.view',
                'report.view',
            ],
        ],
    ];

    public function run(): void
    {
        $this->seedPermissions(self::PERMISSIONS, 'web');
        $this->seedPermissions(self::PORTAL_PERMISSIONS, CustomerUser::GUARD);

        $this->seedRoles(self::ROLES, 'web');
        $this->seedRoles(self::PORTAL_ROLES, CustomerUser::GUARD);
    }

    /**
     * @param  array<string, array<string, string>>  $groups
     */
    private function seedPermissions(array $groups, string $guard): void
    {
        foreach ($groups as $group => $items) {
            foreach ($items as $slug => $name) {
                Permission::updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $name, 'group' => $group, 'guard' => $guard],
                );
            }
        }
    }

    /**
     * @param  array<string, array{name:string, description:string, permissions:array<int, string>}>  $roles
     */
    private function seedRoles(array $roles, string $guard): void
    {
        foreach ($roles as $slug => $definition) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'guard' => $guard,
                    'is_system' => true,
                ],
            );

            // Dibatasi guard yang sama: kalau tidak, salah tulis satu slug di
            // daftar di atas bisa menyelipkan permission staf ke role portal
            // tanpa ada yang menahannya.
            $role->permissions()->sync(
                Permission::forGuard($guard)
                    ->whereIn('slug', $definition['permissions'])
                    ->pluck('id'),
            );
        }
    }
}
