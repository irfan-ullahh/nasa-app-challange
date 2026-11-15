<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-[11px] text-(--text-secondary)">NASA POWER environmental ingestion</p>
            <h2 class="text-lg font-semibold text-(--text-primary)">Add Scan Region</h2>
        </div>
        <span class="rounded-full bg-(--accent-bg) px-2.5 py-1 text-[10px] font-medium text-(--accent)">30-day ETL</span>
    </div>

    @if (session('upload-status'))
        <div class="rounded-xl border border-[#10b981]/30 bg-[#10b981]/10 px-4 py-3 text-xs text-[#10b981]"
            role="status">
            {{ session('upload-status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-12">
        <form wire:submit="saveRegion" x-data
            class="rounded-xl border border-(--border-color) bg-(--bg-surface) p-4 sm:p-6 xl:col-span-5">
            <div class="mb-5">
                <h3 class="text-sm font-semibold text-(--text-primary)">Region metadata</h3>
                <p class="mt-1 text-[11px] text-(--text-secondary)">
                    Enter coordinates to fetch and store the latest 30 days of NASA POWER telemetry.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="space-y-1 sm:col-span-2">
                    <span class="text-[10px] text-(--text-secondary)">Region name</span>
                    <input type="text" wire:model.blur="region_name" placeholder="Amazon Basin"
                        class="w-full rounded-lg border border-(--border-color) p-2.5 text-[13px] hover:border-(--accent) focus:border-(--accent)">
                    @error('region_name')
                        <span class="text-xs font-medium text-red-500">{{ $message }}</span>
                    @enderror
                </label>

                <label class="space-y-1">
                    <span class="text-[10px] text-(--text-secondary)">Latitude</span>
                    <input type="number" step="any" min="-90" max="90" wire:model.blur="latitude"
                        placeholder="e.g. 34.0151"
                        class="w-full rounded-lg border border-(--border-color) p-2.5 font-mono text-[13px] tabular-nums placeholder:text-(--text-secondary) hover:border-(--accent) focus:border-(--accent)">
                    @error('latitude')
                        <span class="text-xs font-medium text-red-500">{{ $message }}</span>
                    @enderror
                </label>

                <label class="space-y-1">
                    <span class="text-[10px] text-(--text-secondary)">Longitude</span>
                    <input type="number" step="any" min="-180" max="180" wire:model.blur="longitude"
                        placeholder="e.g. 71.5249"
                        class="w-full rounded-lg border border-(--border-color) p-2.5 font-mono text-[13px] tabular-nums placeholder:text-(--text-secondary) hover:border-(--accent) focus:border-(--accent)">
                    @error('longitude')
                        <span class="text-xs font-medium text-red-500">{{ $message }}</span>
                    @enderror
                </label>
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                @foreach ([
                    ['name' => 'Peshawar Basin', 'latitude' => 34.0151, 'longitude' => 71.5249],
                    ['name' => 'Amazon Basin', 'latitude' => -3.4653, 'longitude' => -62.2159],
                    ['name' => 'Sahara Fringe', 'latitude' => 25.2841, 'longitude' => 5.5228],
                    ['name' => 'Mekong Delta', 'latitude' => 10.0452, 'longitude' => 105.7469],
                ] as $preset)
                    <button type="button"
                        @click="$wire.region_name = '{{ $preset['name'] }}'; $wire.latitude = {{ $preset['latitude'] }}; $wire.longitude = {{ $preset['longitude'] }}"
                        class="rounded-full border border-(--border-color) bg-(--bg-hover)/60 px-2.5 py-1 text-[10px] text-(--text-secondary) transition hover:border-(--accent) hover:text-(--text-primary)">
                        {{ $preset['name'] }}
                    </button>
                @endforeach
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="saveRegion"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#4d49ff] px-5 py-2.5 text-xs font-medium text-white transition hover:bg-[#3f3bd9] disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
                    <svg wire:loading.remove wire:target="saveRegion" class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M4 13.5 20 4l-3.5 16-5.2-5.2L7 18l1.5-5.5L4 13.5Z" stroke-linecap="round"
                            stroke-linejoin="round" />
                        <path d="m11.3 14.8 4.9-5.1" stroke-linecap="round" />
                    </svg>
                    <svg wire:loading wire:target="saveRegion" class="h-4 w-4 animate-spin" viewBox="0 0 24 24"
                        fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" class="opacity-25" stroke="currentColor"
                            stroke-width="3" />
                        <path d="M21 12a9 9 0 0 1-9 9" stroke="currentColor" stroke-width="3"
                            stroke-linecap="round" />
                    </svg>
                    <span wire:loading.remove wire:target="saveRegion">Fetch NASA Telemetry &amp; Save Region</span>
                    <span wire:loading wire:target="saveRegion">Fetching 30-day telemetry...</span>
                </button>
            </div>
        </form>

        <section class="rounded-xl border border-(--border-color) bg-(--bg-surface) p-4 xl:col-span-7">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-(--text-primary)">Recent regions ingested</h3>
                <p class="mt-1 text-[10px] text-(--text-secondary)">Latest local NASA telemetry snapshots</p>
            </div>
            <span class="text-[10px] text-(--text-secondary)">{{ $recentScans->count() }} records</span>
        </div>

        <div class="table-wrap">
            <table class="text-xs">
                <thead>
                    <tr>
                        <th class="px-3 py-2.5">Region</th>
                        <th class="px-3 py-2.5">Coordinates</th>
                        <th class="px-3 py-2.5">Status</th>
                        <th class="px-3 py-2.5">Created</th>
                        <th class="px-3 py-2.5">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentScans as $scan)
                        <tr wire:key="scan-{{ $scan->id }}">
                            <td class="px-3 py-2.5">
                                <div class="font-medium text-(--text-primary)">{{ $scan->region_name }}</div>
                                <div class="mt-1 text-[10px] text-(--text-secondary)">
                                    {{ $scan->telemetry_logs_count }} NASA daily logs
                                </div>
                                <div class="text-[10px] capitalize text-(--text-secondary)">
                                    Auto-detected: {{ str_replace('_', ' ', $scan->scan_type) }}
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5 font-mono text-xs tabular-nums text-(--text-secondary)">
                                {{ number_format((float) $scan->latitude, 4) }}, {{ number_format((float) $scan->longitude, 4) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5">
                                @php
                                    $statusClasses = match ($scan->status) {
                                        'alert' => 'bg-red-500/10 text-red-400 border border-red-500/20',
                                        'monitoring' => 'bg-amber-500/10 text-amber-400 border border-amber-500/20',
                                        'active' => 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20',
                                        default => 'bg-(--bg-hover) text-(--text-secondary) border border-(--border-color)',
                                    };
                                @endphp
                                <span class="{{ $statusClasses }} inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] capitalize">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                    {{ $scan->status }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-(--text-secondary)">{{ $scan->scanned_at->diffForHumans(['short' => true]) }}</td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('map.index', ['region' => $scan->region_name]) }}"
                                        title="View on map"
                                        class="rounded-lg p-1.5 text-(--text-secondary) transition hover:bg-(--accent-bg) hover:text-(--accent)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.8" aria-hidden="true">
                                            <path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z" />
                                            <circle cx="12" cy="10" r="2.2" />
                                        </svg>
                                    </a>
                                    <button type="button" wire:click="deleteRegion({{ $scan->id }})"
                                        wire:confirm="Remove this region and its telemetry logs?"
                                        title="Delete region"
                                        class="rounded-lg p-1.5 text-(--text-secondary) transition hover:bg-red-500/10 hover:text-red-400">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.8" aria-hidden="true">
                                            <path d="M4 7h16m-10 4v6m4-6v6M9 7V4h6v3m-9 0 1 13h10l1-13" stroke-linecap="round"
                                                stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-2.5 text-center text-(--text-secondary)">No regions have been ingested yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
