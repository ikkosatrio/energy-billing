<?php

namespace Tests\Feature;

use App\Models\MeterReading;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use App\Services\Monitoring\DailyAggregationService;
use App\Services\SettingService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MeterIngestTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-uji-yang-cukup-panjang-untuk-lolos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
        app(SettingService::class)->put('api_token', self::TOKEN);
    }

    private function meter(array $overrides = []): PowerMeter
    {
        return PowerMeter::create(array_merge([
            'code' => 'MTR-01',
            'name' => 'LVMDP 01',
            'multiplier' => 1,
            'status' => 'active',
        ], $overrides));
    }

    /** Permintaan dengan token yang benar. */
    private function api(): self
    {
        return $this->withHeader('X-Api-Token', self::TOKEN);
    }

    private function payload(PowerMeter $meter, string $readAt, float $lwbp, float $wbp): array
    {
        return [
            'meter_id' => $meter->id,
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

    // ── Autentikasi ──────────────────────────────────────────────────────

    public function test_tanpa_token_ditolak(): void
    {
        $meter = $this->meter();

        $this->postJson('/api/v1/readings', $this->payload($meter, '2026-08-13 10:00:00', 100, 50))
            ->assertStatus(401);
    }

    public function test_token_salah_ditolak(): void
    {
        $meter = $this->meter();

        $this->withHeader('X-Api-Token', 'token-salah')
            ->postJson('/api/v1/readings', $this->payload($meter, '2026-08-13 10:00:00', 100, 50))
            ->assertStatus(401);
    }

    public function test_token_juga_diterima_sebagai_bearer(): void
    {
        $meter = $this->meter();

        $this->withHeader('Authorization', 'Bearer '.self::TOKEN)
            ->postJson('/api/v1/readings', $this->payload($meter, '2026-08-13 10:00:00', 100, 50))
            ->assertStatus(201);
    }

    public function test_token_kosong_mematikan_autentikasi(): void
    {
        app(SettingService::class)->put('api_token', '');
        $meter = $this->meter();

        $this->postJson('/api/v1/readings', $this->payload($meter, '2026-08-13 10:00:00', 100, 50))
            ->assertStatus(201);
    }

    // ── Identifikasi meter ───────────────────────────────────────────────

    public function test_meter_id_wajib_diisi(): void
    {
        $this->meter();

        $this->api()
            ->postJson('/api/v1/readings', ['read_at' => '2026-08-13 10:00:00', 'stand_lwbp' => 1, 'stand_wbp' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('meter_id');
    }

    public function test_meter_id_tidak_dikenal_ditolak(): void
    {
        $this->meter();

        $this->api()
            ->postJson('/api/v1/readings', [
                'meter_id' => 9999, 'read_at' => '2026-08-13 10:00:00',
                'stand_lwbp' => 1, 'stand_wbp' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('meter_id');
    }

    public function test_meter_nonaktif_ditolak(): void
    {
        $meter = $this->meter(['status' => 'inactive']);

        $this->api()
            ->postJson('/api/v1/readings', $this->payload($meter, '2026-08-13 10:00:00', 100, 50))
            ->assertStatus(403);
    }

    public function test_endpoint_daftar_meter_mengembalikan_id(): void
    {
        $meter = $this->meter();

        $this->api()->getJson('/api/v1/meters')
            ->assertOk()
            ->assertJsonPath('data.0.meter_id', $meter->id)
            ->assertJsonPath('data.0.code', 'MTR-01');
    }

    // ── Penyimpanan ──────────────────────────────────────────────────────

    public function test_pembacaan_tunggal_tersimpan(): void
    {
        $meter = $this->meter();

        $this->api()
            ->postJson('/api/v1/readings', $this->payload($meter, '2026-08-13 10:00:00', 1000.5, 400.25))
            ->assertStatus(201)
            ->assertJson(['stored' => 1, 'duplicate' => 0, 'meter_id' => $meter->id]);

        $reading = MeterReading::first();

        $this->assertEquals(1000.5, $reading->stand_lwbp);
        $this->assertEquals(400.25, $reading->stand_wbp);
        $this->assertNotNull($meter->fresh()->last_seen_at);
    }

    public function test_batch_tersimpan_sekaligus(): void
    {
        $meter = $this->meter();

        $this->api()
            ->postJson('/api/v1/readings', [
                'meter_id' => $meter->id,
                'readings' => [
                    ['read_at' => '2026-08-13 10:00:00', 'stand_lwbp' => 1000, 'stand_wbp' => 400],
                    ['read_at' => '2026-08-13 10:01:00', 'stand_lwbp' => 1001, 'stand_wbp' => 400],
                    ['read_at' => '2026-08-13 10:02:00', 'stand_lwbp' => 1002, 'stand_wbp' => 401],
                ],
            ])
            ->assertStatus(201)
            ->assertJson(['stored' => 3]);

        $this->assertSame(3, MeterReading::count());
    }

    public function test_pengiriman_ulang_tidak_menggandakan_data(): void
    {
        $meter = $this->meter();

        $batch = [
            'meter_id' => $meter->id,
            'readings' => [
                ['read_at' => '2026-08-13 10:00:00', 'stand_lwbp' => 1000, 'stand_wbp' => 400],
                ['read_at' => '2026-08-13 10:01:00', 'stand_lwbp' => 1001, 'stand_wbp' => 400],
            ],
        ];

        $this->api()->postJson('/api/v1/readings', $batch);

        // Gateway mengirim ulang buffer yang sama setelah jaringan pulih.
        $this->api()->postJson('/api/v1/readings', $batch)
            ->assertStatus(201)
            ->assertJson(['stored' => 0, 'duplicate' => 2]);

        $this->assertSame(2, MeterReading::count());
    }

    public function test_pengiriman_ulang_tidak_menimpa_nilai_yang_sudah_ada(): void
    {
        $meter = $this->meter();

        $this->api()->postJson('/api/v1/readings', $this->payload($meter, '2026-08-13 10:00:00', 1000, 400));
        $this->api()->postJson('/api/v1/readings', $this->payload($meter, '2026-08-13 10:00:00', 9999, 9999));

        // Pembacaan asli adalah data yang benar; kiriman ulang diabaikan.
        $this->assertEquals(1000, MeterReading::first()->stand_lwbp);
    }

    public function test_pengali_ct_diterapkan_saat_menyimpan(): void
    {
        $meter = $this->meter(['multiplier' => 160]);

        $this->api()->postJson('/api/v1/readings', $this->payload($meter, '2026-08-13 10:00:00', 10, 4));

        $reading = MeterReading::first();

        $this->assertEquals(1600, $reading->stand_lwbp);
        $this->assertEquals(640, $reading->stand_wbp);
    }

    public function test_payload_tanpa_stand_ditolak(): void
    {
        $meter = $this->meter();

        $this->api()
            ->postJson('/api/v1/readings', ['meter_id' => $meter->id, 'read_at' => '2026-08-13 10:00:00'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_ping_mengembalikan_interval_push(): void
    {
        $this->api()->getJson('/api/v1/ping')
            ->assertOk()
            ->assertJson(['message' => 'OK'])
            ->assertJsonStructure(['server_time', 'push_interval_seconds']);
    }

    // ── Agregasi ─────────────────────────────────────────────────────────

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
