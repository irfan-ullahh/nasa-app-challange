<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\DataLayerDetail;
use App\Models\SatelliteScan;
use App\Models\TelemetryLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DataLayerDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_layer_route_filters_scans_to_its_metric_type(): void
    {
        $floodScan = SatelliteScan::factory()->create([
            'region_name' => 'Kalimantan Basin',
            'scan_type' => 'flood_mapping',
            'metrics' => ['flood_area' => 610.8],
        ]);
        SatelliteScan::factory()->create([
            'region_name' => 'Mekong Delta',
            'scan_type' => 'vegetation_changing',
        ]);
        TelemetryLog::factory()->for($floodScan)->create(['signal_intensity' => 86.4]);

        $this->get(route('layers.show', ['layer' => 'flood-mapping']))
            ->assertOk()
            ->assertSeeLivewire(DataLayerDetail::class)
            ->assertSee('Flood Mapping')
            ->assertSee('Kalimantan Basin')
            ->assertSee('610.8')
            ->assertDontSee('Mekong Delta');
    }

    public function test_unknown_layer_slug_returns_not_found(): void
    {
        $this->get(route('layers.show', ['layer' => 'unknown-layer']))
            ->assertNotFound();
    }
}
