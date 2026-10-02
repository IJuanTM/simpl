ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-apache

ARG UID=1000
ARG GID=1000

# PDO, mbstring, sodium and fileinfo ship compiled into this base image; only pdo_mysql needs building.
RUN apt-get update && apt-get install -y --no-install-recommends git unzip openssl \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers deflate expires ssl \
    && rm -rf /var/lib/apt/lists/*

# Matching the host user's UID/GID keeps bind-mounted files writable by Apache and owned by the host user on Linux; -o tolerates IDs already taken in the image (e.g. macOS's GID 20).
RUN groupmod -o -g ${GID} www-data && usermod -o -u ${UID} -g www-data www-data

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Makes container environment variables visible to $_ENV, which database.php reads directly.
COPY docker/php/simpl.ini /usr/local/etc/php/conf.d/simpl.ini
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Explicit CA:FALSE is required - openssl req -x509's default extensions mark the cert as its own CA, which Chrome rejects outright (ERR_SSL_PROTOCOL_ERROR).
RUN mkdir -p /etc/apache2/ssl && openssl req -x509 -nodes -newkey rsa:2048 -days 825 \
        -keyout /etc/apache2/ssl/simpl.key -out /etc/apache2/ssl/simpl.crt \
        -subj "/CN=localhost" \
        -addext "subjectAltName=DNS:localhost,DNS:*.test,DNS:*.local,IP:127.0.0.1" \
        -addext "basicConstraints=critical,CA:FALSE" \
        -addext "keyUsage=critical,digitalSignature,keyEncipherment" \
        -addext "extendedKeyUsage=serverAuth"

WORKDIR /var/www/html

# Baked at build time to seed the named vendor volume; bracket globs tolerate a missing composer.lock.
COPY composer.jso[n] composer.loc[k] ./
COPY src/app ./src/app
COPY src/.env ./src/.env

# Installed as www-data so the vendor volume it seeds stays writable for later `simpl composer install` runs.
RUN mkdir -p src/vendor && chown -R www-data:www-data /var/www
USER www-data
RUN composer install --no-interaction --no-progress --optimize-autoloader
USER root

EXPOSE 80 443
