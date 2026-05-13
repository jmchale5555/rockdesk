# PHP MVC + HTMX + AlpineJS (no build system)

## Introduction

This repo is intended as a starting point for projects that need a clean PHP MVC monolith without framework bloat.

- Keeps only the simple MVC/OOP foundation from the legacy codebase.
- Prioritizes readability and low abstraction over framework-like patterns or excessive indirection.
- Prefers server-side rendering with small interactive enhancements, not an SPA architecture.

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

## Running without Docker

- Use the Apache vhost config in `docker/apache/000-default.conf` as the reference setup, or an equivalent config for other web servers.
- Important web-server behavior:
  - Serve from `public/` as the document root.
  - Rewrite non-file/non-directory routes to `index.php?url=...`.
  - Keep static assets under `public/assets/...` directly web-accessible.
- Ensure PHP extensions required by this app are installed (`gd`, `mysqli`, `pdo_mysql`, `pdo_sqlite`, `curl`, `fileinfo`, `intl`, `exif`, `mbstring`).
- Copy `.env.example` to `.env` and set your `APP_*` and `DB_*` values for your local server/database.
- Install dependencies with Composer from project root: `composer install`.
- Initialize database schema/data with `php scripts/migrate.php` then `php scripts/seed.php`.

If using docker, all of the above is taken care of by the docker compose setup included in the repo.