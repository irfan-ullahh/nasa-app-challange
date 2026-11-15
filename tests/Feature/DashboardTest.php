<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\SatelliteScan;
use App\Models\TelemetryLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_route_returns_the_livewire_dashboard(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeLivewire(Dashboard::class);
    }

    public function test_dashboard_global_metrics_use_daily_local_nasa_telemetry(): void
    {
        $scan = SatelliteScan::factory()->create([
            'region_name' => 'Amazon Basin',
            'status' => 'active',
        ]);

        TelemetryLog::factory()->create([
            'satellite_scan_id' => $scan->id,
            'recorded_at' => Carbon::parse('2026-11-14 08:00:00'),
            'signal_intensity' => 80,
            'moisture_index' => 0.4,
            'metrics' => ['temperature_c' => 28.0, 'precipitation_mm' => 2.0],
        ]);
        TelemetryLog::factory()->create([
            'satellite_scan_id' => $scan->id,
            'recorded_at' => Carbon::parse('2026-11-14 12:00:00'),
            'signal_intensity' => 90,
            'moisture_index' => 0.6,
            'metrics' => ['temperature_c' => 29.0, 'precipitation_mm' => 3.0],
        ]);
        TelemetryLog::factory()->create([
            'satellite_scan_id' => $scan->id,
            'recorded_at' => Carbon::parse('2026-11-13 12:00:00'),
            'signal_intensity' => 50,
            'moisture_index' => 0.65,
            'metrics' => ['temperature_c' => null, 'precipitation_mm' => null],
        ]);

        Livewire::test(Dashboard::class)
            ->assertSee('Nov 14')
            ->assertDontSee('Nov 13')
            ->assertSee('signal_intensity')
            ->assertSee('85')
            ->assertSee('50')
            ->assertSee('90.0 dB');
    }
}
