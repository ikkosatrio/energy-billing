@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', 'Ringkasan pemakaian listrik dan tagihan gudang')

@section('actions')
  <div class="chip">
    <span class="pulse-dot{{ $meterStatus['offline'] > 0 ? ' offline' : '' }}"></span>
    {{ $meterStatus['offline'] > 0 ? $meterStatus['offline'].' meter offline' : 'Semua meter online' }}
  </div>
  {{-- Route::has dipakai di seluruh halaman ini karena modul dibangun
       bertahap; tautan ke modul yang belum ada cukup disembunyikan. --}}
  @if (Route::has('billing.periods.index') && auth()->user()->can('invoice.generate'))
    <a href="{{ route('billing.periods.index') }}" wire:navigate class="btn btn-primary">
      <i data-lucide="file-plus-2" style="width:15px;height:15px"></i>
      Generate Invoice
    </a>
  @endif
@endsection

@section('content')

  {{-- ── KPI ─────────────────────────────────────────────────────────── --}}
  <div class="stat-grid stat-grid-4">
    <div class="stat">
      <div class="stat-head">
        <div class="stat-label">Total Pemakaian Bulan Ini</div>
        <div class="stat-icon blue"><i data-lucide="activity" style="width:16px;height:16px"></i></div>
      </div>
      <div class="stat-value">{{ kwh($usage['total']) }} <small>kWh</small></div>
      @if ($usage['change_percent'] !== null)
        @php $naik = $usage['change_percent'] >= 0; @endphp
        <div class="stat-foot {{ $naik ? 'up' : 'down' }}">
          <i data-lucide="{{ $naik ? 'trending-up' : 'trending-down' }}" style="width:14px;height:14px"></i>
          {{ number_format(abs($usage['change_percent']), 1, ',', '.') }}% vs {{ $usage['previous_label'] }}
        </div>
      @else
        <div class="stat-foot">Belum ada pembanding bulan lalu</div>
      @endif
    </div>

    <div class="stat" style="animation-delay:.06s">
      <div class="stat-head">
        <div class="stat-label">Nilai Tagihan Berjalan</div>
        <div class="stat-icon green"><i data-lucide="receipt" style="width:16px;height:16px"></i></div>
      </div>
      <div class="stat-value sm">{{ rupiah($billing['total']) }}</div>
      <div class="stat-foot">Periode {{ $billing['label'] }}</div>
    </div>

    <div class="stat" style="animation-delay:.12s">
      <div class="stat-head">
        <div class="stat-label">Meter Aktif</div>
        <div class="stat-icon {{ $meterStatus['offline'] > 0 ? 'red' : 'green' }}">
          <i data-lucide="cpu" style="width:16px;height:16px"></i>
        </div>
      </div>
      <div class="stat-value">{{ $meterStatus['online'] }} <small>/ {{ $meterStatus['total'] }}</small></div>
      @if ($meterStatus['offline'] > 0)
        <div class="stat-foot down">
          <i data-lucide="triangle-alert" style="width:14px;height:14px"></i>
          {{ $meterStatus['offline'] }} meter offline
        </div>
      @else
        <div class="stat-foot up">
          <i data-lucide="circle-check" style="width:14px;height:14px"></i>
          Seluruh meter terhubung
        </div>
      @endif
    </div>

    <div class="stat" style="animation-delay:.18s">
      <div class="stat-head">
        <div class="stat-label">Belum Dibayar</div>
        <div class="stat-icon amber"><i data-lucide="wallet" style="width:16px;height:16px"></i></div>
      </div>
      <div class="stat-value sm">{{ rupiah($outstanding['amount']) }}</div>
      <div class="stat-foot {{ $outstanding['overdue_count'] > 0 ? 'down' : '' }}">
        {{ $outstanding['count'] }} invoice belum lunas
        @if ($outstanding['overdue_count'] > 0)
          · {{ $outstanding['overdue_count'] }} jatuh tempo
        @endif
      </div>
    </div>
  </div>

  {{-- ── Real-time perangkat ──────────────────────────────────────────── --}}
  @can('monitoring.view')
    <livewire:dashboard.device-status-widget />
  @endcan

@endsection
