<?php

namespace App\Services\Monitoring;

/**
 * Menghitung pemakaian kWh dari deretan pembacaan, tahan terhadap meter yang
 * di-reset maupun register yang berputar kembali ke nol.
 *
 * Cara naif punya dua kelemahan yang sama-sama berbahaya:
 *
 *   max(0, akhir - awal)  meter reset 9300 -> 0, lalu naik ke 80.
 *                         Hasil 0 — seluruh pemakaian periode itu hilang.
 *
 *   MAX(stand) - MIN()    Pada data yang sama menghasilkan 9300, padahal
 *                         pemakaian sebenarnya 380. Membengkak berkali lipat
 *                         dan tidak meninggalkan jejak apa pun.
 *
 * Yang dipakai di sini: jumlahkan selisih antar pembacaan berurutan. Selisih
 * negatif ditangani berbeda tergantung penyebabnya:
 *
 *   RESET    Meter diganti atau dinolkan teknisi dari angka yang masih jauh
 *            dari batas register. Pemakaian dihitung dari nol, yaitu sebesar
 *            angka barunya.
 *
 *   ROLLOVER Register penuh lalu berputar. Selain angka baru, sisa pemakaian
 *            antara pembacaan terakhir dan titik putar ikut dihitung —
 *            butuh `stand_max` pada data meter. Tanpa itu, rollover terpaksa
 *            diperlakukan sebagai reset dan sisanya hilang (besarnya terbatas
 *            pada pemakaian satu interval pembacaan).
 */
class ConsumptionCalculator
{
    /**
     * Ambang untuk menyimpulkan rollover: pembacaan sebelumnya harus sudah
     * mendekati batas register. Stand 9.000 yang jatuh ke 0 pada meter
     * berbatas 999.999 jelas bukan rollover, melainkan penggantian meter.
     */
    private const ROLLOVER_PROXIMITY = 0.9;

    /**
     * @param  iterable<object>  $readings   Urut menaik berdasarkan read_at,
     *                                       punya properti stand_lwbp & stand_wbp.
     * @param  float|null  $rolloverAt       Titik putar register, dalam satuan
     *                                       yang sama dengan kolom stand.
     * @return array{lwbp:float, wbp:float, reset_count:int, rollover_count:int}
     */
    public function fromReadings(iterable $readings, ?float $rolloverAt = null): array
    {
        $lwbp = 0.0;
        $wbp = 0.0;
        $resets = 0;
        $rollovers = 0;
        $prev = null;

        foreach ($readings as $reading) {
            $currentLwbp = (float) $reading->stand_lwbp;
            $currentWbp = (float) $reading->stand_wbp;

            if ($prev !== null) {
                [$addLwbp, $kindLwbp] = $this->step($prev[0], $currentLwbp, $rolloverAt);
                [$addWbp, $kindWbp] = $this->step($prev[1], $currentWbp, $rolloverAt);

                $lwbp += $addLwbp;
                $wbp += $addWbp;

                if ($kindLwbp === 'rollover' || $kindWbp === 'rollover') {
                    $rollovers++;
                } elseif ($kindLwbp === 'reset' || $kindWbp === 'reset') {
                    $resets++;
                }
            }

            $prev = [$currentLwbp, $currentWbp];
        }

        return [
            'lwbp' => $lwbp,
            'wbp' => $wbp,
            'reset_count' => $resets,
            'rollover_count' => $rollovers,
        ];
    }

    /**
     * Mengelompokkan pemakaian per jam dalam sehari.
     *
     * Pembacaan terakhir sebelum tiap jam ikut menjadi titik awal jam
     * berikutnya, sehingga pemakaian yang terjadi di antara dua jam tidak
     * hilang di batasnya.
     *
     * @param  iterable<object>  $readings  Urut menaik, satu meter, satu hari.
     * @return array<int, array{lwbp:float, wbp:float}>  Indeks 0–23.
     */
    public function byHour(iterable $readings, ?float $rolloverAt = null): array
    {
        $hours = [];
        for ($h = 0; $h < 24; $h++) {
            $hours[$h] = ['lwbp' => 0.0, 'wbp' => 0.0];
        }

        $prev = null;

        foreach ($readings as $reading) {
            $currentLwbp = (float) $reading->stand_lwbp;
            $currentWbp = (float) $reading->stand_wbp;
            $hour = (int) $reading->read_at->format('G');

            if ($prev !== null) {
                $hours[$hour]['lwbp'] += $this->step($prev[0], $currentLwbp, $rolloverAt)[0];
                $hours[$hour]['wbp'] += $this->step($prev[1], $currentWbp, $rolloverAt)[0];
            }

            $prev = [$currentLwbp, $currentWbp];
        }

        return $hours;
    }

    /**
     * Pemakaian antara dua pembacaan berurutan.
     *
     * @return array{0:float, 1:string}  [pemakaian, 'normal'|'reset'|'rollover']
     */
    private function step(float $previous, float $current, ?float $rolloverAt): array
    {
        $delta = $current - $previous;

        if ($delta >= 0) {
            return [$delta, 'normal'];
        }

        // Register penuh lalu berputar: sisa sampai titik putar ikut dihitung.
        if ($rolloverAt !== null && $previous >= $rolloverAt * self::ROLLOVER_PROXIMITY) {
            return [($rolloverAt - $previous) + $current, 'rollover'];
        }

        // Meter diganti atau dinolkan: pemakaian dihitung sejak nol.
        return [$current, 'reset'];
    }
}
