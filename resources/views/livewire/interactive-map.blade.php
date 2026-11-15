<div x-data x-on:open-scan-drawer.window="$wire.selectScanPoint($event.detail.scanId)"
    data-interactive-map='@json($mapData)' x-init="$dispatch('interactive-map-updated', { floodMapping: {{ $flood_mapping ? 'true' : 'false' }}, vegetationChanging: {{ $vegetation_changing ? 'true' : 'false' }}, soilMoisture: {{ $soil_moisture ? 'true' : 'false' }}, region: '{{ $region }}' })">

    <div class="page-fill h-[76vh]">

        <div class="fill-layout lg:flex-row">

            <div class="map-shell min-h-[420px] lg:min-h-0">

                <div id="interactive-map" class="map-canvas" wire:ignore></div>

                <div
                    class="absolute top-3 left-3 z-10 rounded-xl border border-(--border-color) bg-(--bg-surface)/90 px-3 py-2 backdrop-blur-sm">

                    <p class="text-[10px] text-(--text-secondary)">Live satellite coverage</p>

                    <p class="text-sm font-semibold text-(--text-primary)">{{ $visibleScanCount }} scan points</p>

                </div>

            </div>

            <aside
                class="w-full lg:w-80 lg:shrink-0 lg:min-h-0 lg:overflow-y-auto flex flex-col gap-6 justify-between bg-(--bg-surface) p-4 rounded-xl border border-(--border-color)">

                <div>

                    <div class="flex items-center justify-between gap-3 mb-4">

                        <h2 class="text-(--text-primary) font-semibold">SAR Data Analytics</h2>

                        <span class="text-[10px] text-(--text-secondary)">Live</span>

                    </div>

                    <label class="block text-[11px] text-(--text-secondary) mb-2" for="map-region">Region filter</label>

                    <select id="map-region" wire:model.live="region" class="w-full px-3 py-2 text-xs">

                        <option value="all">All regions</option>

                        @foreach ($regions as $regionName)
                            <option value="{{ $regionName }}">{{ $regionName }}</option>
                        @endforeach

                    </select>

                </div>



                <div class="space-y-4">

                    <div class="flex justify-between items-center">

                        <span class="text-(--text-primary) text-sm">Flood Mapping</span>

                        <label for="flood_mapping" class="relative inline-block w-8.5 h-4.5 cursor-pointer">

                            <input type="checkbox" id="flood_mapping" class="peer sr-only"
                                wire:model.live="flood_mapping">

                            <span
                                class="absolute inset-0 bg-(--bg-hover) rounded-full transition-colors duration-200 ease-in-out peer-checked:bg-(--accent)"></span>

                            <span
                                class="absolute top-1/2 left-0.5 -translate-y-1/2 size-3.5 bg-(--accent-hover) rounded-full shadow-sm transition-transform duration-200 ease-in-out peer-checked:bg-(--bg-surface) peer-checked:translate-x-4"></span>

                        </label>

                    </div>

                    <div class="flex justify-between items-center">

                        <span class="text-(--text-primary) text-sm">Vegetation Changing</span>

                        <label for="vegetation_changing" class="relative inline-block w-8.5 h-4.5 cursor-pointer">

                            <input type="checkbox" id="vegetation_changing" class="peer sr-only"
                                wire:model.live="vegetation_changing">

                            <span
                                class="absolute inset-0 bg-(--bg-hover) rounded-full transition-colors duration-200 ease-in-out peer-checked:bg-(--accent)"></span>

                            <span
                                class="absolute top-1/2 left-0.5 -translate-y-1/2 size-3.5 bg-(--accent-hover) rounded-full shadow-sm transition-transform duration-200 ease-in-out peer-checked:bg-(--bg-surface) peer-checked:translate-x-4"></span>

                        </label>

                    </div>

                    <div class="flex justify-between items-center">

                        <span class="text-(--text-primary) text-sm">Soil Moisture</span>

                        <label for="soil_moisture" class="relative inline-block w-8.5 h-4.5 cursor-pointer">

                            <input type="checkbox" id="soil_moisture" class="peer sr-only"
                                wire:model.live="soil_moisture">

                            <span
                                class="absolute inset-0 bg-(--bg-hover) rounded-full transition-colors duration-200 ease-in-out peer-checked:bg-(--accent)"></span>

                            <span
                                class="absolute top-1/2 left-0.5 -translate-y-1/2 size-3.5 bg-(--accent-hover) rounded-full shadow-sm transition-transform duration-200 ease-in-out peer-checked:bg-(--bg-surface) peer-checked:translate-x-4"></span>

                        </label>

                    </div>

                </div>



                <div>

                    <p class="text-[13px] text-(--text-secondary) mb-2">Radar Signal Intensity</p>

                    <div class="relative h-40 w-full"><canvas id="radarChart" wire:ignore></canvas></div>

                </div>



                <div class="rounded-xl border border-(--border-color) bg-(--bg-hover)/40 p-3">

                    <p class="text-[11px] font-semibold text-(--text-primary) mb-2">Region status</p>

                    <div class="space-y-2">

                        @foreach ($mapData['scans'] as $scan)
                            <div class="flex items-center gap-2 text-[10px]">

                                <span
                                    class="size-2 rounded-full {{ $scan['status'] === 'alert' ? 'bg-red-500' : ($scan['status'] === 'active' ? 'bg-[#10b981]' : 'bg-orange-500') }}"></span>

                                <span class="text-(--text-secondary) flex-1 truncate">{{ $scan['region_name'] }}</span>

                                <span class="text-(--text-primary) capitalize">{{ $scan['status'] }}</span>

                            </div>
                        @endforeach

                    </div>

                </div>



                <button type="button" wire:click="downloadReport" wire:loading.attr="disabled"
                    wire:target="downloadReport"
                    class="w-full cursor-pointer flex items-center justify-center gap-2 rounded-lg bg-[#4d49ff] py-2.5 text-sm font-medium text-white transition hover:bg-[#3f3bd9] disabled:cursor-not-allowed disabled:opacity-60">

                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="1 1 22 22"
                        aria-hidden="true">

                        <g fill="none">

                            <path fill="currentColor"
                                d="M2.69 14.2H21.3c0 2.59 0 3.89-.58 4.85a4 4 0 0 1-1.34 1.34c-.96.58-2.26.58-4.86.58H9.47c-2.6 0-3.9 0-4.85-.58a4 4 0 0 1-1.34-1.34c-.58-.96-.58-2.26-.58-4.86"
                                opacity="0.2" />

                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M2.69 14.5c0 2.3 0 3.46.46 4.34a4 4 0 0 0 1.67 1.67c.88.46 2.03.46 4.34.46h5.68c2.3 0 3.46 0 4.34-.46a4 4 0 0 0 1.67-1.68c.46-.87.46-2.02.46-4.33" />

                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                stroke-width="1.5"
                                d="m17.37 10.6-2.55 2.57c-1.34 1.33-2 2-2.83 2s-1.5-.67-2.83-2L6.59 10.6M11.98 2v12.82" />

                        </g>

                    </svg>

                    <span wire:loading.remove wire:target="downloadReport" class="flex items-center gap-2">

                        Download Report

                    </span>

                    <span wire:loading wire:target="downloadReport">Generating Report...</span>

                </button>

            </aside>

        </div>

    </div>

    <div x-data="{
        drawerOpen: @entangle('isDrawerOpen'),
        closeDrawer() {
            if (!this.drawerOpen) return;
            this.drawerOpen = false;
            setTimeout(() => $wire.closeScan(), 260);
        }
    }" x-show="drawerOpen" x-on:keydown.escape.window="closeDrawer()"
        class="fixed inset-0 z-40 flex justify-end" style="display: none;" role="dialog" aria-modal="true"
        aria-label="Scan point details">

        <button type="button" x-show="drawerOpen"
            x-transition:enter="transition-opacity ease-out duration-300"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-250"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            @click="closeDrawer()" class="absolute inset-0 cursor-default bg-black/40 backdrop-blur-xs"
            aria-label="Close scan details"></button>

        <aside x-show="drawerOpen" x-transition:enter="transform transition ease-out duration-300"
            x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in duration-250"
            x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
            class="relative z-10 flex h-full w-full max-w-md flex-col gap-5 overflow-y-auto border-l border-(--border-color) bg-(--bg-surface) p-5 shadow-2xl">

            @if ($selectedScan)
                @php($selectedMetrics = $selectedScan->metrics ?? [])

                <div class="flex items-start justify-between gap-4">

                    <div>

                        <p class="text-[10px] uppercase tracking-[0.16em] text-(--text-secondary)">Scan point details
                        </p>

                        <h2 class="mt-1 text-lg font-semibold text-(--text-primary)">{{ $selectedScan->region_name }}
                        </h2>

                    </div>

                    <button type="button" @click="closeDrawer()"
                        class="rounded-lg p-2 text-(--text-secondary) transition hover:bg-(--bg-hover) hover:text-(--text-primary)"
                        aria-label="Close scan details">

                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" aria-hidden="true">

                            <path d="M6 6l12 12M18 6 6 18" />

                        </svg>

                    </button>

                </div>



                <div class="grid grid-cols-2 gap-3">

                    <div class="rounded-xl border border-(--border-color) bg-(--bg-hover)/40 p-3">

                        <p class="text-[10px] text-(--text-secondary)">Coordinates</p>

                        <p class="mt-1 text-xs font-medium text-(--text-primary)">

                            {{ number_format((float) $selectedScan->latitude, 4) }},

                            {{ number_format((float) $selectedScan->longitude, 4) }}

                        </p>

                    </div>

                    <div class="rounded-xl border border-(--border-color) bg-(--bg-hover)/40 p-3">

                        <p class="text-[10px] text-(--text-secondary)">Scan type</p>

                        <p class="mt-1 text-xs font-medium capitalize text-(--text-primary)">

                            {{ str_replace('_', ' ', $selectedScan->scan_type) }}

                        </p>

                    </div>

                    <div class="rounded-xl border border-(--border-color) bg-(--bg-hover)/40 p-3">

                        <p class="text-[10px] text-(--text-secondary)">Status</p>

                        <p class="mt-1 text-xs font-medium capitalize text-[#10b981]">{{ $selectedScan->status }}</p>

                    </div>

                    <div class="rounded-xl border border-(--border-color) bg-(--bg-hover)/40 p-3">

                        <p class="text-[10px] text-(--text-secondary)">Signal intensity</p>

                        <p class="mt-1 text-xs font-medium text-(--text-primary)">

                            {{ data_get($selectedMetrics, 'signal_intensity_db') !== null ? number_format((float) data_get($selectedMetrics, 'signal_intensity_db'), 1) . ' dB' : 'N/A' }}

                        </p>

                    </div>

                </div>



                <div class="rounded-xl border border-(--border-color) p-4">

                    <div class="mb-4 flex items-center justify-between gap-3">

                        <div>

                            <h3 class="text-sm font-semibold text-(--text-primary)">NASA POWER telemetry</h3>

                            <p class="mt-1 text-[10px] text-(--text-secondary)">Latest environmental observation</p>

                        </div>

                        <span
                            class="rounded-full bg-(--accent-bg) px-2.5 py-1 text-[10px] font-medium text-(--accent)">

                            Live feed

                        </span>

                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">

                        <div>

                            <p class="text-[10px] text-(--text-secondary)">Soil moisture</p>

                            <p class="mt-1 text-sm font-semibold text-(--text-primary)">

                                {{ data_get($selectedMetrics, 'moisture_index') !== null ? number_format((float) data_get($selectedMetrics, 'moisture_index'), 3) : 'N/A' }}

                            </p>

                        </div>

                        <div>

                            <p class="text-[10px] text-(--text-secondary)">Temperature</p>

                            <p class="mt-1 text-sm font-semibold text-(--text-primary)">

                                {{ data_get($selectedMetrics, 'temperature_c') !== null ? number_format((float) data_get($selectedMetrics, 'temperature_c'), 1) . ' °C' : 'N/A' }}

                            </p>

                        </div>

                        <div>

                            <p class="text-[10px] text-(--text-secondary)">Precipitation</p>

                            <p class="mt-1 text-sm font-semibold text-(--text-primary)">

                                {{ data_get($selectedMetrics, 'precipitation_mm') !== null ? number_format((float) data_get($selectedMetrics, 'precipitation_mm'), 1) . ' mm/day' : 'N/A' }}

                            </p>

                        </div>

                    </div>

                    <div class="mt-4 flex items-center justify-between gap-3 border-t border-(--border-color) pt-3">

                        <span class="text-[10px] text-(--text-secondary)">Data source</span>

                        <span class="text-right text-[10px] font-medium text-(--text-primary)">

                            {{ data_get($selectedMetrics, 'source', 'No data') }}

                        </span>

                    </div>

                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <p class="text-[10px] text-(--text-secondary)">Flood area</p>
                        <p class="mt-1 text-sm font-semibold text-(--text-primary)">
                            {{ data_get($selectedMetrics, 'flood_area') !== null ? number_format((float) data_get($selectedMetrics, 'flood_area'), 1).' km²' : 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] text-(--text-secondary)">Vegetation loss</p>
                        <p class="mt-1 text-sm font-semibold text-(--text-primary)">
                            {{ data_get($selectedMetrics, 'vegetation_loss') !== null ? number_format((float) data_get($selectedMetrics, 'vegetation_loss'), 1).' %' : 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] text-(--text-secondary)">Soil saturation</p>
                        <p class="mt-1 text-sm font-semibold text-(--text-primary)">
                            {{ data_get($selectedMetrics, 'soil_saturation') !== null ? number_format((float) data_get($selectedMetrics, 'soil_saturation'), 1).' %' : 'N/A' }}
                        </p>
                    </div>
                </div>

            @endif
        </aside>

        </div>

</div>
