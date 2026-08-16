<?php

namespace Tests\Feature;

use App\Livewire\Monitoring\DevicePage;
use App\Models\MeterReading;
use App\Models\PowerMeter;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kolom "Stand Akhir" di Status Perangkat — LWBP dan WBP adalah register
 * independen, jadi ditampilkan terpisah (sama seperti pola di Power Meter
 * Device), bukan dijumlahkan jadi satu "stand total" yang tidak berarti
 * apa-apa secara fisik.
 */
class DevicePageStandTest extends TestCase
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

    public function test_stand_lwbp_dan_wbp_ditampilkan_terpisah_bukan_dijumlahkan(): void
    {
        $meter = PowerMeter::create(['code' => 'MTR-01', 'name' => 'Panel Satu', 'multiplier' => 1, 'status' => 'active']);

        MeterReading::create([
            'power_meter_id' => $meter->id, 'read_at' => now(),
            'stand_lwbp' => 1_488_089.8, 'stand_wbp' => 413_713.8, 'source' => 'api',
        ]);

        Livewire::test(DevicePage::class)
            ->assertSee('Stand LWBP')
            ->assertSee('Stand WBP')
            ->assertDontSee('Stand Akhir')
            // 1.488.089,8 + 413.713,8 dijumlahkan (1.901.803,6) tidak boleh muncul.
            ->assertDontSee('1.901.803');
    }
}
