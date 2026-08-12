<div>

    <div class="card mb-18">
        <div class="filter-bar">
            <div class="field">
                <label class="field-label">Dari Tanggal</label>
                <input type="date" class="input mono" wire:model.live="from">
            </div>
            <div class="field">
                <label class="field-label">Sampai Tanggal</label>
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
                <a href="{{ route('report.export', ['type' => 'usage', 'format' => 'xlsx']) }}?{{ $exportQuery }}"
                   class="btn btn-outline">
                    <i data-lucide="sheet" style="width:15px;height:15px"></i> Excel
                </a>
                <a href="{{ route('report.export', ['type' => 'usage', 'format' => 'pdf']) }}?{{ $exportQuery }}"
                   class="btn btn-outline">
                    <i data-lucide="file-text" style="width:15px;height:15px"></i> PDF
                </a>
            @endcan
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Pelanggan</th>
                        <th>Meter</th>
                        <th>Golongan</th>
                        <th class="num">LWBP (kWh)</th>
                        <th class="num">WBP (kWh)</th>
                        <th class="num">Total kWh</th>
                        <th class="num">Beban Puncak</th>
                        <th class="num">Hari Berdata</th>
                        <th class="num">Tagihan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="strong">
                                {{ $row['customer'] }}
                                <div class="sub">{{ $row['code'] }}</div>
                            </td>
                            <td class="num text-muted" style="text-align:left">{{ $row['meter'] ?? '—' }}</td>
                            <td>{{ $row['tariff_group'] ?? '—' }}</td>
                            <td class="num">{{ kwh($row['lwbp'], 1) }}</td>
                            <td class="num">{{ kwh($row['wbp'], 1) }}</td>
                            <td class="num strong">{{ kwh($row['total_kwh'], 1) }}</td>
                            <td class="num">{{ $row['peak_kw'] !== null ? kwh($row['peak_kw'], 1).' kW' : '—' }}</td>
                            <td class="num text-muted">{{ $row['days'] }}</td>
                            <td class="num strong">{{ rupiah($row['billed']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="table-empty">
                                Belum ada data agregat harian pada rentang ini.
                                Data terisi setelah gateway mengirim pembacaan dan job agregasi berjalan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr style="background:var(--bg-subtle);font-weight:700">
                            <td colspan="3">Total</td>
                            <td class="num">{{ kwh($totals['lwbp'], 1) }}</td>
                            <td class="num">{{ kwh($totals['wbp'], 1) }}</td>
                            <td class="num">{{ kwh($totals['total_kwh'], 1) }}</td>
                            <td colspan="2"></td>
                            <td class="num">{{ rupiah($totals['billed']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
