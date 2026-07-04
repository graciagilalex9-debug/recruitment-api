# Recruitment API — developer commands.
# Everything runs inside the Docker containers; you never need PHP locally.

DC   := docker compose
EXEC := $(DC) exec -e HOME=/tmp app
export UID := $(shell id -u)
export GID := $(shell id -g)

.PHONY: help setup up down build restart logs shell \
        test stan pint pint-fix quality \
        migrate fresh tinker

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

## --- Setup ---
setup: ## First-time setup from a fresh clone (env, build, start, deps, app key, migrate + seed)
	test -f .env || cp .env.example .env
	$(DC) build
	$(DC) up -d
	$(EXEC) composer install
	$(EXEC) php artisan key:generate
	$(EXEC) php artisan migrate --seed
	@echo "\nReady -> API http://localhost:8080  ·  Swagger http://localhost:8080/docs  ·  Mailpit http://localhost:8025"

## --- Stack ---
up: ## Start the whole stack
	$(DC) up -d

down: ## Stop and remove containers
	$(DC) down

build: ## Rebuild the app image
	$(DC) build

restart: down up ## Restart the stack

logs: ## Tail logs
	$(DC) logs -f

shell: ## Open a shell in the app container
	$(EXEC) bash

## --- Quality gates ---
test: ## Run the test suite (against mysql-test)
	$(EXEC) php artisan test

stan: ## Static analysis (PHPStan/Larastan)
	$(EXEC) ./vendor/bin/phpstan analyse --memory-limit=512M

pint: ## Check code style (no changes)
	$(EXEC) ./vendor/bin/pint --test

pint-fix: ## Fix code style
	$(EXEC) ./vendor/bin/pint

quality: pint stan test ## Run all quality gates

## --- Database ---
migrate: ## Run migrations (dev DB)
	$(EXEC) php artisan migrate

fresh: ## Drop, re-migrate and seed (dev DB)
	$(EXEC) php artisan migrate:fresh --seed

tinker: ## Open tinker
	$(EXEC) php artisan tinker
