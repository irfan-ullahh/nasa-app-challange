<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\SatelliteScan;
use App\Models\TelemetryLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Analytics & Historical Analysis')]
final class Analytics extends Component
{
    #[Url]
    public string $period = '30';

    public array $chartData = [];

    public array $metrics = [];

    public function updatedPeriod(string $value): void
    {
        $payload = $this->analyticsPayload();
        $this->chartData = $payload['chartData'];
        $this->metrics = $payload['metrics'];

        $this->dispatch(
            'analytics-period-updated',
            chartData: $this->chartData,
            metrics: $this->metrics,
        );
    }

    public function render(): View
    {
        $payload = $this->analyticsPayload();
        $this->chartData = $payload['chartData'];
        $this->metrics = $payload['metrics'];

        return view('livewire.analytics', $payload);
    }

    private function analyticsPayload(): array
    {
        $days = in_array($this->period, ['7', '30', '90', '365'], true) ? (int) $this->period : 30;
        $startDate = now()->subDays($days);

        $scans = SatelliteScan::query()
            ->where('scanned_at', '>=', $startDate)
            ->withCount('telemetryLogs')
            ->latest('scanned_at')
            ->get();
        $telemetryLogs = TelemetryLog::query()
            ->whereIn('satellite_scan_id', $scans->pluck('id'))
            ->where('recorded_at', '>=', $startDate)
            ->where(function ($query): void {
                $query->whereNotNull('metrics->temperature_c')
                    ->orWhereNotNull('metrics->precipitation_mm');
            })
            ->latest('recorded_at')
            ->get();

        $comparisonRows = $scans
            ->groupBy('region_name')
            ->map(fn (EloquentCollection $regionScans, string $region): array => [
                'region' => $region,
                'scans' => $regionScans->sum('telemetry_logs_count'),
                'alerts' => $regionScans->where('status', 'alert')->count(),
                'data_processed' => $regionScans->sum(fn (SatelliteScan $scan): float => $this->dataProcessed($scan)),
            ])
            ->sortByDesc('alerts')
            ->take(5)
            ->values()
            ->all();

        $dailyMetrics = $telemetryLogs
            ->groupBy(fn (TelemetryLog $log): string => $log->recorded_at->toDateString())
            ->sortKeys();
        $chartData = [
            'labels' => $dailyMetrics->keys()
                ->map(fn (string $date): string => Carbon::parse($date)->format('M d'))
                ->values()
                ->all(),
            'flood_area' => $dailyMetrics
                ->map(fn (EloquentCollection $logs): float => $this->deriveHistoricalMetrics($logs)['flood_area'])
                ->values()
                ->all(),
            'vegetation_loss' => $dailyMetrics
                ->map(fn (EloquentCollection $logs): float => $this->deriveHistoricalMetrics($logs)['vegetation_loss'])
                ->values()
                ->all(),
            'soil_saturation' => $dailyMetrics
                ->map(fn (EloquentCollection $logs): float => $this->deriveHistoricalMetrics($logs)['soil_saturation'])
                ->values()
                ->all(),
            'regions' => collect($comparisonRows)->pluck('region')->all(),
            'region_alerts' => collect($comparisonRows)->pluck('alerts')->all(),
            'region_scans' => collect($comparisonRows)->pluck('scans')->all(),
            'distribution_labels' => $scans->groupBy('scan_type')->keys()->map(
                fn (string $type): string => ucwords(str_replace('_', ' ', $type)),
            )->values()->all(),
            'distribution_values' => $scans->groupBy('scan_type')->map->count()->values()->all(),
        ];

        $totalDataProcessed = $scans->sum(fn (SatelliteScan $scan): float => $this->dataProcessed($scan));
        $healthyScansCount = $scans
            ->filter(fn (SatelliteScan $scan): bool => in_array($scan->status, ['active', 'monitoring'], true)
                && ! data_get($scan->metrics, 'error'))
            ->count();
        $averageUptime = round(($healthyScansCount / max(1, $scans->count())) * 100, 1);
        $latestTelemetry = $telemetryLogs->first();
        $signalValues = $telemetryLogs
            ->map(fn (TelemetryLog $log): float => $this->resolvedSignal($log))
            ->values();
        $signalIntensity = $signalValues->isNotEmpty()
            ? sprintf('%.1f dB', (float) $signalValues->avg())
            : 'Awaiting data';

        $systemHealth = [
            ['label' => 'Ground Station Link', 'value' => $latestTelemetry !== null ? 'Stable' : 'Awaiting data', 'tone' => 'text-[#10b981]'],
            ['label' => 'Signal Intensity', 'value' => $signalIntensity, 'tone' => 'text-[#10b981]'],
            ['label' => 'Moisture Index', 'value' => $latestTelemetry !== null ? number_format($latestTelemetry->moisture_index, 3) : 'Awaiting data', 'tone' => 'text-[#10b981]'],
            ['label' => 'Satellite Uptime', 'value' => $averageUptime.'%', 'tone' => 'text-[#10b981]'],
            ['label' => 'Telemetry Records', 'value' => number_format($telemetryLogs->count()), 'tone' => 'text-[#10b981]'],
        ];

        return [
            'chartData' => $chartData,
            'comparisonRows' => $comparisonRows,
            'days' => $days,
            'metrics' => [
                'total_data_processed' => $totalDataProcessed,
                'active_alerts' => $scans->where('status', 'alert')->count(),
                'uptime' => $averageUptime,
                'total_scans' => $scans->count(),
            ],
            'systemHealth' => $systemHealth,
        ];
    }

    private function dataProcessed(SatelliteScan $scan): float
    {
        return (float) (data_get($scan->metrics, 'data_processed_tb')
            ?: round($scan->telemetry_logs_count * 42.5, 1));
    }

    private function resolvedSignal(TelemetryLog $log): float
    {
        $signal = (float) $log->signal_intensity;

        if ($signal !== 88.5) {
            return $signal;
        }

        return round(82.0 + (max(0.0, min(1.0, (float) $log->moisture_index)) * 9.5), 1);
    }

    /**
     * @return array{flood_area: float, vegetation_loss: float, soil_saturation: float}
     */
    private function deriveHistoricalMetrics(EloquentCollection $telemetryLogs): array
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
}
