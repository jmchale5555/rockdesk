# AGENTS.md

## What This Repo Is Now
- Treat this as a fresh PHP MVC monolith that keeps only the simple MVC/OOP foundation from the legacy codebase.
- Prioritize readability and low abstraction over framework-like patterns or excessive indirection.
- Prefer server-side rendering with small interactive enhancements; do not evolve this into an SPA architecture.

## Non-Negotiable Frontend Direction
- No Node/npm build pipeline, no `node_modules`, no bundler output dependency, no CDN/runtime internet requirement.
- Use local static files only (source files are in repo root, served from `public/assets/css/` and `public/assets/js/`):
  - `pico-2-1-1.min.css`
  - `alpine-3-15-11.min.js`
  - `htmx-2-0-10.min.js`
- HTMX: server-driven interactions (`hx-get`, `hx-post`, fragment swaps), server remains source of truth.
- Alpine: only lightweight local UI state (toggles, dropdowns, tiny form UX), inline with markup.
- Layout target stays simple: title, navbar, main section, footer.

## Current Wiring You Will Trip Over
- Entrypoint: `public/index.php`.
- Routing: Apache vhost rewrite maps non-file/non-dir URLs to `index.php?url=...`.
- Controller resolution is convention-based in `app/core/App.php`:
  - `/foo/bar` -> `app/controllers/Foo.php` -> `\Controller\Foo::bar()`.
- View rendering uses direct PHP includes via `MainController::view()` in `app/core/Controller.php`.
- Shared layout partials already include local static assets:
  - `app/views/partials/header.view.php` includes `assets/css/pico-2-1-1.min.css`
  - `app/views/partials/footer.view.php` includes `assets/js/alpine-3-15-11.min.js` and `assets/js/htmx-2-0-10.min.js`

## Infra and Data Constraints
- First implementation milestone is Docker Compose with Apache + modern PHP + MariaDB.
- Apache must serve from `public/` and keep rewrite behavior in vhost config.
- Do not anchor new work to legacy `support.sql`; keep only useful schema ideas.
- Build project-native PHP migrations and seeders (versioned, runnable, repeatable) as source of truth for DB setup.

## Existing Config Gotcha
- `app/core/config.php` uses environment-driven values (`APP_*`, `DB_*`) with local defaults.
