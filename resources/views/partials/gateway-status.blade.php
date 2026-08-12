{{--
  Ringkasan konektivitas gateway di kaki sidebar.

  Hitungannya di-cache 30 detik: nilainya sama untuk semua halaman dan tidak
  perlu real-time, sehingga tidak layak menambah dua query ke setiap request.
--}}
@php
    $gateway = cache()->remember('sidebar_gateway_status', 30, function () {
        $meters = \App\Models\PowerMeter::query()
            ->where('status', '!=', 'inactive')
            ->get(['id', 'status', 'last_seen_at']);

        return [
            'total' => $meters->count(),
            'online' => $meters->filter->isOnline()->count(),
        ];
    });

    $allOnline = $gateway['total'] > 0 && $gateway['online'] === $gateway['total'];
@endphp

<div class="sidebar-status">
    <div class="sidebar-status-title">
        <span class="pulse-dot{{ $allOnline ? '' : ' offline' }}"></span>GATEWAY IOT
    </div>
    <div class="sidebar-status-body">
        {{ $gateway['online'] }} / {{ $gateway['total'] }} meter terhubung<br>
        <span>push tiap {{ setting('iot_push_interval_seconds', 60) }} detik</span>
    </div>
</div>
