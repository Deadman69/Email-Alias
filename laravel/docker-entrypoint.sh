#!/bin/sh
set -e

# Warm framework caches at container start (not at build time).
# This ensures env vars injected by the orchestrator are always reflected in
# the cached config — rebuilding the image is never needed just for a secret change.
if [ "${APP_ENV}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
