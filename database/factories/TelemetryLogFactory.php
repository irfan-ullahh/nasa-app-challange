<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SatelliteScan;
use App\Models\TelemetryLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<TelemetryLog>
 */
class TelemetryLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'satellite_scan_id' => SatelliteScan::factory(),
            'signal_intensity' => fake()->randomFloat(2, 45, 100),
            'moisture_index' => fake()->randomFloat(4, 0, 1),
            'recorded_at' => Carbon::now()->subMinutes(fake()->numberBetween(1, 1440)),
        ];
    }
}
