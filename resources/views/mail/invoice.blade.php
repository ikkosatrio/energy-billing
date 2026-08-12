<x-mail::message>
# Invoice {{ $invoice->invoice_no }}

Yth. **{{ $invoice->customer_name }}**,

Berikut kami sampaikan tagihan pemakaian listrik untuk periode
**{{ $invoice->period_start->translatedFormat('d F Y') }} – {{ $invoice->period_end->translatedFormat('d F Y') }}**.

<x-mail::table>
| Uraian | Jumlah |
|:-------|-------:|
| Pemakaian LWBP | {{ kwh($invoice->kwh_lwbp) }} kWh |
| Pemakaian WBP | {{ kwh($invoice->kwh_wbp) }} kWh |
| **Total tagihan** | **{{ rupiah($invoice->total_amount) }}** |
| Jatuh tempo | {{ $invoice->due_date?->translatedFormat('d F Y') ?? '—' }} |
</x-mail::table>

Rincian lengkapnya terlampir dalam berkas PDF.

Terima kasih atas kerja samanya.

Salam,<br>
{{ setting('company_name', config('app.name')) }}
</x-mail::message>
