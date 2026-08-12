<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>{{ $invoice->invoice_no }}</title>
  {{--
    DomPDF hanya mendukung sebagian kecil CSS dan tidak memuat font eksternal,
    jadi lembar ini memakai gaya inline sederhana dan font bawaan — bukan
    design system aplikasi.
  --}}
  <style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; margin: 0; }
    .head { width: 100%; border-bottom: 2px solid #0d1b2e; padding-bottom: 14px; }
    .head td { vertical-align: top; }
    .kicker { font-size: 9px; font-weight: bold; letter-spacing: 2px; color: #94a3b8; }
    .no { font-size: 18px; font-weight: bold; margin-top: 5px; }
    .issuer { text-align: right; font-size: 10px; color: #64748b; line-height: 1.6; }
    .issuer strong { font-size: 12px; color: #0f172a; display: block; }
    .meta { width: 100%; margin: 18px 0; }
    .meta td { vertical-align: top; padding-right: 12px; }
    .label { font-size: 9px; font-weight: bold; letter-spacing: 1px; color: #94a3b8; padding-bottom: 4px; }
    table.lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.lines th { background: #f8fafc; font-size: 9px; letter-spacing: 1px; color: #64748b;
                     text-align: right; padding: 8px 7px; }
    table.lines th:first-child { text-align: left; }
    table.lines td { padding: 8px 7px; border-bottom: 1px solid #f1f5f9; text-align: right; }
    table.lines td:first-child { text-align: left; }
    .totals { width: 46%; margin-left: 54%; margin-top: 14px; border-collapse: collapse; }
    .totals td { padding: 4px 0; color: #475569; }
    .totals td.v { text-align: right; font-weight: bold; color: #0f172a; }
    .grand { border-top: 2px solid #0d1b2e; }
    .grand td { padding-top: 10px; font-size: 14px; font-weight: bold; color: #0f172a; }
    .note { margin-top: 26px; padding: 10px 12px; background: #f8fafc; font-size: 10px; color: #475569; }
    .foot { margin-top: 30px; font-size: 9px; color: #94a3b8; text-align: center; }
  </style>
</head>
<body>

  <table class="head">
    <tr>
      <td>
        <div class="kicker">INVOICE PEMAKAIAN LISTRIK</div>
        <div class="no">{{ $invoice->invoice_no }}</div>
      </td>
      <td class="issuer">
        <strong>{{ setting('company_name') }}</strong>
        {{ setting('company_address') }}<br>
        @if (setting('company_phone')) {{ setting('company_phone') }} @endif
        @if (setting('company_email')) · {{ setting('company_email') }} @endif
        @if (setting('company_npwp')) <br>NPWP {{ setting('company_npwp') }} @endif
      </td>
    </tr>
  </table>

  <table class="meta">
    <tr>
      <td width="45%">
        <div class="label">DITAGIHKAN KEPADA</div>
        <div style="font-size:13px;font-weight:bold">{{ $invoice->customer_name }}</div>
        <div style="color:#64748b;line-height:1.6;margin-top:3px">{{ $invoice->customer_address }}</div>
        @if ($invoice->customer_npwp)
          <div style="color:#64748b;margin-top:3px">NPWP {{ $invoice->customer_npwp }}</div>
        @endif
      </td>
      <td width="27%">
        <div class="label">PERIODE</div>
        <div>{{ $invoice->period_start->translatedFormat('d M Y') }} – {{ $invoice->period_end->translatedFormat('d M Y') }}</div>
        <div class="label" style="padding-top:10px">POWER METER</div>
        <div>{{ $invoice->meter_code ?? '—' }}</div>
      </td>
      <td width="28%">
        <div class="label">TANGGAL TERBIT</div>
        <div>{{ $invoice->issue_date?->translatedFormat('d M Y') ?? '—' }}</div>
        <div class="label" style="padding-top:10px">JATUH TEMPO</div>
        <div>{{ $invoice->due_date?->translatedFormat('d M Y') ?? '—' }}</div>
        <div class="label" style="padding-top:10px">GOLONGAN</div>
        <div>{{ $invoice->tariff_group_code ?? '—' }}</div>
      </td>
    </tr>
  </table>

  <table class="lines">
    <thead>
      <tr>
        <th>URAIAN</th>
        <th>STAND AWAL</th>
        <th>STAND AKHIR</th>
        <th>kWh</th>
        <th>TARIF</th>
        <th>SUBTOTAL</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($lines as $line)
        <tr>
          <td>{{ $line['label'] }}</td>
          <td style="color:#64748b">{{ $line['start'] }}</td>
          <td style="color:#64748b">{{ $line['end'] }}</td>
          <td>{{ $line['kwh'] }}</td>
          <td>{{ $line['rate'] }}</td>
          <td style="font-weight:bold">{{ rupiah($line['amount'], false) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <table class="totals">
    @foreach ($totals as $row)
      <tr>
        <td>{{ $row['label'] }}</td>
        <td class="v">{{ $row['value'] }}</td>
      </tr>
    @endforeach
    <tr class="grand">
      <td>TOTAL TAGIHAN</td>
      <td class="v" style="font-size:14px">{{ rupiah($invoice->total_amount) }}</td>
    </tr>
  </table>

  @if ($invoice->notes)
    <div class="note"><strong>Catatan:</strong> {{ $invoice->notes }}</div>
  @endif

  <div class="foot">
    Dokumen ini dihasilkan otomatis oleh {{ setting('app_name', config('app.name')) }}
    pada {{ now()->translatedFormat('d F Y H:i') }} WIB.
  </div>

</body>
</html>
