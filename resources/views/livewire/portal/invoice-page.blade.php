<div>

    {{-- ── Ringkasan ───────────────────────────────────────────────── --}}
    <div class="stat-grid grid-1-1-1">
        <div class="card">
            <div class="stat-label">Belum Lunas</div>
            <div class="stat-value sm" style="margin-top:8px">{{ rupiah($summary['outstanding']) }}</div>
            <div class="stat-foot {{ $summary['overdue_count'] > 0 ? 'down' : '' }}">
                @if ($summary['unpaid_count'] === 0)
                    Semua tagihan sudah lunas
                @else
                    {{ $summary['unpaid_count'] }} invoice menunggu pembayaran
                @endif
            </div>
        </div>
        <div class="card">
            <div class="stat-label">Lewat Jatuh Tempo</div>
            <div class="stat-value sm" style="margin-top:8px">{{ $summary['overdue_count'] }} <small>invoice</small></div>
            <div class="stat-foot {{ $summary['overdue_count'] > 0 ? 'down' : 'up' }}">
                {{ $summary['overdue_count'] > 0 ? 'Segera lakukan pembayaran' : 'Tidak ada tunggakan' }}
            </div>
        </div>
        <div class="card">
            <div class="stat-label">Sudah Lunas</div>
            <div class="stat-value sm" style="margin-top:8px">{{ $summary['paid_count'] }} <small>invoice</small></div>
            <div class="stat-foot">Sepanjang riwayat tagihan Anda</div>
        </div>
    </div>

    {{-- ── Filter ──────────────────────────────────────────────────── --}}
    <div class="card mb-18">
        <div class="filter-bar">
            <div class="field" style="min-width:200px">
                <label class="field-label">Status</label>
                <x-select-search wire:model.live="statusFilter"
                    :options="array_merge(
                        [['value' => '', 'label' => 'Semua status']],
                        collect(\App\Livewire\Portal\InvoicePage::statusOptions())
                            ->map(fn ($status) => [
                                'value' => $status,
                                'label' => \App\Models\Invoice::STATUS_LABELS[$status] ?? $status,
                            ])->all(),
                    )" />
            </div>
        </div>
    </div>

    {{-- ── Tabel ───────────────────────────────────────────────────── --}}
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No. Invoice</th>
                        <th>Periode</th>
                        <th>Jatuh Tempo</th>
                        <th class="num">Total kWh</th>
                        <th class="num">Tagihan</th>
                        <th class="num">Sisa</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td class="mono strong">{{ $invoice->invoice_no }}</td>
                            <td>{{ $invoice->period_start->translatedFormat('M Y') }}</td>
                            <td class="nowrap">
                                {{ $invoice->due_date?->translatedFormat('d M Y') ?? '—' }}
                                @if ($invoice->status === 'overdue')
                                    <span class="badge badge-danger" style="margin-left:6px">Lewat</span>
                                @endif
                            </td>
                            <td class="num">{{ kwh($invoice->total_kwh, 1) }}</td>
                            <td class="num strong">{{ rupiah($invoice->total_amount, false) }}</td>
                            <td class="num {{ $invoice->outstanding > 0 ? '' : 'text-muted' }}"
                                @if ($invoice->outstanding > 0) style="color:var(--danger);font-weight:600" @endif>
                                {{ rupiah($invoice->outstanding, false) }}
                            </td>
                            <td><x-invoice-status :status="$invoice->status" /></td>
                            <td class="text-right nowrap">
                                <span class="link-action" wire:click="show({{ $invoice->id }})">Rincian</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="table-empty">
                                {{ $statusFilter
                                    ? 'Tidak ada invoice dengan status itu.'
                                    : 'Belum ada invoice yang diterbitkan untuk Anda.' }}
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

    {{-- ── Rincian invoice ─────────────────────────────────────────── --}}
    @if ($detail)
        <div class="modal-overlay" wire:click.self="closeDetail">
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

                {{-- Tidak ada blok "invoice dibatalkan" di sini: invoice batal
                     tidak pernah sampai ke portal (Invoice::HIDDEN_FROM_PORTAL),
                     jadi penandanya hanya akan jadi kode yang tak pernah
                     dirender dan menyesatkan pembaca berikutnya. --}}

                {{-- Seluruh angka di bawah ini dibaca dari kolom snapshot pada
                     baris invoice, bukan dari data pelanggan/tarif yang berlaku
                     sekarang — supaya tagihan lama tidak berubah isi ketika
                     tarif atau alamat diperbarui. --}}
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

                @if ($canViewPayment && $detail->payments->isNotEmpty())
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
                    <a href="{{ route('portal.invoices.preview', $detail) }}" target="_blank" rel="noopener"
                       class="btn btn-outline">
                        <i data-lucide="eye" style="width:15px;height:15px"></i>
                        Lihat PDF
                    </a>
                    <a href="{{ route('portal.invoices.download', $detail) }}" class="btn btn-primary">
                        <i data-lucide="download" style="width:15px;height:15px"></i>
                        Unduh PDF
                    </a>
                    <div class="spacer"></div>
                    <button type="button" class="btn btn-ghost" wire:click="closeDetail">Tutup</button>
                </div>

            </div>
        </div>
    @endif

</div>
