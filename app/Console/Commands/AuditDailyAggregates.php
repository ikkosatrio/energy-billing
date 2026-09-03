<?php

namespace App\Console\Commands;

use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use App\Services\Billing\UsageCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Membandingkan agregat harian dengan cara hitung yang dipakai invoice.
 *
 * Chart bulanan di Energy History menjumlahkan `meter_reading_dailies`,
 * sedangkan invoice menghitung ulang dari stand meter. Keduanya HARUS sama;
 * kalau berbeda, penyebabnya bukan rumus yang berbeda melainkan isi tabel
 * agregat yang basi atau bolong — dan chart-lah yang salah, bukan invoice.
 *
 * Perintah ini menunjukkan meter dan bulan mana yang terdampak beserta
 * tanggal yang agregatnya hilang, supaya perbaikannya bisa diarahkan:
 *
 *   php artisan readings:aggregate --month=2026-08
 */
class AuditDailyAggregates extends Command
{
    protected $signature = 'readings:audit
                            {--meter= : Kode power meter tertentu, mis. PM001.}
                            {--month= : Satu bulan (Y-m), mis. 2026-08.}
                            {--from= : Awal rentang bulan (Y-m).}
                            {--to= : Akhir rentang bulan (Y-m); default bulan ini.}
                            {--months=12 : Berapa bulan terakhir bila tidak ada --month/--from.}
                            {--tolerance=0.05 : Selisih kWh yang masih dianggap wajar (pembulatan).}
                            {--all : Tampilkan juga yang sudah cocok.}';

    protected $description = 'Memeriksa agregat harian terhadap perhitungan invoice';

    public function handle(UsageCalculator $calculator): int
    {
        $meters = PowerMeter::query()
            ->when($this->option('meter'), fn ($q) => $q->where('code', $this->option('meter')))
            ->orderBy('code')
            ->get();

        if ($meters->isEmpty()) {
            $this->error('Power meter tidak ditemukan.');

            return self::FAILURE;
        }

        try {
            $months = $this->months();
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $tolerance = (float) $this->option('tolerance');
        $rows = [];
        $bermasalah = 0;
        $dilewati = 0;

        foreach ($meters as $meter) {
            foreach ($months as $month) {
                $start = $month->copy()->startOfMonth();
                $end = min($month->copy()->endOfMonth(), Carbon::today());

                $query = MeterReadingDaily::where('power_meter_id', $meter->id)
                    ->whereBetween('date', [$start->toDateString(), $end->toDateString()]);

                $hari = (clone $query)->count();

                // Bulan tanpa agregat sama sekali bukan temuan: meter memang
                // bisa belum terpasang saat itu.
                if ($hari === 0) {
                    continue;
                }

                /*
                 * Bulan yang pembacaan mentahnya sudah dibuang retensi TIDAK
                 * bisa dibandingkan — dan itu bukan kerusakan, melainkan
                 * rancangannya: agregat harian sengaja tidak pernah ikut
                 * dihapus supaya riwayat lama tetap utuh.
                 *
                 * Tanpa pengecualian ini, setiap bulan di luar masa retensi
                 * dilaporkan "invoice 0, selisih -sekian" — laporan yang
                 * seluruhnya keliru dan menenggelamkan temuan yang sungguhan.
                 */
                if (!$this->punyaPembacaanMentah($meter, $start, $end)) {
                    $dilewati++;

                    continue;
                }

                $harian = (float) (clone $query)->sum(DB::raw('kwh_lwbp + kwh_wbp'));
                $invoice = $calculator->forPeriod($meter, $start, $end);
                $totalInvoice = $invoice['kwh_lwbp'] + $invoice['kwh_wbp'];
                $selisih = $totalInvoice - $harian;

                $cocok = abs($selisih) <= $tolerance;

                if ($cocok && !$this->option('all')) {
                    continue;
                }

                if (!$cocok) {
                    $bermasalah++;
                }

                $rows[] = [
                    $meter->code,
                    $month->format('Y-m'),
                    $hari.'/'.$start->diffInDays($end) + 1,
                    number_format($harian, 2, ',', '.'),
                    number_format($totalInvoice, 2, ',', '.'),
                    number_format($selisih, 2, ',', '.'),
                    $cocok ? 'cocok' : $this->dugaan($meter, $start, $end, $selisih),
                ];
            }
        }

        if ($dilewati > 0) {
            $this->line("{$dilewati} meter-bulan dilewati: pembacaan mentahnya sudah dibuang retensi, "
                .'jadi tidak ada pembanding. Agregat hariannya tetap sah.');
        }

        if (empty($rows)) {
            $this->info('Semua agregat harian cocok dengan perhitungan invoice.');

            return self::SUCCESS;
        }

        $this->table(
            ['Meter', 'Bulan', 'Hari', 'Chart (kWh)', 'Invoice (kWh)', 'Selisih', 'Dugaan'],
            $rows,
        );

        if ($bermasalah > 0) {
            $this->warn("{$bermasalah} meter-bulan tidak cocok. Perbaiki dengan:");
            $this->line('  php artisan readings:aggregate --month=YYYY-MM');
        }

        return self::SUCCESS;
    }

    /**
     * Apakah periode ini masih punya pembacaan mentah untuk dibandingkan.
     *
     * Retensi membuang `meter_readings` lama tapi menyimpan agregat hariannya,
     * jadi ketiadaan pembacaan di sini berarti "tidak bisa diperiksa", bukan
     * "datanya rusak".
     */
    private function punyaPembacaanMentah(PowerMeter $meter, Carbon $start, Carbon $end): bool
    {
        return DB::table('meter_readings')
            ->where('power_meter_id', $meter->id)
            ->whereBetween('read_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->exists();
    }

    /**
     * Menebak penyebabnya dari bentuk datanya, supaya perbaikannya tidak
     * perlu ditebak-tebak sendiri.
     */
    private function dugaan(PowerMeter $meter, Carbon $start, Carbon $end, float $selisih): string
    {
        $adaTanggalHilang = $this->tanggalHilang($meter, $start, $end);

        if ($adaTanggalHilang !== []) {
            $contoh = implode(', ', array_slice($adaTanggalHilang, 0, 3));
            $sisa = count($adaTanggalHilang) > 3 ? ' (+'.(count($adaTanggalHilang) - 3).' lagi)' : '';

            return count($adaTanggalHilang).' hari tanpa agregat: '.$contoh.$sisa;
        }

        $reset = (int) MeterReadingDaily::where('power_meter_id', $meter->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->sum('reset_count');

        if ($reset > 0) {
            return "agregat basi pada bulan ber-reset ({$reset} reset)";
        }

        return $selisih > 0 ? 'agregat lebih rendah dari stand' : 'agregat lebih tinggi dari stand';
    }

    /**
     * Tanggal yang punya pembacaan mentah tapi tidak punya baris agregat —
     * inilah yang membuat chart membaca lebih rendah daripada invoice.
     *
     * @return array<int, string>
     */
    private function tanggalHilang(PowerMeter $meter, Carbon $start, Carbon $end): array
    {
        $adaPembacaan = DB::table('meter_readings')
            ->where('power_meter_id', $meter->id)
            ->whereBetween('read_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw('DATE(read_at) AS tanggal')
            ->distinct()
            ->pluck('tanggal')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();

        $adaAgregat = MeterReadingDaily::where('power_meter_id', $meter->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        return array_values(array_diff($adaPembacaan, $adaAgregat));
    }

    /**
     * Bulan-bulan yang diperiksa.
     *
     * @return array<int, Carbon>
     */
    private function months(): array
    {
        if ($month = $this->option('month')) {
            return [$this->bulan($month, '--month')];
        }

        if ($from = $this->option('from')) {
            $awal = $this->bulan($from, '--from');
            $akhir = $this->option('to')
                ? $this->bulan($this->option('to'), '--to')
                : Carbon::today()->startOfMonth();

            if ($akhir->lt($awal)) {
                throw new \InvalidArgumentException('--to tidak boleh sebelum --from.');
            }

            $months = [];

            for ($m = $awal->copy(); $m->lte($akhir); $m->addMonth()) {
                $months[] = $m->copy();
            }

            return $months;
        }

        $jumlah = max(1, (int) $this->option('months'));
        $months = [];

        for ($i = $jumlah - 1; $i >= 0; $i--) {
            $months[] = Carbon::today()->subMonths($i)->startOfMonth();
        }

        return $months;
    }

    /**
     * Membaca opsi bulan berformat Y-m.
     *
     * Carbon::parse() menerima hampir apa saja dan melempar exception mentah
     * untuk sisanya — di terminal produksi itu muncul sebagai stack trace
     * yang tidak memberi tahu apa yang salah diketik.
     */
    private function bulan(string $nilai, string $opsi): Carbon
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $nilai)) {
            throw new \InvalidArgumentException("Format {$opsi} harus Y-m, mis. 2026-08. Diberikan: {$nilai}");
        }

        return Carbon::createFromFormat('Y-m-d', $nilai.'-01')->startOfMonth();
    }
}
