<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>{{ $title }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #0f172a; margin: 0; }
    h1 { font-size: 15px; margin: 0 0 3px; }
    .sub { font-size: 10px; color: #64748b; margin-bottom: 14px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f8fafc; font-size: 8px; letter-spacing: .5px; color: #64748b;
         text-align: right; padding: 6px 5px; border-bottom: 1px solid #e6ebf2; }
    th:first-child, th:nth-child(2) { text-align: left; }
    td { padding: 6px 5px; border-bottom: 1px solid #f1f5f9; text-align: right; }
    td:first-child, td:nth-child(2) { text-align: left; }
    .foot { margin-top: 20px; font-size: 8px; color: #94a3b8; text-align: center; }
  </style>
</head>
<body>

  <h1>{{ $title }}</h1>
  <div class="sub">
    {{ setting('company_name') }} ·
    Periode {{ $from->translatedFormat('d F Y') }} – {{ $to->translatedFormat('d F Y') }}
  </div>

  <table>
    <thead>
      <tr>
        @foreach ($headings as $heading)
          <th>{{ $heading }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $row)
        <tr>
          @foreach ((array) $row as $value)
            <td>
              @if ($value instanceof \Illuminate\Support\Carbon)
                {{ $value->translatedFormat('d M Y') }}
              @elseif (is_float($value) || is_int($value))
                {{ number_format((float) $value, 0, ',', '.') }}
              @else
                {{ $value ?? '—' }}
              @endif
            </td>
          @endforeach
        </tr>
      @empty
        <tr><td colspan="{{ count($headings) }}" style="text-align:center;padding:24px;color:#94a3b8">
          Tidak ada data pada rentang ini.
        </td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="foot">
    Dicetak {{ now()->translatedFormat('d F Y H:i') }} WIB oleh {{ auth()->user()?->name }}
  </div>

</body>
</html>
