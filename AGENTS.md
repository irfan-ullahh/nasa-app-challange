# Project Rules: Modern TALL Stack (Laravel 13, Livewire, Alpine.js, Tailwind CSS v4)

## Current Stack & Versions
- **Backend:** Laravel 13 (PHP 8.3+), strict typing (`declare(strict_types=1);`), Eloquent relationships, Actions/Services for business logic, and Form Requests or Livewire `#[Validate]` for validation.
- **Livewire:** Latest stable Livewire (`livewire/livewire`). Always use modern PHP attributes (`#[Layout]`, `#[Title]`, `#[Url]`, `#[On]`, `#[Validate]`), `wire:model.live` / `wire:model.blur`, and `wire:navigate` for SPA-like sidebar transitions.
- **Alpine.js:** Bundled automatically with Livewire. Never add an Alpine.js CDN script. Use lightweight Alpine (`x-data`, `x-show`, `x-transition`, `@click.outside`) for UI-only interactivity like dropdowns and modals.
- **Styling:** Tailwind CSS v4 (`@import "tailwindcss";` and `@theme` in `resources/css/app.css`). Do not create a `tailwind.config.js` file. Use Tailwind v4 variable syntax (`bg-(--bg-surface)`, `text-(--text-primary)`) and gradient syntax (`bg-linear-to-r`).

## Existing Frontend & Design System
- **Reference Folder:** Our static HTML/CSS/JS prototype is in `frontend/` at the project root. Keep `frontend/` untouched as a reference until all pages are migrated.
- **Preserve Design System:** Reuse the exact CSS variables, colors, fonts (`Inter` and `JetBrains Mono`), spacing, and layout classes from `frontend/assets/main.css`.
- **Charts & Maps:** Use npm packages (`chart.js` and `mapbox-gl`) via Vite instead of CDNs. Store the Mapbox public token in `.env` as `VITE_MAPBOX_TOKEN`. Always wrap Mapbox containers and Chart.js canvases in `wire:ignore` and re-initialize them on `livewire:navigated`.
- **Modular Blade:** Extract repeated UI patterns (sidebar links, KPI cards, stat tiles, chart cards) into reusable Blade components under `resources/views/components/`.

## AI Behavior
- Keep explanations brief and direct.
- Provide complete, working code without placeholder comments (`// ...`).
- Use named routes (`route('dashboard')`) instead of hardcoded URLs.
