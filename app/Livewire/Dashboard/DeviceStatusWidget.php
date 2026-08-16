<?php

namespace App\Livewire\Dashboard;

use App\Models\PowerMeter;
use App\Services\Monitoring\UsageSummaryService;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Livewire\Component;

/**
 * Ringkasan seluruh power meter di Dashboard — pengganti panel "Status Meter"
 * lama yang cuma menampilkan 6 baris nama+kW. Kartunya dibuat sekompak
 * mungkin (grid, bukan daftar satu kolom) supaya banyak perangkat tetap
 * termuat sekali pandang, tapi data yang ditampilkan sama persis dengan
 * Real-time Monitoring: kW+PF, tegangan/arus tiap jalur, dan kWh hari
 * ini/bulan ini.
 *
 * Diurutkan berdasarkan urgensi (offline/maintenance/beban tinggi dulu),
 * bukan alfabet — dashboard ini untuk memutuskan cepat apa yang perlu
 * ditindaklanjuti, jadi perangkat bermasalah harus langsung terlihat di
 * pojok kiri atas tanpa perlu menggulir.
 */
class DeviceStatusWidget extends Component
{
    /** Urutan prioritas label status; makin kecil makin butuh perhatian. */
    private const STATUS_PRIORITY = [
        'Offline' => 0,
        'Maintenance' => 1,
        'Beban Tinggi' => 2,
        'Normal' => 3,
    ];

    private UsageSummaryService $usage;

    public function boot(UsageSummaryService $usage): void
    {
        $this->usage = $usage;
    }

    /** Sama dengan RealtimePage — satu paradigma kontrol di seluruh aplikasi. */
    public const REFRESH_OPTIONS = [
        5 => '5s',
        10 => '10s',
        30 => '30s',
        60 => '1m',
        300 => '5m',
        600 => '10m',
    ];

    #[Session(key: 'dashboard.device-refresh')]
    public int $refreshEvery = 30;

    public function updatedRefreshEvery(int $value): void
    {
        if ($value !== 0 && !array_key_exists($value, self::REFRESH_OPTIONS)) {
            $this->refreshEvery = 0;
        }
    }

    #[On('refresh-devices')]
    public function refresh(): void
    {
        // wire:poll memanggil ini; render ulang sudah cukup.
    }

    public function render()
    {
        $meters = PowerMeter::query()
            ->with(['customer:id,power_meter_id,name,daya_kva,tariff_group_id', 'latestReading', 'deviceStatus'])
            ->where('status', '!=', 'inactive')
            ->orderBy('name')
            ->get();

        // Digabung sebagai satu paket [meter, kartu] dulu supaya urutan
        // status dan meter tidak bisa saling lepas saat diurutkan ulang.
        $ordered = $meters
            ->map(fn (PowerMeter $meter) => ['meter' => $meter, 'card' => $meter->statusBadge()])
            ->sortBy(fn ($row) => sprintf('%d-%s', self::STATUS_PRIORITY[$row['card']['status']] ?? 9, $row['meter']->name))
            ->values();

        return view('livewire.dashboard.device-status-widget', [
            'meters' => $ordered->pluck('meter'),
            'cards' => $ordered->pluck('card')->all(),
            'usage' => $this->usage->forMeters($meters),
            'attentionCount' => $ordered->filter(fn ($row) => $row['card']['status'] !== 'Normal')->count(),
        ]);
    }
}
