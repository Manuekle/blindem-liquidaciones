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
# Catalogs use firstOrCreate, so loading them on every start is safe; demo data stays out.
if [ "${RUN_SEED:-false}" = "true" ]; then
    php artisan db:seed --class=CatalogSeeder --force
fi
# First admin for hosts without a shell. Skipped when the account already exists.
if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
    printf '%s\n%s\n' "$ADMIN_PASSWORD" "$ADMIN_PASSWORD" \
        | SHELL_INTERACTIVE=1 php artisan blindem:admin "$ADMIN_EMAIL" || true
fi
if [ "$1" = "apache2-foreground" ] && [ "${APP_ENV:-local}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan event:cache
    php artisan view:cache
fi
exec docker-php-entrypoint "$@"
