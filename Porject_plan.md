# Project Refactor Plan

## Status Snapshot
- Overall completion: ~100%
- Last updated: 2026-04-26
- Current state: all planned phases completed for the template/auth-focused scope.

## Goal
Refactor this repo into a clean PHP MVC monolith that keeps the simple MVC/OOP foundation, removes frontend build tooling, and uses local Pico + Alpine + HTMX assets with server-side rendering as the default model.

## Guiding Principles
- Keep abstraction low and code readable.
- Favor direct PHP includes/controllers/models over framework-like layers.
- Server owns state and rendering; HTMX swaps fragments; Alpine handles tiny local UI state.
- No npm, no bundler, no CDN, no runtime internet dependency.

## Phase 1 - Container Baseline (first milestone)
1. [x] Add `docker-compose.yml` with:
   - `web`: Apache + modern PHP (8.2/8.3), source mounted, docroot at `public/`.
   - `db`: MariaDB with persistent volume.
2. [x] Add web Dockerfile:
   - Enable `mod_rewrite`.
   - Install required PHP extensions used by `app/core/functions.php` (`gd`, `mysqli`, `pdo_mysql`, `curl`, `fileinfo`, `intl`, `exif`, `mbstring`).
3. [x] Ensure Apache rewrite behavior is preserved (implemented in vhost config).
4. [x] Define environment variables for app/db config and make `app/core/config.php` consume env values with safe defaults.

## Phase 2 - Remove Frontend Build Pipeline
1. [x] Move/copy local vendor assets into public static paths (`public/assets/css/`, `public/assets/js/`).
2. [x] Update shared layout includes:
   - Replace Tailwind output include in `app/views/partials/header.view.php`.
   - Replace bundled app script in `app/views/partials/footer.view.php`.
   - Include local Pico CSS, Alpine JS, HTMX JS directly.
3. [x] Remove build-era files and artifacts:
   - `package.json`, `package-lock.json`, `webpack.mix.cjs`, `tailwind.config.cjs`, `postcss.config.js`
   - `public/src/app.js`, `public/dist/*`, Tailwind input/output CSS files if no longer needed.
4. [x] Update `.gitignore` to reflect new workflow and remove Node-specific ignores if obsolete.

## Phase 3 - Simplify UI Structure
1. [x] Normalize views to a simple shell: title, navbar, main section, footer.
2. [x] Replace Tailwind/Flowbite-heavy markup with simpler semantic/Pico-based HTML.
3. [x] Keep interactivity focused:
   - HTMX for server-driven updates and fragment swaps.
   - Alpine only where local state is genuinely useful.

## Phase 4 - Database Source of Truth (PHP migrations + seeders)
1. [x] Create a project-native migration system in PHP:
   - `database/migrations/` with timestamped migration classes/files.
   - `database/schema_migrations` table to track applied versions.
   - CLI runner script (for example `php scripts/migrate.php`).
2. [x] Create seeders in PHP:
   - `database/seeders/` with deterministic seed classes/files.
   - CLI runner script (for example `php scripts/seed.php`).
3. [x] Start from minimal schema needed by this app (users/auth first), reusing only useful concepts from `support.sql` only if required.
4. [x] Make migration/seeder commands idempotent and safe to rerun.

## Phase 5 - Cleanup and Verification
1. [x] Verify app boots in Docker and template routes resolve (`/home`, `/login`, `/signup`, `/logout`).
2. [x] Verify removed feature routes return 404 (`/book`, `/admin`).
3. [x] Verify DB connectivity through env-driven config.
4. [x] Verify no references remain to build artifacts or Node tooling.
5. [x] Document run/setup commands in `README.md`.

## Deliverables
- [x] `docker-compose.yml` + web Dockerfile + Apache/PHP config.
- [x] Environment-driven app config for DB and base URL.
- [x] Updated layout partials with local Pico/Alpine/HTMX includes.
- [x] Build-tooling files removed.
- [x] PHP migration and seeder framework with initial schema + starter seed data.
- [x] Updated `README.md` with local dev flow.

## Recommended Execution Order
1. Containerize first (web + db).
2. Switch frontend includes to local static vendor files.
3. Remove Node/build files.
4. Implement migration + seeder system.
5. Refactor pages toward the simple shared layout.
6. Final cleanup and docs.

## Out of Scope / Future Enhancements
- Optional `composer.json` PSR-4 autoload mapping to replace most manual core `require_once` calls.
- Optional CSRF protection helpers for form POST routes.
- Optional `make logs` / `make logs-dev` convenience targets for faster troubleshooting.
- Optional auth hardening features (password complexity policy tuning, password reset token flow via email if needed).
- Optional test scaffolding (basic feature smoke tests for routes and auth flows).
