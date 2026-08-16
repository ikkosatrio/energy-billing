@props(['status'])

{{--
  Badge status invoice. Dikumpulkan di satu komponen supaya label dan warnanya
  konsisten di dashboard, daftar invoice, dan halaman pembayaran.
--}}
@php
    // Labelnya diambil dari Invoice::STATUS_LABELS — satu-satunya sumber,
    // supaya export Excel/PDF (yang tidak bisa memanggil komponen ini) tetap
    // menampilkan teks yang sama persis dengan yang terlihat di layar.
    $badgeClass = [
        'draft' => 'badge-neutral',
        'issued' => 'badge-warning',
        'partial' => 'badge-info',
        'paid' => 'badge-success',
        'overdue' => 'badge-danger',
        'cancelled' => 'badge-neutral',
    ];

    $label = \App\Models\Invoice::STATUS_LABELS[$status] ?? $status;
    $class = $badgeClass[$status] ?? 'badge-neutral';
@endphp

<span class="badge {{ $class }}">{{ $label }}</span>
