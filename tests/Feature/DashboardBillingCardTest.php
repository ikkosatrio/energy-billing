<?php

namespace Tests\Feature;

use App\Models\BillingPeriod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Kartu "Nilai Tagihan Berjalan" di Dashboard.
 *
 * Dasarnya periode PEMAKAIAN, bukan tanggal terbit invoice. Tagihan listrik
 * selalu menagih bulan yang sudah selesai, jadi invoice yang terbit awal
 * September berisi pemakaian Agustus — dan kartu ini harus bicara tentang
 * Agustus, sesuai labelnya.
 */
class DashboardBillingCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 4 September: periode yang sedang ditagihkan adalah Agustus.
        Carbon::setTestNow('2026-09-04 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Tabel invoice punya unique (billing_period_id, customer_id) — satu
     * pelanggan hanya boleh punya satu invoice per periode. Karena itu tiap
     * invoice dalam satu periode dibuat untuk pelanggan yang berbeda.
     */
    private function invoice(
        string $periodStart,
        string $issueDate,
        float $total,
        string $status = 'issued',
        string $customerCode = 'C-001',
    ): Invoice {
        $customer = Customer::firstOrCreate(
            ['code' => $customerCode],
            ['name' => 'PT Pelanggan '.$customerCode, 'status' => 'active'],
        );

        $start = Carbon::parse($periodStart);
        $period = BillingPeriod::firstOrCreate(
            ['code' => $start->format('Y-m')],
            [
                'period_start' => $start->toDateString(),
                'period_end' => $start->copy()->endOfMonth()->toDateString(),
                'cut_off_date' => $start->copy()->addMonth()->startOfMonth()->toDateString(),
            ],
        );

        return Invoice::create([
            'invoice_no' => 'INV-'.$start->format('Ym').'-'.$customerCode.'-'.$status,
            'billing_period_id' => $period->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->endOfMonth()->toDateString(),
            'issue_date' => $issueDate,
            'due_date' => Carbon::parse($issueDate)->addDays(14)->toDateString(),
            'total_amount' => $total,
            'status' => $status,
        ]);
    }

    public function test_menghitung_periode_bulan_lalu_bukan_bulan_terbit(): void
    {
        // Pemakaian Agustus, ditagihkan awal September — inilah yang dihitung.
        $this->invoice('2026-08-01', '2026-09-01', 5_000_000);
        // Pemakaian Juli, terbit Agustus — periode sebelumnya, tidak ikut.
        $this->invoice('2026-07-01', '2026-08-01', 9_000_000);

        $billing = app(DashboardService::class)->currentBilling();

        $this->assertEquals(5_000_000, $billing['total']);
        $this->assertSame('01 Agt – 31 Agt 2026', $billing['label']);
    }

    public function test_invoice_draft_tidak_dihitung(): void
    {
        $this->invoice('2026-08-01', '2026-09-01', 5_000_000);
        $this->invoice('2026-08-01', '2026-09-01', 3_000_000, 'draft', 'C-002');

        $this->assertEquals(5_000_000, app(DashboardService::class)->currentBilling()['total']);
    }

    public function test_invoice_dibatalkan_tidak_dihitung(): void
    {
        $this->invoice('2026-08-01', '2026-09-01', 5_000_000);
        $this->invoice('2026-08-01', '2026-09-01', 2_000_000, 'cancelled', 'C-002');

        $this->assertEquals(5_000_000, app(DashboardService::class)->currentBilling()['total']);
    }

    /**
     * Nol karena belum digenerate harus bisa dibedakan dari nol karena tidak
     * ada pemakaian — keduanya terlihat sama di layar tanpa penanda ini.
     */
    public function test_periode_yang_belum_digenerate_ditandai(): void
    {
        $this->invoice('2026-07-01', '2026-08-01', 9_000_000);

        $billing = app(DashboardService::class)->currentBilling();

        $this->assertEquals(0, $billing['total']);
        $this->assertSame(0, $billing['issued_count']);
    }

    public function test_periode_yang_sudah_digenerate_tidak_ditandai(): void
    {
        $this->invoice('2026-08-01', '2026-09-01', 5_000_000);

        $this->assertSame(1, app(DashboardService::class)->currentBilling()['issued_count']);
    }

    /** Invoice yang terbit terlambat tetap masuk ke periode pemakaiannya. */
    public function test_invoice_terlambat_terbit_tetap_masuk_periodenya(): void
    {
        $this->invoice('2026-08-01', '2026-09-28', 7_500_000);

        $this->assertEquals(7_500_000, app(DashboardService::class)->currentBilling()['total']);
    }
}
