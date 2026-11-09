#!/bin/sh
set -eu

APP_HOME="${APP_HOME:-/var/www/html}"
cd "$APP_HOME"

PORT="${PORT:-8080}"
export PORT

echo "[entrypoint] Preparing storage directories..."
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Persistent Render disks remount over storage/; ensure ownership is usable.
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwx storage bootstrap/cache || true

echo "[entrypoint] Rendering Nginx config for PORT=${PORT}..."
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template \
    > /etc/nginx/conf.d/default.conf
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true

if [ ! -f artisan ]; then
    echo "[entrypoint] ERROR: artisan not found in ${APP_HOME}" >&2
    exit 1
fi

if [ -z "${APP_KEY:-}" ]; then
    echo "[entrypoint] ERROR: APP_KEY is not set. Generate one with 'php artisan key:generate --show' and set it in Render." >&2
    exit 1
fi

echo "[entrypoint] Discovering packages / caching config..."
php artisan package:discover --ansi || true

# Cache only when env is complete enough; failures should not skip serving if
# the operator intentionally omitted optional AI keys.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Production-safe migrations (never migrate:fresh). Opt out with RUN_MIGRATIONS=false.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[entrypoint] Running migrations (php artisan migrate --force)..."
    php artisan migrate --force --no-interaction
else
    echo "[entrypoint] Skipping migrations (RUN_MIGRATIONS=${RUN_MIGRATIONS:-})"
fi

# Optional one-time admin bootstrap. Requires strong ADMIN_PASSWORD in production.
if [ "${RUN_DB_SEED:-false}" = "true" ]; then
    echo "[entrypoint] Running database seeders..."
    php artisan db:seed --force --no-interaction
fi

echo "[entrypoint] Starting process manager..."
exec "$@"
