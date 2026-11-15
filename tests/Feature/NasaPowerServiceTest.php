<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\NasaPowerService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class NasaPowerServiceTest extends TestCase
{
    public function test_nasa_power_daily_response_is_normalized_to_environmental_metrics(): void
    {
        Http::fakeSequence()
            ->push([
                'properties' => [
                    'parameter' => [
                        'TS' => ['20261112' => 28.4, '20261113' => 29.1],
                        'PRECTOTCORR' => ['20261112' => 4.0, '20261113' => 6.0],
                    ],
                ],
            ]);

        $metrics = app(NasaPowerService::class)->fetchLatest(-3.4653, -62.2159);

        $this->assertSame('NASA POWER', $metrics['source']);
        $this->assertSame('2026-11-13', $metrics['observation_date']);
        $this->assertSame(29.1, $metrics['soil_temperature_c']);
        $this->assertSame(6.0, $metrics['precipitation_mm']);
        $this->assertSame(0.375, $metrics['moisture_index']);

        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), NasaPowerService::ENDPOINT)
            && $request['parameters'] === 'T2M,PRECTOTCORR,GWETTOP,RH2M'
            && $request['community'] === 'AG'
            && $request['latitude'] === -3.4653
            && $request['longitude'] === -62.2159
            && $request['format'] === 'JSON');
    }

    public function test_nasa_power_failures_return_null_without_throwing(): void
    {
        Http::fakeSequence()->push([], 503);

        $this->assertNull(app(NasaPowerService::class)->fetchLatest(0, 0));
    }

    public function test_nasa_power_historical_response_is_normalized_in_chronological_order(): void
    {
        Http::fakeSequence()->push([
            'properties' => [
                'parameter' => [
                    'TS' => ['20261113' => 29.1, '20261112' => 28.4],
                    'PRECTOTCORR' => ['20261113' => 6.0, '20261112' => 4.0],
                ],
            ],
        ]);

        $timeSeries = app(NasaPowerService::class)->fetchHistoricalTimeSeries(-3.4653, -62.2159, 2);

        $this->assertCount(2, $timeSeries);
        $this->assertSame('2026-11-12', $timeSeries[0]['date']);
        $this->assertSame(28.4, $timeSeries[0]['temperature_c']);
        $this->assertSame(0.2857, $timeSeries[0]['moisture_index']);
        $this->assertSame('2026-11-13', $timeSeries[1]['date']);
        $this->assertSame(0.375, $timeSeries[1]['moisture_index']);
    }
}
