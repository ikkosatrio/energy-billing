<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\DeviceStatusWidget;
use App\Models\Customer;
use App\Models\PowerMeter;
use App\Models\Role;
use App\Models\TariffGroup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Widget "Real-time Perangkat" di Dashboard — pengganti panel Status Meter
 * dan Invoice Terbaru lama. Fokus tesnya: urutan prioritas (supaya perangkat
 * bermasalah langsung terlihat tanpa menggulir) dan bahwa kedua panel lama
 * benar-benar sudah tidak ada.
 */
class DashboardDeviceWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);

        Carbon::setTestNow('2026-08-15 14:00:00');

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

    private function meter(array $overrides = []): PowerMeter
    {
        // last_seen_at sengaja tidak fillable (hanya ditulis oleh gateway
        // lewat DeviceStatusService/ReadingIngestService), jadi dipaksa di
        // sini lewat forceFill seperti yang dipakai kode produksinya sendiri.
        $lastSeenAt = $overrides['last_seen_at'] ?? null;
        unset($overrides['last_seen_at']);

        $meter = PowerMeter::create(array_merge([
            'code' => 'MTR-'.Str::random(6), 'name' => 'Panel', 'multiplier' => 1, 'status' => 'active',
        ], $overrides));

        if ($lastSeenAt !== null) {
            $meter->forceFill(['last_seen_at' => $lastSeenAt])->save();
        }

        return $meter;
    }

    public function test_widget_tampil_di_dashboard(): void
    {
        $this->meter(['name' => 'Panel Uji']);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSeeLivewire(DeviceStatusWidget::class)
            ->assertSee('Panel Uji');
    }

    public function test_panel_status_meter_dan_invoice_lama_sudah_tidak_ada(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Status Meter')
            ->assertDontSee('Invoice Terbaru');
    }

    /**
     * Urutan mengikuti ID meter, bukan urgensi maupun abjad.
     *
     * Panel ini menyegarkan diri tiap 30 detik. Kalau urutannya mengikuti
     * status, kartu berpindah tempat sendiri begitu ada meter yang berubah
     * jadi offline — dan perangkat yang sedang diamati operator ikut bergeser.
     */
    public function test_kartu_diurutkan_berdasarkan_id_meter(): void
    {
        $this->meter(['name' => 'Z Normal', 'last_seen_at' => now()]);
        $this->meter(['name' => 'A Offline', 'last_seen_at' => now()->subHours(2)]);
        $this->meter(['name' => 'M Maintenance', 'status' => 'maintenance', 'last_seen_at' => now()]);

        $order = Livewire::test(DeviceStatusWidget::class)
            ->viewData('meters')
            ->pluck('name')
            ->all();

        // Urutan pembuatan, bukan abjad dan bukan status.
        $this->assertSame(['Z Normal', 'A Offline', 'M Maintenance'], $order);
    }

    public function test_urutan_tidak_berubah_saat_ada_meter_jadi_offline(): void
    {
        $satu = $this->meter(['name' => 'Panel Satu', 'last_seen_at' => now()]);
        $this->meter(['name' => 'Panel Dua', 'last_seen_at' => now()]);

        $sebelum = Livewire::test(DeviceStatusWidget::class)->viewData('meters')->pluck('name')->all();

        $satu->forceFill(['last_seen_at' => now()->subHours(2)])->save();

        $sesudah = Livewire::test(DeviceStatusWidget::class)->viewData('meters')->pluck('name')->all();

        $this->assertSame($sebelum, $sesudah);
    }

    public function test_filter_phase_menyaring_kartu(): void
    {
        $this->meter(['name' => 'Tiga Phase', 'phase' => '3', 'last_seen_at' => now()]);
        $this->meter(['name' => 'Satu Phase', 'phase' => '1', 'last_seen_at' => now()]);

        $component = Livewire::test(DeviceStatusWidget::class);

        $this->assertCount(2, $component->viewData('meters'));

        $component->set('phaseFilter', '1');
        $this->assertSame(['Satu Phase'], $component->viewData('meters')->pluck('name')->all());

        $component->set('phaseFilter', '3');
        $this->assertSame(['Tiga Phase'], $component->viewData('meters')->pluck('name')->all());

        $component->set('phaseFilter', '');
        $this->assertCount(2, $component->viewData('meters'));
    }

    public function test_jumlah_per_phase_dihitung_dari_seluruh_meter_aktif(): void
    {
        $this->meter(['phase' => '3', 'last_seen_at' => now()]);
        $this->meter(['phase' => '3', 'last_seen_at' => now()]);
        $this->meter(['phase' => '1', 'last_seen_at' => now()]);
        // Nonaktif tidak dihitung — tidak pernah muncul di panel ini.
        $this->meter(['phase' => '1', 'status' => 'inactive']);

        $counts = Livewire::test(DeviceStatusWidget::class)
            // Angkanya harus tetap utuh walau sedang difilter, karena dipakai
            // sebagai label pilihan filter itu sendiri.
            ->set('phaseFilter', '1')
            ->viewData('phaseCounts');

        $this->assertSame(['1' => 1, '3' => 2, 'all' => 3], $counts);
    }

    public function test_beban_tinggi_dihitung_dari_daya_kva_pelanggan(): void
    {
        $group = TariffGroup::create(['code' => 'I-3/TR', 'name' => 'I-3']);

        $tinggi = $this->meter(['name' => 'Beban Tinggi', 'last_seen_at' => now()]);
        Customer::create([
            'code' => 'C-1', 'name' => 'Pelanggan Berat', 'power_meter_id' => $tinggi->id,
            'tariff_group_id' => $group->id, 'daya_kva' => 100, 'status' => 'active',
        ]);
        \App\Models\MeterReading::create([
            'power_meter_id' => $tinggi->id, 'read_at' => now(),
            'stand_lwbp' => 100, 'stand_wbp' => 0, 'active_power_kw' => 90, 'source' => 'api',
        ]);

        $normal = $this->meter(['name' => 'Beban Normal', 'last_seen_at' => now()]);
        Customer::create([
            'code' => 'C-2', 'name' => 'Pelanggan Ringan', 'power_meter_id' => $normal->id,
            'tariff_group_id' => $group->id, 'daya_kva' => 100, 'status' => 'active',
        ]);
        \App\Models\MeterReading::create([
            'power_meter_id' => $normal->id, 'read_at' => now(),
            'stand_lwbp' => 100, 'stand_wbp' => 0, 'active_power_kw' => 10, 'source' => 'api',
        ]);

        $cards = Livewire::test(DeviceStatusWidget::class)->viewData('cards');

        $this->assertSame('Beban Tinggi', $cards[0]['status']);
        $this->assertSame('Normal', $cards[1]['status']);
    }

    public function test_jumlah_perlu_perhatian_tidak_menghitung_yang_normal(): void
    {
        $this->meter(['name' => 'Normal 1', 'last_seen_at' => now()]);
        $this->meter(['name' => 'Normal 2', 'last_seen_at' => now()]);
        $this->meter(['name' => 'Offline', 'last_seen_at' => now()->subHours(2)]);

        Livewire::test(DeviceStatusWidget::class)
            ->assertViewHas('attentionCount', 1);
    }

    public function test_meter_satu_phase_hanya_menampilkan_jalur_r(): void
    {
        $this->meter(['name' => 'Satu Phase', 'phase' => '1', 'last_seen_at' => now()]);

        Livewire::test(DeviceStatusWidget::class)
            ->assertSeeHtml('>R<')
            ->assertDontSeeHtml('>S<')
            ->assertDontSeeHtml('>T<');
    }

    public function test_jeda_penyegaran_menghasilkan_polling(): void
    {
        $this->meter();

        Livewire::test(DeviceStatusWidget::class)
            ->assertSeeHtml('wire:poll.30s')
            ->set('refreshEvery', 5)
            ->assertSeeHtml('wire:poll.5s')
            ->set('refreshEvery', 0)
            ->assertDontSeeHtml('wire:poll');
    }
}
