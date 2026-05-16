# IT Helpdesk System - PHP MVC + HTMX + AlpineJS (no build system)

## Introduction

A straightforward IT support ticket handling system built on a custom PHP MVC monolith without framework bloat.

- Provides a simple support queue for creating, tracking, assigning, prioritizing, replying to, resolving, and auto-closing IT support tickets.
- Uses server-rendered PHP views with small HTMX and Alpine enhancements instead of a SPA or frontend build pipeline.
- Supports admin-managed local users with roles for users, staff, and admins.
- Includes optional Active Directory / LDAPS authentication while keeping application roles controlled locally.
- Records ticket events for key workflow changes such as creation, replies, assignment, priority changes, status changes, reopening, and auto-close.
- Keeps deployment practical with Docker Compose, Apache, PHP, MariaDB, local static assets, project-native migrations, and seeders.
- Defers heavier features such as email notifications, attachments, internal notes, SLAs, departments, and reporting until after the core ticket workflow is stable.

## Docker commands

Run these from the project root:

- `make up` - run base compose stack in attached mode
- `make up-build` - rebuild and run base stack in attached mode
- `make down` - stop base stack
- `make up-dev` - run dev stack (bind mount + UID/GID mapping) in attached mode
- `make up-dev-build` - rebuild and run dev stack in attached mode
- `make down-dev` - stop dev stack
- `make composer-install` - run `composer install` in dev container (writes to host via bind mount)
- `make composer-update` - run `composer update` in dev container (writes to host via bind mount)
- `make migrate` - run PHP migrations in dev container
- `make seed` - run PHP seeders in dev container
- `make close-resolved` - auto-close tickets resolved longer than `TICKET_AUTO_CLOSE_DAYS` days
- `make db-status` - print migration/seeder/user table status from dev DB
- `make db-reset` - drop/recreate dev database, then run migrations + seeders
- `make prune-all` - run `docker system prune -a --volumes` (destructive)

## SELinux note (Fedora/RHEL-like hosts)

- The dev bind mount uses `:z` in `docker-compose.dev.yml` so SELinux labels are shared safely across the long-running web container and one-off `docker compose run` commands.
- If you hit 403 errors like "search permissions are missing on a component of the path", verify parent path execute bits (for example `/home/<user>` should be at least `711`) and restart the dev stack with `make down-dev && make up-dev`.

## Composer

- `vendor/` is intentionally gitignored.
- `composer.json` keeps direct dependencies only; packages under `vendor/symfony`, `vendor/psr`, etc. are transitive dependencies of direct packages (for example `nesbot/carbon`).
- The web image runs `composer install` during build so image-copy mode is self-contained.
- In dev bind-mount mode, run `make composer-install` after dependency changes so `vendor/` is present on your host-mounted project.

## Database migrations and seeders

- Migrations are in `database/migrations/`.
- Seeders are in `database/seeders/`.
- Run `make migrate` to apply pending migrations.
- Run `make seed` to apply pending seeders.
- Run `make db-status` to check whether migration/seeder tables and users table are present.
- Run `make db-reset` to rebuild the dev database from scratch.
- Initial seeder creates `admin@example.com` with password `password` (change after first login in real projects).

## Ticket maintenance

- Resolved tickets auto-close after `TICKET_AUTO_CLOSE_DAYS` days; default is `14`.
- Run `make close-resolved` to close eligible tickets manually in development.
- In production, run `php scripts/close-resolved-tickets.php` from cron, for example once per day.

## Active Directory / LDAPS

- LDAP settings are environment-driven; use `.env.example` as the template.
- Set `LDAP_ENABLED=true` only after `LDAP_HOST`, `LDAP_BASE_DN`, bind credentials, and TLS settings are correct.
- The app attempts local authentication first, then LDAP if local auth fails.
- LDAP users are created or synced locally after successful AD authentication.
- LDAP passwords are never stored locally; roles remain controlled in the app.

## Running without Docker

- Use the Apache vhost config in `docker/apache/000-default.conf` as the reference setup, or an equivalent config for other web servers.
- Important web-server behavior:
  - Serve from `public/` as the document root.
  - Rewrite non-file/non-directory routes to `index.php?url=...`.
  - Keep static assets under `public/assets/...` directly web-accessible.
- Ensure PHP extensions required by this app are installed (`gd`, `mysqli`, `pdo_mysql`, `pdo_sqlite`, `curl`, `fileinfo`, `intl`, `ldap`, `exif`, `mbstring`).
- Copy `.env.example` to `.env` and set your `APP_*` and `DB_*` values for your local server/database.
- Install dependencies with Composer from project root: `composer install`.
- Initialize database schema/data with `php scripts/migrate.php` then `php scripts/seed.php`.

If using docker, all of the above is taken care of by the docker compose setup included in the repo.
