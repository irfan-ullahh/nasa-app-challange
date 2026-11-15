<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\SatelliteScan;
use App\Models\TelemetryLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('SAR Data Layer')]
final class DataLayerDetail extends Component
{
    #[Url]
    public string $layer = 'flood-mapping';

    #[Url]
    public string $period = '30';

    #[Url]
    public string $region = 'all';

    public function mount(string $layer): void
    {
        if (! array_key_exists($layer, self::layerDefinitions())) {
            abort(404);
        }

        $this->layer = $layer;
    }

    public function render(): View
    {
        $definition = self::layerDefinitions()[$this->layer];
        $days = in_array($this->period, ['7', '30', '90', '365'], true) ? (int) $this->period : 30;
        $scans = $this->scans($definition['scan_type'], $days);
        $telemetryLogs = $this->telemetryLogs($scans, $days);

        $this->backfillDerivedMetrics($scans, $telemetryLogs);
        $regionalRows = $this->regionalRows($scans, $telemetryLogs, $definition['metric_key']);
        $trendData = $this->trendData($telemetryLogs, $definition['metric_key'], $days);
        $nasaPower = $scans
            ->map(fn (SatelliteScan $scan): mixed => data_get($scan->metrics, 'nasa_power'))
            ->filter(fn (mixed $data): bool => is_array($data))
            ->sortByDesc('observation_date')
            ->first();
        $latestTelemetry = $telemetryLogs->first();

        if ($nasaPower === null && $latestTelemetry !== null) {
            $nasaPower = [
                'source' => data_get($latestTelemetry->metrics, 'source'),
                'observation_date' => $latestTelemetry->recorded_at->toDateString(),
                'soil_temperature_c' => data_get($latestTelemetry->metrics, 'temperature_c'),
                'precipitation_mm' => data_get($latestTelemetry->metrics, 'precipitation_mm'),
                'moisture_index' => $latestTelemetry->moisture_index,
            ];
        }

        return view('livewire.data-layer-detail', [
            'definition' => $definition,
            'days' => $days,
            'regions' => SatelliteScan::query()
                ->where('scan_type', $definition['scan_type'])
                ->distinct()
                ->orderBy('region_name')
                ->pluck('region_name'),
            'regionalRows' => $regionalRows,
            'alerts' => $scans->where('status', 'alert')->take(5),
            'metrics' => $this->metrics($scans, $telemetryLogs, $definition['metric_key']),
            'nasaPower' => $nasaPower,
            'chartData' => [
                'labels' => $trendData['labels'],
                'values' => $trendData['values'],
                'metric_label' => $definition['metric_label'],
                'accent' => $definition['accent'],
            ],
            'mapScans' => $scans->map(function (SatelliteScan $scan) use ($definition, $telemetryLogs): array {
                $scanTelemetry = $telemetryLogs->where('satellite_scan_id', $scan->id);
                $metrics = $this->mergedMetrics($scan, $scanTelemetry);

                return [
                    'id' => $scan->id,
                    'region_name' => $scan->region_name,
                    'scan_type' => $scan->scan_type,
                    'latitude' => $scan->latitude,
                    'longitude' => $scan->longitude,
                    'status' => $scan->status,
                    'temperature_c' => data_get($metrics, 'temperature_c'),
                    'moisture_index' => data_get($metrics, 'moisture_index'),
                    'precipitation_mm' => data_get($metrics, 'precipitation_mm'),
                    'signal_intensity_db' => data_get($metrics, 'signal_intensity_db'),
                    'metric_value' => data_get($metrics, $definition['metric_key']),
                    'metric_unit' => $definition['unit'],
                ];
            })->values()->all(),
        ]);
    }

    private function scans(string $scanType, int $days): Collection
    {
        $query = SatelliteScan::query()
            ->where('scan_type', $scanType)
            ->withCount('telemetryLogs')
            ->when($this->region !== 'all', fn ($query) => $query->where('region_name', $this->region));
        $scans = (clone $query)
            ->where('scanned_at', '>=', now()->subDays($days))
            ->latest('scanned_at')
            ->get();

        if ($scans->isNotEmpty()) {
            return $scans;
        }

        return $query->latest('scanned_at')->get();
    }

    private function telemetryLogs(Collection $scans, int $days): Collection
    {
        $query = TelemetryLog::query()
            ->whereIn('satellite_scan_id', $scans->pluck('id'))
            ->where(function ($query): void {
                $query->whereNotNull('metrics->temperature_c')
                    ->orWhereNotNull('metrics->precipitation_mm');
            })
            ->latest('recorded_at');
        $logs = (clone $query)
            ->where('recorded_at', '>=', now()->subDays($days))
            ->get();

        if ($logs->isNotEmpty()) {
            return $logs;
        }

        return TelemetryLog::query()
            ->whereIn('satellite_scan_id', $scans->pluck('id'))
            ->latest('recorded_at')
            ->get();
    }

    private function metrics(Collection $scans, Collection $telemetryLogs, string $metricKey): array
    {
        return [
            'primary' => $scans->sum(fn (SatelliteScan $scan): float => (float) data_get($scan->metrics, $metricKey, 0)),
            'alerts' => $scans->where('status', 'alert')->count(),
            'regions' => $scans->pluck('region_name')->unique()->count(),
            'signal' => $telemetryLogs->isNotEmpty()
                ? round((float) $telemetryLogs->map(fn (TelemetryLog $log): float => $this->resolvedSignal($log))->avg(), 1)
                : null,
            'scans' => $scans->count(),
            'acquisitions' => $scans->sum(fn (SatelliteScan $scan): int => max(1, (int) $scan->telemetry_logs_count)),
        ];
    }

    private function regionalRows(Collection $scans, Collection $telemetryLogs, string $metricKey): array
    {
        return $scans->groupBy('region_name')
            ->map(function (Collection $regionScans, string $region) use ($telemetryLogs, $metricKey): array {
                $signals = $regionScans
                    ->flatMap(fn (SatelliteScan $scan): SupportCollection => $telemetryLogs
                        ->where('satellite_scan_id', $scan->id)
                        ->map(fn (TelemetryLog $log): float => $this->resolvedSignal($log)))
                    ->filter(fn (?float $signal): bool => $signal !== null);

                return [
                    'region' => $region,
                    'acquisitions' => $regionScans->sum(
                        fn (SatelliteScan $scan): int => max(1, (int) $scan->telemetry_logs_count),
                    ),
                    'alerts' => $regionScans->where('status', 'alert')->count(),
                    'metric' => $regionScans->sum(
                        fn (SatelliteScan $scan): float => (float) data_get($scan->metrics, $metricKey, 0),
                    ),
                    'signal' => $signals->isNotEmpty() ? round((float) $signals->avg(), 1) : null,
                ];
            })
            ->sortByDesc('metric')
            ->values()
            ->all();
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    private function trendData(Collection $telemetryLogs, string $metricKey, int $days): array
    {
        $dailyLogs = $telemetryLogs
            ->groupBy(fn (TelemetryLog $log): string => $log->recorded_at->toDateString())
            ->sortKeys()
            ->take(-$days);

        return [
            'labels' => $dailyLogs->keys()
                ->map(fn (string $date): string => Carbon::parse($date)->format('M d'))
                ->values()
                ->all(),
            'values' => $dailyLogs
                ->map(fn (Collection $logs): float => $this->deriveLayerMetrics($logs)[$metricKey])
                ->values()
                ->all(),
        ];
    }

    private function backfillDerivedMetrics(Collection $scans, Collection $telemetryLogs): void
    {
        $logsByScan = $telemetryLogs->groupBy('satellite_scan_id');

        $scans->each(function (SatelliteScan $scan) use ($logsByScan): void {
            $logs = $logsByScan->get($scan->id, new Collection);

            if ($logs->isEmpty()) {
                return;
            }

            $derived = $this->deriveLayerMetrics($logs);
            $metrics = is_array($scan->metrics) ? $scan->metrics : [];
            $updated = false;

            foreach (['flood_area', 'vegetation_loss', 'soil_saturation'] as $metricKey) {
                if (! array_key_exists($metricKey, $metrics) || $metrics[$metricKey] === null) {
                    $metrics[$metricKey] = $derived[$metricKey];
                    $updated = true;
                }
            }

            if ($updated) {
                $scan->metrics = $metrics;
                $scan->saveQuietly();
            }
        });
    }

    /**
     * @return array{flood_area: float, vegetation_loss: float, soil_saturation: float}
     */
    private function deriveLayerMetrics(Collection $telemetryLogs): array
    {
        $moistureValues = $telemetryLogs
            ->pluck('moisture_index')
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): float => (float) $value);
        $precipitationValues = $telemetryLogs
            ->map(fn (TelemetryLog $log): mixed => data_get($log->metrics, 'precipitation_mm'))
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): float => (float) $value);
        $temperatureValues = $telemetryLogs
            ->map(fn (TelemetryLog $log): mixed => data_get($log->metrics, 'temperature_c'))
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): float => (float) $value);
        $avgMoisture = $moistureValues->isNotEmpty() ? (float) $moistureValues->avg() : 0.0;
        $avgPrecip = $precipitationValues->isNotEmpty() ? (float) $precipitationValues->avg() : 0.0;
        $maxPrecip = $precipitationValues->isNotEmpty() ? (float) $precipitationValues->max() : 0.0;
        $avgTemp = $temperatureValues->isNotEmpty() ? (float) $temperatureValues->avg() : 0.0;

        return [
            'flood_area' => round(max(0, ($avgMoisture * 420) + ($maxPrecip * 18.5)), 1),
            'vegetation_loss' => round(max(0, min(100, (1 - $avgMoisture) * 35 + max(0, ($avgTemp - 20) * 1.4))), 1),
            'soil_saturation' => round(min(100, $avgMoisture * 100), 1),
        ];
    }

    private function mergedMetrics(SatelliteScan $scan, Collection $telemetry): array
    {
        $metrics = is_array($scan->metrics) ? $scan->metrics : [];
        $latest = $telemetry->sortByDesc('recorded_at')->first();

        if ($latest !== null) {
            $metrics['signal_intensity_db'] = $this->resolvedSignal($latest);
            $metrics['moisture_index'] = $latest->moisture_index;
            $metrics['temperature_c'] = data_get($latest->metrics, 'temperature_c');
            $metrics['precipitation_mm'] = data_get($latest->metrics, 'precipitation_mm');
            $metrics['source'] = data_get($latest->metrics, 'source') ?? data_get($metrics, 'source');
        }

        if ($latest === null && (float) data_get($metrics, 'signal_intensity_db') === 88.5) {
            unset($metrics['signal_intensity_db']);
        }

        foreach ($this->deriveLayerMetrics($telemetry) as $key => $value) {
            if (! array_key_exists($key, $metrics) || $metrics[$key] === null) {
                $metrics[$key] = $value;
            }
        }

        return $metrics;
    }

    private function resolvedSignal(TelemetryLog $log): float
    {
        return (float) $log->signal_intensity !== 88.5
            ? (float) $log->signal_intensity
            : round(82.0 + (max(0.0, min(1.0, (float) $log->moisture_index)) * 9.5), 1);
    }

    private static function layerDefinitions(): array
    {
        return [
            'flood-mapping' => [
                'title' => 'Flood Mapping',
                'scan_type' => 'flood_mapping',
                'metric_key' => 'flood_area',
                'metric_label' => 'Flooded Area',
                'unit' => 'km²',
                'accent' => '#06b6d4',
                'description' => 'Monitor active inundation zones and surface-water expansion from SAR acquisitions.',
            ],
            'vegetation-change' => [
                'title' => 'Vegetation Change',
                'scan_type' => 'vegetation_changing',
                'metric_key' => 'vegetation_loss',
                'metric_label' => 'Vegetation Loss',
                'unit' => '%',
                'accent' => '#10b981',
                'description' => 'Track vegetation loss signals and regional change detected through radar observations.',
            ],
            'soil-moisture' => [
                'title' => 'Soil Moisture',
                'scan_type' => 'soil_moisture',
                'metric_key' => 'soil_saturation',
                'metric_label' => 'Soil Saturation',
                'unit' => '%',
                'accent' => '#8b5cf6',
                'description' => 'Compare soil saturation, telemetry signal quality, and regional moisture conditions.',
            ],
        ];
    }
}
