<?php

namespace App\Http\Controllers\Report;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
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

    /**
     * Export laporan ke Excel atau PDF.
     *
     * Rentang tanggal dan filter dibaca dari query string agar tautan export
     * bisa dibentuk langsung dari state komponen Livewire.
     */
    public function export(Request $request, string $type, string $format)
    {
        $this->authorize('report.export');

        abort_unless(in_array($type, ['usage', 'billing'], true), 404);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'customer_id' => ['nullable', 'integer'],
        ]);

        $from = Carbon::parse($validated['from']);
        $to = Carbon::parse($validated['to']);
        $customerId = $validated['customer_id'] ?? null;

        [$rows, $headings, $title] = $type === 'usage'
            ? [$this->reports->usage($from, $to, $customerId), $this->usageHeadings(), 'Rekap Pemakaian kWh']
            : [$this->reports->billing($from, $to, $customerId), $this->billingHeadings(), 'Rekap Tagihan & Penerimaan'];

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
            'Total kWh', 'Beban Puncak (kW)', 'Hari Berdata', 'Tagihan (Rp)'];
    }

    private function billingHeadings(): array
    {
        return ['No Invoice', 'Pelanggan', 'Periode', 'Tanggal Terbit', 'Jatuh Tempo',
            'Total kWh', 'Tagihan (Rp)', 'Dibayar (Rp)', 'Sisa (Rp)', 'Status'];
    }
}
