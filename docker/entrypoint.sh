#!/bin/sh
set -eu
export PORT="${PORT:-8000}"
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi
if [ "$1" = "apache2-foreground" ] && [ "${APP_ENV:-local}" = "production" ]; then
    php artisan config:cache
    php artisan view:cache
fi
exec docker-php-entrypoint "$@"
