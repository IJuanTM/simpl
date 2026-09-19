ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-apache

# ext-fileinfo ships enabled by default on this base image; the others still need building.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libonig-dev libsodium-dev openssl \
        default-libmysqlclient-dev default-mysql-client \
    && docker-php-ext-install pdo pdo_mysql mbstring sodium \
    && a2enmod rewrite headers deflate expires ssl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Makes container environment variables visible to $_ENV, which database.php reads directly.
COPY docker/php/simpl.ini /usr/local/etc/php/conf.d/simpl.ini
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Self-signed cert for local HTTPS; covers localhost/127.0.0.1 plus the *.test/*.local
# hostnames the "Matching your real APP_URL" docs recommend. A real hostname outside that
# still works, just with an added browser hostname-mismatch warning on top of the usual
# self-signed one.
RUN mkdir -p /etc/apache2/ssl && openssl req -x509 -nodes -newkey rsa:2048 -days 825 \
        -keyout /etc/apache2/ssl/simpl.key -out /etc/apache2/ssl/simpl.crt \
        -subj "/CN=localhost" \
        -addext "subjectAltName=DNS:localhost,DNS:*.test,DNS:*.local,IP:127.0.0.1"

WORKDIR /var/www/html

# Baked at build time to seed the named vendor volume; bracket globs tolerate a missing composer.lock.
COPY composer.jso[n] composer.loc[k] ./
COPY src/app ./src/app
COPY src/.env ./src/.env
RUN composer install --no-interaction --no-progress --optimize-autoloader

EXPOSE 80
