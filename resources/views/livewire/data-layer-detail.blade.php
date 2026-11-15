<div
    data-layer-page
    data-layer-chart='@json($chartData)'
    data-layer-scans='@json($mapScans)'
    class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-[11px] text-(--text-secondary)">Environmental SAR data layer</p>
            <h2 class="text-lg font-semibold text-(--text-primary)">{{ $definition['title'] }}</h2>
            <p class="mt-1 max-w-2xl text-xs text-(--text-secondary)">{{ $definition['description'] }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <label for="layer-period" class="text-xs text-(--text-secondary)">Window</label>
            <select id="layer-period" wire:model.live="period" class="px-3 py-2 text-xs">
                <option value="7">7 days</option>
                <option value="30">30 days</option>
                <option value="90">90 days</option>
                <option value="365">1 year</option>
            </select>
            <label for="layer-region" class="sr-only">Region</label>
            <select id="layer-region" wire:model.live="region" class="px-3 py-2 text-xs">
                <option value="all">All regions</option>
                @foreach ($regions as $regionName)
                    <option value="{{ $regionName }}">{{ $regionName }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <div class="rounded-xl border border-(--border-color) bg-(--bg-surface) p-4">
            <p class="text-[10px] text-(--text-secondary)">{{ $definition['metric_label'] }}</p>
            <p class="mt-2 text-2xl font-semibold text-(--text-primary)">{{ number_format($metrics['primary'], 1) }} <span class="text-sm font-normal text-(--text-secondary)">{{ $definition['unit'] }}</span></p>
        </div>
        <div class="rounded-xl border border-(--border-color) bg-(--bg-surface) p-4">
            <p class="text-[10px] text-(--text-secondary)">Active alerts</p>
            <p class="mt-2 text-2xl font-semibold text-red-500">{{ number_format($metrics['alerts']) }}</p>
        </div>
        <div class="rounded-xl border border-(--border-color) bg-(--bg-surface) p-4">
            <p class="text-[10px] text-(--text-secondary)">Regions monitored</p>
            <p class="mt-2 text-2xl font-semibold text-(--text-primary)">{{ number_format($metrics['regions']) }}</p>
        </div>
        <div class="rounded-xl border border-(--border-color) bg-(--bg-surface) p-4">
            <p class="text-[10px] text-(--text-secondary)">Average signal</p>
            <p class="mt-2 text-2xl font-semibold text-(--text-primary)">{{ $metrics['signal'] !== null ? number_format($metrics['signal'], 1).' dB' : 'N/A' }}</p>
        </div>
    </div>

    @if ($definition['scan_type'] === 'soil_moisture' && is_array($nasaPower))
        <section class="rounded-xl border border-(--border-color) bg-(--bg-surface) p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-xs font-semibold text-(--text-primary)">NASA POWER environmental feed</h3>
                    <p class="text-[10px] text-(--text-secondary)">Latest daily observation for the selected soil-moisture data</p>
                </div>
                <span class="text-[10px] text-(--accent)">{{ $nasaPower['observation_date'] }}</span>
            </div>
            <div class="grid grid-cols-2 gap-3 text-[10px] sm:grid-cols-3">
                <div>
                    <p class="text-(--text-secondary)">Soil temperature</p>
                    <p class="mt-1 text-sm font-semibold text-(--text-primary)">{{ $nasaPower['soil_temperature_c'] !== null ? number_format($nasaPower['soil_temperature_c'], 1).' °C' : 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-(--text-secondary)">Precipitation</p>
                    <p class="mt-1 text-sm font-semibold text-(--text-primary)">{{ $nasaPower['precipitation_mm'] !== null ? number_format($nasaPower['precipitation_mm'], 2).' mm' : 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-(--text-secondary)">Moisture index</p>
                    <p class="mt-1 text-sm font-semibold text-(--text-primary)">{{ $nasaPower['moisture_index'] !== null ? number_format($nasaPower['moisture_index'], 3) : 'N/A' }}</p>
                </div>
            </div>
        </section>
    @endif

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(19rem,0.65fr)]">
        <section class="relative min-h-[420px] overflow-hidden rounded-xl border border-(--border-color) bg-(--bg-surface)">
            <div id="layer-map" class="map-canvas" data-layer-scans='@json($mapScans)'
                data-layer-accent="{{ $definition['accent'] }}" wire:ignore></div>
            <div
                x-data="{ selectedPin: null }"
                x-on:data-layer-scan-selected.window="selectedPin = $event.detail"
                x-show="selectedPin !== null"
                x-transition
                x-cloak
                class="absolute bottom-10 left-3 z-20 w-64 rounded-xl border border-(--border-color) bg-(--bg-surface)/95 p-3.5 shadow-xl backdrop-blur-md">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-xs font-semibold text-(--text-primary)" x-text="selectedPin?.region_name"></p>
                        <p class="mt-0.5 text-[10px] capitalize text-(--text-secondary)"
                            x-text="selectedPin?.scan_type?.replaceAll('_', ' ')"></p>
                    </div>
                    <button type="button" @click="selectedPin = null"
                        class="text-(--text-secondary) hover:text-(--text-primary)" aria-label="Close preview">✕</button>
                </div>
                <div class="mt-2.5 space-y-1.5 border-t border-(--border-color) pt-2 text-[10px] text-(--text-secondary)">
                    <div class="flex items-center justify-between gap-2">
                        <span>Temperature</span>
                        <span class="font-medium text-(--text-primary)"
                            x-text="selectedPin?.temperature_c != null ? `${Number(selectedPin.temperature_c).toFixed(1)} °C` : 'N/A'"></span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span>{{ $definition['metric_label'] }}</span>
                        <span class="font-medium text-(--text-primary)"
                            x-text="selectedPin?.metric_value != null ? `${Number(selectedPin.metric_value).toFixed(1)} ${selectedPin.metric_unit ?? ''}` : 'N/A'"></span>
                    </div>
                    <a :href="`/map?region=${encodeURIComponent(selectedPin?.region_name ?? '')}`"
                        class="mt-1 inline-block font-medium text-(--accent) hover:underline">
                        Inspect on Map →
                    </a>
                </div>
            </div>
            <div class="absolute bottom-10 left-3 right-3 z-10 flex items-center gap-3 rounded-xl border border-white/10 bg-black/70 p-3 backdrop-blur-md">
                <span class="size-2 rounded-full" style="background-color: {{ $definition['accent'] }}"></span>
                <span class="text-[10px] text-white">{{ number_format($metrics['acquisitions']) }} acquisitions in {{ $days }} days</span>
                <span class="ml-auto text-[10px] font-mono text-white/70">{{ $definition['metric_label'] }}</span>
            </div>
        </section>

        <aside class="flex flex-col gap-4">
            <section class="rounded-xl border border-(--border-color) bg-(--bg-surface) p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-xs font-semibold text-(--text-primary)">{{ $definition['metric_label'] }} trend</h3>
                    <span class="text-[10px] text-(--text-secondary)">{{ $days }} days</span>
                </div>
                <div class="relative h-44"><canvas id="layerTrendChart" wire:ignore></canvas></div>
            </section>
            <section class="rounded-xl border border-(--border-color) bg-(--bg-surface) p-4">
                <h3 class="mb-3 text-xs font-semibold text-(--text-primary)">Layer alerts</h3>
                <div class="space-y-2">
                    @forelse ($alerts as $alert)
                        <div class="flex items-center gap-3 rounded-lg p-2 hover:bg-(--bg-hover)" wire:key="layer-alert-{{ $alert->id }}">
                            <span class="size-2 shrink-0 rounded-full bg-red-500"></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[11px] text-(--text-primary)">{{ $alert->region_name }}</p>
                                <p class="text-[9px] text-(--text-secondary)">{{ $alert->scanned_at->diffForHumans() }}</p>
                            </div>
                            <span class="text-[10px] text-red-500">Alert</span>
                        </div>
                    @empty
                        <p class="text-[10px] text-(--text-secondary)">No alerts in this layer.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>

    <section class="rounded-xl border border-(--border-color) bg-(--bg-surface) p-4">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-(--text-primary)">Regional breakdown</h3>
                <p class="text-[10px] text-(--text-secondary)">Filtered to {{ $definition['title'] }} acquisitions</p>
            </div>
            <span class="text-[10px] text-(--text-secondary)">{{ $days }} day window</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Region</th><th>Acquisitions</th><th>Alerts</th><th>{{ $definition['metric_label'] }}</th><th>Signal</th></tr>
                </thead>
                <tbody>
                    @forelse ($regionalRows as $row)
                        <tr wire:key="layer-region-{{ $row['region'] }}">
                            <td>{{ $row['region'] }}</td>
                            <td>{{ number_format($row['acquisitions']) }}</td>
                            <td class="{{ $row['alerts'] > 0 ? 'text-red-500' : '' }}">{{ number_format($row['alerts']) }}</td>
                            <td>{{ number_format($row['metric'], 1) }} {{ $definition['unit'] }}</td>
                            <td>{{ $row['signal'] !== null ? number_format($row['signal'], 1).' dB' : 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-(--text-secondary)">No {{ strtolower($definition['title']) }} data found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
