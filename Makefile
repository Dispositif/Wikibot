# Description: Makefile for the project

.PHONY: phpstan # Launch local phpstan
phpstan:
	php -d memory_limit=1G ./vendor/bin/phpstan

.PHONY: phpunit # Phpunit all tests
phpunit:
	php ./vendor/bin/phpunit



.PHONY: help # Generate list of targets with descriptions
help:
	@grep '^.PHONY: .* #' Makefile | sed 's/\.PHONY: \(.*\) # \(.*\)/\1 \2/' | expand -t20
