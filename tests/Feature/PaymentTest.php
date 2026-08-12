<?php

namespace Tests\Feature;

use App\Livewire\Billing\PaymentPage;
use App\Models\BillingPeriod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\InvoiceDocumentService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);

        $this->actingAs(User::create([
            'name' => 'Admin', 'username' => 'admin', 'email' => 'admin@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'),
        ]));
    }

    private function invoice(float $total = 1_000_000, string $status = 'issued'): Invoice
    {
        $customer = Customer::create(['code' => 'C-001', 'name' => 'PT Contoh', 'status' => 'active']);

        $period = BillingPeriod::create([
            'code' => '2026-07', 'period_start' => '2026-07-01',
            'period_end' => '2026-07-31', 'cut_off_date' => '2026-08-01',
        ]);

        return Invoice::create([
            'invoice_no' => 'INV/2026/07/001',
            'billing_period_id' => $period->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-15',
            'kwh_lwbp' => 24_180, 'rate_lwbp' => 1114.74, 'amount_lwbp' => 26_954_617.2,
            'kwh_wbp' => 7_940, 'rate_wbp' => 1560.64, 'amount_wbp' => 12_391_481.6,
            'subtotal' => $total, 'total_amount' => $total,
            'status' => $status,
        ]);
    }

    public function test_pembayaran_penuh_menandai_invoice_lunas(): void
    {
        $invoice = $this->invoice(1_000_000);

        Livewire::test(PaymentPage::class)
            ->call('create', $invoice->id)
            ->set('form.amount', 1_000_000)
            ->set('form.payment_date', '2026-08-05')
            ->call('save')
            ->assertHasNoErrors();

        $invoice->refresh();

        $this->assertSame('paid', $invoice->status);
        $this->assertEquals(1_000_000, $invoice->paid_amount);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_pembayaran_sebagian_menandai_status_partial(): void
    {
        $invoice = $this->invoice(1_000_000);

        Livewire::test(PaymentPage::class)
            ->call('create', $invoice->id)
            ->set('form.amount', 400_000)
            ->set('form.payment_date', '2026-08-05')
            ->call('save');

        $invoice->refresh();

        $this->assertSame('partial', $invoice->status);
        $this->assertEquals(400_000, $invoice->paid_amount);
        $this->assertEquals(600_000, $invoice->outstanding);
    }

    public function test_cicilan_bertahap_akhirnya_melunasi(): void
    {
        $invoice = $this->invoice(1_000_000);

        foreach ([400_000, 350_000, 250_000] as $amount) {
            InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'payment_date' => '2026-08-05',
                'amount' => $amount,
                'method' => 'transfer',
            ]);
        }

        $invoice->refresh();

        $this->assertSame('paid', $invoice->status);
        $this->assertEquals(1_000_000, $invoice->paid_amount);
    }

    public function test_menghapus_pembayaran_mengembalikan_status_invoice(): void
    {
        $invoice = $this->invoice(1_000_000);

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id, 'payment_date' => '2026-08-05',
            'amount' => 1_000_000, 'method' => 'transfer',
        ]);

        $this->assertSame('paid', $invoice->fresh()->status);

        Livewire::test(PaymentPage::class)->call('delete', $payment->id);

        $invoice->refresh();

        $this->assertEquals(0, $invoice->paid_amount);
        $this->assertNotSame('paid', $invoice->status);
    }

    public function test_selisih_pembulatan_di_bawah_satu_rupiah_tetap_dianggap_lunas(): void
    {
        $invoice = $this->invoice(1_000_000.4);

        InvoicePayment::create([
            'invoice_id' => $invoice->id, 'payment_date' => '2026-08-05',
            'amount' => 1_000_000, 'method' => 'transfer',
        ]);

        // Sisa 0,4 rupiah tidak mungkin dibayar; invoice tetap lunas.
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_invoice_draft_tidak_bisa_menerima_pembayaran(): void
    {
        $invoice = $this->invoice(1_000_000, 'draft');

        Livewire::test(PaymentPage::class)
            ->call('create')
            ->set('form.invoice_id', $invoice->id)
            ->set('form.amount', 1_000_000)
            ->set('form.payment_date', '2026-08-05')
            ->call('save');

        $this->assertSame(0, InvoicePayment::count());
        $this->assertSame('draft', $invoice->fresh()->status);
    }

    public function test_jumlah_pembayaran_harus_lebih_dari_nol(): void
    {
        $invoice = $this->invoice();

        Livewire::test(PaymentPage::class)
            ->call('create', $invoice->id)
            ->set('form.amount', 0)
            ->call('save')
            ->assertHasErrors(['form.amount']);
    }

    public function test_pdf_invoice_bisa_dihasilkan(): void
    {
        $invoice = $this->invoice();

        $output = app(InvoiceDocumentService::class)->pdf($invoice)->output();

        $this->assertStringStartsWith('%PDF', $output);
        $this->assertGreaterThan(1000, strlen($output));
    }

    public function test_baris_invoice_menyembunyikan_komponen_bernilai_nol(): void
    {
        $invoice = $this->invoice();
        $invoice->update(['biaya_beban' => 0, 'biaya_admin' => 0]);

        $lines = app(InvoiceDocumentService::class)->lines($invoice->fresh());

        // Hanya LWBP dan WBP yang tersisa.
        $this->assertCount(2, $lines);
    }

    public function test_route_unduh_pdf_mengembalikan_berkas(): void
    {
        $invoice = $this->invoice();

        $this->get(route('billing.invoices.download', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
