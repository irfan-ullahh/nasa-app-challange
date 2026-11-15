<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SatelliteScanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'region_name',
    'scan_type',
    'latitude',
    'longitude',
    'metrics',
    'status',
    'scanned_at',
])]
class SatelliteScan extends Model
{
    /** @use HasFactory<SatelliteScanFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'metrics' => 'array',
            'scanned_at' => 'datetime',
        ];
    }

    public function telemetryLogs(): HasMany
    {
        return $this->hasMany(TelemetryLog::class);
    }
}
