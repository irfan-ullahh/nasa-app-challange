<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\SatelliteScan;
use App\Models\TelemetryLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]

class Dashboard extends Component
{
    private const CRITICAL_SIGNAL_INTENSITY = 50.0;

    private const CRITICAL_MOISTURE_INDEX = 0.85;

    #[Url]
    public bool $flood_mapping = true;

    #[Url]
    public bool $vegetation_changing = true;

    #[Url]
    public bool $soil_moisture = true;

    #[Url(as: 'sidebar')]
    public string $sidebarFilter = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['flood_mapping', 'vegetation_changing', 'soil_moisture'], true)) {
            $this->dispatchLayerVisibility();
        }

        if ($property === 'sidebarFilter') {
            $this->dispatch('sidebar-filter-updated', filter: $this->sidebarFilter);
        }
    }

    public function refreshTelemetry(): void
    {
        $this->dispatch('telemetry-updated', payload: $this->telemetryPayload($this->latestTelemetryLogs()));
    }

    public function render(): View
    {
        $scans = SatelliteScan::query()
            ->withCount('telemetryLogs')
            ->latest('scanned_at')
            ->get();
        $activeScans = $scans->where('status', 'active')->values();
        $healthyScans = $scans->filter(
            fn (SatelliteScan $scan): bool => in_array($scan->status, ['active', 'monitoring'], true)
                && data_get($scan->metrics, 'error') === null,
        );
        $latestByScan = $this->latestTelemetryByScan($scans);

        $dashboardMetrics = [
            'active_satellites' => $activeScans->count(),
            'global_alerts' => $scans->where('status', 'alert')->count(),
            'data_processed_tb' => $scans->sum(fn (SatelliteScan $scan): float => $this->dataProcessed($scan)),
            'system_uptime' => $scans->isEmpty()
                ? 0.0
                : round(($healthyScans->count() / $scans->count()) * 100, 1),
        ];

        $mapScans = $activeScans->map(function (SatelliteScan $scan) use ($latestByScan): array {
            $telemetry = $latestByScan->get($scan->id);

            return [
                'region_name' => $scan->region_name,
                'scan_type' => $scan->scan_type,
                'status' => $scan->status,
                'latitude' => $scan->latitude,
                'longitude' => $scan->longitude,
                'signal_intensity_db' => $this->signalForScan($scan, $telemetry),
                'moisture_index' => $this->telemetryValue($scan, $telemetry, 'moisture_index'),
                'temperature_c' => $this->telemetryValue($scan, $telemetry, 'temperature_c'),
                'precipitation_mm' => $this->telemetryValue($scan, $telemetry, 'precipitation_mm'),
            ];
        })->values()->all();
        $mapScans = is_array($mapScans) ? $mapScans : [];

        $alertScans = $scans->filter(fn (SatelliteScan $scan): bool => $scan->status === 'alert');
        $elevatedScans = $scans
            ->reject(fn (SatelliteScan $scan): bool => $scan->status === 'alert')
            ->filter(function (SatelliteScan $scan) use ($latestByScan): bool {
                $telemetry = $latestByScan->get($scan->id);

                return (float) $this->telemetryValue($scan, $telemetry, 'moisture_index') >= 0.65
                    || (float) $this->telemetryValue($scan, $telemetry, 'precipitation_mm') >= 5.0;
            });
        $activeSyncScans = $scans
            ->where('status', 'active')
            ->reject(fn (SatelliteScan $scan): bool => $alertScans->contains('id', $scan->id)
                || $elevatedScans->contains('id', $scan->id));

        $threatFeed = $alertScans
            ->concat($elevatedScans)
            ->concat($activeSyncScans)
            ->take(4)
            ->map(function (SatelliteScan $scan) use ($latestByScan): array {
                $telemetry = $latestByScan->get($scan->id);
                $isElevated = (float) $this->telemetryValue($scan, $telemetry, 'moisture_index') >= 0.65
                    || (float) $this->telemetryValue($scan, $telemetry, 'precipitation_mm') >= 5.0;

                return [
                    'tone' => $scan->status === 'alert' ? 'text-red-500' : ($isElevated ? 'text-amber-400' : 'text-emerald-400'),
                    'text' => $scan->status === 'alert'
                        ? sprintf('Alert: %s (%s)', $scan->region_name, str_replace('_', ' ', $scan->scan_type))
                        : ($isElevated
                            ? sprintf('Elevated telemetry: %s (%s)', $scan->region_name, str_replace('_', ' ', $scan->scan_type))
                            : sprintf('Active sync: %s (%s)', $scan->region_name, str_replace('_', ' ', $scan->scan_type))),
                ];
            })
            ->values()
            ->all();

        $telemetryLogs = $this->latestTelemetryLogs();
        $telemetryRows = $scans
            ->unique('region_name')
            ->take(6)
            ->map(function (SatelliteScan $scan) use ($latestByScan): array {
                $telemetry = $latestByScan->get($scan->id);
                $signal = $this->signalForScan($scan, $telemetry);
                $moisture = $this->telemetryValue($scan, $telemetry, 'moisture_index');
                $temperature = $this->telemetryValue($scan, $telemetry, 'temperature_c');

                return [
                    'label' => $scan->region_name,
                    'value' => sprintf('%.1f dB', $signal),
                    'detail' => sprintf(
                        'Moisture %s · Temp %s',
                        is_numeric($moisture) ? number_format((float) $moisture, 3) : 'No data',
                        is_numeric($temperature) ? number_format((float) $temperature, 1).' °C' : 'N/A',
                    ),
                    'tone' => $signal >= 70 ? 'text-[#10b981]' : 'text-orange-500',
                ];
            })
            ->all();
        $telemetryPayload = $this->telemetryPayload($telemetryLogs);

        return view('livewire.dashboard', compact(
            'dashboardMetrics',
            'mapScans',
            'telemetryPayload',
            'telemetryRows',
            'threatFeed',
        ));
    }

    private function latestTelemetryLogs(): EloquentCollection
    {
        return TelemetryLog::query()
            ->with('satelliteScan')
            ->latest('recorded_at')
            ->limit(12)
            ->get();
    }

    private function telemetryPayload(EloquentCollection $logs): array
    {
        $criticalLog = $logs->first(
            fn (TelemetryLog $log): bool => $this->resolvedSignal($log) < self::CRITICAL_SIGNAL_INTENSITY
                || $log->moisture_index > self::CRITICAL_MOISTURE_INDEX,
        );

        return array_merge($this->globalMetricsPayload(), [
            'critical' => $criticalLog !== null,
            'alert_message' => $criticalLog === null
                ? ''
                : sprintf(
                    'Critical telemetry threshold reported for %s.',
                    $criticalLog->satelliteScan->region_name,
                ),
        ]);
    }

    private function globalMetricsPayload(): array
    {
        $telemetry = TelemetryLog::query()
            ->where('recorded_at', '>=', now()->subDays(30))
            ->where(function ($query): void {
                $query->whereNotNull('metrics->temperature_c')
                    ->orWhereNotNull('metrics->precipitation_mm');
            })
            ->get();
        $dailyTelemetry = $telemetry
            ->groupBy(fn (TelemetryLog $log): string => $log->recorded_at->toDateString())
            ->sortKeys();

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
            'critical_threshold' => array_fill(0, $dailyTelemetry->count(), self::CRITICAL_SIGNAL_INTENSITY),
        ];
    }

    private function latestTelemetryByScan(EloquentCollection $scans): Collection
    {
        return TelemetryLog::query()
            ->whereIn('satellite_scan_id', $scans->pluck('id'))
            ->latest('recorded_at')
            ->get()
            ->groupBy('satellite_scan_id')
            ->map(fn (EloquentCollection $logs): TelemetryLog => $logs->first());
    }

    private function telemetryValue(
        SatelliteScan $scan,
        ?TelemetryLog $telemetry,
        string $key,
    ): mixed {
        return data_get($telemetry?->metrics, $key)
            ?? data_get($scan->metrics, $key);
    }

    private function signalForScan(SatelliteScan $scan, ?TelemetryLog $telemetry): float
    {
        if ($telemetry !== null) {
            return $this->resolvedSignal($telemetry);
        }

        $storedSignal = data_get($scan->metrics, 'signal_intensity_db');
        $moisture = (float) data_get($scan->metrics, 'moisture_index', 0);

        return $storedSignal !== null && (float) $storedSignal !== 88.5
            ? (float) $storedSignal
            : round(82.0 + (max(0.0, min(1.0, $moisture)) * 9.5), 1);
    }

    private function resolvedSignal(TelemetryLog $log): float
    {
        return (float) $log->signal_intensity !== 88.5
            ? (float) $log->signal_intensity
            : round(82.0 + (max(0.0, min(1.0, (float) $log->moisture_index)) * 9.5), 1);
    }

    private function dataProcessed(SatelliteScan $scan): float
    {
        return (float) (data_get($scan->metrics, 'data_processed_tb')
            ?: round($scan->telemetry_logs_count * 42.5, 1));
    }

    private function dispatchLayerVisibility(): void
    {
        $this->dispatch(
            'dashboard-layers-updated',
            floodMapping: $this->flood_mapping,
            vegetationChanging: $this->vegetation_changing,
            soilMoisture: $this->soil_moisture,
        );
    }
}
