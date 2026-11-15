<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\SatelliteScan;
use App\Models\TelemetryLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('NISAR Satellite Feed')]
final class NisarFeed extends Component
{
    #[Url]
    public string $statusFilter = 'all';

    #[Url]
    public string $window = 'all';

    public function refreshFeed(): void
    {
        $this->dispatch('nisar-feed-refreshed');
    }

    public function render(): View
    {
        $scans = $this->scans();
        $telemetryLogs = TelemetryLog::query()
            ->whereIn('satellite_scan_id', $scans->pluck('id'))
            ->latest('recorded_at')
            ->get();

        $signalByScanId = $this->signalByScanId($scans, $telemetryLogs);
        $latestTelemetry = $telemetryLogs->first();

        return view('livewire.nisar-feed', [
            'orbitalPasses' => $this->orbitalPasses($scans, $signalByScanId),
            'downlinkStatuses' => $this->downlinkStatuses($scans, $telemetryLogs, $signalByScanId),
            'radarParameters' => $this->radarParameters($scans, $signalByScanId),
            'telemetryPayload' => $this->telemetryPayload($telemetryLogs),
            'metrics' => [
                'active_passes' => $scans->where('status', 'active')->count(),
                'tracked_regions' => $scans->pluck('region_name')->unique()->count(),
                'latest_signal' => $latestTelemetry !== null
                    ? ($signalByScanId[$latestTelemetry->satellite_scan_id] ?? null)
                    : null,
                'telemetry_records' => $telemetryLogs->count(),
            ],
        ]);
    }

    private function scans(): Collection
    {
        $hours = match ($this->window) {
            '6h', '6 hours' => 6,
            '72h', '72 hours' => 72,
            '168h', '168 hours' => 168,
            default => 24,
        };
        $status = match (strtolower(trim($this->statusFilter))) {
            'active', 'monitoring', 'alert' => strtolower(trim($this->statusFilter)),
            default => 'all',
        };

        $scans = SatelliteScan::query()
            ->when(
                strtolower(trim($this->window)) !== 'all',
                fn ($query) => $query->where('scanned_at', '>=', now()->subHours($hours)),
            )
            ->when(
                $status !== 'all',
                fn ($query) => $query->where('status', $status),
            )
            ->latest('scanned_at')
            ->get();

        if ($scans->isNotEmpty()) {
            return $scans;
        }

        return SatelliteScan::query()
            ->where('scanned_at', '>=', now()->subDays(365))
            ->when(
                $status !== 'all',
                fn ($query) => $query->where('status', $status),
            )
            ->latest('scanned_at')
            ->get();
    }

    /**
     * @return array<int, float>
     */
    private function signalByScanId(Collection $scans, Collection $telemetryLogs): array
    {
        return $scans->mapWithKeys(
            fn (SatelliteScan $scan): array => [
                $scan->id => $this->resolvedSignalIntensity($scan, $telemetryLogs),
            ],
        )->all();
    }

    private function orbitalPasses(Collection $scans, array $signalByScanId): array
    {
        return $scans->map(function (SatelliteScan $scan) use ($signalByScanId): array {
            return [
                'satellite' => sprintf('NISAR-%02d', $scan->id),
                'region' => $scan->region_name,
                'pass_time' => $scan->scanned_at->format('M d, H:i'),
                'coverage' => ucwords(str_replace('_', ' ', $scan->scan_type)),
                'status' => $scan->status,
                'signal' => sprintf('%.1f dB', $signalByScanId[$scan->id]),
            ];
        })->values()->all();
    }

    private function downlinkStatuses(Collection $scans, Collection $telemetryLogs, array $signalByScanId): array
    {
        return $scans->map(function (SatelliteScan $scan) use ($telemetryLogs, $signalByScanId): array {
            $telemetry = $telemetryLogs->firstWhere('satellite_scan_id', $scan->id);
            $status = match ($scan->status) {
                'alert' => 'Degraded',
                'monitoring' => 'Standby',
                default => 'Connected',
            };

            return [
                'station' => $scan->region_name.' Ground Station',
                'status' => $status,
                'last_contact' => $telemetry?->recorded_at?->diffForHumans() ?? 'Awaiting data',
                'signal' => sprintf('%.1f dB', $signalByScanId[$scan->id]),
            ];
        })->values()->all();
    }

    private function radarParameters(Collection $scans, array $signalByScanId): array
    {
        return $scans->map(function (SatelliteScan $scan) use ($signalByScanId): array {
            $metrics = $scan->metrics;
            $resolution = (float) (data_get($metrics, 'resolution_m')
                ?? $this->defaultResolution($scan));

            return [
                'region' => $scan->region_name,
                'mode' => strtoupper(str_replace('_', ' ', $scan->scan_type)),
                'polarization' => match ($scan->scan_type) {
                    'vegetation_changing' => 'VV / VH',
                    'soil_moisture' => 'HH',
                    default => 'HH / HV',
                },
                'resolution' => sprintf('%.1f m', $resolution),
                'incidence_angle' => sprintf(
                    '%.1f°',
                    (float) (data_get($metrics, 'incidence_angle')
                        ?? $this->defaultIncidenceAngle($scan)),
                ),
                'swath_width' => sprintf(
                    '%.0f km',
                    (float) (data_get($metrics, 'swath_width_km')
                        ?? $this->defaultSwathWidth($resolution)),
                ),
                'signal' => sprintf('%.1f dB', $signalByScanId[$scan->id]),
            ];
        })->values()->all();
    }

    private function defaultResolution(SatelliteScan $scan): float
    {
        return match ($scan->scan_type) {
            'flood_mapping' => $scan->status === 'alert' ? 3.0 : 10.0,
            'soil_moisture' => 6.0,
            default => 10.0,
        };
    }

    private function defaultIncidenceAngle(SatelliteScan $scan): float
    {
        $typeOffset = match ($scan->scan_type) {
            'soil_moisture' => 2.0,
            'vegetation_changing' => 4.0,
            default => 0.0,
        };

        return round(28.0 + $typeOffset + (abs((float) $scan->latitude) % 14), 1);
    }

    private function defaultSwathWidth(float $resolution): float
    {
        return match (true) {
            $resolution <= 3.0 => 120.0,
            $resolution <= 6.0 => 180.0,
            default => 240.0,
        };
    }

    private function resolvedSignalIntensity(SatelliteScan $scan, Collection $telemetryLogs): float
    {
        $metrics = $scan->metrics;
        $telemetry = $telemetryLogs
            ->where('satellite_scan_id', $scan->id)
            ->first(fn (TelemetryLog $log): bool => $log->signal_intensity !== null
                || $log->moisture_index !== null);
        $moistureIndex = (float) (data_get($metrics, 'moisture_index')
            ?? $telemetry?->moisture_index
            ?? 0.0);

        $storedSignal = data_get($metrics, 'signal_intensity_db');
        $telemetrySignal = $telemetry?->signal_intensity;

        if ($storedSignal !== null && (float) $storedSignal !== 88.5) {
            return (float) $storedSignal;
        }

        if ($telemetrySignal !== null && (float) $telemetrySignal !== 88.5) {
            return (float) $telemetrySignal;
        }

        return round(82.0 + (max(0.0, min(1.0, $moistureIndex)) * 9.5), 1);
    }

    private function telemetryPayload(Collection $telemetryLogs): array
    {
        $logs = $telemetryLogs->sortBy('recorded_at')->values();

        return [
            'labels' => $logs->map(fn (TelemetryLog $log): string => $log->recorded_at->format('H:i'))->all(),
            'signal_intensity' => $logs->map(fn (TelemetryLog $log): float => $log->signal_intensity)->all(),
            'moisture_index' => $logs->map(fn (TelemetryLog $log): float => round($log->moisture_index * 100, 2))->all(),
        ];
    }
}
