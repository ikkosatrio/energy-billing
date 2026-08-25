<?php

namespace App\Http\Controllers\Portal;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\Billing\InvoiceDocumentService;
use App\Services\Billing\ReceiptService;

/**
 * Unduhan dokumen portal: PDF invoice dan kuitansi.
 *
 * Dokumennya dibuat service yang sama dengan sisi staf, jadi angka dan tata
 * letaknya tidak mungkin berbeda antara yang dilihat pelanggan dan yang
 * dipegang bagian penagihan.
 */
class DocumentController extends PortalController
{
    public function invoice(Invoice $invoice, InvoiceDocumentService $documents)
    {
        $this->authorize('portal.invoice.view');
        $this->authorizeInvoice($invoice);

        // Aturan yang sama dengan daftar invoice — dokumen tidak boleh keluar
        // lewat URL langsung untuk invoice yang di daftar pun disembunyikan.
        abort_unless($invoice->isVisibleToPortal(), 404);

        return $documents->pdf($invoice)->download($documents->filename($invoice));
    }

    public function invoicePreview(Invoice $invoice, InvoiceDocumentService $documents)
    {
        $this->authorize('portal.invoice.view');
        $this->authorizeInvoice($invoice);

        abort_unless($invoice->isVisibleToPortal(), 404);

        return $documents->pdf($invoice)->stream($documents->filename($invoice));
    }

    /**
     * Kuitansi hanya yang nomornya SUDAH diterbitkan staf.
     *
     * ReceiptService::pdf() memberi nomor pada akses pertama — kalau jalur ini
     * dibuka untuk kuitansi yang belum bernomor, klik pelanggan yang akan
     * menentukan urutan nomor dokumen akuntansi.
     */
    public function receipt(InvoicePayment $payment, ReceiptService $receipts)
    {
        $this->authorize('portal.payment.view');
        $this->authorizePayment($payment->load('invoice'));

        abort_unless($payment->hasReceipt(), 404);

        $pdf = $receipts->pdf($payment->load('recordedBy'));

        return $pdf->download($receipts->filename($payment));
    }

    public function receiptPreview(InvoicePayment $payment, ReceiptService $receipts)
    {
        $this->authorize('portal.payment.view');
        $this->authorizePayment($payment->load('invoice'));

        abort_unless($payment->hasReceipt(), 404);

        $pdf = $receipts->pdf($payment->load('recordedBy'));

        return $pdf->stream($receipts->filename($payment));
    }
}
