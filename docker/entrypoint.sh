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
# Seeders are idempotent, so running them on every start is safe.
# Demo accounts and data load only when ALLOW_DEMO_SEED=true; otherwise just catalogs.
if [ "${RUN_SEED:-false}" = "true" ]; then
    if [ "${ALLOW_DEMO_SEED:-false}" = "true" ]; then
        php artisan db:seed --force
        php artisan db:seed --class=DemoSettlementSeeder --force
    else
        php artisan db:seed --class=CatalogSeeder --force
    fi
fi
# Opt-in: the two Excel test cases (template method), without demo users. Needs an existing admin.
if [ "${SEED_EXCEL_CASES:-false}" = "true" ]; then
    php artisan db:seed --class=ExcelCaseSeeder --force
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
