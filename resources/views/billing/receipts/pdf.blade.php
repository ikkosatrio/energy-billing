<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>{{ $payment->receipt_no }}</title>
  {{--
    Sama seperti PDF invoice: DomPDF hanya mendukung sebagian kecil CSS, jadi
    lembar ini memakai gaya sederhana dan font bawaan, bukan design system
    aplikasi. Ukurannya A5 landscape — kuitansi lazimnya setengah halaman.
  --}}
  <style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; margin: 0; }
    .head { width: 100%; border-bottom: 2px solid #0d1b2e; padding-bottom: 12px; }
    .head td { vertical-align: top; }
    .kicker { font-size: 9px; font-weight: bold; letter-spacing: 2px; color: #94a3b8; }
    .no { font-size: 17px; font-weight: bold; margin-top: 4px; }
    .issuer { text-align: right; font-size: 10px; color: #64748b; line-height: 1.6; }
    .issuer strong { font-size: 12px; color: #0f172a; display: block; }
    .meta { width: 100%; margin: 16px 0 4px; }
    .meta td { vertical-align: top; padding: 5px 12px 5px 0; }
    .label { font-size: 9px; font-weight: bold; letter-spacing: 1px; color: #94a3b8; }
    .value { font-size: 12px; }
    .amount-box { border: 2px solid #0d1b2e; padding: 10px 14px; margin-top: 12px; }
    .amount-label { font-size: 9px; font-weight: bold; letter-spacing: 2px; color: #64748b; }
    .amount { font-size: 20px; font-weight: bold; margin-top: 3px; }
    .terbilang { font-size: 10px; color: #475569; font-style: italic; margin-top: 4px; }
    .status { display: inline-block; padding: 4px 10px; font-size: 10px; font-weight: bold; letter-spacing: 1px; }
    .status.lunas { background: #dcfce7; color: #15803d; }
    .status.sebagian { background: #fef3c7; color: #b45309; }
    table.ctx { width: 100%; margin-top: 12px; border-collapse: collapse; }
    table.ctx td { padding: 5px 0; border-bottom: 1px solid #f1f5f9; color: #475569; }
    table.ctx td.v { text-align: right; font-weight: bold; color: #0f172a; }
    .sign { width: 100%; margin-top: 22px; }
    .sign td { vertical-align: top; font-size: 10px; color: #64748b; }
    .sign .line { margin-top: 44px; border-top: 1px solid #cbd5e1; padding-top: 4px; width: 170px; }
    .foot { margin-top: 16px; font-size: 8px; color: #94a3b8; text-align: center; }
  </style>
</head>
<body>

  <table class="head">
    <tr>
      <td>
        <div class="kicker">KUITANSI — TANDA TERIMA PEMBAYARAN</div>
        <div class="no">{{ $payment->receipt_no }}</div>
      </td>
      <td class="issuer">
        <strong>{{ setting('company_name') }}</strong>
        {{ setting('company_address') }}<br>
        @if (setting('company_phone')) {{ setting('company_phone') }} @endif
        @if (setting('company_email')) · {{ setting('company_email') }} @endif
      </td>
    </tr>
  </table>

  <table class="meta">
    <tr>
      <td width="55%">
        <div class="label">TELAH DITERIMA DARI</div>
        <div class="value"><strong>{{ $invoice->customer_name }}</strong></div>
      </td>
      <td width="45%">
        <div class="label">TANGGAL DITERIMA</div>
        <div class="value">{{ $payment->payment_date->translatedFormat('d F Y') }}</div>
      </td>
    </tr>
    <tr>
      <td>
        <div class="label">UNTUK PEMBAYARAN</div>
        <div class="value">
          Invoice {{ $invoice->invoice_no }} —
          pemakaian listrik {{ $invoice->period_start->translatedFormat('F Y') }}
        </div>
      </td>
      <td>
        <div class="label">METODE</div>
        <div class="value">
          {{ ['transfer' => 'Transfer', 'cash' => 'Tunai'][$payment->method] ?? 'Lainnya' }}
          @if ($payment->reference_no) · {{ $payment->reference_no }} @endif
        </div>
      </td>
    </tr>
  </table>

  <div class="amount-box">
    <div class="amount-label">JUMLAH DITERIMA</div>
    <div class="amount">{{ rupiah($payment->amount) }}</div>
  </div>

  {{-- Konteks tagihan. Penting terutama untuk pembayaran sebagian: tanpa ini,
       kuitansi cicilan mudah disalahartikan sebagai bukti pelunasan. --}}
  <table class="ctx">
    <tr>
      <td>Total tagihan invoice</td>
      <td class="v">{{ rupiah($invoice->total_amount) }}</td>
    </tr>
    <tr>
      <td>Terbayar sampai kuitansi ini</td>
      <td class="v">{{ rupiah($payment->receipt_paid_total) }}</td>
    </tr>
    <tr>
      <td><strong>Sisa yang belum dibayar</strong></td>
      <td class="v">{{ rupiah($payment->receipt_outstanding_after) }}</td>
    </tr>
  </table>

  <div style="margin-top:12px">
    @if ($payment->receiptSettlesInvoice())
      <span class="status lunas">INVOICE LUNAS</span>
    @else
      <span class="status sebagian">PEMBAYARAN SEBAGIAN</span>
    @endif
  </div>

  <table class="sign">
    <tr>
      <td width="60%"></td>
      <td width="40%">
        {{ setting('company_city', '') ?: '' }}{{ setting('company_city') ? ', ' : '' }}{{ $payment->receipt_issued_at?->translatedFormat('d F Y') }}<br>
        {{ setting('company_name') }}
        <div class="line">{{ $payment->recordedBy?->name ?? '' }}</div>
      </td>
    </tr>
  </table>

  <div class="foot">
    Kuitansi ini dihasilkan otomatis oleh {{ setting('company_name', config('app.name')) }}
    pada {{ $payment->receipt_issued_at?->translatedFormat('d F Y H:i') }} WIB dan sah tanpa tanda tangan basah.
  </div>

</body>
</html>
