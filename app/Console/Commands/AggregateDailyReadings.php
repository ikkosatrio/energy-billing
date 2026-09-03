<?php

namespace App\Console\Commands;

use App\Services\Monitoring\DailyAggregationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AggregateDailyReadings extends Command
{
    protected $signature = 'readings:aggregate
                            {--date= : Tanggal yang diringkas (Y-m-d). Default: kemarin dan hari ini.}
                            {--today : Hanya hari ini, tanpa mengulang kemarin.}
                            {--from= : Awal rentang yang dibangun ulang (Y-m-d).}
                            {--to= : Akhir rentang; default hari ini.}
                            {--month= : Membangun ulang satu bulan penuh (Y-m). Jalan pintas untuk --from/--to.}';

    protected $description = 'Meringkas pembacaan meter menjadi agregat harian';

    public function handle(DailyAggregationService $service): int
    {
        try {
            $dates = $this->dates();
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // Rentang panjang bisa memakan waktu; beri tahu apa yang dikerjakan
        // supaya tidak terlihat menggantung.
        if (count($dates) > 2) {
            $this->info(sprintf(
                'Membangun ulang %d hari: %s s/d %s',
                count($dates),
                $dates[0]->toDateString(),
                end($dates)->toDateString(),
            ));
        }

        $total = 0;

        foreach ($dates as $date) {
            $count = $service->aggregateAll($date);
            $total += $count;

            // Rentang panjang cukup diringkas di akhir; baris per hari hanya
            // menenggelamkan pesan yang penting.
            if (count($dates) <= 31) {
                $this->line("{$date->toDateString()}: {$count} meter diringkas.");
            }
        }

        if (count($dates) > 2) {
            $this->info("Selesai. {$total} baris agregat diperbarui.");
        }

        return self::SUCCESS;
    }

    /**
     * Tanggal-tanggal yang akan diringkas.
     *
     * Tanpa opsi apa pun, hari ini ikut diproses agar chart bulan berjalan
     * sudah terisi sebelum tengah malam; kemarin diproses ulang untuk
     * menangkap pembacaan yang datang terlambat dari gateway.
     *
     * --from/--to (dan --month) ada untuk memperbaiki hari yang TERLEWAT.
     * Jadwal rutin hanya menyentuh kemarin dan hari ini, jadi hari yang
     * agregatnya gagal dibuat — scheduler mati, deploy, atau pembacaan yang
     * baru masuk belakangan — tidak akan pernah terkejar sendiri, dan
     * chart bulanan akan terus membaca lebih rendah daripada invoice
     * selamanya.
     *
     * @return array<int, Carbon>
     */
    private function dates(): array
    {
        if ($month = $this->option('month')) {
            $start = Carbon::createFromFormat('Y-m', $month)?->startOfMonth();

            if (!$start) {
                throw new \InvalidArgumentException("Format --month harus Y-m, mis. 2026-08. Diberikan: {$month}");
            }

            // Bulan berjalan berhenti di hari ini: tanggal yang belum terjadi
            // tidak punya pembacaan, jadi hanya menghabiskan query.
            return $this->range($start, min($start->copy()->endOfMonth(), Carbon::today()));
        }

        if ($from = $this->option('from')) {
            $start = Carbon::parse($from)->startOfDay();
            $end = $this->option('to') ? Carbon::parse($this->option('to'))->startOfDay() : Carbon::today();

            if ($end->lt($start)) {
                throw new \InvalidArgumentException('--to tidak boleh sebelum --from.');
            }

            return $this->range($start, $end);
        }

        return match (true) {
            (bool) $this->option('date') => [Carbon::parse($this->option('date'))],
            (bool) $this->option('today') => [Carbon::today()],
            default => [Carbon::yesterday(), Carbon::today()],
        };
    }

    /** @return array<int, Carbon> */
    private function range(Carbon $start, Carbon $end): array
    {
        $dates = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->copy();
        }

        return $dates;
    }
}
