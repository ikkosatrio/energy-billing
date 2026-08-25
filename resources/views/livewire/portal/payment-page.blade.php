<div>

    {{-- ── Ringkasan ───────────────────────────────────────────────── --}}
    <div class="stat-grid grid-1-1-1">
        <div class="card">
            <div class="stat-label">Total Dibayar</div>
            <div class="stat-value sm" style="margin-top:8px">{{ rupiah($summary['total']) }}</div>
            <div class="stat-foot">Sepanjang riwayat pembayaran Anda</div>
        </div>
        <div class="card">
            <div class="stat-label">Jumlah Transaksi</div>
            <div class="stat-value sm" style="margin-top:8px">{{ $summary['count'] }} <small>pembayaran</small></div>
            <div class="stat-foot">Termasuk pembayaran bertahap</div>
        </div>
        <div class="card">
            <div class="stat-label">Pembayaran Terakhir</div>
            <div class="stat-value sm" style="margin-top:8px;font-size:15px">
                {{ $summary['last_at']
                    ? \Illuminate\Support\Carbon::parse($summary['last_at'])->translatedFormat('d M Y')
                    : '—' }}
            </div>
            <div class="stat-foot">Tanggal bayar tercatat</div>
        </div>
    </div>

    {{-- ── Tabel ───────────────────────────────────────────────────── --}}
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal Bayar</th>
                        <th>No. Invoice</th>
                        <th>Metode</th>
                        <th>Referensi</th>
                        <th class="num">Jumlah</th>
                        <th>Kuitansi</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td class="mono nowrap">{{ $payment->payment_date->translatedFormat('d M Y') }}</td>
                            <td class="mono strong">{{ $payment->invoice?->invoice_no ?? '—' }}</td>
                            <td>
                                <span class="badge badge-neutral">{{ ucfirst($payment->method) }}</span>
                            </td>
                            <td class="mono text-muted">{{ $payment->reference_no ?: '—' }}</td>
                            <td class="num strong">{{ rupiah($payment->amount, false) }}</td>
                            <td class="nowrap">
                                @if ($payment->hasReceipt())
                                    <span class="mono" style="font-size:12px">{{ $payment->receipt_no }}</span>
                                @else
                                    {{-- Nomor kuitansi diberikan saat dokumennya
                                         diterbitkan pengelola, jadi sebelum itu
                                         memang belum ada yang bisa diunduh. --}}
                                    <span class="text-faint">belum diterbitkan</span>
                                @endif
                            </td>
                            <td class="text-right nowrap">
                                @if ($payment->hasReceipt())
                                    <a href="{{ route('portal.payments.receipt.preview', $payment) }}"
                                       target="_blank" rel="noopener"
                                       class="link-action" style="margin-right:12px">Lihat</a>
                                    <a href="{{ route('portal.payments.receipt', $payment) }}"
                                       class="link-action">Unduh</a>
                                @else
                                    <span class="text-faint">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="table-empty">
                                Belum ada pembayaran yang tercatat untuk tagihan Anda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($payments->hasPages())
            <div style="margin-top:16px">{{ $payments->links() }}</div>
        @endif

        <div class="card-sub" style="margin-top:12px">
            Pembayaran tercatat setelah diverifikasi pengelola. Bila Anda sudah membayar
            tapi belum muncul di sini, hubungi pengelola dengan menyertakan bukti transfer.
        </div>
    </div>

</div>
