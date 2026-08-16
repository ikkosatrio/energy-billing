<?php

namespace App\Livewire\Billing;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PaymentBatch;
use App\Services\ActivityLogger;
use App\Services\Billing\BulkPaymentService;
use App\Services\Billing\PaymentImportService;
use App\Services\Billing\ReceiptService;
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

    // ── Entri cepat ──────────────────────────────────────────────────────

    /** Nomor invoice yang diketik atau dipindai operator. */
    public string $quickInvoiceNo = '';

    public ?string $quickAmount = null;

    public string $quickDate = '';

    public string $quickMethod = 'transfer';

    // ── Impor berkas ─────────────────────────────────────────────────────

    public bool $showImport = false;

    /** Berkas impor yang diunggah. */
    public $importFile = null;

    /** @var array<int, array<string, mixed>> */
    public array $importRows = [];

    public ?array $importSummary = null;

    public ?array $importResult = null;

    public function mount(): void
    {
        $this->resetForm();
        $this->quickDate = now()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Begitu invoice dipilih di modal, nominalnya langsung terisi sisa
     * tagihan — kasus paling umum adalah pelunasan penuh, operator tinggal
     * mengubahnya kalau memang mau bayar sebagian. Kosong lagi kalau
     * invoice-nya dibatalkan pilih.
     */
    public function updatedFormInvoiceId(): void
    {
        $invoice = $this->form['invoice_id'] ? Invoice::find($this->form['invoice_id']) : null;

        $this->form['amount'] = $invoice?->outstanding;
    }

    /**
     * Pratinjau hasil pembayaran SEBELUM disimpan — apakah nominal ini akan
     * melunasi, menyisakan sebagian, atau melebihi tagihan. Dipakai baik di
     * modal maupun Entri Cepat supaya operator tidak perlu menebak dari
     * status yang baru berubah setelah tersimpan.
     *
     * @return ?array{remaining:float, kind:string}
     */
    private function paymentPreview(?Invoice $invoice, mixed $amount): ?array
    {
        if (!$invoice || $amount === null || $amount === '' || (float) $amount <= 0) {
            return null;
        }

        // Toleransi pembulatan yang sama dengan Invoice::refreshPaymentStatus(),
        // supaya pratinjau di sini selalu cocok dengan status yang benar-benar
        // tersimpan nanti.
        $remaining = (float) $invoice->outstanding - (float) $amount;

        return [
            'remaining' => $remaining,
            'kind' => match (true) {
                $remaining > 0.5 => 'partial',
                $remaining < -0.5 => 'overpaid',
                default => 'settles',
            },
        ];
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

    // ── Entri cepat ──────────────────────────────────────────────────────

    /**
     * Mengisi nominal dengan sisa tagihan begitu nomor invoice dikenali.
     *
     * Dipanggil saat operator berpindah dari kolom nomor invoice, sehingga
     * pelunasan penuh — kasus yang paling sering — cukup Tab lalu Enter.
     */
    public function updatedQuickInvoiceNo(): void
    {
        $invoice = $this->quickInvoice();

        $this->quickAmount = $invoice ? (string) $invoice->outstanding : null;
    }

    public function quickSave(): void
    {
        $this->authorize('payment.create');

        $invoice = $this->quickInvoice();

        if (!$invoice) {
            $this->dispatch('toast', type: 'error', message: 'Nomor invoice tidak ditemukan.');

            return;
        }

        $reason = app(BulkPaymentService::class)->rejectionReason($invoice);

        if ($reason) {
            $this->dispatch('toast', type: 'error', message: "Invoice {$invoice->invoice_no} {$reason}.");

            return;
        }

        $validated = $this->validate([
            'quickDate' => ['required', 'date'],
            'quickAmount' => ['required', 'numeric', 'min:0.01'],
            'quickMethod' => ['required', 'in:transfer,cash,other'],
        ], attributes: [
            'quickDate' => 'tanggal bayar',
            'quickAmount' => 'jumlah',
        ]);

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => $validated['quickDate'],
            'amount' => $validated['quickAmount'],
            'method' => $validated['quickMethod'],
            'recorded_by' => auth()->id(),
        ]);

        ActivityLogger::log(
            'created',
            $payment,
            "Catat pembayaran {$invoice->invoice_no} sebesar ".rupiah($validated['quickAmount']),
        );

        // Tanggal dan metode sengaja dipertahankan: operator biasanya
        // memasukkan sederet pembayaran dari mutasi hari yang sama.
        $this->quickInvoiceNo = '';
        $this->quickAmount = null;
        $this->resetErrorBag();

        $this->dispatch('toast', type: 'success', message: "{$invoice->invoice_no} — ".rupiah($validated['quickAmount']).' tercatat.');
        $this->dispatch('focus-quick-entry');
    }

    private function quickInvoice(): ?Invoice
    {
        $no = trim($this->quickInvoiceNo);

        return $no === '' ? null : Invoice::where('invoice_no', $no)->first();
    }

    // ── Impor berkas ─────────────────────────────────────────────────────

    public function openImport(): void
    {
        $this->authorize('payment.bulk');

        $this->reset(['importFile', 'importRows', 'importSummary', 'importResult']);
        $this->showImport = true;
    }

    /**
     * Membaca berkas dan menampilkan hasil pemeriksaan — belum menyimpan.
     */
    public function previewImport(PaymentImportService $importer): void
    {
        $this->authorize('payment.bulk');

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:4096'],
        ], attributes: ['importFile' => 'berkas']);

        $preview = $importer->preview($this->importFile->getRealPath());

        $this->importRows = $preview['rows'];
        $this->importSummary = [
            'valid' => $preview['valid'],
            'invalid' => $preview['invalid'],
            'total' => $preview['total'],
        ];

        if (empty($this->importRows)) {
            $this->dispatch('toast', type: 'warning', message: 'Berkas tidak berisi baris data.');
        }
    }

    public function commitImport(PaymentImportService $importer): void
    {
        $this->authorize('payment.bulk');

        if (empty($this->importRows)) {
            return;
        }

        $result = $importer->commit($this->importRows, $this->importFile?->getClientOriginalName() ?? 'impor');

        $this->importResult = [
            'created' => $result['created'],
            'total' => $result['total'],
            'failed' => $result['failed'],
            'batch_id' => $result['batch']?->id,
        ];

        $this->reset(['importFile', 'importRows', 'importSummary']);
        $this->showImport = false;

        $this->dispatch(
            'toast',
            type: $result['created'] ? 'success' : 'warning',
            message: $result['created']
                ? "{$result['created']} pembayaran diimpor."
                : 'Tidak ada baris yang bisa diimpor.',
        );
    }

    // ── Kuitansi ─────────────────────────────────────────────────────────

    public function sendReceipt(int $paymentId, ReceiptService $receipts): void
    {
        $this->authorize('payment.receipt');

        $payment = InvoicePayment::with('invoice.customer')->findOrFail($paymentId);

        try {
            $receipts->email($payment);

            $this->dispatch(
                'toast',
                type: 'success',
                message: "Kuitansi {$payment->refresh()->receipt_no} terkirim ke {$payment->invoice->customer->email}.",
            );
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Gagal mengirim: '.$e->getMessage());
        }
    }

    // ── Pembatalan batch ─────────────────────────────────────────────────

    /**
     * Menarik kembali seluruh pembayaran dari satu batch.
     *
     * Batch yang kuitansinya sudah terkirim ditolak di sini dan hanya bisa
     * dilanjutkan lewat forceRevertBatch() oleh pemegang izin khusus.
     */
    public function revertBatch(int $batchId, BulkPaymentService $bulk): void
    {
        $this->authorize('payment.bulk');

        try {
            $count = $bulk->revert(PaymentBatch::findOrFail($batchId));
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->importResult = null;

        $this->dispatch(
            'toast',
            type: $count ? 'success' : 'warning',
            message: $count
                ? "{$count} pembayaran ditarik kembali."
                : 'Batch ini sudah pernah dibatalkan.',
        );
    }

    /**
     * Membatalkan batch yang kuitansinya sudah dipegang pelanggan.
     *
     * Tiap pelanggan terdampak otomatis dikirimi pemberitahuan bahwa
     * kuitansinya batal — menariknya diam-diam berarti membiarkan mereka
     * memegang bukti yang tidak lagi berlaku tanpa tahu.
     */
    public function forceRevertBatch(int $batchId, ?string $reason, BulkPaymentService $bulk): void
    {
        $this->authorize('payment.force_revert');

        $batch = PaymentBatch::findOrFail($batchId);
        $notified = $bulk->sentReceiptCount($batch);

        $count = $bulk->revert($batch, force: true, reason: trim((string) $reason) ?: null);

        $this->importResult = null;

        $this->dispatch(
            'toast',
            type: $count ? 'warning' : 'error',
            message: $count
                ? "{$count} pembayaran ditarik kembali, {$notified} pelanggan diberi tahu kuitansinya batal."
                : 'Batch ini sudah pernah dibatalkan.',
        );
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
        // Hanya invoice yang masih punya sisa tagihan yang bisa dipilih.
        $openInvoices = Invoice::unpaid()
            ->orderByDesc('issue_date')
            ->get(['id', 'invoice_no', 'customer_name', 'total_amount', 'paid_amount']);

        // Invoice yang sedang diketik di entri cepat, untuk ditampilkan
        // sebagai konfirmasi sebelum operator menekan Enter.
        $quickInvoice = $this->quickInvoice();

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
            'openInvoices' => $openInvoices,
            'quickInvoice' => $quickInvoice,
            'formPreview' => $this->paymentPreview(
                $openInvoices->firstWhere('id', $this->form['invoice_id'] ?? null),
                $this->form['amount'] ?? null,
            ),
            'quickPreview' => $this->paymentPreview($quickInvoice, $this->quickAmount),
            // withCount kuitansi terkirim: menentukan apakah batch masih boleh
            // dibatalkan biasa, atau sudah butuh izin khusus.
            'batches' => PaymentBatch::with(['createdBy:id,name'])
                ->withCount(['payments as sent_receipts_count' => fn ($q) => $q->whereNotNull('receipt_sent_at')])
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
        ]);
    }
}
