<?php

namespace App\Livewire\Report;

use App\Models\MeterReading;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use App\Services\Report\ReportService;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Data mentah pembacaan meter — dipakai saat menelusuri angka tagihan yang
 * dianggap janggal, atau memeriksa gateway yang datanya bolong.
 *
 * Selalu terikat pada satu meter dan rentang tanggal: tabel `meter_readings`
 * bisa berisi jutaan baris (±43.000 per meter per bulan pada interval 1 menit).
 */
class ReadingReportPage extends Component
{
    use WithPagination;

    public ?int $meterId = null;

    public string $from = '';

    public string $to = '';

    /** Hanya tampilkan baris yang ditandai anomali. */
    public bool $onlyAnomalies = false;

    public int $perPage = 50;

    public function mount(): void
    {
        $this->meterId = PowerMeter::orderBy('code')->value('id');
        // Default 1 hari saja — rentang lebar pada data mentah jarang berguna
        // dan berat untuk dibaca.
        $this->from = now()->toDateString();
        $this->to = now()->toDateString();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['meterId', 'from', 'to', 'onlyAnomalies', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function render(ReportService $reports)
    {
        $meters = PowerMeter::orderBy('code')->get(['id', 'code', 'name', 'location']);

        if (!$this->meterId || $meters->isEmpty()) {
            return view('livewire.report.reading-report-page', [
                'meters' => $meters,
                'rows' => collect(),
                'readings' => null,
                'summary' => $this->emptySummary(),
                'exportQuery' => '',
            ]);
        }

        $from = Carbon::parse($this->from);
        $to = Carbon::parse($this->to);

        $readings = $reports->rawReadingsQuery($this->meterId, $from, $to)->paginate($this->perPage);

        // Pembacaan tepat sebelum baris pertama halaman ini. Tanpa itu, baris
        // pembuka setiap halaman tidak punya pembanding dan selalu terbaca
        // normal walau sebenarnya ada jeda atau stand yang mundur.
        $previous = $readings->isNotEmpty()
            ? MeterReading::where('power_meter_id', $this->meterId)
                ->where('read_at', '<', $readings->first()->read_at)
                ->orderByDesc('read_at')
                ->first()
            : null;

        $rows = collect($reports->flagAnomalies($readings->items(), $previous));

        if ($this->onlyAnomalies) {
            $rows = $rows->filter(fn ($row) => $row['is_anomaly'])->values();
        }

        return view('livewire.report.reading-report-page', [
            'meters' => $meters,
            'rows' => $rows,
            'readings' => $readings,
            'summary' => $this->summary($from, $to),
            'exportQuery' => http_build_query([
                'from' => $this->from,
                'to' => $this->to,
                'meter_id' => $this->meterId,
            ]),
        ]);
    }

    /**
     * Ringkasan seluruh rentang — dihitung terpisah dari halaman yang sedang
     * ditampilkan, supaya angkanya menggambarkan keseluruhan data.
     */
    private function summary(Carbon $from, Carbon $to): array
    {
        $stats = MeterReading::where('power_meter_id', $this->meterId)
            ->between($from->copy()->startOfDay()->toDateTimeString(), $to->copy()->endOfDay()->toDateTimeString())
            ->selectRaw('COUNT(*) AS jumlah, MIN(read_at) AS pertama, MAX(read_at) AS terakhir')
            ->first();

        $count = (int) ($stats->jumlah ?? 0);

        // Pemakaian diambil dari agregat harian, bukan dihitung ulang dari
        // pembacaan mentah: rentangnya bisa sebulan penuh (puluhan ribu baris)
        // dan agregat harian sudah menangani reset meter dengan benar.
        $daily = MeterReadingDaily::where('power_meter_id', $this->meterId)
            ->between($from->toDateString(), $to->toDateString())
            ->selectRaw('COALESCE(SUM(kwh_lwbp),0) AS lwbp,
                         COALESCE(SUM(kwh_wbp),0) AS wbp,
                         COALESCE(SUM(reset_count),0) AS resets')
            ->first();

        // Berapa banyak pembacaan yang seharusnya masuk pada rentang ini,
        // dipakai menghitung kelengkapan data.
        $interval = max(1, (int) setting('iot_push_interval_seconds', 60));
        $spanSeconds = $from->copy()->startOfDay()->diffInSeconds(
            min($to->copy()->endOfDay(), now())
        );
        $expected = max(1, (int) floor($spanSeconds / $interval));

        return [
            'count' => $count,
            'expected' => $expected,
            'completeness' => min(100, (int) round($count / $expected * 100)),
            'first_at' => $stats->pertama ? Carbon::parse($stats->pertama) : null,
            'last_at' => $stats->terakhir ? Carbon::parse($stats->terakhir) : null,
            'kwh_lwbp' => (float) ($daily->lwbp ?? 0),
            'kwh_wbp' => (float) ($daily->wbp ?? 0),
            'reset_count' => (int) ($daily->resets ?? 0),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'count' => 0, 'expected' => 0, 'completeness' => 0,
            'first_at' => null, 'last_at' => null, 'kwh_lwbp' => 0, 'kwh_wbp' => 0,
            'reset_count' => 0,
        ];
    }
}
