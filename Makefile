.PHONY: help up up-build down up-dev up-dev-build down-dev composer-install composer-update migrate seed db-status db-reset prune-all

COMPOSE_BASE = docker compose
COMPOSE_DEV = docker compose -f docker-compose.yml -f docker-compose.dev.yml

help:
	@printf "Targets:\n"
	@printf "  make up            - compose up (attached)\n"
	@printf "  make up-build      - compose up --build (attached)\n"
	@printf "  make down          - compose down\n"
	@printf "  make up-dev        - compose up with dev override (attached)\n"
	@printf "  make up-dev-build  - compose up --build with dev override (attached)\n"
	@printf "  make down-dev      - compose down with dev override\n"
	@printf "  make composer-install - run composer install in dev container\n"
	@printf "  make composer-update  - run composer update in dev container\n"
	@printf "  make migrate       - run PHP migrations in dev container\n"
	@printf "  make seed          - run PHP seeders in dev container\n"
	@printf "  make db-status     - print DB migration/seeder/user status\n"
	@printf "  make db-reset      - drop/recreate DB, then migrate + seed (dev)\n"
	@printf "  make prune-all     - docker system prune -a --volumes (destructive)\n"

up:
	$(COMPOSE_BASE) up

up-build:
	$(COMPOSE_BASE) up --build

down:
	$(COMPOSE_BASE) down

up-dev:
	$(COMPOSE_DEV) up

up-dev-build:
	$(COMPOSE_DEV) up --build

down-dev:
	$(COMPOSE_DEV) down

composer-install:
	$(COMPOSE_DEV) run --rm --no-deps --user "$$(id -u):$$(id -g)" web composer install

composer-update:
	$(COMPOSE_DEV) run --rm --no-deps --user "$$(id -u):$$(id -g)" web composer update

migrate:
	$(COMPOSE_DEV) run --rm --no-deps --user "$$(id -u):$$(id -g)" web php scripts/migrate.php

seed:
	$(COMPOSE_DEV) run --rm --no-deps --user "$$(id -u):$$(id -g)" web php scripts/seed.php

db-status:
	$(COMPOSE_DEV) run --rm --no-deps --user "$$(id -u):$$(id -g)" web php scripts/db-status.php

db-reset:
	$(COMPOSE_DEV) exec db mariadb -uroot -p"$${DB_ROOT_PASS:-root}" -e "DROP DATABASE IF EXISTS \`$${DB_NAME:-phpmon}\`; CREATE DATABASE \`$${DB_NAME:-phpmon}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON \`$${DB_NAME:-phpmon}\`.* TO '$${DB_USER:-phpmon}'@'%'; FLUSH PRIVILEGES;"
	$(MAKE) migrate
	$(MAKE) seed

prune-all:
	docker system prune -a --volumes
