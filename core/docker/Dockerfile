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
RUN composer install --no-interaction --no-progress --optimize-autoloader

EXPOSE 80
