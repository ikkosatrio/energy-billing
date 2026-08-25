<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CustomerUser;
use App\Models\Invoice;
use App\Models\InvoicePayment;

/**
 * Basis controller portal untuk hal yang bukan halaman Livewire — unduhan PDF.
 *
 * Route model binding memuat Invoice/InvoicePayment dari id di URL apa adanya.
 * Permission `portal.invoice.view` hanya menjawab "boleh lihat invoice", bukan
 * "invoice ini miliknya" — jadi tanpa pemeriksaan di sini, mengganti angka di
 * URL cukup untuk mengunduh invoice pelanggan lain.
 *
 * Ini persis lubang yang masih terbuka di controller invoice sisi staf
 * (di sana semua penggunanya orang dalam, jadi konsekuensinya berbeda).
 */
abstract class PortalController extends Controller
{
    protected function portalUser(): CustomerUser
    {
        return auth(CustomerUser::GUARD)->user();
    }

    /** Menggagalkan request bila invoice bukan milik pelanggan yang diakses. */
    protected function authorizeInvoice(Invoice $invoice): void
    {
        abort_unless($this->portalUser()->canAccessCustomer($invoice->customer_id), 403);
    }

    /**
     * Kuitansi ikut aturan invoice induknya — SEMUA aturannya, bukan hanya
     * kepemilikan.
     *
     * Menyaring baris di tabel tidak menutup URL-nya: kuitansi atas invoice
     * yang disembunyikan (draft, dibatalkan) tetap terunduh dengan menebak
     * satu angka, padahal invoice-nya sendiri sudah 404. Status diperiksa di
     * sini supaya kedua jalur menjawab hal yang sama.
     *
     * Pembayaran yang invoice-nya hilang (mis. terhapus di luar alur normal)
     * ditolak, bukan dianggap boleh — default yang aman.
     */
    protected function authorizePayment(InvoicePayment $payment): void
    {
        $invoice = $payment->invoice;

        abort_unless($invoice !== null && $this->portalUser()->canAccessCustomer($invoice->customer_id), 403);

        // 404, bukan 403: keberadaan dokumennya sendiri memang tidak perlu
        // dikonfirmasi ke pelanggan — sama seperti invoice-nya.
        abort_unless($invoice->isVisibleToPortal(), 404);
    }
}
