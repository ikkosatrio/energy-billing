<?php

namespace App\Livewire\Report;

use App\Models\Customer;
use App\Services\Report\ReportService;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Laporan pembayaran — satu baris per transaksi (bukan per invoice), supaya
 * cicilan/pembayaran sebagian tetap tertelusuri satu-satu. Dilengkapi
 * ringkasan tunggakan saat ini (aging + invoice sebagian) yang tidak
 * terikat filter tanggal bayar di bawahnya, karena menjawab pertanyaan yang
 * berbeda ("berapa yang masih harus ditagih sekarang").
 */
class PaymentReportPage extends Component
{
    public string $from = '';

    public string $to = '';

    public ?int $customerId = null;

    /** Kosong = semua metode. */
    public string $method = '';

    public bool $partialOnly = false;

    public function mount(): void
    {
        // Default bulan berjalan — konsisten dengan halaman Pembayaran yang
        // juga berorientasi ke transaksi terbaru.
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->endOfMonth()->toDateString();
    }

    public function render(ReportService $reports)
    {
        $rows = $reports->payments(
            Carbon::parse($this->from),
            Carbon::parse($this->to),
            $this->customerId,
            $this->method ?: null,
            $this->partialOnly,
        );

        return view('livewire.report.payment-report-page', [
            'rows' => $rows,
            'totals' => $reports->totals($rows, ['amount']),
            // Dijumlah per metode supaya rekonsiliasi ke rekening bank/kas
            // tidak perlu menjumlah manual dari tabel transaksi.
            'methodTotals' => [
                'transfer' => (float) $rows->where('method', 'transfer')->sum('amount'),
                'cash' => (float) $rows->where('method', 'cash')->sum('amount'),
                'other' => (float) $rows->where('method', 'other')->sum('amount'),
            ],
            'tracking' => $reports->paymentTracking(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'exportQuery' => http_build_query([
                'from' => $this->from,
                'to' => $this->to,
                'customer_id' => $this->customerId,
                'method' => $this->method,
                'partial_only' => $this->partialOnly ? 1 : 0,
            ]),
        ]);
    }
}
