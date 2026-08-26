<?php

namespace Tests\Feature;

use App\Mail\InvoiceMail;
use App\Mail\ReceiptMail;
use App\Mail\ReceiptVoidedMail;
use App\Mail\SmtpTestMail;
use App\Models\BillingPeriod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\Billing\InvoiceDocumentService;
use App\Services\Billing\ReceiptService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setiap email harus benar-benar bisa dirender.
 *
 * Test lain memakai Mail::fake(), yang mencatat email tanpa pernah menyusun
 * isinya — sehingga kesalahan pada template tidak terdeteksi sama sekali.
 * Itulah yang membuat seluruh email aplikasi pernah gagal terkirim dengan
 * "No hint path defined for [mail]" sementara semua test tetap hijau:
 * komponen <x-mail::message> hanya tersedia bila Content memakai markdown:,
 * bukan view:.
 *
 * Kegagalannya pun tidak terlihat di aplikasi — pengiriman berjalan lewat
 * antrean, jadi pesan galatnya hanya masuk log worker.
 */
class MailRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    private function invoice(): Invoice
    {
        $customer = Customer::create([
            'code' => 'C-001', 'name' => 'PT Pelanggan Uji',
            'email' => 'pelanggan@uji.test', 'status' => 'active',
        ]);

        $period = BillingPeriod::create([
            'code' => '2026-07', 'period_start' => '2026-07-01',
            'period_end' => '2026-07-31', 'cut_off_date' => '2026-08-01',
        ]);

        return Invoice::create([
            'invoice_no' => 'INV/2026/07/001',
            'billing_period_id' => $period->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'period_start' => '2026-07-01', 'period_end' => '2026-07-31',
            'issue_date' => '2026-08-01', 'due_date' => '2026-08-15',
            'kwh_lwbp' => 1234.56, 'kwh_wbp' => 321.44,
            'total_amount' => 2_500_000, 'status' => 'issued',
        ]);
    }

    public function test_email_invoice_bisa_dirender(): void
    {
        $invoice = $this->invoice();

        $html = (new InvoiceMail($invoice, app(InvoiceDocumentService::class)))->render();

        $this->assertStringContainsString('INV/2026/07/001', $html);
        $this->assertStringContainsString('PT Pelanggan Uji', $html);
    }

    public function test_email_kuitansi_bisa_dirender(): void
    {
        $payment = InvoicePayment::create([
            'invoice_id' => $this->invoice()->id, 'payment_date' => '2026-08-10',
            'amount' => 1_000_000, 'method' => 'transfer',
        ]);

        $html = (new ReceiptMail($payment, app(ReceiptService::class)))->render();

        $this->assertStringContainsString('PT Pelanggan Uji', $html);
    }

    public function test_email_pembatalan_kuitansi_bisa_dirender(): void
    {
        $html = (new ReceiptVoidedMail(
            receiptNo: 'KW/2026/08/001',
            invoiceNo: 'INV/2026/07/001',
            customerName: 'PT Pelanggan Uji',
            amount: 1_000_000,
            reason: 'Salah input',
        ))->render();

        $this->assertStringContainsString('KW/2026/08/001', $html);
        $this->assertStringContainsString('Salah input', $html);
    }

    public function test_email_uji_smtp_bisa_dirender(): void
    {
        $html = (new SmtpTestMail('Energy Billing'))->render();

        $this->assertStringContainsString('SMTP', $html);
    }

    /**
     * Komponen <x-mail::message> harus benar-benar terpasang, bukan tercetak
     * apa adanya sebagai teks. Kalau namespace 'mail' tidak terdaftar,
     * rendernya melempar exception — dan bila kelak diganti view biasa,
     * tag komponennya akan bocor ke isi email.
     */
    public function test_komponen_mail_terpasang_bukan_tercetak_mentah(): void
    {
        $html = (new SmtpTestMail('Energy Billing'))->render();

        $this->assertStringNotContainsString('x-mail::', $html);
        // Tata letak markdown mail Laravel selalu menghasilkan tabel pembungkus.
        $this->assertStringContainsString('<table', $html);
    }
}
