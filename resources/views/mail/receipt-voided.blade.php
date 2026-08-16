<x-mail::message>
# Pembatalan Kuitansi {{ $receiptNo }}

Yth. **{{ $customerName }}**,

Kami perlu memberi tahu bahwa kuitansi **{{ $receiptNo }}** sebesar
**{{ rupiah($amount) }}** untuk invoice **{{ $invoiceNo }}** telah **dibatalkan**,
dan tidak lagi berlaku sebagai bukti pembayaran.

@if ($reason)
<x-mail::panel>
Alasan: {{ $reason }}
</x-mail::panel>
@endif

Pembatalan ini terjadi karena koreksi pencatatan di pihak kami. Bila Anda memang
sudah melakukan pembayaran tersebut, mohon hubungi kami agar dapat kami catat
ulang dengan benar dan kuitansi penggantinya kami terbitkan.

Mohon maaf atas ketidaknyamanannya.

Salam,<br>
{{ setting('company_name', config('app.name')) }}
</x-mail::message>
