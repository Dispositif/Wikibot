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

.PHONY: externref # 	extern reference wikipedia
externref:
	php ./src/Application/CLI/externRefprocess.php

.PHONY: googleExtern # 	extern reference google
googleExtern:
	php ./src/Application/CLI/gooExternProcess.php

.PHONY: cleanError # 	clean error reports on wiki
cleanError:
	php ./src/Application/CLI/cleanErrorReport.php

.PHONY: ouvrageComplete # 	complete ouvrage
ouvrageComplete:
	php ./src/Application/CLI/ouvrageCompleteProcess.php

.PHONY: ouvrageEdit # 	edit ouvrage
ouvrageEdit:
	php ./src/Application/CLI/ouvrageEditProcess.php
