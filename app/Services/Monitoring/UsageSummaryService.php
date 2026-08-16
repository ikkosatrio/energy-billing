<?php

namespace App\Services\Monitoring;

use App\Models\MeterReading;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use App\Models\TariffRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ringkasan pemakaian untuk kartu Real-time Monitoring: hari ini, minggu
 * berjalan, bulan berjalan, dan hari terboros bulan ini — masing-masing
 * beserta perkiraan rupiahnya.
 *
 * Dua sumber data dipakai sekaligus, dengan alasan berbeda:
 *
 *   - Hari ini dihitung langsung dari `meter_readings`. Agregat harian baru
 *     terisi setelah job agregasi berjalan, jadi memakainya membuat angka hari
 *     ini tertinggal — padahal justru itu yang dipantau di halaman ini.
 *   - Hari-hari sebelumnya diambil dari `meter_readings_daily`. Membaca
 *     pembacaan mentah sebulan penuh untuk seluruh meter tiap kali polling
 *     akan menghabiskan memori.
 *
 * Rupiah di sini adalah **biaya energi saja** (kWh × tarif LWBP/WBP). Biaya
 * beban, admin, PPJ, dan PPN sengaja tidak ikut: ketiganya berlaku per bulan,
 * sehingga membaginya ke angka "hari ini" hanya menghasilkan angka yang
 * kelihatan pasti padahal mengada-ada. Nominal tagihan yang sah tetap datang
 * dari invoice.
 */
class UsageSummaryService
{
    public function __construct(private readonly ConsumptionCalculator $consumption)
    {
    }

    /**
     * @param  Collection<int, PowerMeter>  $meters  sudah memuat relasi customer.tariffGroup
     * @return array<int, array{
     *     today: array{kwh:float, rp:?float},
     *     week: array{kwh:float, rp:?float},
     *     month: array{kwh:float, rp:?float},
     *     peak: ?array{date:Carbon, kwh:float, rp:?float},
     *     days: array<int, array{date:Carbon, kwh:float, is_today:bool, is_peak:bool}>,
     *     max_kwh: float,
     *     has_rate: bool,
     *     week_start: Carbon,
     *     span_label: string
     * }>
     */
    public function forMeters(Collection $meters): array
    {
        if ($meters->isEmpty()) {
            return [];
        }

        $today = now()->startOfDay();
        $monthStart = $today->copy()->startOfMonth();
        $weekStart = $today->copy()->startOfWeek();

        // Minggu berjalan bisa dimulai sebelum tanggal 1 — muat sejak yang
        // paling awal supaya totalnya tidak terpotong pergantian bulan.
        $from = $weekStart->lessThan($monthStart) ? $weekStart : $monthStart;

        $meterIds = $meters->pluck('id')->all();
        $rates = $this->ratesFor($meters, $today);
        $daily = $this->dailyByMeter($meterIds, $from, $today);
        $liveToday = $this->liveToday($meterIds);

        $summaries = [];

        foreach ($meters as $meter) {
            $rate = $rates[$meter->id] ?? null;
            $rows = $daily[$meter->id] ?? [];

            // Angka hari ini dari pembacaan mentah menggantikan barisnya di
            // agregat harian, bukan ditambahkan — kalau tidak, hari ini
            // terhitung dua kali.
            $rows[$today->toDateString()] = $liveToday[$meter->id] ?? ['lwbp' => 0.0, 'wbp' => 0.0];

            $summaries[$meter->id] = $this->summarise($rows, $rate, $today, $monthStart, $weekStart);
        }

        return $summaries;
    }

    /**
     * @param  array<string, array{lwbp:float, wbp:float}>  $rows  dikunci tanggal Y-m-d
     */
    private function summarise(array $rows, ?TariffRate $rate, Carbon $today, Carbon $monthStart, Carbon $weekStart): array
    {
        $days = [];
        $peakKey = null;
        $max = 0.0;

        // Seluruh tanggal bulan ini sampai hari ini selalu punya batang, juga
        // hari yang tidak berdata — celah pada grafik justru informasi.
        for ($date = $monthStart->copy(); $date->lessThanOrEqualTo($today); $date->addDay()) {
            $key = $date->toDateString();
            $kwh = $this->totalOf($rows[$key] ?? null);

            if ($kwh > $max) {
                $max = $kwh;
                $peakKey = $key;
            }

            $days[$key] = ['date' => $date->copy(), 'kwh' => $kwh, 'is_today' => $key === $today->toDateString()];
        }

        foreach ($days as $key => $day) {
            $days[$key]['is_peak'] = $key === $peakKey && $max > 0;
        }

        return [
            'today' => $this->figure($rows[$today->toDateString()] ?? null, $rate),
            'week' => $this->figure($this->sumRange($rows, $weekStart, $today), $rate),
            'month' => $this->figure($this->sumRange($rows, $monthStart, $today), $rate),
            'peak' => $peakKey === null ? null : [
                'date' => $days[$peakKey]['date'],
                'kwh' => $max,
            ] + $this->figure($rows[$peakKey] ?? null, $rate),
            'days' => array_values($days),
            'max_kwh' => $max,
            'has_rate' => $rate !== null,
            'week_start' => $weekStart,
            // Rentang sumbu grafik ditulis di judulnya, bukan sebagai label
            // sumbu tersendiri — satu elemen lebih sedikit untuk dibaca.
            'span_label' => '1–'.$today->translatedFormat('j M Y'),
        ];
    }

    /**
     * @param  array<string, array{lwbp:float, wbp:float}>  $rows
     * @return array{lwbp:float, wbp:float}
     */
    private function sumRange(array $rows, Carbon $start, Carbon $end): array
    {
        $total = ['lwbp' => 0.0, 'wbp' => 0.0];

        for ($date = $start->copy(); $date->lessThanOrEqualTo($end); $date->addDay()) {
            $row = $rows[$date->toDateString()] ?? null;

            $total['lwbp'] += (float) ($row['lwbp'] ?? 0);
            $total['wbp'] += (float) ($row['wbp'] ?? 0);
        }

        return $total;
    }

    /**
     * @param  ?array{lwbp:float, wbp:float}  $row
     * @return array{kwh:float, rp:?float}
     */
    private function figure(?array $row, ?TariffRate $rate): array
    {
        return [
            'kwh' => $this->totalOf($row),
            // Null, bukan nol: golongan yang belum punya tarif berlaku harus
            // terbaca sebagai "belum bisa dihitung", bukan "gratis".
            'rp' => $rate === null || $row === null
                ? null
                : round($row['lwbp'] * (float) $rate->rate_lwbp + $row['wbp'] * (float) $rate->rate_wbp),
        ];
    }

    /** @param  ?array{lwbp:float, wbp:float}  $row */
    private function totalOf(?array $row): float
    {
        return (float) ($row['lwbp'] ?? 0) + (float) ($row['wbp'] ?? 0);
    }

    /**
     * Agregat harian, dikelompokkan per meter lalu per tanggal.
     *
     * @param  array<int>  $meterIds
     * @return array<int, array<string, array{lwbp:float, wbp:float}>>
     */
    private function dailyByMeter(array $meterIds, Carbon $from, Carbon $to): array
    {
        return MeterReadingDaily::query()
            ->whereIn('power_meter_id', $meterIds)
            ->between($from->toDateString(), $to->toDateString())
            ->get(['power_meter_id', 'date', 'kwh_lwbp', 'kwh_wbp'])
            ->groupBy('power_meter_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($row) => [
                $row->date->toDateString() => [
                    'lwbp' => (float) $row->kwh_lwbp,
                    'wbp' => (float) $row->kwh_wbp,
                ],
            ])->all())
            ->all();
    }

    /**
     * Pemakaian hari ini dari pembacaan mentah.
     *
     * Dihitung di PHP, bukan MAX(stand)−MIN(stand) di SQL: meter yang di-reset
     * hari ini akan membuat selisih SQL membaca stand sebelum reset sebagai
     * pemakaian, dan menghasilkan angka raksasa.
     *
     * @param  array<int>  $meterIds
     * @return array<int, array{lwbp:float, wbp:float}>
     */
    public function liveToday(array $meterIds): array
    {
        $readings = MeterReading::query()
            ->whereIn('power_meter_id', $meterIds)
            ->between(now()->startOfDay()->toDateTimeString(), now()->toDateTimeString())
            ->orderBy('read_at')
            ->get(['power_meter_id', 'read_at', 'stand_lwbp', 'stand_wbp']);

        $rolloverAt = PowerMeter::whereIn('id', $meterIds)
            ->get(['id', 'stand_max', 'multiplier'])
            ->mapWithKeys(fn ($m) => [$m->id => $m->effective_stand_max]);

        return $readings->groupBy('power_meter_id')
            ->map(function ($rows, $meterId) use ($rolloverAt) {
                $usage = $this->consumption->fromReadings($rows, $rolloverAt[$meterId] ?? null);

                return ['lwbp' => $usage['lwbp'], 'wbp' => $usage['wbp']];
            })
            ->all();
    }

    /**
     * Tarif yang berlaku hari ini untuk tiap meter, lewat golongan pelanggannya.
     * Meter tanpa pelanggan — atau golongan tanpa tarif berlaku — tidak masuk.
     *
     * @param  Collection<int, PowerMeter>  $meters
     * @return array<int, TariffRate>
     */
    private function ratesFor(Collection $meters, Carbon $date): array
    {
        $groupIds = $meters->pluck('customer.tariff_group_id')->filter()->unique()->all();

        if (empty($groupIds)) {
            return [];
        }

        // Satu query untuk semua golongan; yang terpilih adalah tarif dengan
        // effective_from terbaru yang masih mencakup hari ini.
        $byGroup = TariffRate::query()
            ->whereIn('tariff_group_id', $groupIds)
            ->effectiveOn($date->toDateString())
            ->get()
            ->groupBy('tariff_group_id')
            ->map(fn ($rates) => $rates->first());

        return $meters->mapWithKeys(function (PowerMeter $meter) use ($byGroup) {
            $rate = $byGroup[$meter->customer?->tariff_group_id] ?? null;

            return $rate ? [$meter->id => $rate] : [];
        })->all();
    }
}
