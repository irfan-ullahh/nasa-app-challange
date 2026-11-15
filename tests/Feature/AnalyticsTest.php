<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Analytics;
use App\Models\SatelliteScan;
use App\Models\TelemetryLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_route_renders_historical_scan_aggregates(): void
    {
        SatelliteScan::factory()->create([
            'region_name' => 'Amazon Basin',
            'status' => 'alert',
            'metrics' => ['data_processed_tb' => 123.4, 'system_uptime' => 99.1],
            'scanned_at' => now()->subDays(3),
        ]);

        $this->get(route('analytics.index', ['period' => '30']))
            ->assertOk()
            ->assertSeeLivewire(Analytics::class)
            ->assertSee('Amazon Basin')
            ->assertSee('123.4');
    }

    public function test_period_changes_dispatch_fresh_analytics_payload(): void
    {
        SatelliteScan::factory()->create([
            'scanned_at' => now()->subDays(3),
        ]);

        Livewire::test(Analytics::class)
            ->set('period', '7')
            ->assertDispatched('analytics-period-updated');
    }

    public function test_analytics_uses_daily_telemetry_for_health_and_regional_counts(): void
    {
        $healthyScan = SatelliteScan::factory()->create([
            'region_name' => 'Amazon Basin',
            'status' => 'active',
            'metrics' => [],
            'scanned_at' => now()->subDay(),
        ]);
        $alertScan = SatelliteScan::factory()->create([
            'region_name' => 'Kalimantan Basin',
            'status' => 'alert',
            'metrics' => [],
            'scanned_at' => now(),
        ]);

        foreach (range(1, 2) as $index) {
            TelemetryLog::factory()->for($healthyScan)->create([
                'signal_intensity' => 88.5,
                'moisture_index' => 0.0,
                'recorded_at' => now()->subHours($index),
                'metrics' => ['temperature_c' => 20.0, 'precipitation_mm' => 1.0],
            ]);
        }

        TelemetryLog::factory()->for($alertScan)->create([
            'signal_intensity' => 84.2,
            'moisture_index' => 0.5,
            'recorded_at' => now()->subHours(3),
            'metrics' => ['temperature_c' => 21.0, 'precipitation_mm' => 2.0],
        ]);

        Livewire::test(Analytics::class)
            ->assertSet('metrics.uptime', 50.0)
            ->assertSet('chartData.labels', [now()->format('M d')])
            ->assertSet('chartData.region_scans', [1, 2])
            ->assertSee('82.7 dB');
    }
}
