<?php

namespace App\Livewire\Billing;

use App\Models\BillingPeriod;
use App\Models\Invoice;
use App\Models\PaymentBatch;
use App\Services\ActivityLogger;
use App\Services\Billing\BulkPaymentService;
use App\Services\Billing\InvoiceDocumentService;
use Livewire\Component;
use Livewire\WithPagination;

class InvoicePage extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public ?int $periodFilter = null;

    /** Invoice yang sedang dibuka detailnya. */
    public ?int $detailId = null;

    /**
     * Invoice yang dicentang untuk pelunasan massal.
     *
     * @var array<int, string>
     */
    public array $selected = [];

    public bool $showBulk = false;

    /** @var array<string, ?string> */
    public array $bulkForm = [];

    /** Ringkasan operasi massal terakhir; bertahan sampai ditutup operator. */
    public ?array $bulkResult = null;

    /**
     * Periode terbaru dipilih lebih dulu — itu yang hampir selalu sedang
     * dikerjakan, dan tanpa ini halaman membuka seluruh riwayat tagihan yang
     * harus disaring manual tiap kali dibuka.
     *
     * Dipilih periode paling akhir, bukan yang kodenya sama dengan bulan
     * berjalan: penagihan sering baru dijalankan awal bulan berikutnya,
     * sehingga bulan berjalan kerap belum punya periode sama sekali dan
     * filternya akan kosong justru saat paling dibutuhkan.
     */
    public function mount(): void
    {
        $this->periodFilter = BillingPeriod::orderByDesc('period_start')->value('id');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedPeriodFilter(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    /**
     * Centangan dibuang setiap kali daftarnya berganti.
     *
     * Kalau dipertahankan, operator bisa mencentang lima invoice, mengganti
     * filter, lalu menekan "Tandai Lunas" dan melunasi invoice yang sudah
     * tidak terlihat di layar.
     */
    private function clearSelection(): void
    {
        $this->selected = [];
    }

    public function updatedPage(): void
    {
        $this->clearSelection();
    }

    public function show(int $id): void
    {
        $this->authorize('invoice.view');
        $this->detailId = $id;
    }

    /**
     * Menerbitkan invoice: draft menjadi resmi ditagihkan.
     */
    public function issue(int $id): void
    {
        $this->authorize('invoice.update');

        $invoice = Invoice::findOrFail($id);

        if ($invoice->status !== 'draft') {
            $this->dispatch('toast', type: 'error', message: 'Hanya invoice draft yang bisa diterbitkan.');

            return;
        }

        $invoice->update(['status' => 'issued']);
        ActivityLogger::log('issue_invoice', $invoice, "Terbitkan invoice {$invoice->invoice_no}");

        $this->dispatch('toast', type: 'success', message: "Invoice {$invoice->invoice_no} diterbitkan.");
    }

    public function sendEmail(int $id, InvoiceDocumentService $documents): void
    {
        $this->authorize('invoice.send');

        $invoice = Invoice::with('customer')->findOrFail($id);

        try {
            $documents->email($invoice);
            $this->dispatch('toast', type: 'success', message: "Invoice dikirim ke {$invoice->customer->email}.");
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Gagal mengirim: '.$e->getMessage());
        }
    }

    /**
     * Membatalkan invoice. Tidak dihapus karena nomornya sudah terpakai dan
     * mungkin sudah beredar ke pelanggan — yang dibatalkan tetap tersimpan
     * lengkap dengan jejak siapa, kapan, dan alasannya.
     */
    public function cancel(int $id, ?string $reason = null): void
    {
        $this->authorize('invoice.delete');

        $invoice = Invoice::findOrFail($id);

        if (! $invoice->isCancellable()) {
            $this->dispatch('toast', type: 'error', message: 'Invoice dengan status ini tidak bisa dibatalkan.');

            return;
        }

        if ($invoice->payments()->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Invoice sudah punya pembayaran. Hapus pembayarannya dulu.');

            return;
        }

        $reason = trim((string) $reason) ?: null;

        $invoice->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
            'cancelled_by' => auth()->id(),
        ])->save();

        ActivityLogger::log('cancel_invoice', $invoice, "Batalkan invoice {$invoice->invoice_no}".($reason ? " — {$reason}" : ''));

        $this->dispatch('toast', type: 'warning', message: "Invoice {$invoice->invoice_no} dibatalkan.");
    }

    /**
     * Membuka kembali invoice yang terlanjur dibatalkan.
     *
     * Selalu kembali ke draft, tidak ke status sebelumnya: pembatalan sudah
     * tercetak di dokumen dan mungkin sudah dibaca pelanggan, jadi tagihannya
     * harus diterbitkan ulang secara sadar — bukan hidup lagi diam-diam
     * sebagai piutang berjalan.
     */
    public function reopen(int $id): void
    {
        $this->authorize('invoice.reopen');

        $invoice = Invoice::findOrFail($id);

        if (! $invoice->isCancelled()) {
            $this->dispatch('toast', type: 'error', message: 'Hanya invoice yang dibatalkan yang bisa dibuka kembali.');

            return;
        }

        $invoice->forceFill([
            'status' => 'draft',
            'cancelled_at' => null,
            'cancel_reason' => null,
            'cancelled_by' => null,
        ])->save();

        ActivityLogger::log('reopen_invoice', $invoice, "Buka kembali invoice {$invoice->invoice_no} sebagai draft");

        $this->dispatch('toast', type: 'success', message: "Invoice {$invoice->invoice_no} dibuka kembali sebagai draft.");
    }

    // ── Pelunasan massal ─────────────────────────────────────────────────

    /**
     * Mencentang seluruh invoice di halaman ini yang memang bisa dibayar.
     *
     * Yang draft, batal, atau sudah lunas sengaja dilewati: mencentangnya
     * hanya menghasilkan daftar "dilewati" yang panjang setelah operasi.
     */
    public function selectAllPayable(): void
    {
        $this->selected = $this->payableOnPage()->map(fn ($id) => (string) $id)->all();
    }

    public function toggleSelectAll(): void
    {
        $this->selected ? $this->clearSelection() : $this->selectAllPayable();
    }

    public function openBulk(): void
    {
        $this->authorize('payment.bulk');

        if (empty($this->selected)) {
            $this->dispatch('toast', type: 'warning', message: 'Pilih dulu invoice yang akan dilunasi.');

            return;
        }

        $this->bulkForm = [
            'payment_date' => now()->toDateString(),
            'method' => 'transfer',
            'notes' => '',
        ];

        $this->showBulk = true;
    }

    public function bulkMarkPaid(BulkPaymentService $bulk): void
    {
        $this->authorize('payment.bulk');

        $validated = $this->validate([
            'bulkForm.payment_date' => ['required', 'date'],
            'bulkForm.method' => ['required', 'in:transfer,cash,other'],
            'bulkForm.notes' => ['nullable', 'string', 'max:500'],
        ], attributes: [
            'bulkForm.payment_date' => 'tanggal bayar',
            'bulkForm.method' => 'metode',
        ])['bulkForm'];

        $result = $bulk->markPaid(
            array_map('intval', $this->selected),
            $validated['payment_date'],
            $validated['method'],
            $validated['notes'] ?: null,
        );

        $this->bulkResult = [
            'created' => $result['created'],
            'total' => $result['total'],
            'skipped' => $result['skipped'],
            'batch_id' => $result['batch']?->id,
        ];

        $this->showBulk = false;
        $this->clearSelection();

        $this->dispatch(
            'toast',
            type: $result['created'] ? 'success' : 'warning',
            message: $result['created']
                ? "{$result['created']} invoice ditandai lunas."
                : 'Tidak ada invoice yang bisa dilunasi dari pilihan itu.',
        );
    }

    /** Menarik kembali seluruh pembayaran dari operasi massal barusan. */
    public function revertBulk(int $batchId, BulkPaymentService $bulk): void
    {
        $this->authorize('payment.bulk');

        $batch = PaymentBatch::findOrFail($batchId);
        $count = $bulk->revert($batch);

        $this->bulkResult = null;

        $this->dispatch(
            'toast',
            type: $count ? 'success' : 'warning',
            message: $count
                ? "{$count} pembayaran ditarik kembali."
                : 'Batch ini sudah pernah dibatalkan.',
        );
    }

    /**
     * ID invoice di halaman aktif yang layak dibayar.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function payableOnPage()
    {
        $bulk = app(BulkPaymentService::class);

        return $this->pageInvoices()
            ->filter(fn (Invoice $invoice) => $bulk->rejectionReason($invoice) === null)
            ->pluck('id');
    }

    /** Daftar invoice sesuai filter yang sedang aktif. */
    private function query()
    {
        return Invoice::query()
            ->when($this->search, fn ($q) => $q->where(function ($sub) {
                $sub->where('invoice_no', 'like', "%{$this->search}%")
                    ->orWhere('customer_name', 'like', "%{$this->search}%")
                    ->orWhere('meter_code', 'like', "%{$this->search}%");
            }))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->periodFilter, fn ($q) => $q->where('billing_period_id', $this->periodFilter))
            ->orderByDesc('issue_date')
            ->orderByDesc('id');
    }

    /** @return \Illuminate\Support\Collection<int, Invoice> */
    private function pageInvoices()
    {
        return $this->query()->paginate(20)->getCollection();
    }

    public function render(InvoiceDocumentService $documents)
    {
        $invoices = $this->query()->paginate(20);

        $detail = $this->detailId ? Invoice::with('payments')->find($this->detailId) : null;

        return view('livewire.billing.invoice-page', [
            'invoices' => $invoices,
            'periods' => BillingPeriod::orderByDesc('period_start')->limit(24)->get(['id', 'code']),
            'summary' => $this->summary(),
            'detail' => $detail,
            // Baris uraian & total disusun oleh service yang sama dengan PDF,
            // supaya angka di layar dan di dokumen tidak pernah berbeda.
            'detailLines' => $detail ? $documents->lines($detail) : [],
            'detailTotals' => $detail ? $documents->totals($detail) : [],
            // Dipakai blade untuk menonaktifkan centang pada invoice yang
            // memang tidak bisa dibayar, memakai aturan yang sama persis
            // dengan yang nanti dipakai saat menyimpan.
            'bulkService' => app(BulkPaymentService::class),
        ]);
    }

    /**
     * Kartu ringkasan di atas tabel.
     */
    private function summary(): array
    {
        $unpaid = Invoice::unpaid()->get(['total_amount', 'paid_amount', 'status']);
        $lastMonth = now()->subMonth();

        return [
            'outstanding' => $unpaid->sum(fn ($i) => $i->outstanding),
            'overdue_count' => $unpaid->where('status', 'overdue')->count(),
            'paid_last_month' => (float) Invoice::whereBetween('issue_date', [
                $lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth(),
            ])->where('status', 'paid')->sum('total_amount'),
            'paid_last_month_label' => $lastMonth->translatedFormat('F Y'),
            'draft_count' => Invoice::where('status', 'draft')->count(),
        ];
    }
}
