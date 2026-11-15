<div class="space-y-4" wire:poll.15s="refreshFeed">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-[11px] text-(--text-secondary)">Near real-time orbital telemetry</p>
            <h2 class="text-lg font-semibold text-(--text-primary)">NISAR Satellite Feed</h2>
        </div>
        <div class="flex items-center gap-2">
            <label for="nisar-status" class="text-xs text-(--text-secondary)">Status</label>
            <select id="nisar-status" wire:model.live="statusFilter" class="px-3 py-2 text-xs">
                <option value="all">All passes</option>
                <option value="active">Active</option>
                <option value="monitoring">Monitoring</option>
                <option value="alert">Alerts</option>
            </select>
            <label for="nisar-window" class="sr-only">Time window</label>
            <select id="nisar-window" wire:model.live="window" class="px-3 py-2 text-xs">
                <option value="all">All available data</option>
                <option value="6h">6 hours</option>
                <option value="24h">24 hours</option>
                <option value="72h">72 hours</option>
                <option value="168h">168 hours</option>
            </select>
            <span class="rounded-full bg-(--accent-bg) px-2.5 py-1 text-[10px] font-medium text-(--accent)">Live</span>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <div class="rounded-xl border border-(--border-color) bg-(--bg-surface) p-4">
            <p class="text-[10px] text-(--text-secondary)">Active orbital passes</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight text-(--text-primary)">{{ number_format($metrics['active_passes']) }}</p>
        </div>
        <div class="rounded-xl border border-(--border-color) bg-(--bg-surface) p-4">
            <p class="text-[10px] text-(--text-secondary)">Tracked regions</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight text-(--text-primary)">{{ number_format($metrics['tracked_regions']) }}</p>
        </div>
        <div class="rounded-xl border border-(--border-color) bg-(--bg-surface) p-4">
            <p class="text-[10px] text-(--text-secondary)">Latest signal</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight text-(--text-primary)">{{ $metrics['latest_signal'] !== null ? number_format($metrics['latest_signal'], 1).' dB' : 'N/A' }}</p>
        </div>
        <div class="rounded-xl border border-(--border-color) bg-(--bg-surface) p-4">
            <p class="text-[10px] text-(--text-secondary)">Telemetry records</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight text-(--text-primary)">{{ number_format($metrics['telemetry_records']) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(20rem,1fr)] h-[400px]">
        <section class="rounded-xl border border-(--border-color) bg-(--bg-surface) p-4 h-full overflow-auto">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-(--text-primary)">Live orbital passes</h3>
                    <p class="text-[10px] text-(--text-secondary)">Latest NISAR coverage windows from satellite scans</p>
                </div>
                <span class="text-[10px] text-(--text-secondary)">Updated automatically</span>
            </div>
            <div class="table-wrap">
                <table class="table-fixed">
                    <thead>
                        <tr>
                            <th class="px-2 py-2.5 text-[11px]">Satellite</th>
                            <th class="px-2 py-2.5 text-[11px]">Region</th>
                            <th class="px-2 py-2.5 text-[11px]">Pass time</th>
                            <th class="max-w-[110px] truncate px-2 py-2.5 text-[11px]">Coverage</th>
                            <th class="whitespace-nowrap px-2 py-2.5 text-[11px]">Status</th>
                            <th class="px-2 py-2.5 text-[11px]">Signal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orbitalPasses as $pass)
                            <tr wire:key="nisar-pass-{{ $pass['satellite'] }}">
                                <td class="px-2 py-2.5 text-[11px] font-medium">{{ $pass['satellite'] }}</td>
                                <td class="px-2 py-2.5 text-[11px]">{{ $pass['region'] }}</td>
                                <td class="px-2 py-2.5 text-[11px]">{{ $pass['pass_time'] }}</td>
                                <td class="max-w-[110px] truncate px-2 py-2.5 text-[11px]">{{ $pass['coverage'] }}</td>
                                <td class="px-2 py-2.5 text-[11px]">
                                    <span class="{{ $pass['status'] === 'alert' ? 'text-red-500' : ($pass['status'] === 'active' ? 'text-[#10b981]' : 'text-orange-500') }}">
                                        {{ ucfirst($pass['status']) }}
                                    </span>
                                </td>
                                <td class="px-2 py-2.5 text-[11px]">{{ $pass['signal'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-(--text-secondary)">No orbital passes match this filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border border-(--border-color) bg-(--bg-surface) p-4  h-full overflow-auto">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-(--text-primary)">Downlink status</h3>
                <span class="text-[10px] text-(--text-secondary)">Ground stations</span>
            </div>
            <div class="space-y-3">
                @forelse ($downlinkStatuses as $downlink)
                    <div class="rounded-lg border border-(--border-color) p-3" wire:key="downlink-{{ $downlink['station'] }}">
                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate text-xs text-(--text-primary)">{{ $downlink['station'] }}</span>
                            <span class="{{ $downlink['status'] === 'Degraded' ? 'text-red-500' : ($downlink['status'] === 'Standby' ? 'text-orange-500' : 'text-[#10b981]') }} text-[10px] font-medium">{{ $downlink['status'] }}</span>
                        </div>
                        <div class="mt-2 flex justify-between text-[10px] text-(--text-secondary)">
                            <span>{{ $downlink['last_contact'] }}</span>
                            <span>{{ $downlink['signal'] }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-(--text-secondary)">No downlink stations available.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="rounded-xl border border-(--border-color) bg-(--bg-surface) p-4">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-(--text-primary)">Radar image parameters</h3>
                <p class="text-[10px] text-(--text-secondary)">Acquisition settings and latest telemetry signal</p>
            </div>
            <span class="text-[10px] text-(--text-secondary)">NISAR SAR payload</span>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($radarParameters as $parameter)
                <article class="rounded-lg border border-(--border-color) p-3" wire:key="radar-parameter-{{ $parameter['region'] }}">
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div>
                            <h4 class="text-xs font-medium text-(--text-primary)">{{ $parameter['region'] }}</h4>
                            <p class="text-[10px] text-(--text-secondary)">{{ $parameter['mode'] }}</p>
                        </div>
                        <span class="rounded-full bg-(--accent-bg) px-2 py-1 text-[10px] text-(--accent)">{{ $parameter['signal'] }}</span>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-[10px]">
                        <div><dt class="text-(--text-secondary)">Polarization</dt><dd class="text-(--text-primary)">{{ $parameter['polarization'] }}</dd></div>
                        <div><dt class="text-(--text-secondary)">Resolution</dt><dd class="text-(--text-primary)">{{ $parameter['resolution'] }}</dd></div>
                        <div><dt class="text-(--text-secondary)">Incidence angle</dt><dd class="text-(--text-primary)">{{ $parameter['incidence_angle'] }}</dd></div>
                        <div><dt class="text-(--text-secondary)">Swath width</dt><dd class="text-(--text-primary)">{{ $parameter['swath_width'] }}</dd></div>
                    </dl>
                </article>
            @empty
                <p class="text-xs text-(--text-secondary)">No radar acquisitions available.</p>
            @endforelse
        </div>
    </section>

    <div class="rounded-xl border border-(--border-color) bg-(--bg-surface) p-4" x-data="{ open: false }">
        <button type="button" class="flex w-full items-center justify-between text-left" @click="open = !open">
            <span class="text-xs font-semibold text-(--text-primary)">Telemetry payload details</span>
            <svg class="text-(--text-secondary) transition-transform" :class="{ 'rotate-180': open }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
        </button>
        <div x-show="open" x-cloak x-transition class="pt-3">
            <div class="grid grid-cols-1 gap-2 text-[10px] text-(--text-secondary) sm:grid-cols-3">
                <span>Samples: {{ count($telemetryPayload['labels']) }}</span>
                <span>Signal points: {{ count($telemetryPayload['signal_intensity']) }}</span>
                <span>Moisture points: {{ count($telemetryPayload['moisture_index']) }}</span>
            </div>
        </div>
    </div>
</div>
