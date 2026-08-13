<div>

    {{-- ── Ringkasan ───────────────────────────────────────────────────── --}}
    <div class="stat-grid grid-1-1-1">
        <div class="card">
            <div class="stat-label">Belum Dibayar</div>
            <div class="stat-value sm" style="margin-top:8px">{{ rupiah($summary['outstanding']) }}</div>
            <div class="stat-foot {{ $summary['overdue_count'] > 0 ? 'down' : '' }}">
                {{ $summary['overdue_count'] }} invoice jatuh tempo
            </div>
        </div>
        <div class="card">
            <div class="stat-label">Terbayar {{ $summary['paid_last_month_label'] }}</div>
            <div class="stat-value sm" style="margin-top:8px">{{ rupiah($summary['paid_last_month']) }}</div>
            <div class="stat-foot up">Invoice berstatus lunas</div>
        </div>
        <div class="card">
            <div class="stat-label">Draft Menunggu Terbit</div>
            <div class="stat-value sm" style="margin-top:8px">{{ $summary['draft_count'] }}</div>
            <div class="stat-foot">Belum ditagihkan ke pelanggan</div>
        </div>
    </div>

    {{-- ── Filter ──────────────────────────────────────────────────────── --}}
    <div class="card mb-18">
        <div class="filter-bar">
            <div class="field">
                <label class="field-label">Cari</label>
                <input type="text" class="input" placeholder="No invoice, pelanggan, atau meter…"
                       wire:model.live.debounce.400ms="search">
            </div>
            <div class="field">
                <label class="field-label">Periode</label>
                <x-select-search
                    wire:model.live="periodFilter"
                    placeholder="Semua periode"
                    search-placeholder="Cari periode…"
                    :options="$periods
                        ->map(fn ($period) => ['value' => $period->id, 'label' => $period->code])
                        ->prepend(['value' => '', 'label' => 'Semua periode'])" />
            </div>
            <div class="field">
                <label class="field-label">Status</label>
                <x-select-search wire:model.live="statusFilter" placeholder="Semua status"
                    :options="[
                        ['value' => '', 'label' => 'Semua status'],
                        ['value' => 'draft', 'label' => 'Draft'],
                        ['value' => 'issued', 'label' => 'Belum Bayar'],
                        ['value' => 'partial', 'label' => 'Bayar Sebagian'],
                        ['value' => 'paid', 'label' => 'Lunas'],
                        ['value' => 'overdue', 'label' => 'Jatuh Tempo'],
                        ['value' => 'cancelled', 'label' => 'Dibatalkan'],
                    ]" />
            </div>
        </div>
    </div>

    {{-- ── Tabel ───────────────────────────────────────────────────────── --}}
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No Invoice</th>
                        <th>Pelanggan</th>
                        <th>Meter</th>
                        <th>Periode</th>
                        <th class="num">LWBP</th>
                        <th class="num">WBP</th>
                        <th class="num">Tagihan</th>
                        <th class="num">Sisa</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr class="clickable" wire:click="show({{ $invoice->id }})">
                            <td class="num strong" style="text-align:left;color:var(--primary)">{{ $invoice->invoice_no }}</td>
                            <td>{{ $invoice->customer_name }}</td>
                            <td class="num text-muted" style="text-align:left">{{ $invoice->meter_code ?? '—' }}</td>
                            <td class="text-muted">
                                {{ $invoice->period_start->translatedFormat('d M') }} –
                                {{ $invoice->period_end->translatedFormat('d M Y') }}
                            </td>
                            <td class="num">{{ kwh($invoice->kwh_lwbp) }}</td>
                            <td class="num">{{ kwh($invoice->kwh_wbp) }}</td>
                            <td class="num strong">{{ rupiah($invoice->total_amount) }}</td>
                            <td class="num" style="color:{{ $invoice->outstanding > 0 ? 'var(--danger)' : 'var(--success)' }}">
                                {{ rupiah($invoice->outstanding) }}
                            </td>
                            <td><x-invoice-status :status="$invoice->status" /></td>
                            <td class="text-right nowrap" wire:click.stop>
                                <a href="{{ route('billing.invoices.preview', $invoice) }}" target="_blank"
                                   class="link-action muted" style="margin-right:12px">PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="table-empty">
                                {{ $search || $statusFilter || $periodFilter
                                    ? 'Tidak ada invoice yang cocok dengan filter.'
                                    : 'Belum ada invoice. Generate lewat menu Periode & Generate.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($invoices->hasPages())
            <div style="margin-top:16px">{{ $invoices->links() }}</div>
        @endif
    </div>

    {{-- ── Detail invoice ──────────────────────────────────────────────── --}}
    @if ($detail)
        <div class="modal-overlay" wire:click.self="$set('detailId', null)">
            <div class="modal">

                <div class="invoice-head">
                    <div>
                        <div class="invoice-kicker">INVOICE PEMAKAIAN LISTRIK</div>
                        <div class="invoice-no">{{ $detail->invoice_no }}</div>
                        <div style="margin-top:10px"><x-invoice-status :status="$detail->status" /></div>
                    </div>
                    <div class="invoice-issuer">
                        <strong>{{ setting('company_name') }}</strong>
                        {{ setting('company_address') }}<br>
                        {{ setting('company_domain') }}
                    </div>
                </div>

                <div class="invoice-meta">
                    <div>
                        <div class="field-label">Ditagihkan Kepada</div>
                        <div style="font-size:15px;font-weight:700">{{ $detail->customer_name }}</div>
                        <div class="text-muted" style="font-size:13px;margin-top:4px;line-height:1.6">
                            {{ $detail->customer_address }}
                        </div>
                    </div>
                    <div class="invoice-meta-grid">
                        <div>
                            <div class="field-label">Periode</div>
                            <div style="font-size:13px;font-weight:600">
                                {{ $detail->period_start->translatedFormat('d M') }} –
                                {{ $detail->period_end->translatedFormat('d M Y') }}
                            </div>
                        </div>
                        <div>
                            <div class="field-label">Jatuh Tempo</div>
                            <div style="font-size:13px;font-weight:600">
                                {{ $detail->due_date?->translatedFormat('d M Y') ?? '—' }}
                            </div>
                        </div>
                        <div>
                            <div class="field-label">Power Meter</div>
                            <div class="mono" style="font-size:13px;font-weight:600">{{ $detail->meter_code ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="field-label">Golongan</div>
                            <div style="font-size:13px;font-weight:600">{{ $detail->tariff_group_code ?? '—' }}</div>
                        </div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="table invoice-table">
                        <thead>
                            <tr>
                                <th>Uraian</th>
                                <th class="num">Stand Awal</th>
                                <th class="num">Stand Akhir</th>
                                <th class="num">kWh</th>
                                <th class="num">Tarif</th>
                                <th class="num">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detailLines as $line)
                                <tr>
                                    <td class="strong">{{ $line['label'] }}</td>
                                    <td class="num text-muted">{{ $line['start'] }}</td>
                                    <td class="num text-muted">{{ $line['end'] }}</td>
                                    <td class="num">{{ $line['kwh'] }}</td>
                                    <td class="num">{{ $line['rate'] }}</td>
                                    <td class="num strong">{{ rupiah($line['amount'], false) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="invoice-totals" style="margin-top:18px">
                    <div class="invoice-totals-inner">
                        @foreach ($detailTotals as $row)
                            <div class="invoice-total-row">
                                <span>{{ $row['label'] }}</span>
                                <span class="value">{{ $row['value'] }}</span>
                            </div>
                        @endforeach
                        <div class="invoice-grand">
                            <span class="label">TOTAL TAGIHAN</span>
                            <span class="value">{{ rupiah($detail->total_amount) }}</span>
                        </div>
                        @if ($detail->paid_amount > 0)
                            <div class="invoice-total-row" style="margin-top:10px">
                                <span>Sudah dibayar</span>
                                <span class="value" style="color:var(--success)">{{ rupiah($detail->paid_amount) }}</span>
                            </div>
                            <div class="invoice-total-row">
                                <span>Sisa tagihan</span>
                                <span class="value" style="color:var(--danger)">{{ rupiah($detail->outstanding) }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($detail->notes)
                    <div class="alert alert-warning" style="margin-top:20px">{{ $detail->notes }}</div>
                @endif

                @if ($detail->payments->isNotEmpty())
                    <div style="margin-top:24px">
                        <div class="card-title" style="margin-bottom:10px">Riwayat Pembayaran</div>
                        @foreach ($detail->payments as $payment)
                            <div class="kv-row">
                                <span class="kv-label">
                                    {{ $payment->payment_date->translatedFormat('d M Y') }} ·
                                    {{ ucfirst($payment->method) }}
                                    {{ $payment->reference_no ? ' · '.$payment->reference_no : '' }}
                                </span>
                                <span class="kv-value">{{ rupiah($payment->amount) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="row" style="margin-top:26px;padding-top:20px;border-top:1px solid var(--border-soft);flex-wrap:wrap">
                    @if ($detail->status === 'draft')
                        @can('invoice.update')
                            <button type="button" class="btn btn-primary" wire:click="issue({{ $detail->id }})">
                                <i data-lucide="check" style="width:15px;height:15px"></i>
                                Terbitkan
                            </button>
                        @endcan
                    @endif

                    @can('invoice.send')
                        <button type="button" class="btn btn-outline"
                                wire:click="sendEmail({{ $detail->id }})"
                                wire:loading.attr="disabled"
                                wire:confirm="Kirim invoice {{ $detail->invoice_no }} ke email pelanggan?">
                            <i data-lucide="mail" style="width:15px;height:15px"></i>
                            <span wire:loading.remove wire:target="sendEmail">Kirim ke Pelanggan</span>
                            <span wire:loading wire:target="sendEmail">Mengirim…</span>
                        </button>
                    @endcan

                    <a href="{{ route('billing.invoices.download', $detail) }}" class="btn btn-outline">
                        <i data-lucide="download" style="width:15px;height:15px"></i>
                        Unduh PDF
                    </a>

                    @if (!in_array($detail->status, ['paid', 'cancelled']))
                        @can('invoice.delete')
                            <button type="button" class="btn btn-ghost" style="color:var(--danger)"
                                    wire:click="cancel({{ $detail->id }})"
                                    wire:confirm="Batalkan invoice {{ $detail->invoice_no }}?">
                                Batalkan
                            </button>
                        @endcan
                    @endif

                    <button type="button" class="btn btn-ghost" style="margin-left:auto"
                            wire:click="$set('detailId', null)">Tutup</button>
                </div>

            </div>
        </div>
    @endif

</div>
