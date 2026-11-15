<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TelemetryLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'satellite_scan_id',
    'signal_intensity',
    'moisture_index',
    'recorded_at',
    'metrics',
])]
class TelemetryLog extends Model
{
    /** @use HasFactory<TelemetryLogFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'signal_intensity' => 'float',
            'moisture_index' => 'float',
            'recorded_at' => 'datetime',
            'metrics' => 'array',
        ];
    }

    public function satelliteScan(): BelongsTo
    {
        return $this->belongsTo(SatelliteScan::class);
    }
}
