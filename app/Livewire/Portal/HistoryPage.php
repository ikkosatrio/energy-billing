<?php

namespace App\Livewire\Portal;

use App\Models\MeterReading;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use App\Services\Monitoring\ConsumptionCalculator;
use Illuminate\Support\Carbon;

/**
 * Riwayat energi versi portal — per jam, per hari, per bulan.
 *
 * Sama isinya dengan halaman staf, tapi daftar meter yang bisa dipilih hanya
 * meter milik pelanggan yang diakses, dan `meterId` yang datang dari browser
 * selalu diperiksa ulang lewat scopedMeterIds(): properti publik Livewire
 * bisa diubah dari sisi klien, jadi mempercayainya berarti mengizinkan
 * pergantian satu angka untuk membaca meter pelanggan lain.
 */
class HistoryPage extends PortalComponent
{
    protected string $permission = 'portal.monitoring.view';

    public ?int $meterId = null;

    /** Bulan yang ditampilkan, format Y-m. */
    public string $month = '';

    /** Tanggal untuk chart per jam, format Y-m-d. */
    public string $day = '';

    public function mount(): void
    {
        $this->meterId = PowerMeter::whereIn('id', $this->meterIds())
            ->orderBy('name')
            ->value('id');
        $this->month = now()->format('Y-m');
        $this->day = now()->toDateString();
    }

    public function render(ConsumptionCalculator $consumption)
    {
        // Satu-satunya sumber id meter yang dipakai query. Nilai di luar daftar
        // akses digagalkan di sini, bukan dibiarkan lolos ke query.
        $meterId = $this->safeMeterId();

        $monthStart = Carbon::parse($this->month.'-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $dailies = $meterId
            ? MeterReadingDaily::where('power_meter_id', $meterId)
                ->between($monthStart->toDateString(), $monthEnd->toDateString())
                ->orderBy('date')
                ->get()
            : collect();

        return view('livewire.portal.history-page', [
            'meters' => PowerMeter::whereIn('id', $this->meterIds())
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'dailies' => $dailies,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'hourly' => $this->hourlyUsage($meterId, $consumption),
            'monthly' => $this->monthlyUsage($meterId),
            'summary' => $this->summary($dailies),
        ]);
    }

    /**
     * Id meter terpilih yang sudah dipastikan boleh diakses; null bila akun
     * ini belum punya meter sama sekali.
     */
    private function safeMeterId(): ?int
    {
        if ($this->meterId === null) {
            return null;
        }

        return $this->scopedMeterIds($this->meterId)[0] ?? null;
    }

    /**
     * Pemakaian per jam pada tanggal terpilih, dihitung dari selisih stand
     * antar pembacaan — bukan MAX(stand)-MIN(stand) di SQL, yang salah besar
     * ketika meter di-reset di tengah jam.
     *
     * @return array<int, array{hour:int, lwbp:float, wbp:float}>
     */
    private function hourlyUsage(?int $meterId, ConsumptionCalculator $consumption): array
    {
        if (!$meterId) {
            return [];
        }

        $date = Carbon::parse($this->day);

        $readings = MeterReading::query()
            ->where('power_meter_id', $meterId)
            ->between($date->copy()->startOfDay()->toDateTimeString(), $date->copy()->endOfDay()->toDateTimeString())
            ->orderBy('read_at')
            ->get(['read_at', 'stand_lwbp', 'stand_wbp']);

        $hours = $consumption->byHour($readings, PowerMeter::find($meterId)?->effective_stand_max);

        // Seluruh 24 jam selalu dikembalikan supaya sumbu chart tetap utuh
        // walau ada jam yang tidak menerima data.
        $result = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $result[] = [
                'hour' => $hour,
                'lwbp' => $hours[$hour]['lwbp'],
                'wbp' => $hours[$hour]['wbp'],
            ];
        }

        return $result;
    }

    /**
     * Total kWh 12 bulan terakhir.
     *
     * @return array<int, array{label:string, total:float}>
     */
    private function monthlyUsage(?int $meterId): array
    {
        if (!$meterId) {
            return [];
        }

        $from = now()->copy()->subMonths(11)->startOfMonth();

        $rows = MeterReadingDaily::query()
            ->where('power_meter_id', $meterId)
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

    /** Ringkasan periode untuk panel kanan. */
    private function summary($dailies): array
    {
        $lwbp = (float) $dailies->sum('kwh_lwbp');
        $wbp = (float) $dailies->sum('kwh_wbp');
        $total = $lwbp + $wbp;
        $days = $dailies->count();
        $peak = $dailies->whereNotNull('peak_kw')->sortByDesc('peak_kw')->first();

        return [
            'total' => $total,
            'lwbp' => $lwbp,
            'wbp' => $wbp,
            'daily_average' => $days > 0 ? $total / $days : 0,
            'peak_kw' => $peak?->peak_kw,
            'peak_at' => $peak?->peak_at,
        ];
    }
}
