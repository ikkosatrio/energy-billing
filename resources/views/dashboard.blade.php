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

  {{-- ── Status meter ────────────────────────────────────────────────── --}}
  <div class="card mb-18">
    <div class="card-head">
      <div>
        <div class="card-title">Status Meter</div>
        <div class="card-sub">Daya aktif terakhir yang dikirim tiap perangkat</div>
      </div>
      @if (Route::has('monitoring.realtime') && auth()->user()->can('monitoring.view'))
        <a href="{{ route('monitoring.realtime') }}" wire:navigate class="link-action">Lihat real-time →</a>
      @endif
    </div>

    @forelse ($meters as $meter)
      <div class="kv-row">
        <div class="row" style="min-width:0">
          <span class="pulse-dot{{ $meter->isOnline() ? '' : ' offline' }}"></span>
          <div style="min-width:0">
            <div style="font-size:13px;font-weight:600">{{ $meter->name }} — {{ $meter->code }}</div>
            <div class="sub">{{ $meter->customer?->name ?? 'Belum terhubung ke pelanggan' }}</div>
          </div>
        </div>
        <div class="kv-value">
          {{ $meter->latestReading ? kwh($meter->latestReading->active_power_kw, 1).' kW' : '—' }}
        </div>
      </div>
    @empty
      <div class="table-empty">Belum ada power meter terdaftar.</div>
    @endforelse
  </div>

  {{-- ── Invoice terbaru ─────────────────────────────────────────────── --}}
  <div class="card">
    <div class="card-head">
      <div class="card-title">Invoice Terbaru</div>
      @if (Route::has('billing.invoices.index') && auth()->user()->can('invoice.view'))
        <a href="{{ route('billing.invoices.index') }}" wire:navigate class="link-action">Lihat semua →</a>
      @endif
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>No Invoice</th>
            <th>Pelanggan</th>
            <th>Periode</th>
            <th class="num">kWh</th>
            <th class="num">Tagihan</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($recentInvoices as $invoice)
            <tr>
              <td class="num strong" style="text-align:left">{{ $invoice->invoice_no }}</td>
              <td>{{ $invoice->customer_name }}</td>
              <td class="text-muted">
                {{ $invoice->period_start->translatedFormat('d M') }} – {{ $invoice->period_end->translatedFormat('d M Y') }}
              </td>
              <td class="num">{{ kwh($invoice->total_kwh) }}</td>
              <td class="num strong">{{ rupiah($invoice->total_amount) }}</td>
              <td><x-invoice-status :status="$invoice->status" /></td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="table-empty">Belum ada invoice yang digenerate.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

@endsection
