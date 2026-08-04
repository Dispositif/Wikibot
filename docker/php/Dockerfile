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
    && rm -rf /var/lib/apt/lists/*

# ext-sqlite3 (composer.json: require-dev only, for PHPUnit) intentionally not
# rebuilt here: this image is for prod workers, not tests.
# (sqlite3/pdo_sqlite/readline are already shipped precompiled in the
# php:8.3-cli-bookworm base image — no need, and even risky, to rebuild them:
# running "docker-php-ext-install readline" on this base fails on a known
# readline/libedit compile bug on Debian bookworm. Leave them alone.)

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/wikibot.ini

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction --no-progress --prefer-dist --no-autoloader \
        --no-dev

COPY . .
RUN composer dump-autoload --optimize

CMD ["php", "-v"]
