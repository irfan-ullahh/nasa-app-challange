<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\NisarFeed;
use App\Models\SatelliteScan;
use App\Models\TelemetryLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NisarFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_nisar_feed_renders_live_passes_downlink_and_radar_parameters(): void
    {
        $scan = SatelliteScan::factory()->create([
            'region_name' => 'Amazon Basin',
            'scan_type' => 'flood_mapping',
            'status' => 'active',
            'metrics' => [
                'resolution_m' => 7.5,
                'incidence_angle' => 32.4,
                'swath_width_km' => 240,
            ],
        ]);

        TelemetryLog::factory()->create([
            'satellite_scan_id' => $scan->id,
            'signal_intensity' => 84.2,
            'moisture_index' => 0.62,
        ]);

        $this->get(route('nisar.index'))
            ->assertOk()
            ->assertSeeLivewire(NisarFeed::class)
            ->assertSee('NISAR-'.str_pad((string) $scan->id, 2, '0', STR_PAD_LEFT))
            ->assertSee('Amazon Basin')
            ->assertSee('7.5 m')
            ->assertSee('84.2 dB')
            ->assertSee('Connected');
    }

    public function test_nisar_feed_falls_back_to_recent_historical_scans_when_window_is_empty(): void
    {
        $scan = SatelliteScan::factory()->create([
            'region_name' => 'Sahara Fringe',
            'status' => 'active',
            'scanned_at' => now()->subDays(330),
        ]);

        $this->get(route('nisar.index', ['window' => '24h']))
            ->assertOk()
            ->assertSee('NISAR-'.str_pad((string) $scan->id, 2, '0', STR_PAD_LEFT))
            ->assertSee('Sahara Fringe');
    }

    public function test_nisar_feed_renders_all_scans_in_the_default_window(): void
    {
        $firstScan = SatelliteScan::factory()->create([
            'region_name' => 'Amazon Basin',
            'scanned_at' => now()->subDays(14),
        ]);
        $secondScan = SatelliteScan::factory()->create([
            'region_name' => 'Patagonia Shelf',
            'scanned_at' => now()->subDays(176),
        ]);

        $this->get(route('nisar.index'))
            ->assertOk()
            ->assertSee('NISAR-'.str_pad((string) $firstScan->id, 2, '0', STR_PAD_LEFT))
            ->assertSee('NISAR-'.str_pad((string) $secondScan->id, 2, '0', STR_PAD_LEFT))
            ->assertSee('Amazon Basin')
            ->assertSee('Patagonia Shelf');
    }
}
