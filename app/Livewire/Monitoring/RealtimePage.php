<?php

namespace App\Livewire\Monitoring;

use App\Models\PowerMeter;
use App\Services\Monitoring\UsageSummaryService;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Livewire\Component;

/**
 * Kartu tiap meter: kondisi kelistrikan saat ini di bagian atas, akumulasi
 * pemakaian dan biayanya di bagian bawah. Disegarkan berkala lewat wire:poll.
 */
class RealtimePage extends Component
{
    /** Livewire tidak menyuntik lewat constructor; diambil dari container. */
    private UsageSummaryService $usage;

    public function boot(UsageSummaryService $usage): void
    {
        $this->usage = $usage;
    }

    /**
     * Pilihan jeda penyegaran, dalam detik. `0` berarti manual — tidak ada
     * polling sama sekali.
     *
     * Labelnya pendek supaya muat sebagai tombol berderet; nilainya selalu
     * detik karena itu yang dimengerti wire:poll.
     */
    public const REFRESH_OPTIONS = [
        5 => '5s',
        10 => '10s',
        30 => '30s',
        60 => '1m',
        300 => '5m',
        600 => '10m',
    ];

    /**
     * Disimpan di sesi supaya pilihannya bertahan saat pindah halaman dan
     * kembali lagi — memilih ulang tiap kali membuka halaman ini melelahkan.
     */
    #[Session(key: 'realtime.refresh')]
    public int $refreshEvery = 30;

    /** Kosong = semua jenis sambungan. */
    public string $phaseFilter = '';

    public function updatedRefreshEvery(int $value): void
    {
        // Nilai di luar daftar hanya bisa datang dari payload yang dikarang;
        // dikembalikan ke manual daripada dipakai sebagai jeda polling.
        if ($value !== 0 && !array_key_exists($value, self::REFRESH_OPTIONS)) {
            $this->refreshEvery = 0;
        }
    }

    #[On('refresh-realtime')]
    public function refresh(): void
    {
        // wire:poll memanggil ini; render ulang sudah cukup untuk mengambil
        // pembacaan terbaru.
    }

    public function render()
    {
        $meters = PowerMeter::query()
            ->with(['customer:id,power_meter_id,name,daya_kva,tariff_group_id', 'latestReading', 'deviceStatus'])
            ->where('status', '!=', 'inactive')
            ->when($this->phaseFilter, fn ($q) => $q->where('phase', $this->phaseFilter))
            ->orderBy('name')
            ->get();

        return view('livewire.monitoring.realtime-page', [
            'meters' => $meters,
            'cards' => $meters->map(fn ($meter) => $meter->statusBadge())->all(),
            'usage' => $this->usage->forMeters($meters),
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
