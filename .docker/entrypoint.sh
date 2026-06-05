#!/usr/bin/env sh
set -e

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if [ -f artisan ] && [ -z "$APP_KEY" ]; then
    php artisan key:generate --force >/dev/null 2>&1 || true
fi

exec "$@"
