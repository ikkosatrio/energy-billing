<?php

namespace App\Services\Billing;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Pembuatan PDF dan pengiriman invoice.
 *
 * Seluruh isi dokumen diambil dari kolom snapshot pada baris invoice, bukan
 * dari relasi pelanggan atau tarif — supaya PDF yang dicetak ulang bertahun
 * kemudian tetap sama persis dengan yang dulu dikirim.
 */
class InvoiceDocumentService
{
    public function pdf(Invoice $invoice)
    {
        return Pdf::loadView('billing.invoices.pdf', [
            'invoice' => $invoice,
            'lines' => $this->lines($invoice),
            'totals' => $this->totals($invoice),
        ])->setPaper('a4');
    }

    public function filename(Invoice $invoice): string
    {
        return Str::slug($invoice->invoice_no).'.pdf';
    }

    /**
     * Mengirim invoice ke email pelanggan beserta lampiran PDF.
     */
    public function email(Invoice $invoice): void
    {
        $email = $invoice->customer?->email;

        if (!$email) {
            throw new \RuntimeException('Pelanggan belum punya alamat email.');
        }

        Mail::to($email)->send(new InvoiceMail($invoice, $this));

        $invoice->forceFill([
            'sent_at' => now(),
            // Mengirim invoice berarti menagihkannya; draft naik menjadi terbit.
            'status' => $invoice->status === 'draft' ? 'issued' : $invoice->status,
        ])->save();

        ActivityLogger::log('send_invoice', $invoice, "Kirim invoice {$invoice->invoice_no} ke {$email}");
    }

    /**
     * Baris uraian pada invoice. Komponen bernilai nol tidak ditampilkan agar
     * dokumennya tidak dipenuhi baris kosong.
     *
     * @return array<int, array<string, mixed>>
     */
    public function lines(Invoice $invoice): array
    {
        $lines = [
            [
                'label' => 'Pemakaian LWBP',
                'start' => kwh($invoice->stand_lwbp_start),
                'end' => kwh($invoice->stand_lwbp_end),
                'kwh' => kwh($invoice->kwh_lwbp),
                'rate' => number_format((float) $invoice->rate_lwbp, 2, ',', '.'),
                'amount' => $invoice->amount_lwbp,
            ],
            [
                'label' => 'Pemakaian WBP',
                'start' => kwh($invoice->stand_wbp_start),
                'end' => kwh($invoice->stand_wbp_end),
                'kwh' => kwh($invoice->kwh_wbp),
                'rate' => number_format((float) $invoice->rate_wbp, 2, ',', '.'),
                'amount' => $invoice->amount_wbp,
            ],
        ];

        if ((float) $invoice->biaya_beban != 0.0) {
            $label = $invoice->biaya_beban_mode === 'per_kva'
                ? 'Biaya beban ('.kwh($invoice->daya_kva).' kVA × '.rupiah($invoice->rate_beban_per_kva).')'
                : 'Biaya beban';

            $lines[] = ['label' => $label, 'start' => '—', 'end' => '—', 'kwh' => '—', 'rate' => '—', 'amount' => $invoice->biaya_beban];
        }

        if ((float) $invoice->biaya_admin != 0.0) {
            $lines[] = ['label' => 'Biaya admin', 'start' => '—', 'end' => '—', 'kwh' => '—', 'rate' => '—', 'amount' => $invoice->biaya_admin];
        }

        return $lines;
    }

    /**
     * Baris ringkasan di bawah tabel uraian.
     *
     * @return array<int, array{label:string, value:string}>
     */
    public function totals(Invoice $invoice): array
    {
        $totals = [
            ['label' => 'Subtotal', 'value' => rupiah($invoice->subtotal)],
        ];

        if ((float) $invoice->ppj_amount != 0.0) {
            $totals[] = [
                'label' => 'PPJ '.rtrim(rtrim(number_format((float) $invoice->ppj_percent, 2, ',', '.'), '0'), ',').'%',
                'value' => rupiah($invoice->ppj_amount),
            ];
        }

        if ((float) $invoice->ppn_amount != 0.0) {
            $totals[] = [
                'label' => 'PPN '.rtrim(rtrim(number_format((float) $invoice->ppn_percent, 2, ',', '.'), '0'), ',').'%',
                'value' => rupiah($invoice->ppn_amount),
            ];
        }

        if ((float) $invoice->adjustment != 0.0) {
            $totals[] = ['label' => 'Penyesuaian', 'value' => rupiah($invoice->adjustment)];
        }

        if ((float) $invoice->rounding != 0.0) {
            $totals[] = ['label' => 'Pembulatan', 'value' => rupiah($invoice->rounding)];
        }

        $totals[] = ['label' => 'Total kWh', 'value' => kwh($invoice->total_kwh).' kWh'];

        return $totals;
    }
}
