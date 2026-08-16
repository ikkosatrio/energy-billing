<?php

/*
|--------------------------------------------------------------------------
| Sidebar Menu
|--------------------------------------------------------------------------
|
| Struktur menu mengikuti desain di template-ref. Sidebar dirender dari file
| ini (resources/views/partials/sidebar.blade.php), jadi menambah halaman
| cukup menambah entri di sini — tidak perlu menyentuh Blade.
|
| Setiap item:
|   title   — label yang tampil
|   route   — nama route Laravel. Item dilewati bila route belum terdaftar,
|             sehingga menu bisa lengkap sejak awal walau modulnya menyusul.
|   icon    — nama ikon Lucide (https://lucide.dev/icons)
|   active  — pola route untuk menandai item sedang aktif; default = route
|   permits — daftar slug permission; kosong = boleh diakses semua user login
|
| Grup tanpa 'items' dirender sebagai satu baris menu langsung (mis. Dashboard).
|
*/

return [

    [
        'title' => 'Dashboard',
        'route' => 'dashboard',
        'icon' => 'layout-dashboard',
        'permits' => [],
    ],

    [
        'title' => 'Monitoring',
        'icon' => 'radio-tower',
        'items' => [
            [
                'title' => 'Real-time Monitoring',
                'route' => 'monitoring.realtime',
                'icon' => 'radio',
                'permits' => ['monitoring.view'],
            ],
            [
                'title' => 'Energy History',
                'route' => 'monitoring.history',
                'icon' => 'chart-line',
                'permits' => ['monitoring.view'],
            ],
            [
                'title' => 'Status Perangkat',
                'route' => 'monitoring.devices',
                'icon' => 'cpu',
                'permits' => ['monitoring.view'],
            ],
        ],
    ],

    [
        'title' => 'Billing & Invoice',
        'icon' => 'receipt',
        'items' => [
            [
                'title' => 'Daftar Invoice',
                'route' => 'billing.invoices.index',
                'active' => 'billing.invoices.*',
                'icon' => 'file-text',
                'permits' => ['invoice.view'],
            ],
            [
                'title' => 'Periode & Generate',
                'route' => 'billing.periods.index',
                'active' => 'billing.periods.*',
                'icon' => 'calendar-clock',
                'permits' => ['invoice.generate'],
            ],
            [
                'title' => 'Pembayaran',
                'route' => 'billing.payments.index',
                'active' => 'billing.payments.*',
                'icon' => 'wallet',
                'permits' => ['payment.view'],
            ],
        ],
    ],

    [
        'title' => 'Master Data',
        'icon' => 'database',
        'items' => [
            [
                'title' => 'Data Pelanggan',
                'route' => 'master.customers.index',
                'active' => 'master.customers.*',
                'icon' => 'users',
                'permits' => ['customer.view'],
            ],
            [
                'title' => 'Power Meter Device',
                'route' => 'master.meters.index',
                'active' => 'master.meters.*',
                'icon' => 'gauge',
                'permits' => ['meter.view'],
            ],
        ],
    ],

    [
        'title' => 'Tarif & Konfigurasi',
        'icon' => 'sliders-horizontal',
        'items' => [
            [
                'title' => 'Golongan & Tarif',
                'route' => 'tariff.groups.index',
                'active' => 'tariff.groups.*',
                'icon' => 'tags',
                'permits' => ['tariff.view'],
            ],
            [
                'title' => 'Jadwal WBP / LWBP',
                'route' => 'tariff.schedules.index',
                'active' => 'tariff.schedules.*',
                'icon' => 'clock',
                'permits' => ['tariff.view'],
            ],
        ],
    ],

    [
        'title' => 'Report',
        'icon' => 'chart-column',
        'items' => [
            [
                'title' => 'Rekap Pemakaian kWh',
                'route' => 'report.usage',
                'icon' => 'zap',
                'permits' => ['report.view'],
            ],
            [
                'title' => 'Rekap Tagihan & Penerimaan',
                'route' => 'report.billing',
                'icon' => 'banknote',
                'permits' => ['report.view'],
            ],
            [
                'title' => 'Laporan Pembayaran',
                'route' => 'report.payments',
                'icon' => 'receipt-text',
                'permits' => ['report.view'],
            ],
            [
                'title' => 'Data Meter Mentah',
                'route' => 'report.readings',
                'icon' => 'list',
                'permits' => ['report.view'],
            ],
        ],
    ],

    [
        'title' => 'Sistem',
        'icon' => 'settings',
        'items' => [
            [
                'title' => 'Setting Aplikasi',
                'route' => 'system.settings.index',
                'active' => 'system.settings.*',
                'icon' => 'sliders',
                'permits' => ['setting.manage'],
            ],
            [
                'title' => 'User Management',
                'route' => 'system.users.index',
                'active' => 'system.users.*',
                'icon' => 'user-cog',
                'permits' => ['user.view'],
            ],
            [
                'title' => 'Role & Hak Akses',
                'route' => 'system.roles.index',
                'active' => 'system.roles.*',
                'icon' => 'shield-check',
                'permits' => ['role.view'],
            ],
            [
                'title' => 'Log Aktivitas',
                'route' => 'system.activity-logs.index',
                'icon' => 'history',
                'permits' => ['activity_log.view'],
            ],
            // [
            //     'title' => 'Dokumentasi API',
            //     'route' => 'l5-swagger.default.api',
            //     'icon' => 'book-open',
            //     'permits' => [],
            //     // Swagger UI adalah halaman terpisah, bukan bagian layout
            //     // aplikasi — dibuka di tab baru.
            //     'external' => true,
            // ],
        ],
    ],

];
