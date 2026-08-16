<?php

namespace Tests\Feature;

use App\Models\MeterReading;
use App\Models\PowerMeter;
use App\Models\PowerMeterStatus;
use App\Services\SettingService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Endpoint kondisi perangkat: satu baris per meter yang terus ditimpa, dipakai
 * hanya untuk mengetahui keadaan terakhir. Riwayatnya tidak disimpan — itu
 * tugas endpoint pembacaan.
 */
class DeviceStatusTest extends TestCase
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

    private function api(): self
    {
        return $this->withHeader('X-Api-Token', self::TOKEN);
    }

    private function payload(PowerMeter $meter, array $overrides = []): array
    {
        return array_merge([
            'meter_id' => $meter->id,
            'signal_dbm' => -62,
            'ip_address' => '192.168.1.44',
            'mac_address' => 'A4:CF:12:9B:00:7E',
            'firmware_version' => '1.4.2',
            'stand_lwbp' => 1000.5,
            'stand_wbp' => 400.25,
            'active_power_kw' => 412.6,
            'voltage_r' => 380.1,
            'current_r' => 410.2,
            'power_factor' => 0.95,
            'frequency' => 50,
        ], $overrides);
    }

    // ── Autentikasi & kelayakan meter ────────────────────────────────────

    public function test_tanpa_token_ditolak(): void
    {
        $meter = $this->meter();

        $this->postJson('/api/v1/status', $this->payload($meter))->assertStatus(401);
    }

    public function test_meter_nonaktif_ditolak(): void
    {
        $meter = $this->meter(['status' => 'inactive']);

        $this->api()->postJson('/api/v1/status', $this->payload($meter))->assertStatus(403);

        $this->assertSame(0, PowerMeterStatus::count());
    }

    public function test_meter_id_tidak_dikenal_ditolak(): void
    {
        $this->api()
            ->postJson('/api/v1/status', ['meter_id' => 9999, 'signal_dbm' => -60])
            ->assertStatus(422)
            ->assertJsonValidationErrors('meter_id');
    }

    // ── Validasi ─────────────────────────────────────────────────────────

    public function test_dbm_di_luar_rentang_wajar_ditolak(): void
    {
        $meter = $this->meter();

        $this->api()
            ->postJson('/api/v1/status', $this->payload($meter, ['signal_dbm' => 40]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('signal_dbm');
    }

    public function test_mac_address_dengan_format_salah_ditolak(): void
    {
        $meter = $this->meter();

        $this->api()
            ->postJson('/api/v1/status', $this->payload($meter, ['mac_address' => 'bukan-mac']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('mac_address');
    }

    public function test_ip_dengan_format_salah_ditolak(): void
    {
        $meter = $this->meter();

        $this->api()
            ->postJson('/api/v1/status', $this->payload($meter, ['ip_address' => '999.1.1.1']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('ip_address');
    }

    // ── Penyimpanan ──────────────────────────────────────────────────────

    public function test_kondisi_perangkat_tersimpan(): void
    {
        $meter = $this->meter();

        $this->api()->postJson('/api/v1/status', $this->payload($meter))
            ->assertOk()
            ->assertJson(['meter_id' => $meter->id, 'meter_code' => 'MTR-01']);

        $status = $meter->fresh()->deviceStatus;

        $this->assertSame(-62, $status->signal_dbm);
        $this->assertSame('192.168.1.44', $status->ip_address);
        $this->assertSame('A4:CF:12:9B:00:7E', $status->mac_address);
        $this->assertSame('1.4.2', $status->firmware_version);
        $this->assertEquals(412.6, $status->active_power_kw);
    }

    public function test_status_tidak_dicatat_sebagai_pembacaan(): void
    {
        $meter = $this->meter();

        $this->api()->postJson('/api/v1/status', $this->payload($meter));

        // Status hanya menggambarkan keadaan terakhir; tagihan tetap dihitung
        // dari tabel pembacaan yang tidak boleh ikut bertambah.
        $this->assertSame(0, MeterReading::count());
    }

    public function test_pengiriman_berulang_menimpa_baris_yang_sama(): void
    {
        $meter = $this->meter();

        $this->api()->postJson('/api/v1/status', $this->payload($meter));
        $this->api()->postJson('/api/v1/status', $this->payload($meter, ['signal_dbm' => -80]));

        $this->assertSame(1, PowerMeterStatus::count());
        $this->assertSame(-80, $meter->fresh()->deviceStatus->signal_dbm);
    }

    public function test_kiriman_ringkas_tidak_menghapus_nilai_lama(): void
    {
        $meter = $this->meter();

        $this->api()->postJson('/api/v1/status', $this->payload($meter));

        // Gateway hanya mengabarkan sinyal yang melemah.
        $this->api()->postJson('/api/v1/status', [
            'meter_id' => $meter->id,
            'signal_dbm' => -88,
        ])->assertOk();

        $status = $meter->fresh()->deviceStatus;

        $this->assertSame(-88, $status->signal_dbm);
        $this->assertSame('192.168.1.44', $status->ip_address);
        $this->assertSame('1.4.2', $status->firmware_version);
        $this->assertEquals(412.6, $status->active_power_kw);
    }

    public function test_pengali_ct_diterapkan_pada_stand(): void
    {
        $meter = $this->meter(['multiplier' => 160]);

        $this->api()->postJson('/api/v1/status', $this->payload($meter, [
            'stand_lwbp' => 10,
            'stand_wbp' => 4,
        ]));

        $status = $meter->fresh()->deviceStatus;

        $this->assertEquals(1600, $status->stand_lwbp);
        $this->assertEquals(640, $status->stand_wbp);
    }

    public function test_status_menandai_perangkat_masih_hidup(): void
    {
        $meter = $this->meter();

        $this->assertNull($meter->last_seen_at);

        $this->api()->postJson('/api/v1/status', $this->payload($meter));

        // Gateway yang mengirim status lebih sering daripada pembacaan tetap
        // terbaca online di halaman monitoring.
        $this->assertNotNull($meter->fresh()->last_seen_at);
        $this->assertSame('online', $meter->fresh()->connection_status);
    }

    // ── Kualitas sinyal ──────────────────────────────────────────────────

    public function test_kualitas_sinyal_diturunkan_dari_dbm(): void
    {
        $cases = [
            -50 => [4, 'good'],
            -60 => [3, 'good'],
            -70 => [2, 'fair'],
            -80 => [1, 'weak'],
            -95 => [1, 'poor'],
        ];

        foreach ($cases as $dbm => [$level, $tone]) {
            $status = new \App\Models\PowerMeterStatus(['signal_dbm' => $dbm]);

            $this->assertSame($level, $status->signal_quality['level'], "dBm {$dbm}");
            $this->assertSame($tone, $status->signal_quality['tone'], "dBm {$dbm}");
        }
    }

    public function test_tanpa_dbm_kualitas_sinyal_tidak_diketahui(): void
    {
        $status = new PowerMeterStatus;

        $this->assertSame(0, $status->signal_quality['level']);
        $this->assertSame('unknown', $status->signal_quality['tone']);
    }

    // ── Jenis sambungan ──────────────────────────────────────────────────

    public function test_meter_default_tiga_phase(): void
    {
        $meter = $this->meter();

        $this->assertSame('3', $meter->fresh()->phase);
        $this->assertFalse($meter->fresh()->isSinglePhase());
        $this->assertSame('3 Phase', $meter->fresh()->phase_label);
    }

    public function test_meter_satu_phase_dikenali(): void
    {
        $meter = $this->meter(['phase' => '1']);

        $this->assertTrue($meter->fresh()->isSinglePhase());
        $this->assertSame('1 Phase', $meter->fresh()->phase_label);
    }
}
