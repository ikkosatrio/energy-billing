@props(['status'])

{{--
  Badge status invoice. Dikumpulkan di satu komponen supaya label dan warnanya
  konsisten di dashboard, daftar invoice, dan halaman pembayaran.
--}}
@php
    $map = [
        'draft'     => ['Draft', 'badge-neutral'],
        'issued'    => ['Belum Bayar', 'badge-warning'],
        'partial'   => ['Bayar Sebagian', 'badge-info'],
        'paid'      => ['Lunas', 'badge-success'],
        'overdue'   => ['Jatuh Tempo', 'badge-danger'],
        'cancelled' => ['Dibatalkan', 'badge-neutral'],
    ];

    [$label, $class] = $map[$status] ?? [$status, 'badge-neutral'];
@endphp

<span class="badge {{ $class }}">{{ $label }}</span>
