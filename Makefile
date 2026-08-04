# Description: Makefile for the project

# php ./vendor/bin/phpstan analyse -c phpstan.neon -l 4 src/Domain/Publisher/SeoSanitizer.php

# default target
all: help

.PHONY: help # 	--------- HELP ---------
help:
	@grep '^.PHONY: .* #' Makefile | sed 's/\.PHONY: \(.*\) # \(.*\)/\1 \2/' | expand -t20

.PHONY: phpstan-all # 	Launch local Phpstan
phpstan-all:
	php -d memory_limit=1G ./vendor/bin/phpstan

.PHONY: phpstan # 	phpstan path="src/.../myclass" (level 4)
phpstan:
	php -d memory_limit=1G ./vendor/bin/phpstan analyse -c phpstan.neon -l 4 $(path)

.PHONY: phpunit # 	Phpunit all tests
phpunit:
	php ./vendor/bin/phpunit

.PHONY: coverage # 	Phpunit with coverage
coverage:
	php ./vendor/bin/phpunit --coverage-html coverage

.PHONY: rector # 	rector path="src/.../myclass" (dry run)
rector:
	php ./vendor/bin/rector process ${path} --dry-run

.PHONY: rector-hard # 	make rector-hard path="src/.../myclass" (HARD RUN!)
rector-hard:
	php ./vendor/bin/rector process ${path}

.PHONY: cleanError # 	clean error reports on wiki
cleanError:
	php ./src/Application/CLI/cleanErrorReport.php

.PHONY: ouvrageComplete # 	complete ouvrage (not yet dockerized)
ouvrageComplete:
	php ./src/Application/CLI/ouvrageCompleteProcess.php

.PHONY: ouvrageEdit # 	edit ouvrage (not yet dockerized)
ouvrageEdit:
	php ./src/Application/CLI/ouvrageEditProcess.php

# externref/googleExtern direct-call targets removed: superseded by the
# Docker workers below (extern-ref, goo-extern), which is now how these
# pipelines actually run.

.PHONY: up # 	Start MySQL (persistent, safe default: does not touch the workers)
up:
	docker compose up -d mysql

.PHONY: down # 	Stop containers (keeps MySQL data)
down:
	docker compose down

.PHONY: ps # 	Show running containers
ps:
	docker compose ps

.PHONY: logs # 	Follow logs of a service, e.g. make logs service=mysql
logs:
	docker compose logs -f $(service)

.PHONY: build # 	(Re)build worker images after a code/Dockerfile change
build:
	docker compose build

# To dry-run a worker instead, replaces the default rather than appending to it, e.g.:
#   docker compose run --rm extern-ref php src/Application/CLI/externRefProcess.php --dry-run --page="Some Title"
.PHONY: run # 	Run a one-shot worker for real: make run service=goo-extern|extern-ref|last-extern-ref|zizibot-talk
run:
	docker compose run --rm $(service)

.PHONY: restart-mysql # 	Restart the MySQL container only
restart-mysql:
	docker compose restart mysql
