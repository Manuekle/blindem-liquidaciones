FROM php:8.3-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libpq-dev libicu-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_pgsql zip intl gd bcmath \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.json
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
COPY . .
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache \
    && composer dump-autoload --optimize \
    && php artisan package:discover --ansi
EXPOSE 8000
CMD ["sh","-c","php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
