<div>

    <div class="card mb-18">
        <div class="filter-bar">
            <div class="field">
                <label class="field-label">Terbit Dari</label>
                <input type="date" class="input mono" wire:model.live="from">
            </div>
            <div class="field">
                <label class="field-label">Sampai</label>
                <input type="date" class="input mono" wire:model.live="to">
            </div>
            <div class="field">
                <label class="field-label">Pelanggan</label>
                <select class="select" wire:model.live="customerId">
                    <option value="">Semua pelanggan</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="spacer"></div>
            @can('report.export')
                <a href="{{ route('report.export', ['type' => 'billing', 'format' => 'xlsx']) }}?{{ $exportQuery }}"
                   class="btn btn-outline">
                    <i data-lucide="sheet" style="width:15px;height:15px"></i> Excel
                </a>
                <a href="{{ route('report.export', ['type' => 'billing', 'format' => 'pdf']) }}?{{ $exportQuery }}"
                   class="btn btn-outline">
                    <i data-lucide="file-text" style="width:15px;height:15px"></i> PDF
                </a>
            @endcan
        </div>
    </div>

    {{-- Ringkasan tiga angka utama --}}
    <div class="stat-grid grid-1-1-1">
        <div class="card">
            <div class="stat-label">Total Ditagihkan</div>
            <div class="stat-value sm" style="margin-top:8px">{{ rupiah($totals['total']) }}</div>
            <div class="stat-foot">{{ $rows->count() }} invoice</div>
        </div>
        <div class="card">
            <div class="stat-label">Sudah Terbayar</div>
            <div class="stat-value sm" style="margin-top:8px">{{ rupiah($totals['paid']) }}</div>
            <div class="stat-foot up">
                {{ $totals['total'] > 0 ? round($totals['paid'] / $totals['total'] * 100) : 0 }}% dari total tagihan
            </div>
        </div>
        <div class="card">
            <div class="stat-label">Tunggakan</div>
            <div class="stat-value sm" style="margin-top:8px">{{ rupiah($totals['outstanding']) }}</div>
            <div class="stat-foot {{ $totals['outstanding'] > 0 ? 'down' : 'up' }}">
                {{ $rows->where('outstanding', '>', 0)->count() }} invoice belum lunas
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No Invoice</th>
                        <th>Pelanggan</th>
                        <th>Periode</th>
                        <th>Terbit</th>
                        <th>Jatuh Tempo</th>
                        <th class="num">Total kWh</th>
                        <th class="num">Tagihan</th>
                        <th class="num">Dibayar</th>
                        <th class="num">Sisa</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="num strong" style="text-align:left">{{ $row['invoice_no'] }}</td>
                            <td>{{ $row['customer'] }}</td>
                            <td class="text-muted">{{ $row['period'] }}</td>
                            <td class="text-muted mono">{{ $row['issue_date']?->translatedFormat('d M Y') ?? '—' }}</td>
                            <td class="text-muted mono">{{ $row['due_date']?->translatedFormat('d M Y') ?? '—' }}</td>
                            <td class="num">{{ kwh($row['total_kwh']) }}</td>
                            <td class="num strong">{{ rupiah($row['total']) }}</td>
                            <td class="num" style="color:var(--success)">{{ rupiah($row['paid']) }}</td>
                            <td class="num" style="color:{{ $row['outstanding'] > 0 ? 'var(--danger)' : 'var(--text-faint)' }}">
                                {{ rupiah($row['outstanding']) }}
                            </td>
                            <td><x-invoice-status :status="$row['status']" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="table-empty">Belum ada invoice terbit pada rentang ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr style="background:var(--bg-subtle);font-weight:700">
                            <td colspan="5">Total</td>
                            <td class="num">{{ kwh($totals['total_kwh']) }}</td>
                            <td class="num">{{ rupiah($totals['total']) }}</td>
                            <td class="num">{{ rupiah($totals['paid']) }}</td>
                            <td class="num">{{ rupiah($totals['outstanding']) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
