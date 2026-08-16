<div>

    {{-- ── Filter ──────────────────────────────────────────────────────── --}}
    <div class="card mb-18">
        <div class="filter-bar">
            <div class="field">
                <label class="field-label">Dari Tanggal</label>
                <input type="date" class="input mono" wire:model.live="from">
            </div>
            <div class="field">
                <label class="field-label">Sampai</label>
                <input type="date" class="input mono" wire:model.live="to">
            </div>
            <div class="field">
                <label class="field-label">Pelanggan</label>
                <x-select-search
                    wire:model.live="customerId"
                    placeholder="Semua pelanggan"
                    search-placeholder="Cari nama pelanggan…"
                    :options="$customers
                        ->map(fn ($customer) => ['value' => $customer->id, 'label' => $customer->name])
                        ->prepend(['value' => '', 'label' => 'Semua pelanggan'])" />
            </div>
            <div class="field">
                <label class="field-label">Metode</label>
                <x-select-search wire:model.live="method" placeholder="Semua metode"
                    :options="[
                        ['value' => '', 'label' => 'Semua metode'],
                        ['value' => 'transfer', 'label' => 'Transfer'],
                        ['value' => 'cash', 'label' => 'Tunai'],
                        ['value' => 'other', 'label' => 'Lainnya'],
                    ]" />
            </div>
            <div class="spacer"></div>
            @can('report.export')
                <a href="{{ route('report.export', ['type' => 'payments', 'format' => 'xlsx']) }}?{{ $exportQuery }}"
                   class="btn btn-outline">
                    <i data-lucide="sheet" style="width:15px;height:15px"></i> Excel
                </a>
                <a href="{{ route('report.export', ['type' => 'payments', 'format' => 'pdf']) }}?{{ $exportQuery }}"
                   class="btn btn-outline">
                    <i data-lucide="file-text" style="width:15px;height:15px"></i> PDF
                </a>
            @endcan
        </div>

        <label class="checkbox-row" style="margin-top:12px">
            <input type="checkbox" wire:model.live="partialOnly">
            <span>Hanya invoice yang masih bayar sebagian</span>
        </label>
    </div>

    {{-- ── Ringkasan rentang terfilter ─────────────────────────────────── --}}
    <div class="stat-grid stat-grid-4">
        <div class="card">
            <div class="stat-label">Total Diterima</div>
            <div class="stat-value sm" style="margin-top:8px">{{ rupiah($totals['amount']) }}</div>
            <div class="stat-foot">{{ $rows->count() }} transaksi</div>
        </div>
        <div class="card">
            <div class="stat-label">Transfer</div>
            <div class="stat-value sm" style="margin-top:8px">{{ rupiah($methodTotals['transfer']) }}</div>
            <div class="stat-foot">{{ $rows->where('method', 'transfer')->count() }} transaksi</div>
        </div>
        <div class="card">
            <div class="stat-label">Tunai</div>
            <div class="stat-value sm" style="margin-top:8px">{{ rupiah($methodTotals['cash']) }}</div>
            <div class="stat-foot">{{ $rows->where('method', 'cash')->count() }} transaksi</div>
        </div>
        <div class="card">
            <div class="stat-label">Lainnya</div>
            <div class="stat-value sm" style="margin-top:8px">{{ rupiah($methodTotals['other']) }}</div>
            <div class="stat-foot">{{ $rows->where('method', 'other')->count() }} transaksi</div>
        </div>
    </div>

    {{-- ── Tunggakan saat ini — snapshot hari ini, bukan hasil filter ──── --}}
    <div class="card mb-18">
        <div class="card-head">
            <div>
                <div class="card-title">Tunggakan Saat Ini</div>
                <div class="card-sub">Per hari ini — tidak mengikuti filter tanggal di atas</div>
            </div>
            @if ($tracking['partial']['count'] > 0)
                <span class="badge badge-info badge-square">
                    {{ $tracking['partial']['count'] }} invoice bayar sebagian · {{ rupiah($tracking['partial']['amount']) }}
                </span>
            @endif
        </div>

        <div class="aging-row">
            @foreach ($tracking['aging'] as $key => $bucket)
                @php
                    $tone = match (true) {
                        $key === 'd60_plus' && $bucket['count'] > 0 => 'is-critical',
                        $key !== 'current' && $bucket['count'] > 0 => 'is-warning',
                        default => '',
                    };
                @endphp
                <div class="aging-cell {{ $tone }}">
                    <div class="aging-label">{{ $bucket['label'] }}</div>
                    <div class="aging-count">{{ $bucket['count'] }} <small>invoice</small></div>
                    <div class="aging-amount">{{ rupiah($bucket['amount']) }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ── Transaksi ────────────────────────────────────────────────────── --}}
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal Bayar</th>
                        <th>No Invoice</th>
                        <th>Pelanggan</th>
                        <th class="num">Jumlah</th>
                        <th>Metode</th>
                        <th>Dicatat Oleh</th>
                        <th>Sumber</th>
                        <th>Status Invoice</th>
                        <th class="num">Sisa Invoice</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="mono">{{ $row['payment_date']->translatedFormat('d M Y') }}</td>
                            <td class="num strong" style="text-align:left">{{ $row['invoice_no'] ?? '—' }}</td>
                            <td>{{ $row['customer'] ?? '—' }}</td>
                            <td class="num strong">{{ rupiah($row['amount']) }}</td>
                            <td class="text-muted">{{ ucfirst($row['method']) }}</td>
                            <td class="text-muted">{{ $row['recorded_by'] ?? '—' }}</td>
                            <td><span class="badge badge-neutral">{{ $row['source'] }}</span></td>
                            <td><x-invoice-status :status="$row['invoice_status'] ?? 'issued'" /></td>
                            <td class="num" style="color:{{ ($row['invoice_outstanding'] ?? 0) > 0 ? 'var(--danger)' : 'var(--text-faint)' }}">
                                {{ rupiah($row['invoice_outstanding'] ?? 0) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="table-empty">Belum ada pembayaran pada rentang ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr style="background:var(--bg-subtle);font-weight:700">
                            <td colspan="3">Total</td>
                            <td class="num">{{ rupiah($totals['amount']) }}</td>
                            <td colspan="5"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
