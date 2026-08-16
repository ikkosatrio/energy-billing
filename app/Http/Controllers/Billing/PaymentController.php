<?php

namespace App\Http\Controllers\Billing;

use App\Exports\PaymentTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\InvoicePayment;
use App\Services\Billing\ReceiptService;
use Maatwebsite\Excel\Facades\Excel;

class PaymentController extends Controller
{
    public function index()
    {
        return view('billing.payments.index');
    }

    /**
     * Berkas contoh untuk impor pembayaran.
     *
     * Operator mengunduh ini, menempelkan hasil pivot dari mutasi bank ke
     * dalamnya, lalu mengunggahnya kembali.
     */
    public function template()
    {
        $this->authorize('payment.bulk');

        return Excel::download(new PaymentTemplateExport, 'template-impor-pembayaran.xlsx');
    }

    /**
     * Kuitansi satu pembayaran.
     *
     * Nomornya diberikan saat diakses pertama kali — mengunduh berarti
     * dokumennya resmi terbit.
     */
    public function receipt(InvoicePayment $payment, ReceiptService $receipts)
    {
        $this->authorize('payment.receipt');

        $pdf = $receipts->pdf($payment->load('invoice', 'recordedBy'));

        return $pdf->download($receipts->filename($payment->refresh()));
    }

    public function receiptPreview(InvoicePayment $payment, ReceiptService $receipts)
    {
        $this->authorize('payment.receipt');

        $pdf = $receipts->pdf($payment->load('invoice', 'recordedBy'));

        return $pdf->stream($receipts->filename($payment->refresh()));
    }
}
