<?php

namespace App\Livewire\Billing;

use App\Models\BillingPeriod;
use App\Models\Invoice;
use App\Services\ActivityLogger;
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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPeriodFilter(): void
    {
        $this->resetPage();
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
     * mungkin sudah beredar ke pelanggan.
     */
    public function cancel(int $id): void
    {
        $this->authorize('invoice.delete');

        $invoice = Invoice::findOrFail($id);

        if ($invoice->payments()->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Invoice sudah punya pembayaran. Hapus pembayarannya dulu.');

            return;
        }

        $invoice->update(['status' => 'cancelled']);
        ActivityLogger::log('cancel_invoice', $invoice, "Batalkan invoice {$invoice->invoice_no}");

        $this->dispatch('toast', type: 'warning', message: "Invoice {$invoice->invoice_no} dibatalkan.");
    }

    public function render(InvoiceDocumentService $documents)
    {
        $invoices = Invoice::query()
            ->when($this->search, fn ($q) => $q->where(function ($sub) {
                $sub->where('invoice_no', 'like', "%{$this->search}%")
                    ->orWhere('customer_name', 'like', "%{$this->search}%")
                    ->orWhere('meter_code', 'like', "%{$this->search}%");
            }))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->periodFilter, fn ($q) => $q->where('billing_period_id', $this->periodFilter))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20);

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
