<?php

namespace App\Livewire\Portal;

use App\Models\Invoice;
use App\Services\Billing\InvoiceDocumentService;
use Livewire\WithPagination;

/**
 * Daftar invoice milik pelanggan yang diakses — hanya baca.
 *
 * Tidak menurunkan InvoicePage staf: di sana ada issue(), cancel(), reopen(),
 * sendEmail(), dan pelunasan massal. Mewarisi lalu menimpanya berarti setiap
 * aksi baru yang ditambahkan di sisi staf otomatis ikut terekspos ke portal
 * sampai ada yang ingat menimpanya juga.
 *
 * Yang tampil hanya invoice yang benar-benar berlaku sebagai tagihan — lihat
 * Invoice::HIDDEN_FROM_PORTAL untuk status yang dikecualikan dan alasannya.
 */
class InvoicePage extends PortalComponent
{
    use WithPagination;

    protected string $permission = 'portal.invoice.view';

    public string $statusFilter = '';

    /** Invoice yang sedang dibuka detailnya. */
    public ?int $detailId = null;

    /**
     * Status yang boleh dipilih pelanggan pada filter.
     *
     * Dihitung dari daftar status yang ada dikurangi yang disembunyikan —
     * bukan ditulis ulang sebagai daftar tersendiri. Daftar kedua yang
     * ditulis manual pasti akan tertinggal suatu saat, dan yang tertinggal
     * itulah yang memunculkan status yang seharusnya tidak terlihat.
     *
     * @return array<int, string>
     */
    public static function statusOptions(): array
    {
        return array_values(array_diff(
            array_keys(Invoice::STATUS_LABELS),
            Invoice::HIDDEN_FROM_PORTAL,
        ));
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->detailId = null;
    }

    public function show(int $id): void
    {
        // Diverifikasi di sini, bukan dipercaya dari $id: id yang bukan milik
        // pelanggan ini tidak boleh sampai membuka panel detail.
        abort_unless($this->ownedQuery()->whereKey($id)->exists(), 403);

        $this->detailId = $id;
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
    }

    public function render(InvoiceDocumentService $documents)
    {
        $invoices = $this->ownedQuery()
            ->when(
                in_array($this->statusFilter, self::statusOptions(), true),
                fn ($q) => $q->where('status', $this->statusFilter),
            )
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20);

        // Detail dimuat lewat query yang sama, jadi tidak ada jalur kedua yang
        // perlu diamankan terpisah.
        $detail = $this->detailId
            ? $this->ownedQuery()->with('payments')->find($this->detailId)
            : null;

        return view('livewire.portal.invoice-page', [
            'invoices' => $invoices,
            'detail' => $detail,
            // Baris uraian & total disusun service yang sama dengan PDF,
            // supaya angka di layar dan di dokumen tidak pernah berbeda.
            'detailLines' => $detail ? $documents->lines($detail) : [],
            'detailTotals' => $detail ? $documents->totals($detail) : [],
            'summary' => $this->summary(),
            'canViewPayment' => $this->portalUser()->hasPermission('portal.payment.view'),
        ]);
    }

    /**
     * Satu-satunya pintu ke tabel invoice di halaman ini. Setiap query lain
     * dibangun dari sini supaya batas pelanggan dan penyembunyian draft tidak
     * perlu ditulis ulang — dan tidak bisa terlewat.
     */
    private function ownedQuery()
    {
        return Invoice::query()
            ->whereIn('customer_id', $this->customerIds())
            ->portalVisible();
    }

    /** @return array{outstanding:float, unpaid_count:int, overdue_count:int, paid_count:int} */
    private function summary(): array
    {
        $rows = $this->ownedQuery()->get(['total_amount', 'paid_amount', 'status']);
        $unpaid = $rows->whereIn('status', ['issued', 'partial', 'overdue']);

        return [
            'outstanding' => (float) $unpaid->sum(fn (Invoice $invoice) => $invoice->outstanding),
            'unpaid_count' => $unpaid->count(),
            'overdue_count' => $rows->where('status', 'overdue')->count(),
            'paid_count' => $rows->where('status', 'paid')->count(),
        ];
    }
}
