<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\UploadData;
use App\Models\SatelliteScan;
use App\Models\TelemetryLog;
use App\Services\NasaPowerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

final class UploadDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_region_submission_fetches_nasa_telemetry_and_seeds_historical_logs(): void
    {
        Http::fake([
            NasaPowerService::ENDPOINT.'*' => Http::response([
                'properties' => [
                    'parameter' => [
                        'TS' => ['20261112' => 28.4, '20261113' => -999],
                        'PRECTOTCORR' => ['20261112' => 4.0, '20261113' => -999],
                    ],
                ],
            ]),
        ]);

        Livewire::test(UploadData::class)
            ->set('region_name', 'Amazon Basin')
            ->set('latitude', '-3.4653')
            ->set('longitude', '-62.2159')
            ->call('saveRegion')
            ->assertHasNoErrors()
            ->assertSee('Region telemetry ingested successfully.');

        $scan = SatelliteScan::query()->where('region_name', 'Amazon Basin')->firstOrFail();

        $this->assertSame('vegetation_changing', $scan->scan_type);
        $this->assertSame(-3.4653, $scan->latitude);
        $this->assertSame(-62.2159, $scan->longitude);
        $this->assertSame(28.4, data_get($scan->metrics, 'temperature_c'));
        $this->assertSame(0.2857, data_get($scan->metrics, 'moisture_index'));
        $this->assertSame(2, data_get($scan->metrics, 'historical_days'));

        $telemetry = TelemetryLog::query()
            ->where('satellite_scan_id', $scan->id)
            ->orderBy('recorded_at')
            ->get();

        $this->assertCount(2, $telemetry);
        $this->assertSame('2026-11-12', $telemetry[0]->recorded_at->toDateString());
        $this->assertSame('2026-11-13', $telemetry[1]->recorded_at->toDateString());
        $this->assertSame(28.4, data_get($telemetry[0]->metrics, 'temperature_c'));
        $this->assertNull(data_get($telemetry[1]->metrics, 'precipitation_mm'));

        Http::assertSent(fn ($request): bool => $request['latitude'] === -3.4653
            && $request['longitude'] === -62.2159
            && $request['parameters'] === 'T2M,PRECTOTCORR,GWETTOP,RH2M');
    }

    public function test_region_submission_requires_valid_coordinates_and_metadata(): void
    {
        Livewire::test(UploadData::class)
            ->set('region_name', '')
            ->set('latitude', '100')
            ->set('longitude', '-181')
            ->call('saveRegion')
            ->assertHasErrors(['region_name', 'latitude', 'longitude']);

        $this->assertDatabaseCount('satellite_scans', 0);
        $this->assertDatabaseCount('telemetry_logs', 0);
    }

    public function test_recent_regions_are_rendered_from_local_scans(): void
    {
        SatelliteScan::factory()->create([
            'region_name' => 'Mekong Delta',
            'scan_type' => 'soil_moisture',
            'latitude' => 10.2,
            'longitude' => 105.8,
        ]);

        Livewire::test(UploadData::class)
            ->assertSee('Mekong Delta')
            ->assertSee('soil moisture');
    }

    public function test_resubmitting_a_region_updates_one_scan_and_replaces_telemetry(): void
    {
        Http::fakeSequence()
            ->push([
                'properties' => [
                    'parameter' => [
                        'TS' => ['20261112' => 28.4],
                        'PRECTOTCORR' => ['20261112' => 4.0],
                    ],
                ],
            ])
            ->push([
                'properties' => [
                    'parameter' => [
                        'TS' => ['20261113' => 29.1, '20261114' => 30.0],
                        'PRECTOTCORR' => ['20261113' => 6.0, '20261114' => 8.0],
                    ],
                ],
            ]);

        Livewire::test(UploadData::class)
            ->set('region_name', 'Amazon Basin')
            ->set('latitude', '-3.4653')
            ->set('longitude', '-62.2159')
            ->call('saveRegion')
            ->set('region_name', 'Amazon Basin')
            ->set('latitude', '-3.5000')
            ->set('longitude', '-62.3000')
            ->call('saveRegion')
            ->assertHasNoErrors();

        $scan = SatelliteScan::query()->where('region_name', 'Amazon Basin')->firstOrFail();

        $this->assertSame(1, SatelliteScan::query()->where('region_name', 'Amazon Basin')->count());
        $this->assertSame(-3.5, $scan->latitude);
        $this->assertSame(-62.3, $scan->longitude);
        $this->assertSame('flood_mapping', $scan->scan_type);
        $this->assertSame(2, $scan->telemetryLogs()->count());
        $this->assertEquals(30.0, data_get($scan->metrics, 'temperature_c'));
    }

    public function test_region_deletion_removes_scan_and_telemetry_logs(): void
    {
        $scan = SatelliteScan::factory()->create(['region_name' => 'Amazon Basin']);
        TelemetryLog::factory()->create(['satellite_scan_id' => $scan->id]);

        Livewire::test(UploadData::class)
            ->call('deleteRegion', $scan->id)
            ->assertSee('Region and telemetry logs removed.');

        $this->assertDatabaseMissing('satellite_scans', ['id' => $scan->id]);
        $this->assertDatabaseCount('telemetry_logs', 0);
    }
}
