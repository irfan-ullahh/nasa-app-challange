import { Chart, registerables } from 'chart.js';
import mapboxgl from 'mapbox-gl';

Chart.register(...registerables);

/* ==========================================================================
   main.js — Elite Vision shared runtime
   Load order on every page (end of <body>):
       layout.js  →  main.js  →  (page script)
   Exposes one global: window.EV
     EV.theme            get/toggle/apply theme, EV.theme.on(fn) subscribe
     EV.charts.create()  theme-aware Chart.js (auto destroy + rebuild)
     EV.maps.create()    theme-aware Mapbox (style swap + layer re-add)
   ========================================================================== */
(() => {
    'use strict';

    const root = document.documentElement;
    const $ = (sel, scope = document) => scope.querySelector(sel);
    const $$ = (sel, scope = document) => Array.from(scope.querySelectorAll(sel));
    const DESKTOP = window.matchMedia('(min-width: 768px)');

    const store = {
        get(k) { try { return localStorage.getItem(k); } catch { return null; } },
        set(k, v) { try { localStorage.setItem(k, v); } catch { /* private mode */ } },
    };

    /* ====================================================================
       1. THEME
       ==================================================================== */
    const listeners = new Set();

    const theme = {
        get current() { return root.classList.contains('dark') ? 'dark' : 'light'; },

        apply(next, { persist = true } = {}) {
            const dark = next === 'dark';
            const changed = dark !== root.classList.contains('dark');

            root.classList.toggle('dark', dark);
            root.style.colorScheme = dark ? 'dark' : 'light';
            if (persist) store.set('theme', next);
            $$('#theme-toggle').forEach(b => b.setAttribute('aria-pressed', String(dark)));

            if (changed) {
                listeners.forEach(fn => { try { fn(next); } catch (e) { console.error('[EV] theme listener failed', e); } });
                window.dispatchEvent(new CustomEvent('themechange', { detail: { theme: next } }));
            }
        },

        toggle() { this.apply(this.current === 'dark' ? 'light' : 'dark'); },

        syncPersisted() {
            const saved = store.get('theme');
            if (saved === 'dark' || saved === 'light') this.apply(saved, { persist: false });
        },

        /** Subscribe to theme changes. Returns an unsubscribe function. */
        on(fn) { listeners.add(fn); return () => listeners.delete(fn); },
    };

    // One delegated handler → works even though the header is injected by layout.js
    document.addEventListener('click', e => {
        if (e.target.closest('#theme-toggle')) theme.toggle();
    });

    // Keep other open tabs in sync
    window.addEventListener('storage', e => {
        if (e.key === 'theme' && e.newValue) theme.apply(e.newValue, { persist: false });
    });

    $$('#theme-toggle').forEach(b => b.setAttribute('aria-pressed', String(theme.current === 'dark')));

    /* ====================================================================
       2. SIDEBAR (dropdowns, desktop collapse, mobile drawer)
       ==================================================================== */
    function initSidebar() {
        const nav = $('#navigation');
        const toggle = $('#sidebar-toggle');
        const openBtn = $('#openNavigation');
        const closeBtn = $('#hideNavigation');
        const backdrop = $('#nav-backdrop');
        if (!nav || nav.dataset.evInitialized === 'true') return;
        nav.dataset.evInitialized = 'true';

        /* ── dropdowns ── */
        const dropdowns = $$('.sidebar-dropdown');

        const setDropdown = (dd, open) => {
            const btn = $('.dropdown-btn', dd);
            const content = $('.dropdown-content', dd);
            const arrow = $('.arrow-icon', dd);
            dd.classList.toggle('active', open);
            btn.classList.toggle('active', open);
            btn.setAttribute('aria-expanded', String(open));
            content.style.maxHeight = open ? content.scrollHeight + 'px' : null;
            if (arrow) arrow.style.transform = `rotate(${open ? 90 : 0}deg)`;
        };

        dropdowns.forEach(dd => {
            $('.dropdown-btn', dd).addEventListener('click', () => {
                // In the collapsed rail, a dropdown can't show its children → expand the rail first.
                if (DESKTOP.matches && root.classList.contains('sidebar-collapsed')) setCollapsed(false);
                const willOpen = !dd.classList.contains('active');
                dropdowns.forEach(other => { if (other !== dd) setDropdown(other, false); });
                setDropdown(dd, willOpen);
            });
            // Auto-open the group that contains the current page
            if ($('[aria-current="page"]', dd)) setDropdown(dd, true);
        });

        /* ── desktop collapse (class on <html>, set pre-paint by theme-init.js) ── */
        function setCollapsed(collapsed) {
            root.classList.toggle('sidebar-collapsed', collapsed);
            store.set('sidebarCollapsed', String(collapsed));
            if (toggle) toggle.setAttribute('aria-expanded', String(!collapsed));
        }
        if (toggle) {
            toggle.setAttribute('aria-expanded', String(!root.classList.contains('sidebar-collapsed')));
            toggle.addEventListener('click', () => setCollapsed(!root.classList.contains('sidebar-collapsed')));
        }

        /* ── mobile drawer (class-based; no inline styles to get stuck) ── */
        const openDrawer = () => {
            nav.classList.add('nav-open');
            backdrop && backdrop.classList.add('show');
            document.body.style.overflow = 'hidden';
            openBtn && openBtn.setAttribute('aria-expanded', 'true');
        };
        const closeDrawer = () => {
            nav.classList.remove('nav-open');
            backdrop && backdrop.classList.remove('show');
            document.body.style.overflow = '';
            openBtn && openBtn.setAttribute('aria-expanded', 'false');
        };

        openBtn && openBtn.addEventListener('click', openDrawer);
        closeBtn && closeBtn.addEventListener('click', closeDrawer);
        backdrop && backdrop.addEventListener('click', closeDrawer);
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });
        $$('a[href]', nav).forEach(a => a.addEventListener('click', () => { if (!DESKTOP.matches) closeDrawer(); }));
        DESKTOP.addEventListener('change', e => { if (e.matches) closeDrawer(); });
    }

    /* ====================================================================
       3. CHART.JS — theme-aware, destroy-before-rebuild
       ==================================================================== */
    const hexA = (hex, a) => {
        const n = parseInt(hex.replace('#', ''), 16);
        return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${a})`;
    };

    /** Read the live CSS variables so charts always match the current theme. */
    const palette = () => {
        const s = getComputedStyle(root);
        const v = name => s.getPropertyValue(name).trim();
        return {
            isDark: theme.current === 'dark',
            text: v('--text-secondary'),   // ticks, legends
            textStrong: v('--text-primary'),     // tooltip titles
            grid: v('--border-color'),     // grid lines, borders, empty doughnut track
            surface: v('--bg-surface'),
            accent: v('--accent'),
        };
    };

    const registry = new Map();   // canvasId -> factory(ctx, palette) => Chart config

    function applyChartDefaults(p) {
        const d = Chart.defaults;
        d.color = p.text;                       // axis ticks + legend labels
        d.borderColor = p.grid;                 // grid lines + axis borders
        d.font.family = "'Inter', ui-sans-serif, system-ui, sans-serif";
        const t = d.plugins.tooltip;
        t.backgroundColor = p.surface;
        t.titleColor = p.textStrong;
        t.bodyColor = p.text;
        t.borderColor = p.grid;
        t.borderWidth = 1;
    }

    function buildChart(id, { animate = true } = {}) {
        const factory = registry.get(id);
        const canvas = document.getElementById(id);
        if (!factory || !canvas) return null;

        // ★ destroy the previous instance BEFORE creating a new one (no leaks / ghost canvases)
        const previous = Chart.getChart(canvas);
        if (previous) previous.destroy();

        const p = palette();
        applyChartDefaults(p);

        const ctx = canvas.getContext('2d');
        const config = factory(ctx, p);
        if (!animate) config.options = { ...(config.options || {}), animation: false };
        return new Chart(ctx, config);
    }

    const charts = {
        /** Create (or re-create) a chart. `factory(ctx, p)` must return a Chart.js config. */
        create(id, factory) {
            registry.set(id, factory);
            return buildChart(id);
        },
        instance(id) {
            const canvas = document.getElementById(id);
            return canvas ? Chart.getChart(canvas) : null;
        },
        /** Vertical gradient fill from a hex colour. */
        gradient(ctx, hex, height = 160, top = 0.35) {
            const g = ctx.createLinearGradient(0, 0, 0, height);
            g.addColorStop(0, hexA(hex, top));
            g.addColorStop(1, hexA(hex, 0));
            return g;
        },
        /** Common cartesian scales (colours come from Chart.defaults, set per theme). */
        scales(p, { size = 10, yGrid = true, xGrid = false, showY = true, showX = true } = {}) {
            return {
                x: { display: showX, grid: { display: xGrid, color: p.grid }, border: { display: false }, ticks: { font: { size } } },
                y: { display: showY, grid: { display: yGrid, color: p.grid }, border: { display: false }, ticks: { font: { size } } },
            };
        },
        rebuildAll() { registry.forEach((_, id) => buildChart(id, { animate: false })); },
        destroyAll() {
            registry.forEach((_, id) => {
                const c = document.getElementById(id);
                const inst = c ? Chart.getChart(c) : null;
                if (inst) inst.destroy();
            });
        },
    };

    theme.on(() => charts.rebuildAll());
    window.addEventListener('pagehide', () => charts.destroyAll());

    /* ====================================================================
       4. MAPBOX — theme-aware style swap + layer re-injection
       ==================================================================== */
    // NOTE: pk.* tokens are public by design, but restrict this one to your domains
    // in the Mapbox dashboard (Account → Tokens → URL restrictions).
    const MAPBOX_TOKEN = typeof import.meta.env.VITE_MAPBOX_TOKEN === 'string'
        ? import.meta.env.VITE_MAPBOX_TOKEN.trim()
        : '';
    const VALID_MAPBOX_TOKEN = /^pk\.[A-Za-z0-9._-]+$/.test(MAPBOX_TOKEN);

    const mapStyle = () => theme.current === 'dark'
        ? 'mapbox://styles/mapbox/dark-v11'
        : 'mapbox://styles/mapbox/light-v11';

    /** Neon colours vanish on the light basemap, so layers read their colours from here. */
    const mapPalette = () => theme.current === 'dark'
        ? { glow: '#00ffff', line: '#ffffff', hot: '#ff8800', hotCore: '#ffff00', fill: 0.15, glowOpacity: 0.7 }
        : { glow: '#0891b2', line: '#0e7490', hot: '#ea580c', hotCore: '#b45309', fill: 0.25, glowOpacity: 0.45 };

    const maps = {
        palette: mapPalette,
        active: new Set(),

        /**
         * @param {object}   o
         * @param {string}   o.container            element id
         * @param {number[]} o.center
         * @param {number}   o.zoom
         * @param {boolean} [o.interactive=true]
         * @param {(map, p) => void} o.setupLayers  Add sources/layers here. Called on the first
         *                                          load AND after every theme-triggered setStyle().
         *                                          Must be idempotent (guard with getSource/getLayer).
         */
        create({ container, center, zoom, interactive = true, setupLayers }) {
            const el = document.getElementById(container);
            if (!el) return null;
            if (!VALID_MAPBOX_TOKEN) {
                console.warn('[EV] Mapbox is disabled: VITE_MAPBOX_TOKEN is missing or invalid.');
                return null;
            }
            if (el.clientWidth === 0 || el.clientHeight === 0) {
                console.warn(`[EV] Map container "${container}" has no dimensions`);
                return null;
            }

            let map;
            try {
                mapboxgl.accessToken = MAPBOX_TOKEN;
                map = new mapboxgl.Map({
                    container: el,
                    style: mapStyle(),
                    center, zoom, interactive,
                    attributionControl: false,
                });

                map.addControl(new mapboxgl.AttributionControl({ compact: true }), 'bottom-right');
                if (interactive) map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right');
            } catch (error) {
                console.warn('[EV] Mapbox initialization was skipped.', error);
                return null;
            }

            // 'style.load' fires for the initial style AND for every setStyle() → one handler is enough.
            map.on('style.load', () => {
                try { setupLayers && setupLayers(map, mapPalette()); }
                catch (err) { console.error('[EV] setupLayers failed', err); }
            });
            map.on('load', () => map.resize());

            // Theme → swap basemap. diff:false = full replace (custom layers are re-added by style.load).
            const off = theme.on(() => map.setStyle(mapStyle(), { diff: false }));

            // Container size can change without a window resize (sidebar collapse, panels, drawers).
            let raf = 0;
            const ro = new ResizeObserver(() => { cancelAnimationFrame(raf); raf = requestAnimationFrame(() => map.resize()); });
            ro.observe(el);

            const cleanup = () => {
                window.removeEventListener('pagehide', cleanup);
                off();
                ro.disconnect();
                map.remove();
                maps.active.delete(cleanup);
            };
            maps.active.add(cleanup);
            window.addEventListener('pagehide', cleanup, { once: true });
            return map;
        },

        destroyAll() { this.active.forEach(cleanup => cleanup()); },
        /** Add a source only if the current style doesn't have it yet. */
        ensureSource(map, id, spec) { if (!map.getSource(id)) map.addSource(id, spec); },
        /** Add a layer only if the current style doesn't have it yet. */
        ensureLayer(map, spec) { if (!map.getLayer(spec.id)) map.addLayer(spec); },
    };

    const dashboard = {
        map: null,
        element: null,
        visibility: {
            floodMapping: true,
            vegetationChanging: true,
            soilMoisture: true,
        },
        markers: [],

        isVisible(value) {
            return value === undefined || value === true || value === 1 || value === '1' || value === 'true';
        },

        parseJson(value, fallback) {
            if (typeof value !== 'string' || value.trim() === '') return fallback;

            try {
                const parsed = JSON.parse(value);
                return parsed === null ? fallback : parsed;
            } catch {
                console.warn('[EV] Ignoring invalid dashboard JSON payload.');
                return fallback;
            }
        },

        validScans(scans) {
            return (Array.isArray(scans) ? scans : []).filter(scan => (
                Number.isFinite(Number(scan.latitude))
                && Number.isFinite(Number(scan.longitude))
                && Number(scan.latitude) >= -90
                && Number(scan.latitude) <= 90
                && Number(scan.longitude) >= -180
                && Number(scan.longitude) <= 180
            ));
        },

        applyLayerVisibility() {
            this.markers.forEach(({ marker, scanType }) => {
                const isVisible = {
                    flood_mapping: this.visibility.floodMapping,
                    vegetation_changing: this.visibility.vegetationChanging,
                    soil_moisture: this.visibility.soilMoisture,
                }[scanType] !== false;
                marker.getElement().style.display = isVisible ? '' : 'none';
            });
        },

        updateLayerVisibility(detail) {
            this.visibility = {
                floodMapping: this.isVisible(detail.floodMapping),
                vegetationChanging: this.isVisible(detail.vegetationChanging),
                soilMoisture: this.isVisible(detail.soilMoisture),
            };
            this.element = document.querySelector('#dashboard-map[data-dashboard-scans]') || this.element;
            this.applyLayerVisibility();
            this.updateMapData();
        },

        removeMarkers() {
            this.markers.forEach(({ marker }) => marker.remove());
            this.markers = [];
        },

        renderMarkers(scans) {
            if (!this.map) return;

            this.removeMarkers();
            const colors = {
                flood_mapping: '#06b6d4',
                vegetation_changing: '#10b981',
                soil_moisture: '#8b5cf6',
            };

            scans.forEach(scan => {
                const markerElement = document.createElement('button');
                markerElement.type = 'button';
                markerElement.className = 'cursor-pointer rounded-full border-2 border-[#0b0f19] shadow-sm';
                markerElement.style.width = '12px';
                markerElement.style.height = '12px';
                markerElement.style.cursor = 'pointer';
                markerElement.style.backgroundColor = colors[scan.scan_type] || '#06b6d4';
                markerElement.setAttribute('aria-label', `Select ${scan.region_name}`);
                markerElement.addEventListener('click', event => {
                    event.stopPropagation();
                    window.dispatchEvent(new CustomEvent('dashboard-scan-selected', {
                        detail: scan,
                    }));
                });

                const marker = new mapboxgl.Marker({ element: markerElement, occludedOpacity: 1 })
                    .setLngLat([scan.longitude, scan.latitude])
                    .addTo(this.map);
                this.markers.push({ marker, scanType: scan.scan_type });
            });

            this.applyLayerVisibility();
        },

        updateMapData() {
            if (!this.map || !this.element) return;

            const scans = this.validScans(this.parseJson(this.element.dataset.dashboardScans, []));
            this.renderMarkers(scans);
        },

        updateTelemetryChart(payload) {
            const chart = charts.instance('globalMetricsChart');
            if (!chart) return;

            const labels = Array.isArray(payload.labels) ? payload.labels : [];
            const datasets = [
                Array.isArray(payload.signal_intensity) ? payload.signal_intensity : [],
                Array.isArray(payload.moisture_index) ? payload.moisture_index : [],
                Array.isArray(payload.critical_threshold) ? payload.critical_threshold : [],
            ];

            chart.data.labels = labels.slice();
            chart.data.datasets.forEach((dataset, index) => {
                dataset.data = (datasets[index] || []).slice();
            });

            chart.update('active');
        },

        init() {
            const dashboardElement = document.querySelector('#dashboard-map[data-dashboard-scans]');
            const mapContainer = document.getElementById('dashboard-map-canvas');
            if (!dashboardElement || !mapContainer) return;
            this.element = dashboardElement;

            const metrics = this.parseJson(dashboardElement.dataset.dashboardMetrics, {});
            const telemetry = this.parseJson(dashboardElement.dataset.dashboardTelemetry, {});
            const initializeMap = () => {
                if (!mapContainer.isConnected) return;
                if (mapContainer.clientWidth === 0 || mapContainer.clientHeight === 0) {
                    requestAnimationFrame(initializeMap);
                    return;
                }

                this.map = maps.create({
                    container: 'dashboard-map-canvas',
                    center: [10, 20],
                    zoom: 1,
                    interactive: true,
                    setupLayers(map) {
                        dashboard.map = map;
                        dashboard.updateMapData();
                    },
                });
            };
            const chartValues = data => Array.isArray(data) && data.length ? data : [0];
            const sparkline = (id, color, data, fill = false) => charts.create(id, context => ({
                type: 'line',
                data: {
                    labels: chartValues(data).map((_, index) => String(index + 1)),
                    datasets: [{
                        data: chartValues(data),
                        borderColor: color,
                        borderWidth: 1.5,
                        pointRadius: 0,
                        tension: 0.4,
                        fill,
                        backgroundColor: fill ? charts.gradient(context, color, 30, 0.25) : 'transparent',
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { enabled: false } },
                    scales: { x: { display: false }, y: { display: false } },
                },
            }));

            sparkline('spark1', '#06b6d4', metrics.flood_area, true);
            sparkline('spark2', '#ef4444', metrics.vegetation_loss);
            sparkline('spark3', '#10b981', metrics.soil_saturation, true);

            const series = (context, label, data, color) => ({
                label,
                data,
                borderColor: color,
                backgroundColor: charts.gradient(context, color, 250, 0.38),
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                borderWidth: 2,
            });

            charts.create('globalMetricsChart', (context, p) => ({
                type: 'line',
                data: {
                    labels: telemetry.labels || [],
                    datasets: [
                        series(context, 'Signal Intensity', telemetry.signal_intensity || [], '#06b6d4'),
                        series(context, 'Moisture Index', telemetry.moisture_index || [], '#10b981'),
                        series(context, 'Critical Threshold', telemetry.critical_threshold || [], '#8b5cf6'),
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { display: false } },
                    scales: (() => {
                        const scales = charts.scales(p, { size: 9 });
                        scales.x.ticks = { ...scales.x.ticks, maxTicksLimit: 10, maxRotation: 0, minRotation: 0 };
                        return scales;
                    })(),
                    animation: false,
                },
            }));

            initializeMap();
        },
    };

    const analytics = {
        update(payload) {
            const eventPayload = payload && payload.chartData ? payload : payload && payload[0] ? payload[0] : {};
            const data = eventPayload.chartData || {};
            const updateChart = (id, labels, datasets) => {
                const chart = charts.instance(id);
                if (!chart) return;

                chart.data.labels = Array.isArray(labels) ? labels.slice() : [];
                chart.data.datasets.forEach((dataset, index) => {
                    dataset.data = Array.isArray(datasets[index]) ? datasets[index].slice() : [];
                });
                chart.update('active');
            };
            const values = value => Array.isArray(value) ? value : [];
            const sequenceLabels = value => values(value).map((_, index) => String(index + 1));

            updateChart('sparkline1', sequenceLabels(data.flood_area), [values(data.flood_area)]);
            updateChart('sparkline2', sequenceLabels(data.soil_saturation), [values(data.soil_saturation)]);
            updateChart('sparkline3', sequenceLabels(data.region_scans), [values(data.region_scans)]);
            updateChart('multiLineChart', values(data.labels), [
                values(data.flood_area),
                values(data.vegetation_loss),
                values(data.soil_saturation),
            ]);
            updateChart('regionBarChart', values(data.regions), [
                values(data.region_alerts),
                values(data.region_scans),
            ]);
            updateChart('distributionChart', values(data.distribution_labels), [values(data.distribution_values)]);
        },

        init() {
            const analyticsElement = document.querySelector('[data-analytics]');
            if (!analyticsElement) return;

            const data = dashboard.parseJson(analyticsElement.dataset.analytics, {});
            const spark = (id, type, values, color, extra = {}) => charts.create(id, context => ({
                type,
                data: {
                    labels: values.map((_, index) => String(index + 1)),
                    datasets: [{ data: values.length ? values : [0], borderColor: color, backgroundColor: color, ...extra }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { enabled: false } },
                    scales: { x: { display: false }, y: { display: false } },
                },
            }));

            spark('sparkline1', 'line', data.flood_area || [], '#06b6d4', { borderWidth: 2, pointRadius: 0, tension: 0.4 });
            spark('sparkline2', 'line', data.soil_saturation || [], '#10b981', { borderWidth: 2, pointRadius: 0, tension: 0.4 });
            spark('sparkline3', 'bar', data.region_scans || [], '#8b5cf6', { borderRadius: 2, barThickness: 3 });

            const area = (context, label, values, color) => ({
                label,
                data: values,
                borderColor: color,
                backgroundColor: charts.gradient(context, color, 250, 0.4),
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                borderWidth: 2,
            });

            charts.create('multiLineChart', (context, palette) => {
                const scales = charts.scales(palette, { size: 10 });
                scales.y.ticks.callback = value => `${value}`;
                scales.x.ticks = { ...scales.x.ticks, maxTicksLimit: 10, maxRotation: 0, minRotation: 0 };

                return {
                    type: 'line',
                    data: {
                        labels: data.labels || [],
                        datasets: [
                            area(context, 'Flood Area', data.flood_area || [], '#06b6d4'),
                            area(context, 'Vegetation Loss', data.vegetation_loss || [], '#10b981'),
                            area(context, 'Soil Saturation', data.soil_saturation || [], '#8b5cf6'),
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { display: false } },
                        scales,
                    },
                };
            });

            charts.create('regionBarChart', (context, palette) => ({
                type: 'bar',
                data: {
                    labels: data.regions || [],
                    datasets: [
                        { label: 'Alerts', data: data.region_alerts || [], backgroundColor: '#ef4444', borderRadius: 2, barThickness: 6 },
                        { label: 'Scans', data: data.region_scans || [], backgroundColor: '#06b6d4', borderRadius: 2, barThickness: 6 },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: charts.scales(palette, { size: 8 }),
                },
            }));

            charts.create('distributionChart', (context, palette) => ({
                type: 'doughnut',
                data: {
                    labels: data.distribution_labels || [],
                    datasets: [{
                        data: data.distribution_values || [],
                        backgroundColor: ['#06b6d4', '#10b981', '#8b5cf6', palette.isDark ? '#6b7280' : '#9ca3af'],
                        borderWidth: 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: { legend: { position: 'right', labels: { font: { size: 9 }, boxWidth: 8 } } },
                },
            }));
        },
    };

    const dataLayer = {
        map: null,
        markers: [],

        removeMarkers() {
            this.markers.forEach(marker => marker.remove());
            this.markers = [];
        },

        renderMarkers(map, scans, color) {
            this.removeMarkers();

            scans.forEach(scan => {
                const markerElement = document.createElement('button');
                markerElement.type = 'button';
                markerElement.className = 'cursor-pointer rounded-full border-2 border-[#0b0f19] shadow-sm';
                markerElement.style.width = '12px';
                markerElement.style.height = '12px';
                markerElement.style.cursor = 'pointer';
                markerElement.style.backgroundColor = color;
                markerElement.setAttribute('aria-label', `Select ${scan.region_name}`);
                markerElement.addEventListener('click', event => {
                    event.stopPropagation();
                    window.dispatchEvent(new CustomEvent('data-layer-scan-selected', {
                        detail: scan,
                    }));
                });

                const marker = new mapboxgl.Marker({ element: markerElement })
                    .setLngLat([scan.longitude, scan.latitude])
                    .addTo(map);
                this.markers.push(marker);
            });
        },

        init() {
            const element = document.querySelector('[data-layer-page]');
            const mapElement = document.getElementById('layer-map');
            if (!element || !mapElement) return;

            const chartData = dashboard.parseJson(element.dataset.layerChart, {});
            const scans = dashboard.parseJson(mapElement.dataset.layerScans, []);
            const markerColor = mapElement.dataset.layerAccent || chartData.accent || '#06b6d4';

            this.map = maps.create({
                container: 'layer-map',
                center: [10, 20],
                zoom: 1.1,
                setupLayers: map => {
                    this.map = map;
                    if (map.getProjection().name === 'globe') {
                        map.setProjection({ name: 'mercator' });
                    }
                    this.renderMarkers(map, scans, markerColor);
                },
            });

            charts.create('layerTrendChart', (context, p) => ({
                type: 'line',
                data: {
                    labels: chartData.labels || [],
                    datasets: [{
                        label: chartData.metric_label || 'Metric',
                        data: chartData.values || [],
                        borderColor: chartData.accent || p.accent,
                        backgroundColor: charts.gradient(context, chartData.accent || p.accent, 180, 0.3),
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 2,
                        tension: 0.35,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: (() => {
                        const scales = charts.scales(p, { size: 9 });
                        scales.x.ticks = { ...scales.x.ticks, maxTicksLimit: 8, maxRotation: 0, minRotation: 0 };
                        return scales;
                    })(),
                },
            }));
        },
    };

    const interactiveMap = {
        map: null,
        data: { scans: [], radar: { labels: [], signal_intensity: [], moisture_index: [] } },
        markerInteractionsBound: false,
        visibility: {
            floodMapping: true,
            vegetationChanging: true,
            soilMoisture: true,
        },

        scansForType(scanType) {
            return this.data.scans.filter(scan => scan.scan_type === scanType);
        },

        points(scanType) {
            return {
                type: 'geojson',
                data: {
                    type: 'FeatureCollection',
                    features: this.scansForType(scanType).map(scan => ({
                        type: 'Feature',
                        properties: {
                            scan_id: scan.id,
                            region_name: scan.region_name,
                            scan_type: scan.scan_type,
                            status: scan.status,
                            signal_intensity_db: scan.signal_intensity_db,
                            moisture_index: scan.moisture_index,
                            temperature_c: scan.temperature_c,
                            precipitation_mm: scan.precipitation_mm,
                            source: scan.source,
                        },
                        geometry: {
                            type: 'Point',
                            coordinates: [scan.longitude, scan.latitude],
                        },
                    })),
                },
            };
        },

        applyLayerVisibility() {
            if (!this.map) return;

            const layers = {
                'interactive-flood-layer': this.visibility.floodMapping,
                'interactive-vegetation-layer': this.visibility.vegetationChanging,
                'interactive-soil-layer': this.visibility.soilMoisture,
            };

            Object.entries(layers).forEach(([id, visible]) => {
                if (this.map.getLayer(id)) {
                    this.map.setLayoutProperty(id, 'visibility', visible ? 'visible' : 'none');
                }
            });
        },

        updateLayerVisibility(detail) {
            this.visibility = {
                floodMapping: detail.floodMapping !== false,
                vegetationChanging: detail.vegetationChanging !== false,
                soilMoisture: detail.soilMoisture !== false,
            };
            this.applyLayerVisibility();
        },

        updateChart() {
            const chart = charts.instance('radarChart');
            if (!chart) return;

            chart.data.labels = this.data.radar.labels || [];
            chart.data.datasets[0].data = this.data.radar.signal_intensity || [];
            chart.data.datasets[1].data = this.data.radar.moisture_index || [];
            chart.update('active');
        },

        updateData(data) {
            this.data = data;
            if (this.map) {
                const sources = {
                    'interactive-flood-source': this.points('flood_mapping'),
                    'interactive-vegetation-source': this.points('vegetation_changing'),
                    'interactive-soil-source': this.points('soil_moisture'),
                };
                Object.entries(sources).forEach(([id, source]) => {
                    const mapSource = this.map.getSource(id);
                    if (mapSource) mapSource.setData(source.data);
                });
            }
            this.updateChart();
        },

        bindMarkerClicks(map, element) {
            if (this.markerInteractionsBound) return;

            ['interactive-flood-layer', 'interactive-vegetation-layer', 'interactive-soil-layer'].forEach(layerId => {
                map.on('click', layerId, event => {
                    const feature = event.features?.[0];
                    const scanId = feature?.properties?.scan_id;
                    if (!scanId) return;

                    element.dispatchEvent(new CustomEvent('open-scan-drawer', {
                        bubbles: true,
                        detail: { scanId: Number(scanId) },
                    }));
                });
                map.on('mouseenter', layerId, () => { map.getCanvas().style.cursor = 'pointer'; });
                map.on('mouseleave', layerId, () => { map.getCanvas().style.cursor = ''; });
            });

            this.markerInteractionsBound = true;
        },

        init() {
            const element = document.querySelector('[data-interactive-map]');
            if (!element || !document.getElementById('interactive-map')) return;

            this.markerInteractionsBound = false;
            this.data = dashboard.parseJson(element.dataset.interactiveMap, { scans: [], radar: {} });
            this.map = maps.create({
                container: 'interactive-map',
                center: [10, 20],
                zoom: 1.15,
                setupLayers: map => {
                    maps.ensureSource(map, 'interactive-flood-source', this.points('flood_mapping'));
                    maps.ensureLayer(map, {
                        id: 'interactive-flood-layer',
                        type: 'circle',
                        source: 'interactive-flood-source',
                        paint: {
                            'circle-color': ['match', ['get', 'status'], 'alert', '#ef4444', 'monitoring', '#f59e0b', '#06b6d4'],
                            'circle-radius': 7,
                            'circle-stroke-color': '#ffffff',
                            'circle-stroke-width': 1,
                        },
                    });

                    maps.ensureSource(map, 'interactive-vegetation-source', this.points('vegetation_changing'));
                    maps.ensureLayer(map, {
                        id: 'interactive-vegetation-layer',
                        type: 'circle',
                        source: 'interactive-vegetation-source',
                        paint: {
                            'circle-color': ['match', ['get', 'status'], 'alert', '#ef4444', '#10b981'],
                            'circle-radius': 7,
                            'circle-stroke-color': '#ffffff',
                            'circle-stroke-width': 1,
                        },
                    });

                    maps.ensureSource(map, 'interactive-soil-source', this.points('soil_moisture'));
                    maps.ensureLayer(map, {
                        id: 'interactive-soil-layer',
                        type: 'circle',
                        source: 'interactive-soil-source',
                        paint: {
                            'circle-color': 'transparent',
                            'circle-radius': 12,
                            'circle-stroke-color': '#8b5cf6',
                            'circle-stroke-width': 2,
                            'circle-stroke-opacity': 0.85,
                        },
                    });
                    this.applyLayerVisibility();
                    this.bindMarkerClicks(map, element);
                },
            });

            charts.create('radarChart', (context, p) => ({
                type: 'radar',
                data: {
                    labels: this.data.radar.labels || [],
                    datasets: [
                        {
                            label: 'Signal Intensity',
                            data: this.data.radar.signal_intensity || [],
                            borderColor: '#06b6d4',
                            backgroundColor: charts.gradient(context, '#06b6d4', 180, 0.2),
                            borderWidth: 2,
                            pointRadius: 2,
                        },
                        {
                            label: 'Moisture Index',
                            data: this.data.radar.moisture_index || [],
                            borderColor: '#10b981',
                            backgroundColor: 'transparent',
                            borderWidth: 1.5,
                            pointRadius: 2,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        r: {
                            min: 0,
                            max: 100,
                            ticks: { display: false },
                            grid: { color: p.grid },
                            angleLines: { color: p.grid },
                            pointLabels: { color: '#9ca3af', padding: 6, font: { size: 10 } },
                        },
                    },
                },
            }));
        },
    };

    window.addEventListener('analytics-period-updated', event => analytics.update(event.detail));
    window.addEventListener('dashboard-layers-updated', event => dashboard.updateLayerVisibility(event.detail));
    window.addEventListener('telemetry-updated', event => dashboard.updateTelemetryChart(event.detail.payload));
    window.addEventListener('dashboard-updated', event => dashboard.updateTelemetryChart(event.detail.payload || event.detail));
    window.addEventListener('interactive-map-updated', event => {
        const element = document.querySelector('[data-interactive-map]');
        if (element) {
            const data = dashboard.parseJson(element.dataset.interactiveMap, { scans: [], radar: {} });
            if (event.detail?.radar) data.radar = event.detail.radar;
            interactiveMap.updateData(data);
        }
        interactiveMap.updateLayerVisibility(event.detail);
    });

    /* ====================================================================
       Boot + public API
       ==================================================================== */
    window.EV = { theme, charts, maps, dashboard, analytics, dataLayer, hexA, palette, store };

    const boot = () => {
        theme.syncPersisted();
        charts.destroyAll();
        dataLayer.removeMarkers();
        maps.destroyAll();
        dashboard.map = null;
        dashboard.removeMarkers();
        dataLayer.map = null;
        interactiveMap.map = null;
        initSidebar();
        dashboard.init();
        analytics.init();
        dataLayer.init();
        interactiveMap.init();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();
    window.addEventListener('livewire:navigated', () => {
        theme.syncPersisted();
        boot();
    });
})();