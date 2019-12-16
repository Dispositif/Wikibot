FROM php:7.3-apache
# or FROM php:7.3-cli
# https://hub.docker.com/_/php

# Install extensions
#RUN docker-php-ext-install pdo_mysql mbstring
#RUN docker-php-ext-configure gd –with-gd –with-freetype-dir=/usr/include/

# si on monte un volume dans docker-compose :
RUN mkdir /app
# (si on monte pas de volume avec docker-compose : COPY . /app )

COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf
WORKDIR /app
RUN chown -R www-data:www-data /app && a2enmod rewrite
