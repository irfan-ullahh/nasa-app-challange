<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\SatelliteScan;
use App\Models\TelemetryLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.app')]
#[Title('Interactive Map')]
final class InteractiveMap extends Component
{
    #[Url]
    public bool $flood_mapping = true;

    #[Url]
    public bool $vegetation_changing = true;

    #[Url]
    public bool $soil_moisture = true;

    #[Url]
    public string $region = 'all';

    public ?SatelliteScan $selectedScan = null;

    public bool $isDrawerOpen = false;

    public function selectScanPoint(int $scanId): void
    {
        $scan = SatelliteScan::find($scanId);

        if ($scan !== null) {
            $telemetry = $scan->telemetryLogs()->latest('recorded_at')->get();
            $scan->metrics = $this->mergedScanMetrics($scan, $telemetry);
        }

        $this->selectedScan = $scan;
        $this->isDrawerOpen = $this->selectedScan !== null;
    }

    public function closeScan(): void
    {
        $this->isDrawerOpen = false;
        $this->selectedScan = null;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['flood_mapping', 'vegetation_changing', 'soil_moisture', 'region'], true)) {
            $this->dispatchMapUpdate();
        }
    }

    public function downloadReport(): StreamedResponse
    {
        $scans = $this->scans();
        $telemetry = TelemetryLog::query()
            ->whereIn('satellite_scan_id', $scans->pluck('id'))
            ->latest('recorded_at')
            ->get();
        $telemetryByScan = $telemetry->groupBy('satellite_scan_id');
        $scanCountsByRegion = $scans->groupBy('region_name')->map->count();
        $filename = 'elite-vision-interactive-map-report-'.now()->format('Y-m-d_H-i-s').'.csv';

        return response()->stream(function () use ($scans, $telemetryByScan, $scanCountsByRegion): void {
            $file = fopen('php://output', 'w');

            if ($file === false) {
                throw new \RuntimeException('The interactive map report could not be opened.');
            }

            fputcsv($file, [
                'Region',
                'Status',
                'Scan Count',
                'Last Updated',
                'Latitude',
                'Longitude',
                'Telemetry Records',
                'Average Signal (dB)',
                'Average Moisture Index',
            ]);

            foreach ($scans as $scan) {
                $scanTelemetry = $telemetryByScan->get($scan->id, collect());

                fputcsv($file, [
                    $scan->region_name,
                    $scan->status,
                    $scanCountsByRegion->get($scan->region_name, 0),
                    $scan->scanned_at->toDateTimeString(),
                    $scan->latitude,
                    $scan->longitude,
                    $scanTelemetry->count(),
                    $scanTelemetry->isNotEmpty()
                        ? round((float) $scanTelemetry->map(fn (TelemetryLog $log): float => $this->resolvedSignal($log))->avg(), 2)
                        : 'N/A',
                    $scanTelemetry->isNotEmpty() ? round((float) $scanTelemetry->avg('moisture_index'), 4) : 'N/A',
                ]);
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function render(): View
    {
        $scans = $this->scans();
        $telemetry = TelemetryLog::query()
            ->whereIn('satellite_scan_id', $scans->pluck('id'))
            ->latest('recorded_at')
            ->get();
        $telemetryByScan = $telemetry->groupBy('satellite_scan_id');

        $mapData = [
            'scans' => $scans->map(function (SatelliteScan $scan) use ($telemetryByScan): array {
                $metrics = $this->mergedScanMetrics($scan, $telemetryByScan->get($scan->id, new Collection));

                return [
                    'id' => $scan->id,
                    'region_name' => $scan->region_name,
                    'scan_type' => $scan->scan_type,
                    'status' => $scan->status,
                    'latitude' => $scan->latitude,
                    'longitude' => $scan->longitude,
                    'signal_intensity_db' => data_get($metrics, 'signal_intensity_db'),
                    'moisture_index' => data_get($metrics, 'moisture_index'),
                    'temperature_c' => data_get($metrics, 'temperature_c'),
                    'precipitation_mm' => data_get($metrics, 'precipitation_mm'),
                    'flood_area' => data_get($metrics, 'flood_area'),
                    'vegetation_loss' => data_get($metrics, 'vegetation_loss'),
                    'soil_saturation' => data_get($metrics, 'soil_saturation'),
                    'source' => data_get($metrics, 'source'),
                ];
            })->values()->all(),
            'radar' => $this->buildRadarData($telemetry),
        ];

        return view('livewire.interactive-map', [
            'mapData' => $mapData,
            'regions' => SatelliteScan::query()
                ->distinct()
                ->orderBy('region_name')
                ->pluck('region_name'),
            'visibleScanCount' => $scans->count(),
        ]);
    }

    private function scans(): Collection
    {
        return SatelliteScan::query()
            ->when($this->region !== 'all', fn ($query) => $query->where('region_name', $this->region))
            ->latest('scanned_at')
            ->get();
    }

    private function dispatchMapUpdate(): void
    {
        $scans = $this->scans();
        $telemetry = TelemetryLog::query()
            ->whereIn('satellite_scan_id', $scans->pluck('id'))
            ->get();

        $this->dispatch(
            'interactive-map-updated',
            floodMapping: $this->flood_mapping,
            vegetationChanging: $this->vegetation_changing,
            soilMoisture: $this->soil_moisture,
            region: $this->region,
            radar: $this->buildRadarData($telemetry),
        );
    }

    private function buildRadarData(Collection $telemetry): array
    {
        $dailyTelemetry = $telemetry
            ->groupBy(fn (TelemetryLog $log): string => $log->recorded_at->toDateString())
            ->sortKeys()
            ->take(-7);

        return [
            'labels' => $dailyTelemetry->keys()->map(
                fn (string $date): string => Carbon::parse($date)->format('M d'),
            )->values()->all(),
            'signal_intensity' => $dailyTelemetry->map(
                fn (Collection $dayLogs): float => round((float) $dayLogs
                    ->map(fn (TelemetryLog $log): float => $this->resolvedSignal($log))
                    ->avg(), 1),
            )->values()->all(),
            'moisture_index' => $dailyTelemetry->map(
                fn (Collection $dayLogs): float => round((float) $dayLogs->avg('moisture_index') * 100, 1),
            )->values()->all(),
        ];
    }

    private function mergedScanMetrics(SatelliteScan $scan, Collection $telemetry): array
    {
        $metrics = is_array($scan->metrics) ? $scan->metrics : [];
        $latest = $telemetry->first();

        foreach (['moisture_index', 'temperature_c', 'precipitation_mm'] as $key) {
            $value = $key === 'moisture_index'
                ? $latest?->moisture_index
                : data_get($latest?->metrics, $key);

            if ($value !== null) {
                $metrics[$key] = $value;
            }
        }

        if ($latest !== null) {
            $metrics['signal_intensity_db'] = $this->resolvedSignal($latest);
            $metrics['source'] = data_get($latest->metrics, 'source')
                ?? data_get($metrics, 'source');
        }

        if ($latest === null && (float) data_get($metrics, 'signal_intensity_db') === 88.5) {
            unset($metrics['signal_intensity_db']);
        }

        $derived = $this->deriveLayerMetrics($telemetry);

        foreach ($derived as $key => $value) {
            if (! array_key_exists($key, $metrics) || $metrics[$key] === null) {
                $metrics[$key] = $value;
            }
        }

        return $metrics;
    }

    /**
     * @return array{flood_area: float, vegetation_loss: float, soil_saturation: float}
     */
    private function deriveLayerMetrics(Collection $telemetry): array
    {
        $moistureValues = $telemetry->pluck('moisture_index')
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): float => (float) $value);
        $precipitationValues = $telemetry
            ->map(fn (TelemetryLog $log): mixed => data_get($log->metrics, 'precipitation_mm'))
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): float => (float) $value);
        $temperatureValues = $telemetry
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

    private function resolvedSignal(TelemetryLog $log): float
    {
        return (float) $log->signal_intensity !== 88.5
            ? (float) $log->signal_intensity
            : round(82.0 + (max(0.0, min(1.0, (float) $log->moisture_index)) * 9.5), 1);
    }
}
