<?php

namespace Tests\Feature;

use App\Livewire\Billing\InvoicePage;
use App\Models\BillingPeriod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kartu ringkasan di halaman Daftar Invoice.
 *
 * Kartu "Terbayar <bulan>" memakai PERIODE PEMAKAIAN, bukan tanggal terbit:
 * invoice pemakaian Agustus terbit awal September, sehingga penyaringan
 * berdasarkan tanggal terbit membuat kartu berlabel Agustus hampir selalu nol.
 */
class InvoicePageSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);

        // 4 September: bulan lalu = Agustus.
        Carbon::setTestNow('2026-09-04 09:00:00');

        $this->actingAs(User::create([
            'name' => 'Admin', 'username' => 'admin', 'email' => 'admin@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'),
        ]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Invoice pemakaian Agustus yang terbit September, seperti di lapangan. */
    private function invoice(float $total, string $code = 'C-001', string $status = 'issued'): Invoice
    {
        $customer = Customer::firstOrCreate(
            ['code' => $code],
            ['name' => 'PT Pelanggan '.$code, 'status' => 'active'],
        );

        $period = BillingPeriod::firstOrCreate(
            ['code' => '2026-08'],
            ['period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'cut_off_date' => '2026-09-01'],
        );

        return Invoice::create([
            'invoice_no' => 'INV/2026/09/'.$code,
            'billing_period_id' => $period->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'period_start' => '2026-08-01', 'period_end' => '2026-08-31',
            'issue_date' => '2026-09-01', 'due_date' => '2026-09-15',
            'total_amount' => $total, 'status' => $status,
        ]);
    }

    private function summary(): array
    {
        return Livewire::test(InvoicePage::class)->viewData('summary');
    }

    public function test_invoice_lunas_periode_lalu_terhitung_walau_terbit_bulan_ini(): void
    {
        $invoice = $this->invoice(47_000);
        InvoicePayment::create([
            'invoice_id' => $invoice->id, 'payment_date' => '2026-09-02',
            'amount' => 47_000, 'method' => 'transfer',
        ]);

        $summary = $this->summary();

        $this->assertEquals(47_000, $summary['paid_last_month']);
        $this->assertSame('Agustus 2026', $summary['paid_last_month_label']);
    }

    /** Pembayaran sebagian ikut terhitung — uangnya sudah diterima. */
    public function test_pembayaran_sebagian_ikut_terhitung(): void
    {
        $invoice = $this->invoice(100_000);
        InvoicePayment::create([
            'invoice_id' => $invoice->id, 'payment_date' => '2026-09-02',
            'amount' => 40_000, 'method' => 'transfer',
        ]);

        $this->assertEquals(40_000, $this->summary()['paid_last_month']);
    }

    public function test_invoice_yang_belum_dibayar_tidak_menambah(): void
    {
        $this->invoice(25_800);

        $this->assertEquals(0, $this->summary()['paid_last_month']);
    }

    public function test_invoice_dibatalkan_tidak_terhitung(): void
    {
        $invoice = $this->invoice(80_000, 'C-002');
        InvoicePayment::create([
            'invoice_id' => $invoice->id, 'payment_date' => '2026-09-02',
            'amount' => 80_000, 'method' => 'transfer',
        ]);
        $invoice->forceFill(['status' => 'cancelled'])->save();

        $this->assertEquals(0, $this->summary()['paid_last_month']);
    }
}
