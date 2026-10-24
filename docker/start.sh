#!/bin/sh
# Runs every time Render starts the site.
set -e

# Render gives a random secret; turn it into a Laravel key of the right length.
if [ -z "$APP_KEY" ] && [ -n "$APP_SECRET" ]; then
    APP_KEY="base64:$(php -r 'echo base64_encode(hash("sha256", getenv("APP_SECRET"), true));')"
    export APP_KEY
fi

php artisan config:cache
php artisan view:cache
php artisan migrate --force
php artisan quoteit:demo-data

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
