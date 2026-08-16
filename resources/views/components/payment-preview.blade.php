@props(['preview'])

{{--
  Pratinjau hasil pembayaran SEBELUM disimpan — dipakai di modal Catat
  Pembayaran dan Entri Cepat, supaya operator tahu nominal yang sedang
  diketik akan melunasi, menyisakan sebagian, atau melebihi tagihan, tanpa
  perlu menebak dari status yang baru berubah setelah tersimpan.
--}}
@if ($preview)
    @php
        $tone = match ($preview['kind']) {
            'settles' => 'alert-success',
            'overpaid' => 'alert-warning',
            default => 'alert-info',
        };
    @endphp

    <div {{ $attributes->merge(['class' => 'alert '.$tone]) }}>
        @if ($preview['kind'] === 'settles')
            <strong>Melunasi</strong> — pembayaran ini menutup seluruh sisa tagihan.
        @elseif ($preview['kind'] === 'overpaid')
            <strong>Melebihi tagihan</strong> sebesar {{ rupiah(abs($preview['remaining'])) }} — periksa kembali jumlahnya.
        @else
            <strong>Bayar sebagian</strong> — sisa {{ rupiah($preview['remaining']) }} akan tetap terutang.
        @endif
    </div>
@endif
