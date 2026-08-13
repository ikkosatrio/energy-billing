<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MeterReading;
use App\Models\PowerMeter;
use App\Models\TariffGroup;
use App\Services\Billing\InvoiceGenerator;
use App\Services\SettingService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoiceGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    private function setting(string $key, mixed $value): void
    {
        app(SettingService::class)->put($key, $value);
    }

    /**
     * Pelanggan lengkap: meter + golongan tarif + pembacaan awal & akhir.
     */
    private function scenario(array $customerOverrides = [], float $lwbpUsage = 24_180, float $wbpUsage = 7_940): Customer
    {
        $meter = PowerMeter::create([
            'code' => 'MTR-01', 'name' => 'LVMDP 01', 'multiplier' => 1,
            'status' => 'active',
        ]);

        $group = TariffGroup::create(['code' => 'I-3/TR', 'name' => 'I-3 / TR']);
        $group->rates()->create([
            'rate_lwbp' => 1114.74,
            'rate_wbp' => 1560.64,
            'rate_beban_per_kva' => 15_000,
            'effective_from' => '2026-01-01',
        ]);

        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-01 00:00:00',
                'stand_lwbp' => 1_246_100, 'stand_wbp' => 406_320, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-31 23:59:00',
                'stand_lwbp' => 1_246_100 + $lwbpUsage, 'stand_wbp' => 406_320 + $wbpUsage, 'source' => 'api'],
        ]);

        return Customer::create(array_merge([
            'code' => 'C-001',
            'name' => 'PT Sinar Abadi Logistik',
            'address' => 'Jababeka Blok C-12, Cikarang',
            'power_meter_id' => $meter->id,
            'tariff_group_id' => $group->id,
            'daya_kva' => 630,
            'biaya_beban_mode' => 'flat',
            'biaya_beban' => 9_450_000,
            'status' => 'active',
        ], $customerOverrides));
    }

    private function generate(): Invoice
    {
        $generator = app(InvoiceGenerator::class);
        $period = $generator->periodFor(Carbon::parse('2026-07-01'));
        $generator->generate($period, customerIds: null);

        return Invoice::firstOrFail();
    }

    public function test_pemakaian_dihitung_dari_selisih_stand(): void
    {
        $this->scenario();
        $invoice = $this->generate();

        $this->assertEquals(1_246_100, $invoice->stand_lwbp_start);
        $this->assertEquals(1_270_280, $invoice->stand_lwbp_end);
        $this->assertEquals(24_180, $invoice->kwh_lwbp);
        $this->assertEquals(7_940, $invoice->kwh_wbp);
    }

    public function test_komponen_tagihan_dihitung_lengkap(): void
    {
        $this->setting('biaya_admin', 25_000);
        $this->setting('ppj_percent', 3);
        $this->setting('ppn_percent', 0);
        $this->setting('invoice_rounding_to', 100);

        $this->scenario();
        $invoice = $this->generate();

        $this->assertEquals(round(24_180 * 1114.74, 2), $invoice->amount_lwbp);
        $this->assertEquals(round(7_940 * 1560.64, 2), $invoice->amount_wbp);
        $this->assertEquals(9_450_000, $invoice->biaya_beban);
        $this->assertEquals(25_000, $invoice->biaya_admin);

        $expectedSubtotal = round(24_180 * 1114.74, 2) + round(7_940 * 1560.64, 2) + 9_450_000 + 25_000;
        $this->assertEquals($expectedSubtotal, $invoice->subtotal);
        $this->assertEquals(round($expectedSubtotal * 0.03, 2), $invoice->ppj_amount);

        // Total dibulatkan ke kelipatan 100.
        $this->assertEquals(0, fmod((float) $invoice->total_amount, 100));
    }

    public function test_biaya_beban_mode_per_kva_dihitung_dari_tarif_golongan(): void
    {
        $this->scenario(['biaya_beban_mode' => 'per_kva', 'biaya_beban' => 0, 'daya_kva' => 630]);
        $invoice = $this->generate();

        $this->assertEquals(630 * 15_000, $invoice->biaya_beban);
        $this->assertEquals('per_kva', $invoice->biaya_beban_mode);
    }

    public function test_pembulatan_dimatikan_bila_setting_nol(): void
    {
        $this->setting('invoice_rounding_to', 0);

        $this->scenario();
        $invoice = $this->generate();

        $this->assertEquals(0, $invoice->rounding);
    }

    public function test_identitas_pelanggan_dan_tarif_disnapshot(): void
    {
        $customer = $this->scenario();
        $invoice = $this->generate();

        $customer->update(['name' => 'Nama Baru', 'address' => 'Alamat Baru']);
        $invoice->refresh();

        // Invoice yang sudah terbit tidak boleh ikut berubah.
        $this->assertSame('PT Sinar Abadi Logistik', $invoice->customer_name);
        $this->assertSame('Jababeka Blok C-12, Cikarang', $invoice->customer_address);
        $this->assertEquals(1114.74, $invoice->rate_lwbp);
        $this->assertSame('I-3/TR', $invoice->tariff_group_code);
    }

    public function test_tarif_yang_berubah_tidak_mengubah_invoice_lama(): void
    {
        $customer = $this->scenario();
        $invoice = $this->generate();
        $originalTotal = $invoice->total_amount;

        app(\App\Services\Tariff\TariffService::class)->publishRate($customer->tariffGroup, [
            'rate_lwbp' => 2000, 'rate_wbp' => 2500, 'effective_from' => '2026-08-01',
        ]);

        $this->assertEquals($originalTotal, $invoice->fresh()->total_amount);
        $this->assertEquals(1114.74, $invoice->fresh()->rate_lwbp);
    }

    public function test_generate_ulang_tidak_membuat_invoice_ganda(): void
    {
        $this->scenario();

        $generator = app(InvoiceGenerator::class);
        $period = $generator->periodFor(Carbon::parse('2026-07-01'));

        $generator->generate($period);
        $second = $generator->generate($period);

        $this->assertSame(1, Invoice::count());
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['skipped']);
    }

    public function test_invoice_yang_sudah_terbit_tidak_ditimpa_saat_regenerate(): void
    {
        $this->scenario();

        $generator = app(InvoiceGenerator::class);
        $period = $generator->periodFor(Carbon::parse('2026-07-01'));
        $generator->generate($period);

        Invoice::first()->update(['status' => 'issued', 'total_amount' => 123]);

        $generator->generate($period, regenerate: true);

        $this->assertEquals(123, Invoice::first()->total_amount);
    }

    public function test_draft_ditimpa_saat_regenerate_dengan_nomor_yang_sama(): void
    {
        $this->scenario();

        $generator = app(InvoiceGenerator::class);
        $period = $generator->periodFor(Carbon::parse('2026-07-01'));
        $generator->generate($period);

        $original = Invoice::first();
        $originalNo = $original->invoice_no;
        $original->update(['total_amount' => 1]);

        $generator->generate($period, regenerate: true);

        $refreshed = Invoice::first();
        $this->assertSame($originalNo, $refreshed->invoice_no);
        $this->assertGreaterThan(1, $refreshed->total_amount);
    }

    public function test_pelanggan_tanpa_pembacaan_meter_ditagih_nol_dengan_catatan(): void
    {
        $meter = PowerMeter::create(['code' => 'MTR-02', 'name' => 'B', 'multiplier' => 1]);
        $group = TariffGroup::create(['code' => 'B-3/TR', 'name' => 'B-3']);
        $group->rates()->create(['rate_lwbp' => 1000, 'rate_wbp' => 1400, 'effective_from' => '2026-01-01']);

        Customer::create([
            'code' => 'C-002', 'name' => 'Pelanggan Baru',
            'power_meter_id' => $meter->id, 'tariff_group_id' => $group->id,
            'biaya_beban_mode' => 'flat', 'biaya_beban' => 0, 'status' => 'active',
        ]);

        $invoice = $this->generate();

        $this->assertEquals(0, $invoice->kwh_lwbp);
        $this->assertStringContainsString('Tidak ada pembacaan meter', $invoice->notes);
    }

    public function test_meter_yang_direset_ditandai_dan_tidak_bernilai_negatif(): void
    {
        $meter = PowerMeter::create(['code' => 'MTR-03', 'name' => 'C', 'multiplier' => 1]);
        $group = TariffGroup::create(['code' => 'B-3/TR', 'name' => 'B-3']);
        $group->rates()->create(['rate_lwbp' => 1000, 'rate_wbp' => 1400, 'effective_from' => '2026-01-01']);

        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-01 00:00:00', 'stand_lwbp' => 900_000, 'stand_wbp' => 100, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-31 23:00:00', 'stand_lwbp' => 120, 'stand_wbp' => 200, 'source' => 'api'],
        ]);

        Customer::create([
            'code' => 'C-003', 'name' => 'Pelanggan Reset',
            'power_meter_id' => $meter->id, 'tariff_group_id' => $group->id,
            'biaya_beban_mode' => 'flat', 'status' => 'active',
        ]);

        $invoice = $this->generate();

        $this->assertEquals(0, $invoice->kwh_lwbp);
        $this->assertStringContainsString('Stand meter mundur', $invoice->notes);
    }

    public function test_pelanggan_tanpa_meter_atau_golongan_dilewati(): void
    {
        Customer::create(['code' => 'C-004', 'name' => 'Belum Lengkap', 'status' => 'active']);

        $generator = app(InvoiceGenerator::class);
        $period = $generator->periodFor(Carbon::parse('2026-07-01'));
        $result = $generator->generate($period);

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, Invoice::count());
    }

    public function test_periode_yang_ditutup_tidak_bisa_digenerate(): void
    {
        $this->scenario();

        $generator = app(InvoiceGenerator::class);
        $period = $generator->periodFor(Carbon::parse('2026-07-01'));
        $period->update(['status' => 'closed']);

        $result = $generator->generate($period);

        $this->assertSame(0, $result['created']);
        $this->assertNotEmpty($result['failed']);
        $this->assertSame(0, Invoice::count());
    }

    public function test_nomor_invoice_mengikuti_format_setting(): void
    {
        $this->setting('invoice_number_format', 'INV/{YYYY}/{MM}/{SEQ}');
        $this->setting('invoice_number_padding', 3);

        $this->scenario();
        $invoice = $this->generate();

        $this->assertMatchesRegularExpression('#^INV/\d{4}/\d{2}/\d{3}$#', $invoice->invoice_no);
    }

    public function test_jatuh_tempo_mengikuti_setting(): void
    {
        $this->setting('invoice_due_days', 21);

        $this->scenario();
        $invoice = $this->generate();

        $this->assertSame(21, $invoice->issue_date->diffInDays($invoice->due_date));
    }
}
