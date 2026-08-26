#!/usr/bin/env sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY must be configured before starting the production container." >&2
    exit 1
fi

if [ "$(id -u)" = "0" ]; then
    # Named Docker volumes start owned by root; prepare only Laravel runtime paths.
    mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache
    chmod -R u+rwX,g+rwX storage bootstrap/cache

    # PHP-FPM starts a privileged master process and drops its pool workers to www-data.
    if [ "${1:-}" = "php-fpm" ]; then
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        exec "$@"
    fi

    exec gosu www-data "$0" "$@"
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
