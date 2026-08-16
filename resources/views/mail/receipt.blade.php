<x-mail::message>
# Kuitansi {{ $payment->receipt_no }}

Yth. **{{ $payment->invoice->customer_name }}**,

Terima kasih. Pembayaran Anda sudah kami terima.

<x-mail::table>
| Uraian | Jumlah |
|:-------|-------:|
| Untuk invoice | {{ $payment->invoice->invoice_no }} |
| Tanggal diterima | {{ $payment->payment_date->translatedFormat('d F Y') }} |
| **Jumlah diterima** | **{{ rupiah($payment->amount) }}** |
@if (! $payment->receiptSettlesInvoice())
| Total tagihan | {{ rupiah($payment->invoice->total_amount) }} |
| Sisa yang belum dibayar | **{{ rupiah($payment->receipt_outstanding_after) }}** |
@endif
</x-mail::table>

@if ($payment->receiptSettlesInvoice())
Dengan pembayaran ini, invoice **{{ $payment->invoice->invoice_no }}** dinyatakan **LUNAS**.
@else
Pembayaran ini tercatat sebagai **pembayaran sebagian**. Sisa tagihan sebesar
**{{ rupiah($payment->receipt_outstanding_after) }}** masih menunggu pelunasan
@if ($payment->invoice->due_date)
sampai {{ $payment->invoice->due_date->translatedFormat('d F Y') }}.
@else
.
@endif
@endif

Kuitansi resminya terlampir dalam berkas PDF.

Salam,<br>
{{ setting('company_name', config('app.name')) }}
</x-mail::message>
