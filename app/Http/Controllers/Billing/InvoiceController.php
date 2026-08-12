<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Billing\InvoiceDocumentService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceDocumentService $documents)
    {
    }

    public function index()
    {
        return view('billing.invoices.index');
    }

    /**
     * Unduh invoice sebagai PDF.
     */
    public function download(Invoice $invoice)
    {
        $this->authorize('invoice.view');

        return $this->documents->pdf($invoice)
            ->download($this->documents->filename($invoice));
    }

    /**
     * Pratinjau PDF di browser — berguna untuk memeriksa tata letak sebelum
     * dikirim ke pelanggan.
     */
    public function preview(Invoice $invoice)
    {
        $this->authorize('invoice.view');

        return $this->documents->pdf($invoice)
            ->stream($this->documents->filename($invoice));
    }

    /**
     * Kirim invoice beserta lampiran PDF ke email pelanggan.
     */
    public function send(Request $request, Invoice $invoice)
    {
        $this->authorize('invoice.send');

        try {
            $this->documents->email($invoice);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal mengirim email: '.$e->getMessage());
        }

        return back()->with('status', "Invoice {$invoice->invoice_no} dikirim ke {$invoice->customer->email}.");
    }
}
