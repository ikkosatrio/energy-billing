<?php

namespace App\Services\Tariff;

use App\Models\MeterTariffSchedule;

/**
 * Aturan jadwal WBP/LWBP sesuai panel "Aturan & Validasi" pada desain:
 *   - maksimal 12 periode
 *   - setiap jam mulai kelipatan 15 menit
 *   - tidak ada jam mulai yang sama
 *   - total durasi seluruh periode tepat 24 jam
 *
 * Dipisah dari komponen Livewire supaya bisa diuji tanpa merender halaman.
 */
class ScheduleValidator
{
    /**
     * @param  array<int, array{start_time:string, tariff_type:string}>  $periods
     * @return array<int, string> Daftar pesan kesalahan; kosong berarti valid.
     */
    public function validate(array $periods): array
    {
        $errors = [];

        if (empty($periods)) {
            return ['Jadwal minimal berisi satu periode.'];
        }

        if (count($periods) > MeterTariffSchedule::MAX_PERIODS) {
            $errors[] = 'Jumlah periode tidak boleh lebih dari '.MeterTariffSchedule::MAX_PERIODS.'.';
        }

        $starts = [];

        foreach ($periods as $index => $period) {
            $no = $index + 1;
            $start = $period['start_time'] ?? '';

            if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $start)) {
                $errors[] = "Periode {$no}: jam mulai tidak valid.";

                continue;
            }

            if ($this->minutesOf($start) % MeterTariffSchedule::SLOT_MINUTES !== 0) {
                $errors[] = "Periode {$no}: jam mulai harus kelipatan ".MeterTariffSchedule::SLOT_MINUTES.' menit.';
            }

            if (in_array($start, $starts, true)) {
                $errors[] = "Periode {$no}: jam mulai {$start} duplikat.";
            }

            $starts[] = $start;

            if (!in_array($period['tariff_type'] ?? '', ['LWBP', 'WBP'], true)) {
                $errors[] = "Periode {$no}: jenis tarif harus LWBP atau WBP.";
            }
        }

        // Total 24 jam otomatis terpenuhi bila periode-periodenya bersambung
        // dan periode pertama mulai 00:00 — jadi itulah yang diperiksa.
        if (!$errors && !in_array('00:00', $starts, true)) {
            $errors[] = 'Periode pertama harus mulai pukul 00:00 agar total durasi genap 24 jam.';
        }

        return $errors;
    }

    /**
     * Menyusun ulang periode menjadi baris siap simpan: diurutkan berdasarkan
     * jam mulai, lalu end_time tiap baris diisi dari jam mulai baris
     * berikutnya. Baris terakhir ditutup di 00:00 (mewakili 24:00).
     *
     * @param  array<int, array{start_time:string, tariff_type:string}>  $periods
     * @return array<int, array{sequence:int, start_time:string, end_time:string, tariff_type:string}>
     */
    public function normalize(array $periods): array
    {
        usort($periods, fn ($a, $b) => $this->minutesOf($a['start_time']) <=> $this->minutesOf($b['start_time']));

        $rows = [];
        $count = count($periods);

        foreach ($periods as $index => $period) {
            $rows[] = [
                'sequence' => $index + 1,
                'start_time' => $period['start_time'],
                'end_time' => $index + 1 < $count ? $periods[$index + 1]['start_time'] : '00:00',
                'tariff_type' => $period['tariff_type'],
            ];
        }

        return $rows;
    }

    /**
     * Total menit per jenis tarif, untuk ringkasan "Total WBP / Total LWBP".
     *
     * @param  array<int, array{start_time:string, end_time:string, tariff_type:string}>  $rows
     * @return array{LWBP:int, WBP:int}
     */
    public function totals(array $rows): array
    {
        $totals = ['LWBP' => 0, 'WBP' => 0];

        foreach ($rows as $row) {
            $start = $this->minutesOf($row['start_time']);
            $end = $this->minutesOf($row['end_time']);
            // Baris terakhir berakhir di 00:00, jadi durasinya dihitung
            // memutari tengah malam.
            $totals[$row['tariff_type']] += $end > $start ? $end - $start : (1440 - $start) + $end;
        }

        return $totals;
    }

    public function minutesOf(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $hour * 60 + $minute;
    }
}
