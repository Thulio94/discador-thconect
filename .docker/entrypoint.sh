#!/usr/bin/env sh
set -e

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if [ -f composer.json ] && [ ! -f vendor/autoload.php ]; then
    mkdir -p vendor

    if mkdir vendor/.install-lock 2>/dev/null; then
        trap 'rm -rf vendor/.install-lock' EXIT
        composer install --no-interaction --prefer-dist
        rm -rf vendor/.install-lock
        trap - EXIT
    else
        while [ ! -f vendor/autoload.php ]; do
            sleep 2
        done
    fi
fi

if [ -f artisan ] && [ -z "$APP_KEY" ]; then
    php artisan key:generate --force >/dev/null 2>&1 || true
fi

exec "$@"
