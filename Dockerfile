# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.3.36
ARG COMPOSER_VERSION=2.11.1

FROM composer:${COMPOSER_VERSION} AS composer

# Runtime libraries and Apache setup shared by every final image.
FROM php:${PHP_VERSION}-apache-bookworm AS base
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq5 libzip4 libicu72 libpng16-16 libjpeg62-turbo libfreetype6 \
    && a2enmod rewrite headers \
    && sed -ri 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf \
    && rm -rf /var/lib/apt/lists/*
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/alias.conf /etc/apache2/mods-available/alias.conf
COPY docker/ports.conf /etc/apache2/ports.conf
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/blindem-entrypoint
WORKDIR /var/www/html
ENV PORT=8000
EXPOSE 8000
HEALTHCHECK --interval=10s --timeout=3s --start-period=15s --retries=6 \
    CMD ["php", "-r", "exit(@file_get_contents('http://127.0.0.1:'.getenv('PORT').'/up')===false?1:0);"]
ENTRYPOINT ["blindem-entrypoint"]
CMD ["apache2-foreground"]

# Compiles the PHP extensions; headers and compilers stay in this stage.
FROM base AS php-ext
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libpq-dev libicu-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql zip intl gd bcmath \
    && rm -rf /var/lib/apt/lists/*

# Production dependencies and optimized autoloader.
FROM php-ext AS vendor
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --no-progress
COPY . .
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --optimize --no-dev --no-scripts \
    && php artisan package:discover --ansi

# Local development: dev dependencies and composer; docker-compose mounts the source over it.
# Runs as root so the entrypoint can fix storage ownership on bind mounts.
FROM php-ext AS dev
COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader --no-progress
COPY . .
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --optimize --no-scripts \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

# Last stage, so a plain `docker build` produces it.
FROM base AS production
COPY --from=php-ext /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-ext /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY docker/php/production.ini /usr/local/etc/php/conf.d/zz-production.ini
# The base image keeps compilers for docker-php-ext-install; production does not need them.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*
# Code stays owned by root; the server user only writes storage and the framework cache.
COPY --from=vendor /var/www/html /var/www/html
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache
USER www-data
