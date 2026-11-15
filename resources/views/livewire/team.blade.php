<div class="space-y-6 max-w-7xl mx-auto">
    {{-- Header Banner --}}
    <div
        class="rounded-xl bg-(--bg-surface) border border-(--border-color) p-3 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex items-start sm:items-center gap-4">
            <img src="{{ asset('images/elite-vision.webp') }}" alt="Elite Vision" class="w-13 h-12 shrink-0">
            <div>
                <h2 class="text-sm font-semibold text-(--text-primary)">Project Team &amp; Engineering Roster
                </h2>
                <p class="text-[12px] text-(--text-secondary) mt-1">
                    Builders of the Elite Vision L-Band SAR &amp; NASA POWER Environmental Telemetry Platform.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            <div class="rounded-lg bg-(--bg-hover) border border-(--border-color) px-3.5 py-2 text-right">
                <div class="text-[11px] text-(--text-secondary)">Active Regions</div>
                <div class="text-base font-semibold text-(--text-primary)">{{ $monitoredRegions }}</div>
            </div>
            <div class="rounded-lg bg-(--bg-hover) border border-(--border-color) px-3.5 py-2 text-right">
                <div class="text-[11px] text-(--text-secondary)">Telemetry Logs</div>
                <div class="text-base font-semibold text-(--text-primary)">{{ number_format($telemetryRecords) }}</div>
            </div>
        </div>
    </div>

    {{-- Team Member Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach ($members as $member)
            <div
                class="rounded-xl bg-(--bg-surface) border border-(--border-color) p-6 flex flex-col justify-between gap-5 hover:border-indigo-500/40 transition-colors">
                <div class="space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3.5">
                            <div
                                class="w-13 h-13 rounded-xl overflow-hidden border border-indigo-500/30 text-indigo-400 font-semibold text-base flex items-center justify-center shrink-0">
                                <img src="{{ asset($member['initials']) }}" alt="">

                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-(--text-primary)">{{ $member['name'] }}</h3>
                                <p class="text-xs text-(--text-secondary) mt-0.5">{{ $member['role'] }}</p>
                            </div>
                        </div>

                        <span
                            class="text-[11px] font-medium px-2.5 py-1 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 shrink-0">
                            {{ $member['badge'] }}
                        </span>
                    </div>

                    <p class="text-xs leading-relaxed text-(--text-secondary)">
                        {{ $member['bio'] }}
                    </p>

                    @if (!empty($member['stack']))
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            @foreach ($member['stack'] as $tech)
                                <span
                                    class="text-[11px] px-2 py-0.5 rounded-md bg-(--bg-hover) text-(--text-secondary) border border-(--border-color)">
                                    {{ $tech }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="pt-4 border-t border-(--border-color) flex items-center gap-3">
                    @if (!empty($member['github']))
                        <a href="{{ $member['github'] }}" target="_blank" rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg bg-(--bg-hover) text-(--text-primary) hover:bg-indigo-500/15 hover:text-indigo-400 border border-(--border-color) transition-colors">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0 1 12 6.844a9.59 9.59 0 0 1 2.504.337c1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.02 10.02 0 0 0 22 12.017C22 6.484 17.522 2 12 2Z" />
                            </svg>
                            GitHub
                        </a>
                    @endif

                    @if (!empty($member['linkedin']))
                        <a href="{{ $member['linkedin'] }}" target="_blank" rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg bg-(--bg-hover) text-(--text-primary) hover:bg-indigo-500/15 hover:text-indigo-400 border border-(--border-color) transition-colors">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path
                                    d="M19 3A2 2 0 0 1 21 5V19A2 2 0 0 1 19 21H5A2 2 0 0 1 3 19V5A2 2 0 0 1 5 3H19M18.5 18.5V13.2A3.26 3.26 0 0 0 15.24 9.94C14.39 9.94 13.4 10.46 12.92 11.24V10.13H10.13V18.5H12.92V13.57C12.92 12.8 13.54 12.17 14.31 12.17A1.4 1.4 0 0 1 15.71 13.57V18.5H18.5M6.88 8.56A1.68 1.68 0 0 0 8.56 6.88C8.56 5.95 7.81 5.19 6.88 5.19A1.69 1.69 0 0 0 5.19 6.88C5.19 7.81 5.95 8.56 6.88 8.56M8.27 18.5V10.13H5.5V18.5H8.27Z" />
                            </svg>
                            LinkedIn
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
