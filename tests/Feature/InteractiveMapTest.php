<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\InteractiveMap;
use App\Models\SatelliteScan;
use App\Models\TelemetryLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

final class InteractiveMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_interactive_map_renders_scan_coordinates_and_region_filter(): void
    {
        $scan = SatelliteScan::factory()->create([
            'region_name' => 'Amazon Basin',
            'scan_type' => 'flood_mapping',
            'status' => 'alert',
            'latitude' => -3.4653,
            'longitude' => -62.2159,
        ]);

        TelemetryLog::factory()->create([
            'satellite_scan_id' => $scan->id,
            'signal_intensity' => 82.5,
            'moisture_index' => 0.64,
        ]);

        $this->get(route('map.index'))
            ->assertOk()
            ->assertSeeLivewire(InteractiveMap::class)
            ->assertSee('Amazon Basin')
            ->assertSee('-3.4653')
            ->assertSee('Interactive Map');
    }

    public function test_interactive_map_downloads_a_csv_report_for_current_scans(): void
    {
        $scan = SatelliteScan::factory()->create([
            'region_name' => 'Amazon Basin',
            'status' => 'alert',
            'latitude' => -3.4653,
            'longitude' => -62.2159,
        ]);

        TelemetryLog::factory()->create([
            'satellite_scan_id' => $scan->id,
            'signal_intensity' => 82.5,
            'moisture_index' => 0.64,
        ]);

        $response = app(InteractiveMap::class)->downloadReport();

        $this->assertSame('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $report = ob_get_clean();

        $this->assertIsString($report);
        $this->assertStringContainsString('Amazon Basin', $report);
        $this->assertStringContainsString('82.5', $report);
    }

    public function test_interactive_map_selects_a_scan_point_for_the_detail_drawer(): void
    {
        $scan = SatelliteScan::factory()->create([
            'region_name' => 'Amazon Basin',
            'metrics' => [
                'signal_intensity_db' => 88.5,
                'moisture_index' => 0.375,
                'temperature_c' => 29.1,
                'precipitation_mm' => 6.0,
                'source' => 'NASA POWER API (Live Feed)',
            ],
        ]);
        TelemetryLog::factory()->for($scan)->create([
            'signal_intensity' => 77.0,
            'moisture_index' => 0.41,
            'metrics' => [
                'temperature_c' => 24.5,
                'precipitation_mm' => 3.2,
                'source' => 'NASA POWER API (Historical Feed)',
            ],
        ]);

        Livewire::test(InteractiveMap::class)
            ->call('selectScanPoint', $scan->id)
            ->assertSet('selectedScan.id', $scan->id)
            ->assertSet('isDrawerOpen', true)
            ->assertSee('NASA POWER telemetry')
            ->assertSee('77.0 dB')
            ->assertSee('24.5')
            ->call('closeScan')
            ->assertSet('isDrawerOpen', false)
            ->assertSet('selectedScan', null);
    }

    public function test_interactive_map_radar_data_is_aggregated_to_the_latest_seven_days(): void
    {
        $scan = SatelliteScan::factory()->create();

        foreach (range(0, 7) as $day) {
            TelemetryLog::factory()->create([
                'satellite_scan_id' => $scan->id,
                'recorded_at' => Carbon::parse('2026-11-01')->addDays($day),
                'signal_intensity' => 80 + $day,
                'moisture_index' => 0.1 + ($day / 100),
            ]);
        }

        Livewire::test(InteractiveMap::class)
            ->assertSee('Nov 02')
            ->assertSee('Nov 08')
            ->assertDontSee('Nov 01');
    }
}
