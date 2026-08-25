<?php

namespace App\Livewire\Portal;

use App\Models\InvoicePayment;
use Livewire\WithPagination;

/**
 * Riwayat pembayaran yang tercatat, beserta kuitansinya — hanya baca.
 *
 * Kuitansi hanya bisa diunduh bila NOMORNYA SUDAH TERBIT. Alasannya bukan
 * kerapian: ReceiptService::pdf() memberi nomor kuitansi saat pertama kali
 * diakses, jadi membuka jalur itu ke portal berarti klik seorang pelanggan
 * yang meresmikan nomor dokumen akuntansi — dan urutan nomornya jadi
 * ditentukan oleh siapa yang lebih dulu membuka halaman.
 */
class PaymentPage extends PortalComponent
{
    use WithPagination;

    protected string $permission = 'portal.payment.view';

    public function render()
    {
        $payments = $this->ownedQuery()
            ->with(['invoice:id,invoice_no,customer_id,customer_name,total_amount,paid_amount,status'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(20);

        $rows = $this->ownedQuery()->get(['amount', 'payment_date']);

        return view('livewire.portal.payment-page', [
            'payments' => $payments,
            'summary' => [
                'count' => $rows->count(),
                'total' => (float) $rows->sum('amount'),
                'last_at' => $rows->max('payment_date'),
            ],
        ]);
    }

    /**
     * Pembayaran atas invoice milik pelanggan yang diakses.
     *
     * Dibatasi lewat whereHas ke invoice, karena `invoice_payments` sendiri
     * tidak punya kolom customer_id — jadi tidak ada jalan menyaringnya
     * langsung, dan lupa join berarti seluruh pembayaran seluruh pelanggan
     * ikut tampil.
     *
     * Status invoice ikut disaring di sini memakai aturan yang sama dengan
     * daftar invoice, bukan diandalkan pada alur staf. Ketiga jalur pencatatan
     * pembayaran (manual, massal, impor) memang sudah menolak invoice draft,
     * dan pembatalan ditolak bila invoice sudah punya pembayaran — jadi hari
     * ini kondisi ini tidak akan terpenuhi. Tapi kalau invarian itu berubah,
     * yang bocor adalah nomor invoice yang di halaman invoice justru
     * disembunyikan, lewat halaman yang tidak diduga.
     */
    private function ownedQuery()
    {
        return InvoicePayment::query()
            ->whereHas('invoice', fn ($q) => $q
                ->whereIn('customer_id', $this->customerIds())
                ->portalVisible());
    }
}
