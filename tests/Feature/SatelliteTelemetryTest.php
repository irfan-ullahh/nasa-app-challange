<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SatelliteScan;
use App\Models\TelemetryLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SatelliteTelemetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_satellite_scan_metrics_are_cast_and_telemetry_relationships_are_available(): void
    {
        $scan = SatelliteScan::factory()->create([
            'metrics' => ['flood_area' => 125.5],
        ]);
        $telemetry = TelemetryLog::factory()->for($scan)->create();

        $this->assertIsArray($scan->fresh()->metrics);
        $this->assertSame(125.5, $scan->fresh()->metrics['flood_area']);
        $this->assertTrue($scan->telemetryLogs->contains($telemetry));
        $this->assertTrue($telemetry->satelliteScan->is($scan));
    }
}
