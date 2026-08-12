<?php

namespace App\Livewire\Monitoring;

use App\Models\MeterReading;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Riwayat pemakaian per jam, per hari, dan per bulan.
 *
 * Chart per jam dibaca dari `meter_readings` (butuh detail), sedangkan chart
 * harian dan bulanan dari `meter_reading_dailies` agar tidak memindai jutaan
 * baris mentah.
 */
class HistoryPage extends Component
{
    public ?int $meterId = null;

    /** Bulan yang ditampilkan, format Y-m. */
    public string $month = '';

    /** Tanggal untuk chart per jam, format Y-m-d. */
    public string $day = '';

    public function mount(): void
    {
        $this->meterId = PowerMeter::where('status', '!=', 'inactive')->orderBy('name')->value('id');
        $this->month = now()->format('Y-m');
        $this->day = now()->toDateString();
    }

    public function render()
    {
        $monthStart = Carbon::parse($this->month.'-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $dailies = $this->meterId
            ? MeterReadingDaily::where('power_meter_id', $this->meterId)
                ->between($monthStart->toDateString(), $monthEnd->toDateString())
                ->orderBy('date')
                ->get()
            : collect();

        return view('livewire.monitoring.history-page', [
            'meters' => PowerMeter::where('status', '!=', 'inactive')->orderBy('name')->get(['id', 'code', 'name']),
            'dailies' => $dailies,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'hourly' => $this->hourlyUsage(),
            'monthly' => $this->monthlyUsage(),
            'summary' => $this->summary($dailies),
        ]);
    }

    /**
     * Pemakaian per jam pada tanggal terpilih, dihitung dari selisih stand
     * awal & akhir tiap jam.
     *
     * @return array<int, array{hour:int, lwbp:float, wbp:float}>
     */
    private function hourlyUsage(): array
    {
        if (!$this->meterId) {
            return [];
        }

        $date = Carbon::parse($this->day);

        $rows = MeterReading::query()
            ->where('power_meter_id', $this->meterId)
            ->between($date->copy()->startOfDay()->toDateTimeString(), $date->copy()->endOfDay()->toDateTimeString())
            ->selectRaw('HOUR(read_at) AS jam,
                         MAX(stand_lwbp) - MIN(stand_lwbp) AS lwbp,
                         MAX(stand_wbp) - MIN(stand_wbp) AS wbp')
            ->groupBy('jam')
            ->get()
            ->keyBy('jam');

        // Seluruh 24 jam selalu dikembalikan supaya sumbu chart tetap utuh
        // walau ada jam yang tidak menerima data.
        $result = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $row = $rows->get($hour);
            $result[] = [
                'hour' => $hour,
                'lwbp' => (float) ($row->lwbp ?? 0),
                'wbp' => (float) ($row->wbp ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Total kWh 12 bulan terakhir.
     *
     * @return array<int, array{label:string, total:float}>
     */
    private function monthlyUsage(): array
    {
        if (!$this->meterId) {
            return [];
        }

        $from = now()->copy()->subMonths(11)->startOfMonth();

        $rows = MeterReadingDaily::query()
            ->where('power_meter_id', $this->meterId)
            ->where('date', '>=', $from->toDateString())
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') AS bulan, SUM(kwh_lwbp + kwh_wbp) AS total")
            ->groupBy('bulan')
            ->pluck('total', 'bulan')
            ->all();

        $result = [];
        for ($i = 0; $i < 12; $i++) {
            $month = $from->copy()->addMonths($i);
            $result[] = [
                'label' => $month->translatedFormat('M'),
                'total' => (float) ($rows[$month->format('Y-m')] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Ringkasan periode untuk panel kanan.
     */
    private function summary($dailies): array
    {
        $lwbp = (float) $dailies->sum('kwh_lwbp');
        $wbp = (float) $dailies->sum('kwh_wbp');
        $total = $lwbp + $wbp;
        $days = $dailies->count();
        $peak = $dailies->whereNotNull('peak_kw')->sortByDesc('peak_kw')->first();

        // Load factor = rata-rata beban dibagi beban puncak. Angka mendekati 1
        // berarti pemakaian merata; rendah berarti banyak lonjakan.
        $hours = max(1, $days * 24);
        $loadFactor = $peak && (float) $peak->peak_kw > 0
            ? ($total / $hours) / (float) $peak->peak_kw
            : null;

        return [
            'total' => $total,
            'lwbp' => $lwbp,
            'wbp' => $wbp,
            'daily_average' => $days > 0 ? $total / $days : 0,
            'peak_kw' => $peak?->peak_kw,
            'peak_at' => $peak?->peak_at,
            'load_factor' => $loadFactor,
            'incomplete_days' => $dailies->filter->is_incomplete->count(),
        ];
    }
}
