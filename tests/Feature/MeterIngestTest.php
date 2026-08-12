<?php

namespace Tests\Feature;

use App\Models\MeterReading;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use App\Services\Monitoring\DailyAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MeterIngestTest extends TestCase
{
    use RefreshDatabase;

    private function meter(array $overrides = []): PowerMeter
    {
        return PowerMeter::create(array_merge([
            'code' => 'MTR-01',
            'name' => 'LVMDP 01',
            'multiplier' => 1,
            'device_key' => 'em_testkey',
            'status' => 'active',
        ], $overrides));
    }

    private function payload(string $readAt, float $lwbp, float $wbp): array
    {
        return [
            'read_at' => $readAt,
            'stand_lwbp' => $lwbp,
            'stand_wbp' => $wbp,
            'active_power_kw' => 412.6,
            'voltage_r' => 380.1,
            'current_r' => 410.2,
            'power_factor' => 0.95,
            'frequency' => 50,
        ];
    }

    public function test_tanpa_device_key_ditolak(): void
    {
        $this->meter();

        $this->postJson('/api/v1/readings', $this->payload('2026-08-13 10:00:00', 100, 50))
            ->assertStatus(401);
    }

    public function test_device_key_salah_ditolak(): void
    {
        $this->meter();

        $this->withHeader('X-Device-Key', 'em_salah')
            ->postJson('/api/v1/readings', $this->payload('2026-08-13 10:00:00', 100, 50))
            ->assertStatus(401);
    }

    public function test_meter_nonaktif_ditolak(): void
    {
        $this->meter(['status' => 'inactive']);

        $this->withHeader('X-Device-Key', 'em_testkey')
            ->postJson('/api/v1/readings', $this->payload('2026-08-13 10:00:00', 100, 50))
            ->assertStatus(403);
    }

    public function test_pembacaan_tunggal_tersimpan(): void
    {
        $meter = $this->meter();

        $this->withHeader('X-Device-Key', 'em_testkey')
            ->postJson('/api/v1/readings', $this->payload('2026-08-13 10:00:00', 1000.5, 400.25))
            ->assertStatus(201)
            ->assertJson(['stored' => 1, 'duplicate' => 0]);

        $reading = MeterReading::first();

        $this->assertEquals(1000.5, $reading->stand_lwbp);
        $this->assertEquals(400.25, $reading->stand_wbp);
        $this->assertNotNull($meter->fresh()->last_seen_at);
    }

    public function test_batch_tersimpan_sekaligus(): void
    {
        $this->meter();

        $this->withHeader('X-Device-Key', 'em_testkey')
            ->postJson('/api/v1/readings', ['readings' => [
                $this->payload('2026-08-13 10:00:00', 1000, 400),
                $this->payload('2026-08-13 10:01:00', 1001, 400),
                $this->payload('2026-08-13 10:02:00', 1002, 401),
            ]])
            ->assertStatus(201)
            ->assertJson(['stored' => 3]);

        $this->assertSame(3, MeterReading::count());
    }

    public function test_pengiriman_ulang_tidak_menggandakan_data(): void
    {
        $this->meter();

        $batch = ['readings' => [
            $this->payload('2026-08-13 10:00:00', 1000, 400),
            $this->payload('2026-08-13 10:01:00', 1001, 400),
        ]];

        $this->withHeader('X-Device-Key', 'em_testkey')->postJson('/api/v1/readings', $batch);

        // Gateway mengirim ulang buffer yang sama setelah jaringan pulih.
        $this->withHeader('X-Device-Key', 'em_testkey')
            ->postJson('/api/v1/readings', $batch)
            ->assertStatus(201)
            ->assertJson(['stored' => 0, 'duplicate' => 2]);

        $this->assertSame(2, MeterReading::count());
    }

    public function test_pengiriman_ulang_tidak_menimpa_nilai_yang_sudah_ada(): void
    {
        $this->meter();

        $this->withHeader('X-Device-Key', 'em_testkey')
            ->postJson('/api/v1/readings', $this->payload('2026-08-13 10:00:00', 1000, 400));

        $this->withHeader('X-Device-Key', 'em_testkey')
            ->postJson('/api/v1/readings', $this->payload('2026-08-13 10:00:00', 9999, 9999));

        // Pembacaan asli adalah data yang benar; kiriman ulang diabaikan.
        $this->assertEquals(1000, MeterReading::first()->stand_lwbp);
    }

    public function test_pengali_ct_diterapkan_saat_menyimpan(): void
    {
        $this->meter(['multiplier' => 160]);

        $this->withHeader('X-Device-Key', 'em_testkey')
            ->postJson('/api/v1/readings', $this->payload('2026-08-13 10:00:00', 10, 4));

        $reading = MeterReading::first();

        $this->assertEquals(1600, $reading->stand_lwbp);
        $this->assertEquals(640, $reading->stand_wbp);
    }

    public function test_payload_tanpa_stand_ditolak(): void
    {
        $this->meter();

        $this->withHeader('X-Device-Key', 'em_testkey')
            ->postJson('/api/v1/readings', ['read_at' => '2026-08-13 10:00:00'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_ping_mengembalikan_identitas_meter(): void
    {
        $this->meter();

        $this->withHeader('X-Device-Key', 'em_testkey')
            ->getJson('/api/v1/ping')
            ->assertOk()
            ->assertJson(['meter' => 'MTR-01']);
    }

    public function test_agregasi_harian_menghitung_selisih_stand(): void
    {
        $meter = $this->meter();

        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-08-13 00:00:00', 'stand_lwbp' => 1000, 'stand_wbp' => 400, 'active_power_kw' => 100, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-08-13 12:00:00', 'stand_lwbp' => 1120, 'stand_wbp' => 430, 'active_power_kw' => 486, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-08-13 23:59:00', 'stand_lwbp' => 1240, 'stand_wbp' => 460, 'active_power_kw' => 210, 'source' => 'api'],
        ]);

        app(DailyAggregationService::class)->aggregate($meter, Carbon::parse('2026-08-13'));

        $daily = MeterReadingDaily::first();

        $this->assertEquals(240, $daily->kwh_lwbp);
        $this->assertEquals(60, $daily->kwh_wbp);
        $this->assertEquals(486, $daily->peak_kw);
        $this->assertSame(3, $daily->reading_count);
    }

    public function test_agregasi_ulang_memperbarui_bukan_menggandakan(): void
    {
        $meter = $this->meter();

        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-08-13 00:00:00', 'stand_lwbp' => 1000, 'stand_wbp' => 400, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-08-13 12:00:00', 'stand_lwbp' => 1120, 'stand_wbp' => 430, 'source' => 'api'],
        ]);

        $service = app(DailyAggregationService::class);
        $service->aggregate($meter, Carbon::parse('2026-08-13'));

        // Pembacaan susulan datang terlambat dari gateway.
        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-08-13 23:00:00', 'stand_lwbp' => 1240, 'stand_wbp' => 460, 'source' => 'api'],
        ]);

        $service->aggregate($meter, Carbon::parse('2026-08-13'));

        $this->assertSame(1, MeterReadingDaily::count());
        $this->assertEquals(240, MeterReadingDaily::first()->kwh_lwbp);
    }

    public function test_stand_yang_mundur_dianggap_nol_bukan_negatif(): void
    {
        $meter = $this->meter();

        // Meter di-reset di tengah hari sehingga stand kembali ke angka kecil.
        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-08-13 00:00:00', 'stand_lwbp' => 9000, 'stand_wbp' => 400, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-08-13 23:00:00', 'stand_lwbp' => 12, 'stand_wbp' => 430, 'source' => 'api'],
        ]);

        app(DailyAggregationService::class)->aggregate($meter, Carbon::parse('2026-08-13'));

        $daily = MeterReadingDaily::first();

        $this->assertEquals(0, $daily->kwh_lwbp);
        $this->assertEquals(30, $daily->kwh_wbp);
    }
}
