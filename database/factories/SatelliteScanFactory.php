<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SatelliteScan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SatelliteScan>
 */
class SatelliteScanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'region_name' => fake()->randomElement([
                'Amazon Basin',
                'Kalimantan Basin',
                'Mekong Delta',
                'Sahara Fringe',
                'Great Barrier Reef',
            ]),
            'scan_type' => fake()->randomElement([
                'flood_mapping',
                'vegetation_changing',
                'soil_moisture',
            ]),
            'latitude' => fake()->latitude(-55, 55),
            'longitude' => fake()->longitude(-170, 170),
            'metrics' => [
                'flood_area' => fake()->randomFloat(2, 20, 500),
                'vegetation_loss' => fake()->randomFloat(2, 10, 250),
                'soil_saturation' => fake()->randomFloat(2, 5, 100),
                'data_processed_tb' => fake()->randomFloat(2, 100, 8000),
                'system_uptime' => fake()->randomFloat(2, 96, 100),
            ],
            'status' => fake()->randomElement(['active', 'active', 'monitoring', 'alert']),
            'scanned_at' => Carbon::now()->subMinutes(fake()->numberBetween(5, 1440)),
        ];
    }
}
