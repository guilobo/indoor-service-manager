#!/usr/bin/env sh
set -eu

cd /var/www/html

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rw storage bootstrap/cache

if [ ! -L public/storage ]; then
    php artisan storage:link --force --no-interaction || true
fi

php artisan optimize:clear --no-interaction || true
php artisan package:discover --ansi --no-interaction || true

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

if [ "${CACHE_CONFIG:-true}" = "true" ]; then
    php artisan config:cache --no-interaction || true
fi

if [ "${CACHE_ROUTES:-false}" = "true" ]; then
    php artisan route:cache --no-interaction || true
fi

if [ "${CACHE_VIEWS:-true}" = "true" ]; then
    php artisan view:cache --no-interaction || true
fi

if [ "${CACHE_EVENTS:-false}" = "true" ]; then
    php artisan event:cache --no-interaction || true
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
