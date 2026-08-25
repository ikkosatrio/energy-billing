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
 * Diurutkan berdasarkan ID meter supaya letak tiap kartu tetap sama setiap
 * kali halaman disegarkan. Sempat diurutkan berdasarkan urgensi, tapi pada
 * panel yang menyegarkan diri tiap 30 detik itu justru menyulitkan: kartu
 * berpindah tempat sendiri saat status berubah, dan mata harus mencari ulang
 * perangkat yang tadi sedang dilihat. Yang perlu perhatian tetap mudah
 * ditemukan lewat badge merah dan penghitung "N perlu perhatian".
 */
class DeviceStatusWidget extends Component
{
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

    /** Kosong = semua jenis sambungan. Sama seperti Real-time Monitoring. */
    public string $phaseFilter = '';

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
            ->when($this->phaseFilter, fn ($q) => $q->where('phase', $this->phaseFilter))
            ->orderBy('id')
            ->get();

        $cards = $meters->map(fn (PowerMeter $meter) => $meter->statusBadge());

        return view('livewire.dashboard.device-status-widget', [
            'meters' => $meters,
            'cards' => $cards->all(),
            'usage' => $this->usage->forMeters($meters),
            'attentionCount' => $cards->filter(fn ($card) => $card['status'] !== 'Normal')->count(),
            'phaseCounts' => $this->phaseCounts(),
        ]);
    }

    /**
     * Jumlah meter per jenis sambungan, dipakai sebagai label pada filter
     * supaya terlihat ada berapa sebelum filternya dipilih.
     *
     * @return array{1:int, 3:int, all:int}
     */
    private function phaseCounts(): array
    {
        $counts = PowerMeter::query()
            ->where('status', '!=', 'inactive')
            ->selectRaw('phase, COUNT(*) AS jumlah')
            ->groupBy('phase')
            ->pluck('jumlah', 'phase');

        return [
            '1' => (int) ($counts['1'] ?? 0),
            '3' => (int) ($counts['3'] ?? 0),
            'all' => (int) $counts->sum(),
        ];
    }
}
