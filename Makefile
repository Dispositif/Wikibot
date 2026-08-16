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

.PHONY: up # 	Start MySQL (persistent, safe default: does not touch the workers)
up: docker/mysql/log
	docker compose up -d mysql

# Same gotcha as the google_quota files below: this dir must exist BEFORE
# `docker compose up`, otherwise Docker bind-mounts a root-owned directory
# in its place and MySQL can't write its slow query log there.
docker/mysql/log:
	mkdir -p $@

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
	docker compose --profile workers build

# Gitignored runtime state (real Google API quota count + its flock() lock file), not
# part of the repo. Docker's file-bind-mounts for goo-extern (compose.yaml) need both
# files to exist BEFORE `docker compose run`/`up`, otherwise Docker silently creates a
# directory in their place on a fresh checkout/deployment, and the worker crashes.
src/Infrastructure/resources/google_quota.json:
	echo '{"date":"2020-01-01T00:00:20-07:00","count":0}' > $@

src/Infrastructure/resources/google_quota.lock:
	touch $@

# Same gotcha, same fix : per-worker gitignored dedup files (extern-ref/existing-ref
# read them with file() on startup) bind-mounted individually in compose.yaml -- must
# exist as plain files BEFORE `docker compose run` on a fresh checkout, else Docker
# bind-mounts a directory in their place (2026-08-14 : bit existing-ref in prod).
src/Application/resources/article_externRef_edited.txt src/Application/resources/article_rawExternRef_edited.txt src/Application/resources/article_existingRef_edited.txt:
	touch $@

# To dry-run a worker instead, replaces the default rather than appending to it, e.g.:
#   docker compose run --rm extern-ref php src/Application/CLI/externRefProcess.php --dry-run --page="Some Title"
.PHONY: run # 	Run a one-shot worker for real: make run service=goo-extern|extern-ref|last-extern-ref|raw-extern-ref|existing-ref|fix-typo|ouvrage-scan|ouvrage-complete|ouvrage-edit|test-extern-link (add args="'http://...'" for test-extern-link)
run: src/Infrastructure/resources/google_quota.json src/Infrastructure/resources/google_quota.lock src/Application/resources/article_externRef_edited.txt src/Application/resources/article_rawExternRef_edited.txt src/Application/resources/article_existingRef_edited.txt
	docker compose run --rm $(service) $(args)

# Deploy loop for a non-stop worker (restart: unless-stopped, e.g. last-extern-ref).
# `up -d` recreates the container on the freshly built image: SIGTERM first, the worker
# finishes the article it is on and exits 0, then the new one starts. Nothing to stop by
# hand, and no window where two workers overlap.
# NOT for one-shot workers -- those pick up a new image on their next `make run`.
.PHONY: deploy # 	Rebuild + restart a non-stop worker gracefully: make deploy service=last-extern-ref
deploy: src/Infrastructure/resources/google_quota.json src/Infrastructure/resources/google_quota.lock src/Application/resources/article_externRef_edited.txt src/Application/resources/article_rawExternRef_edited.txt src/Application/resources/article_existingRef_edited.txt
	docker compose build $(service)
	docker compose --profile workers up -d $(service)

.PHONY: stop-worker # 	Stop a non-stop worker for good (restart policy won't revive it): make stop-worker service=last-extern-ref
stop-worker:
	docker compose stop $(service)

.PHONY: db-migrate # 	Apply pending schema migrations (src/Infrastructure/resources/migrations/*.sql)
db-migrate:
	docker compose run --rm db-migrate

.PHONY: rc-scan # 	RC scan: advances rc_cursor, writes rc_signal
rc-scan:
	docker compose run --rm rc-scan

days ?= 0
.PHONY: rc-clean # 	Purge rc_signal's 'observed' samples now: make rc-clean [days=N] (default 0 = all)
rc-clean:
	docker compose run --rm rc-scan php src/Application/CLI/purgeRcSignal.php --older-than-days=$(days)

.PHONY: restart-mysql # 	Restart the MySQL container only
restart-mysql:
	docker compose restart mysql

.PHONY: up-tor # 	Start Tor (persistent, safe default: does not touch the workers)
up-tor:
	docker compose up -d tor

.PHONY: restart-tor # 	Restart the Tor container only (e.g. after a docker/tor/ change)
restart-tor:
	docker compose up -d --build tor
