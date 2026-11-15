<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SatelliteScan;
use App\Models\TelemetryLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $scans = [
            [
                'region_name' => 'Amazon Basin',
                'scan_type' => 'flood_mapping',
                'latitude' => -3.4653,
                'longitude' => -62.2159,
                'status' => 'active',
                'metrics' => [],
            ],
            [
                'region_name' => 'Kalimantan Basin',
                'scan_type' => 'flood_mapping',
                'latitude' => -1.5,
                'longitude' => 113.9,
                'status' => 'alert',
                'metrics' => [],
            ],
            [
                'region_name' => 'Mekong Delta',
                'scan_type' => 'vegetation_changing',
                'latitude' => 10.2,
                'longitude' => 105.8,
                'status' => 'active',
                'metrics' => [],
            ],
            [
                'region_name' => 'Sahara Fringe',
                'scan_type' => 'soil_moisture',
                'latitude' => 18.1,
                'longitude' => 12.4,
                'status' => 'monitoring',
                'metrics' => [],
            ],
            [
                'region_name' => 'Great Barrier Reef',
                'scan_type' => 'vegetation_changing',
                'latitude' => -18.3,
                'longitude' => 147.7,
                'status' => 'active',
                'metrics' => [],
            ],
            [
                'region_name' => 'Patagonia Shelf',
                'scan_type' => 'soil_moisture',
                'latitude' => -49.2,
                'longitude' => -71.8,
                'status' => 'active',
                'metrics' => [],
            ],
        ];

        $historicalDays = [0, 14, 42, 88, 176, 330];

        foreach ($scans as $index => $scanData) {
            $scannedAt = Carbon::now()->subDays($historicalDays[$index])->setTime(12, 0);
            $scan = SatelliteScan::factory()->create([
                ...$scanData,
                'scanned_at' => $scannedAt,
                'created_at' => $scannedAt,
                'updated_at' => $scannedAt,
            ]);

            foreach (range(0, 5) as $telemetryIndex) {
                $recordedAt = $scannedAt->copy()->addMinutes($telemetryIndex * 10);

                TelemetryLog::factory()->for($scan)->create([
                    'recorded_at' => $recordedAt,
                    'created_at' => $recordedAt,
                    'updated_at' => $recordedAt,
                ]);
            }

            $this->hydrateScanMetrics($scan);
        }
    }

    private function hydrateScanMetrics(SatelliteScan $scan): void
    {
        $telemetry = $scan->telemetryLogs()->latest('recorded_at')->get();
        $moisture = (float) $telemetry->avg('moisture_index');
        $precipitation = $telemetry
            ->map(fn (TelemetryLog $log): mixed => data_get($log->metrics, 'precipitation_mm'))
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): float => (float) $value);
        $temperature = $telemetry
            ->map(fn (TelemetryLog $log): mixed => data_get($log->metrics, 'temperature_c'))
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): float => (float) $value);
        $maxPrecipitation = $precipitation->isNotEmpty() ? (float) $precipitation->max() : 0.0;
        $averageTemperature = $temperature->isNotEmpty() ? (float) $temperature->avg() : 0.0;

        $scan->update([
            'metrics' => [
                'signal_intensity_db' => round((float) $telemetry->avg('signal_intensity'), 1),
                'moisture_index' => round($moisture, 4),
                'temperature_c' => $temperature->isNotEmpty() ? $temperature->first() : null,
                'precipitation_mm' => $precipitation->isNotEmpty() ? $precipitation->first() : null,
                'flood_area' => round(max(0, ($moisture * 420) + ($maxPrecipitation * 18.5)), 1),
                'vegetation_loss' => round(max(0, min(100, (1 - $moisture) * 35 + max(0, ($averageTemperature - 20) * 1.4))), 1),
                'soil_saturation' => round(min(100, $moisture * 100), 1),
                'data_processed_tb' => round($telemetry->count() * 42.5, 1),
            ],
        ]);
    }
}
