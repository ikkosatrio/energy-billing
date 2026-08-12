<?php

namespace App\Livewire\Billing;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\ActivityLogger;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class PaymentPage extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $invoiceId = null;

    public array $form = [];

    /** Bukti transfer yang diunggah. */
    public $proof = null;

    public function mount(): void
    {
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'form.invoice_id' => ['required', 'exists:invoices,id'],
            'form.payment_date' => ['required', 'date'],
            'form.amount' => ['required', 'numeric', 'min:0.01'],
            'form.method' => ['required', 'in:transfer,cash,other'],
            'form.reference_no' => ['nullable', 'string', 'max:100'],
            'form.notes' => ['nullable', 'string'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.invoice_id' => 'invoice',
            'form.payment_date' => 'tanggal bayar',
            'form.amount' => 'jumlah',
            'proof' => 'bukti transfer',
        ];
    }

    public function create(?int $invoiceId = null): void
    {
        $this->authorize('payment.create');

        $this->resetForm();

        if ($invoiceId) {
            $invoice = Invoice::find($invoiceId);
            $this->form['invoice_id'] = $invoiceId;
            // Prefill sisa tagihan — kasus paling umum adalah pelunasan penuh.
            $this->form['amount'] = $invoice?->outstanding;
        }

        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('payment.create');

        $validated = $this->validate();
        $data = $validated['form'];

        $invoice = Invoice::findOrFail($data['invoice_id']);

        if (in_array($invoice->status, ['draft', 'cancelled'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Invoice draft atau batal tidak bisa menerima pembayaran.');

            return;
        }

        if ($this->proof) {
            $data['proof_path'] = $this->proof->store('payment-proofs', 'public');
        }

        $data['recorded_by'] = auth()->id();

        // Menyimpan pembayaran memicu refreshPaymentStatus() di model, yang
        // menghitung ulang paid_amount dan status invoice.
        $payment = InvoicePayment::create($data);

        ActivityLogger::log(
            'created',
            $payment,
            "Catat pembayaran {$invoice->invoice_no} sebesar ".rupiah($data['amount']),
        );

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Pembayaran tercatat.');
    }

    public function delete(int $id): void
    {
        $this->authorize('payment.delete');

        $payment = InvoicePayment::with('invoice')->findOrFail($id);
        $invoiceNo = $payment->invoice?->invoice_no;

        ActivityLogger::log('deleted', $payment, "Hapus pembayaran {$invoiceNo}");
        $payment->delete();

        $this->dispatch('toast', type: 'success', message: 'Pembayaran dihapus dan status invoice disesuaikan.');
    }

    private function resetForm(): void
    {
        $this->form = [
            'invoice_id' => null,
            'payment_date' => now()->toDateString(),
            'amount' => null,
            'method' => 'transfer',
            'reference_no' => '',
            'notes' => '',
        ];
        $this->proof = null;
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.billing.payment-page', [
            'payments' => InvoicePayment::query()
                ->with(['invoice:id,invoice_no,customer_name,total_amount', 'recordedBy:id,name'])
                ->when($this->search, fn ($q) => $q->whereHas('invoice', function ($sub) {
                    $sub->where('invoice_no', 'like', "%{$this->search}%")
                        ->orWhere('customer_name', 'like', "%{$this->search}%");
                }))
                ->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->paginate(20),
            // Hanya invoice yang masih punya sisa tagihan yang bisa dipilih.
            'openInvoices' => Invoice::unpaid()
                ->orderByDesc('issue_date')
                ->get(['id', 'invoice_no', 'customer_name', 'total_amount', 'paid_amount']),
        ]);
    }
}
