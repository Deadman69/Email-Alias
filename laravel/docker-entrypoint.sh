#!/bin/sh
set -e

# Warm framework caches at container start (not at build time).
# This ensures env vars injected by the orchestrator are always reflected in
# the cached config — rebuilding the image is never needed just for a secret change.
if [ "${APP_ENV}" = "production" ]; then
    # Run pending migrations automatically. Laravel's migration lock prevents
    # concurrent runs when multiple containers start simultaneously.
    php artisan migrate --force --no-interaction

    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
