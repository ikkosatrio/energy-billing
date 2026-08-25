<?php

/*
|--------------------------------------------------------------------------
| Sidebar Portal Pelanggan
|--------------------------------------------------------------------------
|
| Formatnya sama dengan config/menu.php, tapi daftarnya sengaja terpisah:
| menu staf memuat modul yang tidak boleh sekadar tersembunyi dari pelanggan,
| dan satu file untuk keduanya berarti satu salah ketik pada 'permits' bisa
| memunculkan menu admin di portal.
|
| Slug pada 'permits' adalah permission guard 'customer' (berawalan portal.),
| dicek lewat CustomerUser::hasAnyPermission(). Daftar kosong = tampil untuk
| semua akun portal yang sudah login.
|
*/

return [

    [
        'title' => 'Dashboard',
        'route' => 'portal.dashboard',
        'icon' => 'layout-dashboard',
        'permits' => [],
    ],

    [
        'title' => 'Pemakaian Energi',
        'icon' => 'radio-tower',
        'items' => [
            [
                'title' => 'Monitoring Real-time',
                'route' => 'portal.monitoring',
                'icon' => 'radio',
                'permits' => ['portal.monitoring.view'],
            ],
            [
                'title' => 'Riwayat Energi',
                'route' => 'portal.history',
                'icon' => 'chart-line',
                'permits' => ['portal.monitoring.view'],
            ],
            [
                'title' => 'Rekap Pemakaian kWh',
                'route' => 'portal.usage',
                'icon' => 'zap',
                'permits' => ['portal.usage.view'],
            ],
        ],
    ],

    [
        'title' => 'Tagihan',
        'icon' => 'receipt',
        'items' => [
            [
                'title' => 'Invoice',
                'route' => 'portal.invoices',
                'active' => 'portal.invoices*',
                'icon' => 'file-text',
                'permits' => ['portal.invoice.view'],
            ],
            [
                'title' => 'Riwayat Pembayaran',
                'route' => 'portal.payments',
                'icon' => 'wallet',
                'permits' => ['portal.payment.view'],
            ],
        ],
    ],

];
