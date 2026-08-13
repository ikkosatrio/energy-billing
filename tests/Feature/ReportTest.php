<?php

namespace Tests\Feature;

use App\Models\BillingPeriod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MeterReading;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use App\Models\Role;
use App\Models\TariffGroup;
use App\Models\User;
use App\Services\Report\ReportService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportTest extends TestCase
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

    private function customer(string $code, string $name, bool $withReadings = true): Customer
    {
        $meter = PowerMeter::create([
            'code' => "MTR-{$code}", 'name' => "Panel {$code}", 'multiplier' => 1,
            'status' => 'active',
        ]);

        if ($withReadings) {
            MeterReadingDaily::create([
                'power_meter_id' => $meter->id,
                'date' => '2026-07-15',
                'kwh_lwbp' => 1_000, 'kwh_wbp' => 400,
                'peak_kw' => 250, 'reading_count' => 1440,
            ]);
        }

        return Customer::create([
            'code' => $code, 'name' => $name,
            'power_meter_id' => $meter->id,
            'tariff_group_id' => TariffGroup::firstOrCreate(['code' => 'I-3/TR'], ['name' => 'I-3'])->id,
            'status' => 'active',
        ]);
    }

    public function test_rekap_pemakaian_menjumlahkan_agregat_harian(): void
    {
        $this->customer('C1', 'Pelanggan Satu');

        $rows = app(ReportService::class)->usage(
            Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'),
        );

        $this->assertCount(1, $rows);
        $this->assertEquals(1_000, $rows[0]['lwbp']);
        $this->assertEquals(400, $rows[0]['wbp']);
        $this->assertEquals(1_400, $rows[0]['total_kwh']);
        $this->assertEquals(250, $rows[0]['peak_kw']);
    }

    /**
     * Regresi: pelanggan yang punya meter tapi belum pernah menerima
     * pembacaan sempat membuat halaman rekap error.
     */
    public function test_pelanggan_tanpa_agregat_harian_tetap_muncul_dengan_nilai_nol(): void
    {
        $this->customer('C1', 'Punya Data');
        $this->customer('C2', 'Belum Ada Data', withReadings: false);

        $rows = app(ReportService::class)->usage(
            Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'),
        );

        $kosong = $rows->firstWhere('customer', 'Belum Ada Data');

        $this->assertNotNull($kosong);
        $this->assertEquals(0, $kosong['total_kwh']);
        $this->assertNull($kosong['peak_kw']);
        $this->assertSame(0, $kosong['days']);
    }

    public function test_halaman_rekap_pemakaian_terbuka_saat_ada_pelanggan_tanpa_data(): void
    {
        $this->customer('C2', 'Belum Ada Data', withReadings: false);

        $this->get(route('report.usage'))->assertOk();
    }

    public function test_rekap_tagihan_mengabaikan_invoice_batal(): void
    {
        // Dua pelanggan berbeda, karena satu pelanggan hanya boleh punya satu
        // invoice per periode.
        $satu = $this->customer('C1', 'Pelanggan Satu');
        $dua = $this->customer('C2', 'Pelanggan Dua');

        $period = BillingPeriod::create([
            'code' => '2026-07', 'period_start' => '2026-07-01',
            'period_end' => '2026-07-31', 'cut_off_date' => '2026-08-01',
        ]);

        foreach ([[$satu, 'INV-1', 'issued', 500_000], [$dua, 'INV-2', 'cancelled', 900_000]] as [$customer, $no, $status, $total]) {
            Invoice::create([
                'invoice_no' => $no, 'billing_period_id' => $period->id, 'customer_id' => $customer->id,
                'customer_name' => $customer->name, 'period_start' => '2026-07-01', 'period_end' => '2026-07-31',
                'issue_date' => '2026-08-01', 'total_amount' => $total, 'status' => $status,
            ]);
        }

        $rows = app(ReportService::class)->billing(
            Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'),
        );

        $this->assertCount(1, $rows);
        $this->assertSame('INV-1', $rows[0]['invoice_no']);
    }

    public function test_export_excel_menghasilkan_berkas(): void
    {
        $this->customer('C1', 'Pelanggan Satu');

        $this->get(route('report.export', ['type' => 'usage', 'format' => 'xlsx']).'?from=2026-07-01&to=2026-07-31')
            ->assertOk();
    }

    public function test_export_pdf_menghasilkan_berkas(): void
    {
        $this->customer('C1', 'Pelanggan Satu');

        $this->get(route('report.export', ['type' => 'billing', 'format' => 'pdf']).'?from=2026-07-01&to=2026-07-31')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_menolak_rentang_tanggal_terbalik(): void
    {
        $this->get(route('report.export', ['type' => 'usage', 'format' => 'xlsx']).'?from=2026-07-31&to=2026-07-01')
            ->assertSessionHasErrors('to');
    }

    public function test_export_dengan_tipe_tidak_dikenal_ditolak(): void
    {
        $this->get(route('report.export', ['type' => 'salah', 'format' => 'xlsx']).'?from=2026-07-01&to=2026-07-31')
            ->assertNotFound();
    }

    // ── Laporan data mentah ──────────────────────────────────────────────

    private function meterWithReadings(array $readings): PowerMeter
    {
        $meter = PowerMeter::create([
            'code' => 'MTR-RAW', 'name' => 'Panel Raw', 'multiplier' => 1, 'status' => 'active',
        ]);

        MeterReading::insert(array_map(fn ($r) => [
            'power_meter_id' => $meter->id,
            'read_at' => $r[0],
            'stand_lwbp' => $r[1],
            'stand_wbp' => $r[2],
            'source' => 'api',
        ], $readings));

        return $meter;
    }

    public function test_pembacaan_normal_tidak_ditandai_anomali(): void
    {
        $meter = $this->meterWithReadings([
            ['2026-07-01 10:00:00', 1000, 400],
            ['2026-07-01 10:01:00', 1010, 400],
            ['2026-07-01 10:02:00', 1020, 400],
        ]);

        $rows = app(ReportService::class)->flagAnomalies(
            app(ReportService::class)->rawReadingsQuery($meter->id, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-01'))->get()
        );

        $this->assertCount(3, $rows);
        $this->assertSame([false, false, false], array_column($rows, 'is_anomaly'));
        // Baris pertama tidak punya pembanding, jadi delta-nya null.
        $this->assertNull($rows[0]['delta_lwbp']);
        $this->assertEquals(10, $rows[1]['delta_lwbp']);
    }

    public function test_stand_yang_mundur_ditandai(): void
    {
        $meter = $this->meterWithReadings([
            ['2026-07-01 10:00:00', 9000, 400],
            ['2026-07-01 10:01:00', 12, 400],
        ]);

        $rows = app(ReportService::class)->flagAnomalies(
            app(ReportService::class)->rawReadingsQuery($meter->id, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-01'))->get()
        );

        $this->assertFalse($rows[0]['is_anomaly']);
        $this->assertTrue($rows[1]['stand_dropped']);
        $this->assertTrue($rows[1]['is_anomaly']);
    }

    public function test_jeda_data_ditandai(): void
    {
        // Interval push 60 detik; ambangnya 2x, jadi lompatan 30 menit ditandai.
        $meter = $this->meterWithReadings([
            ['2026-07-01 10:00:00', 1000, 400],
            ['2026-07-01 10:30:00', 1300, 400],
        ]);

        $rows = app(ReportService::class)->flagAnomalies(
            app(ReportService::class)->rawReadingsQuery($meter->id, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-01'))->get()
        );

        $this->assertTrue($rows[1]['has_gap']);
        $this->assertSame(1800, $rows[1]['gap_seconds']);
    }

    /**
     * Baris pembuka halaman harus tetap diperiksa terhadap pembacaan
     * sebelumnya, bukan otomatis dianggap normal.
     */
    public function test_baris_pertama_halaman_dibandingkan_dengan_pembacaan_sebelumnya(): void
    {
        $meter = $this->meterWithReadings([
            ['2026-07-01 10:00:00', 9000, 400],
            ['2026-07-01 10:01:00', 12, 400],
        ]);

        $service = app(ReportService::class);
        $all = $service->rawReadingsQuery($meter->id, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-01'))->get();

        // Halaman kedua berisi baris ke-2 saja, dengan baris ke-1 sebagai pembanding.
        $rows = $service->flagAnomalies([$all[1]], $all[0]);

        $this->assertTrue($rows[0]['stand_dropped']);
    }

    public function test_export_data_mentah_menghasilkan_excel(): void
    {
        $meter = $this->meterWithReadings([
            ['2026-07-01 10:00:00', 1000, 400],
            ['2026-07-01 10:01:00', 1010, 400],
        ]);

        $this->get(route('report.export', ['type' => 'readings', 'format' => 'xlsx'])
            ."?from=2026-07-01&to=2026-07-01&meter_id={$meter->id}")
            ->assertOk();
    }

    public function test_export_data_mentah_wajib_menyertakan_meter(): void
    {
        $this->get(route('report.export', ['type' => 'readings', 'format' => 'xlsx']).'?from=2026-07-01&to=2026-07-01')
            ->assertSessionHasErrors('meter_id');
    }

    public function test_export_data_mentah_ke_pdf_ditolak(): void
    {
        $meter = $this->meterWithReadings([['2026-07-01 10:00:00', 1000, 400]]);

        $this->get(route('report.export', ['type' => 'readings', 'format' => 'pdf'])
            ."?from=2026-07-01&to=2026-07-01&meter_id={$meter->id}")
            ->assertNotFound();
    }

    public function test_halaman_data_mentah_terbuka(): void
    {
        $this->meterWithReadings([['2026-07-01 10:00:00', 1000, 400]]);

        $this->get(route('report.readings'))->assertOk();
    }
}
