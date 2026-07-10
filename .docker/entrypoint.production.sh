#!/usr/bin/env sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY must be configured before starting the production container." >&2
    exit 1
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
