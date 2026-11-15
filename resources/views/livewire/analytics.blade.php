<div data-analytics='@json($chartData)' data-analytics-metrics='@json($metrics)'>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
        <div>
            <p class="text-[11px] text-(--text-secondary)">Historical satellite analysis</p>
            <h2 class="text-lg font-semibold text-(--text-primary)">Analytics &amp; Historical Analysis</h2>
        </div>
        <label class="flex items-center gap-2 text-xs text-(--text-secondary)">
            <span>Analysis period</span>
            <select wire:model.live="period" class="px-3 py-2 text-xs">
                <option value="7">Last 7 days</option>
                <option value="30">Last 30 days</option>
                <option value="90">Last 90 days</option>
                <option value="365">Last year</option>
            </select>
        </label>
    </div>

    <div class="split-layout">
        <div class="split-main flex flex-col gap-4">
            <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
                <div
                    class="bg-(--bg-surface) p-4 rounded-xl border border-(--border-color) flex flex-col justify-between relative overflow-hidden">
                    <div class="flex justify-between items-start mb-2">
                        <p class="text-[10px] text-(--text-secondary)">Total Data Processed (TB)</p>
                        <span class="text-[10px] text-(--text-secondary)">{{ $days }}d</span>
                    </div>
                    <div class="flex items-end justify-between">
                        <p class="text-(--text-primary) font-semibold text-2xl tracking-tight">
                            {{ number_format($metrics['total_data_processed'], 1) }}</p>
                        <div class="relative w-16 h-8"><canvas id="sparkline1" wire:ignore></canvas></div>
                    </div>
                </div>
                <div
                    class="bg-(--bg-surface) p-4 rounded-xl border border-(--border-color) flex flex-col justify-between relative overflow-hidden">
                    <div class="flex justify-between items-start mb-2">
                        <p class="text-[10px] text-(--text-secondary)">Active Alerts</p>
                    </div>
                    <div class="flex items-end justify-between">
                        <p class="text-red-500 font-semibold text-xl tracking-tight">
                            {{ number_format($metrics['active_alerts']) }} critical</p>
                        <div
                            class="w-8 h-8 rounded-full bg-red-500/10 flex items-center justify-center text-red-500 relative">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" />
                                <path d="M12 8v4M12 16h.01" />
                            </svg>
                            <div class="absolute inset-0 rounded-full border border-red-500/30 animate-ping"></div>
                        </div>
                    </div>
                </div>
                <div
                    class="bg-(--bg-surface) p-4 rounded-xl border border-(--border-color) flex flex-col justify-between relative overflow-hidden">
                    <div class="flex justify-between items-start mb-2">
                        <p class="text-[10px] text-(--text-secondary)">Satellite Uptime (%)</p>
                    </div>
                    <div class="flex items-end justify-between">
                        <p class="text-(--text-primary) font-semibold text-2xl tracking-tight">{{ $metrics['uptime'] }}%
                        </p>
                        <div class="relative w-16 h-8"><canvas id="sparkline2" wire:ignore></canvas></div>
                    </div>
                </div>
                <div
                    class="bg-(--bg-surface) p-4 rounded-xl border border-(--border-color) flex flex-col justify-between relative overflow-hidden">
                    <div class="flex justify-between items-start mb-2">
                        <p class="text-[10px] text-(--text-secondary)">Total Scans In Period</p>
                    </div>
                    <div class="flex items-end justify-between">
                        <p class="text-(--text-primary) font-semibold text-2xl tracking-tight">
                            {{ number_format($metrics['total_scans']) }}</p>
                        <div class="relative w-16 h-8"><canvas id="sparkline3" wire:ignore></canvas></div>
                    </div>
                </div>
            </div>

            <div
                class="bg-(--bg-surface) p-4 rounded-xl border border-(--border-color) flex-1 flex flex-col relative min-h-[300px]">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2 mb-2">
                    <h3 class="text-(--text-primary) text-sm font-medium">Historical Metrics</h3>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-[10px]">
                        <div class="flex items-center gap-1">
                            <div class="w-2 h-2 rounded-full bg-[#06b6d4]"></div> Flood Area
                        </div>
                        <div class="flex items-center gap-1">
                            <div class="w-2 h-2 rounded-full bg-[#10b981]"></div> Vegetation Loss
                        </div>
                        <div class="flex items-center gap-1">
                            <div class="w-2 h-2 rounded-full bg-[#8b5cf6]"></div> Soil Saturation
                        </div>
                    </div>
                </div>
                <div class="flex-1 relative w-full min-h-[220px]"><canvas id="multiLineChart" wire:ignore></canvas>
                </div>
            </div>

            <div class="bg-(--bg-surface) rounded-xl border border-(--border-color) overflow-hidden">
                <div class="flex items-center justify-between gap-3 p-4 border-b border-(--border-color)">
                    <div>
                        <h3 class="text-(--text-primary) text-sm font-semibold">Regional comparison</h3>
                        <p class="text-[10px] text-(--text-secondary)">Highest alert volume in the selected period</p>
                    </div>
                    <span class="text-[10px] text-(--text-secondary)">{{ $days }} day window</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Region</th>
                                <th>Scans</th>
                                <th>Alerts</th>
                                <th>Data Processed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($comparisonRows as $row)
                                <tr>
                                    <td>{{ $row['region'] }}</td>
                                    <td>{{ number_format($row['scans']) }}</td>
                                    <td class="{{ $row['alerts'] > 0 ? 'text-red-500' : '' }}">
                                        {{ number_format($row['alerts']) }}</td>
                                    <td>{{ number_format($row['data_processed'], 1) }} TB</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-(--text-secondary)">No scans found for
                                        this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="bg-(--bg-surface) p-4 rounded-xl border border-(--border-color)" x-data="{ open: true }">
                <button type="button" class="w-full flex justify-between items-center text-left" @click="open = !open">
                    <span class="text-(--text-primary) text-xs font-semibold">Analysis notes</span>
                    <svg class="text-(--text-secondary) transition-transform" :class="{ 'rotate-180': open }"
                        width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" aria-hidden="true">
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </button>
                <div x-show="open" x-cloak x-transition class="pt-3 text-[10px] text-(--text-secondary)">
                    Metrics are aggregated from satellite scans and telemetry records captured during the selected
                    period.
                </div>
            </div>
        </div>

        <div class="w-full lg:w-72 flex flex-col gap-4 lg:shrink-0">
            <div class="bg-(--bg-surface) p-4 rounded-xl border border-(--border-color)">
                <h3 class="text-(--text-primary) text-xs font-semibold mb-3">Live System Health</h3>
                <div class="space-y-2">
                    @foreach ($systemHealth as $health)
                        <div class="flex justify-between items-center text-[10px]">
                            <span class="text-(--text-secondary)">{{ $health['label'] }}</span>
                            <span class="{{ $health['tone'] }} font-medium">{{ $health['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-(--bg-surface) p-4 rounded-xl border border-(--border-color) flex-1 flex flex-col">
                <h3 class="text-(--text-primary) text-xs font-semibold mb-3">Top Regions by Alert Volume</h3>
                <div class="space-y-3 flex-1">
                    @forelse ($comparisonRows as $row)
                        @php($width = $metrics['active_alerts'] > 0 ? min(100, max(8, ($row['alerts'] / $metrics['active_alerts']) * 100)) : 8)
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 rounded-full bg-[#06b6d4] shrink-0"></div>
                            <span
                                class="text-[10px] text-(--text-secondary) flex-1 truncate">{{ $row['region'] }}</span>
                            <div class="w-16 h-1.5 bg-(--border-color) rounded-full overflow-hidden">
                                <div class="h-full bg-[#ef4444]" style="width: {{ $width }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-[10px] text-(--text-secondary)">No regional alert data available.</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-(--bg-surface) p-4 rounded-xl border border-(--border-color) flex flex-col relative">
                <h3 class="text-(--text-primary) text-xs font-medium mb-2">Data Ingestion by Region</h3>
                <div class="relative w-full h-48"><canvas id="regionBarChart" wire:ignore></canvas></div>
            </div>
            <div class="bg-(--bg-surface) p-4 rounded-xl border border-(--border-color) flex flex-col relative">
                <h3 class="text-(--text-primary) text-xs font-medium mb-2">Data Type Distribution</h3>
                <div class="relative w-full h-48 flex justify-center"><canvas id="distributionChart"
                        wire:ignore></canvas></div>
            </div>


        </div>
    </div>
</div>
