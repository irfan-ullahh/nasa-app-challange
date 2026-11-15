<div
    wire:poll.5s="refreshTelemetry"
    data-dashboard-scans='@json($mapScans)'
    data-dashboard-metrics='@json($dashboardMetrics)'
    data-dashboard-telemetry='@json($telemetryPayload)'
    x-init="$dispatch('dashboard-layers-updated', { floodMapping: {{ $flood_mapping ? 'true' : 'false' }}, vegetationChanging: {{ $vegetation_changing ? 'true' : 'false' }}, soilMoisture: {{ $soil_moisture ? 'true' : 'false' }} })">
    <div
        x-data="{ visible: {{ $telemetryPayload['critical'] ? 'true' : 'false' }}, message: @js($telemetryPayload['alert_message']) }"
        x-on:telemetry-updated.window="visible = $event.detail.payload.critical; message = $event.detail.payload.alert_message"
        x-show="visible"
        x-transition
        x-cloak
        class="mb-4 flex items-center gap-3 rounded-xl border border-(--error) bg-(--error-bg) px-4 py-3 text-sm text-(--error)"
        role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
            <path d="M12 9v4" />
            <path d="M12 17h.01" />
        </svg>
        <span x-text="message"></span>
    </div>


    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
        <label class="relative flex items-center min-w-0 sm:w-72">
            <span class="sr-only">Filter sidebar navigation</span>
            <svg class="absolute left-3 w-4 h-4 text-(--text-secondary)" xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <circle cx="11" cy="11" r="7" />
                <path d="m20 20-4-4" />
            </svg>
            <input type="search" value="{{ $sidebarFilter }}" wire:model.live="sidebarFilter"
                x-on:input="$dispatch('sidebar-filter-updated', { filter: $event.target.value })"
                x-init="$dispatch('sidebar-filter-updated', { filter: $el.value })"
                placeholder="Filter navigation"
                class="w-full bg-(--bg-surface) border border-(--border-color) rounded-xl py-2 pl-9 pr-3 text-xs text-(--text-primary) placeholder:text-(--text-secondary) focus:border-(--accent) outline-none">
        </label>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[10px] text-(--text-secondary)">
            <span class="font-semibold text-(--text-primary)">Map layers</span>
            <label class="flex items-center gap-1.5 cursor-pointer">
                <input id="dashboard-toggle-flood" type="checkbox" wire:model.live="flood_mapping" class="peer"
                    x-on:change="$dispatch('dashboard-layers-updated', { floodMapping: $event.target.checked, vegetationChanging: document.getElementById('dashboard-toggle-vegetation').checked, soilMoisture: document.getElementById('dashboard-toggle-soil').checked })">
                <span>Flood Mapping</span>
            </label>
            <label class="flex items-center gap-1.5 cursor-pointer">
                <input id="dashboard-toggle-vegetation" type="checkbox" wire:model.live="vegetation_changing" class="peer"
                    x-on:change="$dispatch('dashboard-layers-updated', { floodMapping: document.getElementById('dashboard-toggle-flood').checked, vegetationChanging: $event.target.checked, soilMoisture: document.getElementById('dashboard-toggle-soil').checked })">
                <span>Vegetation Change</span>
            </label>
            <label class="flex items-center gap-1.5 cursor-pointer">
                <input id="dashboard-toggle-soil" type="checkbox" wire:model.live="soil_moisture" class="peer"
                    x-on:change="$dispatch('dashboard-layers-updated', { floodMapping: document.getElementById('dashboard-toggle-flood').checked, vegetationChanging: document.getElementById('dashboard-toggle-vegetation').checked, soilMoisture: $event.target.checked })">
                <span>Soil Moisture</span>
            </label>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div class="bg-(--bg-surface) border border-(--border-color) rounded-xl p-4 flex flex-col justify-between relative overflow-hidden">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-[11px] font-medium text-(--text-secondary)">Active Satellites</p>
                    <h3 class="text-2xl font-bold text-(--text-primary) mt-1">{{ $dashboardMetrics['active_satellites'] }}</h3>
                </div>
                <div class="relative w-16 h-8"><canvas id="spark1" wire:ignore></canvas></div>
            </div>
        </div>
        <div class="bg-(--bg-surface) border border-(--border-color) rounded-xl p-4 flex flex-col justify-between relative overflow-hidden">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-[11px] font-medium text-(--text-secondary)">Global Alerts</p>
                    <h3 class="text-2xl font-bold text-(--text-primary) mt-1">{{ number_format($dashboardMetrics['global_alerts']) }}</h3>
                </div>
                <div class="w-20 h-8 flex justify-end items-center gap-2">
                    <div class="text-red-500 mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>
                        </svg>
                    </div>
                    <div class="relative w-12 h-8"><canvas id="spark2" wire:ignore></canvas></div>
                </div>
            </div>
        </div>
        <div class="bg-(--bg-surface) border border-(--border-color) rounded-xl p-4 flex flex-col justify-between relative overflow-hidden">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-[11px] font-medium text-(--text-secondary)">Data Processed (TB)</p>
                    <h3 class="text-2xl font-bold text-(--text-primary) mt-1">{{ number_format($dashboardMetrics['data_processed_tb']) }}</h3>
                </div>
                <div class="relative w-16 h-8"><canvas id="spark3" wire:ignore></canvas></div>
            </div>
        </div>
        <div class="bg-(--bg-surface) border border-(--border-color) rounded-xl p-4 flex flex-col justify-between relative overflow-hidden">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-[11px] font-medium text-(--text-secondary)">System Uptime</p>
                    <h3 class="text-2xl font-bold text-(--text-primary) mt-1">{{ $dashboardMetrics['system_uptime'] }}%</h3>
                </div>
                <div class="w-16 h-8 flex items-center"><div class="w-full h-0.5 bg-[#06b6d4] shadow-[0_0_8px_#06b6d4]"></div></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4 lg:h-[380px]">
        <div id="dashboard-map"
            data-dashboard-scans='@json($mapScans)'
            data-dashboard-metrics='@json($dashboardMetrics)'
            class="bg-(--bg-surface) border border-(--border-color) rounded-xl flex flex-col overflow-hidden relative isolate h-full min-h-[280px]">
            <div class="p-3 relative z-10 inline w-fit">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-semibold text-(--text-primary)">Global Coverage</h3>
                    <span class="rounded-full bg-(--accent-bg) px-2 py-0.5 text-[10px] font-medium text-(--accent)">
                        {{ $dashboardMetrics['active_satellites'] }} active
                    </span>
                </div>
            </div>
            <div id="dashboard-map-canvas" class="map-canvas absolute inset-0 w-full h-full pt-12" wire:ignore></div>
            <div
                x-data="{ selectedPin: null }"
                x-on:dashboard-scan-selected.window="selectedPin = $event.detail"
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
                <div class="mt-2.5 flex items-center justify-between border-t border-(--border-color) pt-2 text-[10px] text-(--text-secondary)">
                    <span
                        x-text="selectedPin?.temperature_c != null ? `${Number(selectedPin.temperature_c).toFixed(1)} °C` : 'Live feed'"></span>
                    <a :href="`/map?region=${encodeURIComponent(selectedPin?.region_name ?? '')}`"
                        class="font-medium text-(--accent) hover:underline">
                        Inspect on Map →
                    </a>
                </div>
            </div>
        </div>
        <div class="bg-(--bg-surface) border border-(--border-color) rounded-xl p-4 flex flex-col relative min-h-[300px]">
            <div class="flex justify-between items-center mb-2">
                <h3 class="text-sm font-semibold text-(--text-primary)">Global Metrics</h3>
                <span class="text-[10px] text-(--text-secondary)">Last 30 days</span>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-[10px] mb-2">
                <div class="flex items-center gap-1"><div class="w-2 h-2 rounded-full bg-[#06b6d4]"></div> Signal Intensity</div>
                <div class="flex items-center gap-1"><div class="w-2 h-2 rounded-full bg-[#10b981]"></div> Moisture Index</div>
                <div class="flex items-center gap-1"><div class="w-2 h-2 rounded-full bg-[#8b5cf6]"></div> Critical Threshold</div>
            </div>
            <div class="flex-1 relative w-full min-h-[200px]"><canvas id="globalMetricsChart" wire:ignore></canvas></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:h-[250px]">
        <div class="bg-(--bg-surface) border border-(--border-color) rounded-xl p-4 flex flex-col relative overflow-hidden min-h-[250px]">
            <h3 class="text-sm font-semibold text-(--text-primary) mb-3">Live Threat Feed</h3>
            <div class="flex-1 overflow-y-auto pr-2 space-y-3">
                @foreach ($threatFeed as $threat)
                    <div class="flex items-start gap-3">
                        <div class="{{ $threat['tone'] }} mt-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>
                            </svg>
                        </div>
                        <p class="text-[11px] text-(--text-primary) leading-tight">{{ $threat['text'] }}</p>
                    </div>
                @endforeach
            </div>
            <div class="absolute right-1 top-12 bottom-6 w-0.5 bg-(--border-color) rounded-full">
                <div class="absolute top-0 left-0 w-full h-1/2 bg-linear-to-b from-[#06b6d4] to-transparent rounded-full shadow-[0_0_8px_#06b6d4]"></div>
            </div>
        </div>

        <div class="bg-(--bg-surface) border border-(--border-color) rounded-xl p-4 flex flex-col relative overflow-hidden min-h-[250px]">
            <h3 class="text-sm font-semibold text-(--text-primary) mb-3">System Telemetry</h3>
            <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                @foreach ($telemetryRows as $telemetry)
                    <div class="flex items-start gap-2 text-[10px]">
                        <div class="mt-0.5 w-3.5 h-3.5 rounded-full bg-[#10b981]/20 flex items-center justify-center text-[#10b981] shrink-0">
                            <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        </div>
                        <div>
                            <p class="text-(--text-primary) font-medium">{{ $telemetry['label'] }}: <span class="{{ $telemetry['tone'] }}">{{ $telemetry['value'] }}</span></p>
                            <p class="text-[9px] text-(--text-secondary)">{{ $telemetry['detail'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="absolute right-1 top-12 bottom-6 w-0.5 bg-(--border-color) rounded-full">
                <div class="absolute top-0 left-0 w-full h-1/2 bg-linear-to-b from-white to-transparent rounded-full shadow-[0_0_8px_#fff]"></div>
            </div>
        </div>
    </div>
</div>
