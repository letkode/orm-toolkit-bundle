#!/bin/bash
DOCKER_PHP = lka-package-orm-toolkit

OS := $(shell uname)
ifeq ($(OS),Darwin)
	UID = $(shell id -u)
else ifeq ($(OS),Linux)
	UID = $(shell id -u)
else
	UID = 1000
endif

help: ## Show this help message
	@echo 'usage: make [target]'
	@echo
	@echo 'targets:'
	@egrep '^(.+)\:\ ##\ (.+)' ${MAKEFILE_LIST} | column -t -c 2 -s ':#'


# Docker commands
docker-run: ## Start the containers
	U_ID=${UID} docker-compose up -d

docker-stop: ## Stop the containers
	U_ID=${UID} docker-compose stop

docker-down: ## Stop the containers
	U_ID=${UID} docker-compose down

docker-restart: ## Restart the containers
	U_ID=${UID} docker-compose restart

docker-build: ## Rebuilds all the containers
	U_ID=${UID} docker-compose build

docker-build-simple: ## Rebuilds single container
	U_ID=${UID} docker-compose build ${serv}

docker-build-no-cache: ## Rebuilds single container
	U_ID=${UID} docker-compose build --no-cache

docker-build-no-cache-simple: ## Rebuilds single container
	U_ID=${UID} docker-compose build ${serv} --no-cache


# SSH commands
docker-conecnt: ## ssh into the db container
	docker exec -it --user ${UID} ${DOCKER_PHP} /bin/sh


# Composer commands
composer-install: ## Installs composer dependencies
	docker exec -it --user ${UID} ${DOCKER_PHP} composer install --no-scripts --no-interaction --optimize-autoloader

composer-update: ## Update composer dependencies
	docker exec -it --user ${UID} ${DOCKER_PHP} composer update --no-scripts --no-interaction --optimize-autoloader

composer-require: ## Install a composer package. Usage: make composer-require pkg=vendor/package
	docker exec -it --user ${UID} ${DOCKER_PHP} composer require $(pkg)

composer-require-dev: ## Install a composer dev package. Usage: make composer-require-dev pkg=vendor/package
	docker exec -it --user ${UID} ${DOCKER_PHP} composer require --dev $(pkg)


# Git hooks
hooks: ## Installs git hooks from tools/hooks/
	cp tools/hooks/pre-commit .git/hooks/pre-commit
	chmod +x .git/hooks/pre-commit
	@echo "Git hooks installed."


# Tests
test: ## Run all tests
	U_ID=${UID} docker exec -it --user ${UID} ${DOCKER_PHP} php vendor/bin/phpunit

test-file: ## Run a specific test file. Usage: make test-file f=tests/Unit/...
	U_ID=${UID} docker exec -it --user ${UID} ${DOCKER_PHP} php vendor/bin/phpunit $(f)

test-filter: ## Run tests matching a method name. Usage: make test-filter m=testMethodName
	U_ID=${UID} docker exec -it --user ${UID} ${DOCKER_PHP} php vendor/bin/phpunit --filter $(m)

# Code quality
cs-fixer: ## Runs php-cs-fixer following Symfony rules
	U_ID=${UID} docker exec -it --user ${UID} ${DOCKER_PHP} php vendor/bin/php-cs-fixer fix

cs-fixer-diff: ## Runs php-cs-fixer return diff
	U_ID=${UID} docker exec -it --user ${UID} ${DOCKER_PHP} php vendor/bin/php-cs-fixer fix --dry-run --diff
