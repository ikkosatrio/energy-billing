<?php

namespace App\Http\Controllers\Report;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\PowerMeter;
use App\Services\Report\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function usage()
    {
        return view('report.usage.index');
    }

    public function billing()
    {
        return view('report.billing.index');
    }

    public function payments()
    {
        return view('report.payments.index');
    }

    public function readings()
    {
        return view('report.readings.index');
    }

    /**
     * Export laporan ke Excel atau PDF.
     *
     * Rentang tanggal dan filter dibaca dari query string agar tautan export
     * bisa dibentuk langsung dari state komponen Livewire.
     */
    public function export(Request $request, string $type, string $format)
    {
        $this->authorize('report.export');

        abort_unless(in_array($type, ['usage', 'billing', 'payments', 'readings'], true), 404);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        // Data mentah hanya masuk akal sebagai Excel — ribuan baris tidak
        // terbaca di PDF.
        abort_if($type === 'readings' && $format === 'pdf', 404);

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'customer_id' => ['nullable', 'integer'],
            'method' => ['nullable', 'in:transfer,cash,other'],
            'partial_only' => ['nullable', 'boolean'],
            // Wajib untuk data mentah: tabelnya terlalu besar untuk dibaca
            // lintas meter sekaligus.
            'meter_id' => [$type === 'readings' ? 'required' : 'nullable', 'integer', 'exists:power_meters,id'],
        ]);

        $from = Carbon::parse($validated['from']);
        $to = Carbon::parse($validated['to']);
        $customerId = $validated['customer_id'] ?? null;

        [$rows, $headings, $title] = match ($type) {
            'usage' => [$this->reports->usage($from, $to, $customerId), $this->usageHeadings(), 'Rekap Pemakaian kWh'],
            'billing' => [$this->reports->billing($from, $to, $customerId), $this->billingHeadings(), 'Rekap Tagihan & Penerimaan'],
            'payments' => [
                $this->reports->payments(
                    $from, $to, $customerId,
                    $validated['method'] ?? null,
                    (bool) ($validated['partial_only'] ?? false),
                )->map(fn ($row) => [
                    ...$row,
                    // Label, bukan slug status — konsisten dengan yang
                    // terlihat di layar lewat <x-invoice-status>.
                    'invoice_status' => \App\Models\Invoice::STATUS_LABELS[$row['invoice_status']] ?? $row['invoice_status'],
                ]),
                $this->paymentHeadings(),
                'Laporan Pembayaran',
            ],
            'readings' => [
                $this->reports->rawReadings((int) $validated['meter_id'], $from, $to),
                $this->readingHeadings((int) $validated['meter_id']),
                'Data Meter Mentah',
            ],
        };

        $filename = str($title)->slug().'-'.$from->format('Ymd').'-'.$to->format('Ymd');

        if ($format === 'xlsx') {
            return Excel::download(new ReportExport($headings, $rows), "{$filename}.xlsx");
        }

        return Pdf::loadView('report.pdf', [
            'title' => $title,
            'headings' => $headings,
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'type' => $type,
        ])->setPaper('a4', 'landscape')->download("{$filename}.pdf");
    }

    private function usageHeadings(): array
    {
        return ['Pelanggan', 'Kode', 'Meter', 'Golongan', 'LWBP (kWh)', 'WBP (kWh)',
            'Total kWh', 'Beban Puncak (kW)', 'Tagihan (Rp)'];
    }

    private function readingHeadings(int $meterId): array
    {
        // Harus sama persis dengan kolom yang dibentuk ReportService::rawReadings.
        $lines = PowerMeter::find($meterId)?->isSinglePhase() ? ['R'] : ['R', 'S', 'T'];

        return array_merge(
            ['Waktu Baca', 'Stand LWBP', 'Stand WBP', 'Δ LWBP', 'Δ WBP', 'Daya (kW)'],
            array_map(fn ($line) => 'Tegangan '.$line, $lines),
            array_map(fn ($line) => 'Arus '.$line, $lines),
            ['Power Factor', 'Frekuensi', 'Sumber', 'Catatan'],
        );
    }

    private function billingHeadings(): array
    {
        return ['No Invoice', 'Pelanggan', 'Periode', 'Tanggal Terbit', 'Jatuh Tempo',
            'Total kWh', 'Tagihan (Rp)', 'Dibayar (Rp)', 'Sisa (Rp)', 'Status'];
    }

    private function paymentHeadings(): array
    {
        return ['Tanggal Bayar', 'No Invoice', 'Pelanggan', 'Jumlah (Rp)', 'Metode',
            'Dicatat Oleh', 'Sumber', 'Status Invoice', 'Sisa Invoice (Rp)'];
    }
}
