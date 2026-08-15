FROM php:8.3-cli-bookworm

# Debian/glibc base rather than Alpine: the bot does heavy French text processing
# (iconv, intl, transliteration) and musl-libc has known gaps in these conversions.
# Alpine's RAM saving barely matters here (a PHP process's RAM depends on the
# interpreter/opcache/loaded data, not on the base libc).

RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libcurl4-openssl-dev \
        libonig-dev \
        default-libmysqlclient-dev \
        git \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        curl \
        pdo_mysql \
        mbstring \
        sockets \
        pcntl \
    && rm -rf /var/lib/apt/lists/*

# pcntl : SIGTERM handling, so a long-lived worker (restart: unless-stopped) can finish
# the article it is on and exit cleanly when `docker compose up -d` recreates it for a
# deploy, instead of being killed mid-edit. Absent from the base image, and unlike
# readline/sqlite3 above it does compile here.

# ext-sqlite3/pdo_sqlite/readline are already shipped precompiled in the
# php:8.3-cli-bookworm base image — no need, and even risky, to rebuild them:
# running "docker-php-ext-install readline" (or sqlite3) on this base fails
# on a real compile bug (readline needs libedit; sqlite3 has no buildable
# ext/ source in this image). Leave them alone.

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/wikibot.ini

WORKDIR /app

# One image for both workers and tests
# `docker compose run --rm <any-service> vendor/bin/phpunit`
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction --no-progress --prefer-dist --no-autoloader

COPY . .
RUN composer dump-autoload --optimize

CMD ["php", "-v"]
