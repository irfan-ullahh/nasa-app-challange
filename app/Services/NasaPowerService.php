<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class NasaPowerService
{
    public const ENDPOINT = 'https://power.larc.nasa.gov/api/temporal/daily/point';

    /**
     * @return array{
     *     source: string,
     *     observation_date: string,
     *     soil_temperature_c: float|null,
     *     precipitation_mm: float|null,
     *     moisture_index: float|null
     * }|null
     */
    public function fetchLatest(float $latitude, float $longitude, int $days = 30): ?array
    {
        $timeSeries = $this->fetchHistoricalTimeSeries($latitude, $longitude, $days);

        if ($timeSeries === null || $timeSeries === []) {
            return null;
        }

        $latest = end($timeSeries);

        if (! is_array($latest)) {
            return null;
        }

        return [
            'source' => 'NASA POWER',
            'observation_date' => $latest['date'],
            'soil_temperature_c' => $latest['temperature_c'],
            'precipitation_mm' => $latest['precipitation_mm'],
            'moisture_index' => $latest['moisture_index'],
        ];
    }

    /**
     * @return array<int, array{
     *     date: string,
     *     temperature_c: float|null,
     *     precipitation_mm: float|null,
     *     moisture_index: float|null
     * }>|null
     */
    public function fetchHistoricalTimeSeries(float $latitude, float $longitude, int $days = 30): ?array
    {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180 || $days < 1) {
            return null;
        }

        $end = Carbon::today();
        $start = $end->copy()->subDays($days - 1);

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->get(self::ENDPOINT, [
                    'parameters' => 'T2M,PRECTOTCORR,GWETTOP,RH2M',
                    'community' => 'AG',
                    'longitude' => $longitude,
                    'latitude' => $latitude,
                    'start' => $start->format('Ymd'),
                    'end' => $end->format('Ymd'),
                    'format' => 'JSON',
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('NASA POWER connection failed.', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('NASA POWER returned an unsuccessful response.', [
                'status' => $response->status(),
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

            return null;
        }

        $parameters = data_get($response->json(), 'properties.parameter');

        if (! is_array($parameters)) {
            Log::warning('NASA POWER response did not contain daily parameters.', [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

            return null;
        }

        $temperatureSeries = is_array($parameters['T2M'] ?? null)
            ? $parameters['T2M']
            : (is_array($parameters['TS'] ?? null) ? $parameters['TS'] : []);
        $precipitationSeries = is_array($parameters['PRECTOTCORR'] ?? null) ? $parameters['PRECTOTCORR'] : [];
        $soilMoistureSeries = is_array($parameters['GWETTOP'] ?? null) ? $parameters['GWETTOP'] : [];
        $humiditySeries = is_array($parameters['RH2M'] ?? null) ? $parameters['RH2M'] : [];
        $dates = array_unique(array_merge(
            array_keys($temperatureSeries),
            array_keys($precipitationSeries),
            array_keys($soilMoistureSeries),
            array_keys($humiditySeries),
        ));
        sort($dates);
        $timeSeries = [];

        foreach ($dates as $date) {
            $date = (string) $date;
            $observationDate = Carbon::createFromFormat('Ymd', $date);

            if ($observationDate === false) {
                Log::warning('NASA POWER returned an invalid observation date.', ['date' => $date]);

                continue;
            }

            $temperature = $this->numericValue($temperatureSeries[$date] ?? null);
            $precipitation = $this->numericValue($precipitationSeries[$date] ?? null);
            $soilMoisture = $this->numericValue($soilMoistureSeries[$date] ?? null);
            $relativeHumidity = $this->numericValue($humiditySeries[$date] ?? null);

            $timeSeries[] = [
                'date' => $observationDate->toDateString(),
                'temperature_c' => $temperature,
                'precipitation_mm' => $precipitation,
                'moisture_index' => $this->moistureIndex($soilMoisture, $relativeHumidity, $precipitation),
            ];
        }

        return $timeSeries;
    }

    /**
     * @return array{
     *     source: string,
     *     observation_date: string,
     *     temperature_c: float|null,
     *     precipitation_mm: float|null,
     *     moisture_index: float|null
     * }|null
     */
    public function fetchSoilMoisture(float $latitude, float $longitude): ?array
    {
        $metrics = $this->fetchLatest($latitude, $longitude);

        if ($metrics === null) {
            return null;
        }

        return [
            'source' => $metrics['source'],
            'observation_date' => $metrics['observation_date'],
            'temperature_c' => $metrics['soil_temperature_c'],
            'precipitation_mm' => $metrics['precipitation_mm'],
            'moisture_index' => $metrics['moisture_index'],
        ];
    }

    private function numericValue(mixed $value): ?float
    {
        if (! is_numeric($value) || (float) $value <= -900) {
            return null;
        }

        return (float) $value;
    }

    private function moistureIndex(
        ?float $soilMoisture,
        ?float $relativeHumidity,
        ?float $precipitation,
    ): ?float {
        if ($soilMoisture !== null) {
            return round(max(0, min(1, $soilMoisture > 1 ? $soilMoisture / 100 : $soilMoisture)), 4);
        }

        if ($relativeHumidity !== null) {
            return round(max(0, min(1, $relativeHumidity / 100)), 4);
        }

        return $precipitation === null
            ? null
            : round(max(0, min(1, $precipitation / ($precipitation + 10))), 4);
    }
}
