@props([
    'title' => 'Elite Vision',
])

@php
    $navigation = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => '<path d="M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-16v5h6V4h-6Z" fill="currentColor"/>'],
        ['label' => 'Interactive Map', 'route' => 'map.index', 'icon' => '<path d="M12 3.5a4 4 0 0 0-4 4c0 3 4 7 4 7s4-4 4-7a4 4 0 0 0-4-4Zm0 5.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3ZM3 6.5l4.5-1.1M16.5 5.4 21 6.5v11l-4.5-1.1L12 17.5l-4.5-1.1L3 17.5v-11l4.5-1.1M7.5 5.4v11M16.5 5.4v11" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'],
        [
            'label' => 'Data Layers',
            'children' => [
                ['label' => 'Flood Mapping', 'route' => 'layers.show', 'parameters' => ['layer' => 'flood-mapping']],
                ['label' => 'Vegetation Change', 'route' => 'layers.show', 'parameters' => ['layer' => 'vegetation-change']],
                ['label' => 'Soil Moisture', 'route' => 'layers.show', 'parameters' => ['layer' => 'soil-moisture']],
            ],
            'icon' => '<path d="m12 3 8 4.5-8 4.5-8-4.5L12 3Zm-8 8 8 4.5 8-4.5M4 15l8 4.5 8-4.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>',
        ],
        ['label' => 'NISAR Feed', 'route' => 'nisar.index', 'icon' => '<path d="M7 6.5a5 5 0 0 1 5-5h1.5a5 5 0 0 1 5 5v5a5 5 0 0 1-5 5H8a4 4 0 0 0-4 4M8 21h5v-5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>'],
        ['label' => 'Analytics', 'route' => 'analytics.index', 'icon' => '<path d="m4 18 5-6 3 3 7-9M4 21h16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>'],
        ['label' => 'Add Scan Region', 'route' => 'upload.index', 'icon' => '<path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Zm0-8.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Zm6-6.5v5m-2.5-2.5h5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>'],
    ];

    $routeUrl = static fn (string $route, array $parameters = []): string => \Illuminate\Support\Facades\Route::has($route) ? route($route, $parameters) : '#';
    $isActive = static fn (string $route): bool => request()->routeIs($route);
    $isChildActive = static function (array $child): bool {
        if (! request()->routeIs($child['route'])) {
            return false;
        }

        return collect($child['parameters'] ?? [])->every(
            fn (mixed $value, string $key): bool => (string) request()->route($key) === (string) $value,
        );
    };
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    <meta name="color-scheme" content="dark light">
    <link rel="icon" type="image/webp" href="{{ asset('images/elite-vision.webp') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/elite-vision.webp') }}">
    <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.css">
    <title>{{ $title }} - Elite Vision</title>

    <script>
        (function () {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = stored || (prefersDark ? 'dark' : 'dark');
            document.documentElement.classList.toggle('dark', theme === 'dark');
            document.documentElement.dataset.theme = theme;
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body {{ $attributes->merge([]) }}>
    <div id="app" class="flex">
        <div id="nav-backdrop" aria-hidden="true"></div>

        <aside id="navigation" aria-label="Primary" x-data="{ filter: '{{ strtolower((string) request()->query('sidebar', '')) }}' }"
            @sidebar-filter-updated.window="filter = ($event.detail.filter || '').toLowerCase().trim()"
            class="fixed inset-y-0 left-0 z-40 flex flex-col p-3 bg-(--bg-surface) border-r border-(--border-color) overflow-hidden">
            <div class="flex justify-between items-center mb-10 shrink-0">
                <a href="{{ $routeUrl('dashboard') }}" wire:navigate class="flex items-center gap-3 logo"
                    aria-label="Elite Vision - Dashboard">
                    <img class="shrink-0 m-1" src="{{ asset('images/elite-vision.webp') }}" width="35" height="35"
                        alt="Elite Vision">
                    <span class="text-(--text-secondary) font-medium text-2xl sidebar-text">Elite Vision</span>
                </a>
                <button id="hideNavigation" type="button" class="md:hidden text-(--text-secondary) p-1"
                    aria-label="Close navigation">
                    <svg xmlns="http://www.w3.org/2000/svg" width="25" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                            stroke-width="1.5" d="M19.5 12h-15m0 0 5.625-6M4.5 12l5.625 6" />
                    </svg>
                </button>
            </div>

            <ul id="sidebar-navigation"
                class="flex-1 min-h-0 ps-4 relative space-y-2 overflow-y-auto no-scroll-bar text-(--text-secondary)">
                @foreach ($navigation as $item)
                    @if (isset($item['children']))
                        @php
                            $childActive = collect($item['children'])->contains($isChildActive);
                        @endphp
                        <li class="sidebar-dropdown rounded-lg {{ $childActive ? 'active' : '' }}"
                            data-search="{{ strtolower($item['label'].' '.collect($item['children'])->pluck('label')->implode(' ')) }}"
                            x-show="!filter || $el.dataset.search.includes(filter)">
                            <button type="button" aria-expanded="{{ $childActive ? 'true' : 'false' }}"
                                class="dropdown-btn navLink flex justify-between items-center text-sm p-2 rounded-lg duration-200 relative w-full cursor-pointer">
                                <span class="flex gap-2 items-center">
                                    <svg class="shrink-0" xmlns="http://www.w3.org/2000/svg" width="20"
                                        height="20" viewBox="0 0 24 24" aria-hidden="true">{!! $item['icon'] !!}</svg>
                                    <span class="sidebar-text">{{ $item['label'] }}</span>
                                </span>
                                <svg class="arrow-icon transition-transform duration-300" width="12" height="12"
                                    aria-hidden="true">
                                    <path d="m3 4 3 3 3-3" fill="none" stroke="currentColor" stroke-width="1.5" />
                                </svg>
                            </button>
                            <div class="dropdown-content overflow-hidden transition-all duration-300 {{ $childActive ? '' : 'max-h-0' }} ms-5 space-y-2">
                                @foreach ($item['children'] as $child)
                                    @php($active = $isChildActive($child))
                                    <a class="flex gap-3 items-center text-[13px] py-1 {{ $loop->first ? 'mt-2' : '' }} {{ $active ? 'active text-(--text-primary)' : '' }}"
                                        href="{{ $routeUrl($child['route'], $child['parameters'] ?? []) }}" wire:navigate
                                        @if ($active) aria-current="page" @endif>
                                        <span aria-hidden="true">•</span>{{ $child['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </li>
                    @else
                        <li data-search="{{ strtolower($item['label']) }}"
                            x-show="!filter || $el.dataset.search.includes(filter)">
                            <a class="navLink flex gap-2 items-center text-sm p-2 rounded-lg duration-200 relative {{ $isActive($item['route']) ? 'active' : '' }}"
                                href="{{ $routeUrl($item['route']) }}" wire:navigate
                                @if ($isActive($item['route'])) aria-current="page" @endif>
                                <svg class="shrink-0" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                    viewBox="0 0 24 24" aria-hidden="true">{!! $item['icon'] !!}</svg>
                                <span class="sidebar-text">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>

            <div class="border-t border-(--border-color) pt-4 mt-3 shrink-0">
                <a data-search="team members"
                    x-show="!filter || $el.dataset.search.includes(filter)"
                    class="navLink flex items-center gap-2 text-sm p-2 rounded-lg duration-200 relative {{ $isActive('team') ? 'active' : '' }}"
                    href="{{ $routeUrl('team') }}" wire:navigate title="Team Members"
                    @if ($isActive('team')) aria-current="page" @endif>
                    <img class="shrink-0" src="{{ asset('images/elite-vision.webp') }}" width="30" height="30"
                        alt="Elite Vision">
                    <span class="flex flex-col leading-none sidebar-text">
                        <span class="text-sm">Elite Vision</span>
                        <span class="text-[10px] text-(--text-secondary)">Team Members</span>
                    </span>
                </a>
            </div>
        </aside>

        <div id="main-content">
            <header
                class="relative z-30 shrink-0 flex justify-between items-center gap-3 p-3 text-(--text-secondary) bg-(--bg-surface) border-b border-(--border-color)">
                <div class="flex items-center gap-4 min-w-0">
                    <button id="sidebar-toggle" type="button" aria-label="Toggle sidebar"
                        class="hidden md:inline-flex cursor-pointer text-(--text-secondary) hover:text-(--text-primary) transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="1 1 22 22"
                            aria-hidden="true">
                            <rect x="3" y="3" width="18" height="18" rx="3" fill="none" stroke="currentColor"
                                stroke-width="1.5" />
                            <path class="sidebar-bar" d="M8 7v10" stroke="currentColor" stroke-width="1.5"
                                stroke-linecap="round" />
                        </svg>
                    </button>
                    <button id="openNavigation" type="button" aria-label="Open navigation" aria-expanded="false"
                        class="md:hidden p-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="2 2 20 20"
                            aria-hidden="true">
                            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5"
                                d="M2.73 8.34h18.54M2.73 15.66h12.24" />
                        </svg>
                    </button>
                    <span class="hidden md:block h-5 w-px bg-(--border-color)"></span>
                    <h1 class="text-(--text-primary) font-semibold text-sm truncate">{{ $title }}</h1>
                </div>

                <div class="flex items-center gap-3 sm:gap-5">
                    <button id="theme-toggle" type="button" aria-label="Toggle theme" aria-pressed="false"
                        class="text-(--text-secondary) hover:text-(--text-primary) cursor-pointer p-1 transition-colors duration-200">
                        <svg class="theme-icon-sun" xmlns="http://www.w3.org/2000/svg" width="21" height="21"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="4" />
                            <path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" />
                        </svg>
                        <svg class="theme-icon-moon" xmlns="http://www.w3.org/2000/svg" width="21" height="21"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" aria-hidden="true">
                            <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" />
                        </svg>
                    </button>
                </div>
            </header>

            <main class="page-main">
                {{ $slot }}
            </main>

            <footer class="shrink-0 flex items-center justify-between gap-3 px-3 py-2 md:p-3 border-t border-(--border-color) bg-(--bg-surface)">
                <div class="flex items-center gap-3">
                    <span class="text-(--text-secondary) text-xs">&copy; 2026 <span class="text-(--text-primary)">Elite Vision</span><span class="hidden sm:inline">. All rights reserved.</span></span>
                    <span class="hidden md:inline-block h-3 w-px bg-(--border-color)"></span>
                    <span class="text-[11px] font-medium text-(--text-primary)">System Operational</span>
                </div>
                <div class="hidden md:flex justify-center gap-3 items-center">
                    <a class="text-(--text-secondary) text-xs hover:text-(--text-primary)" href="#">Support</a>
                    <span class="font-mono text-[11px] bg-(--bg-hover) px-2 py-0.5 rounded-md text-(--text-primary)">v1.0.0</span>
                </div>
            </footer>
        </div>
    </div>
</body>

</html>
