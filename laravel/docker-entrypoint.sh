#!/bin/sh
set -e

# Hydrate public volume on first boot
if [ ! -f /var/www/html/public/index.php ]; then
    echo "Initializing public volume..."
    cp -R /opt/public-template/. /var/www/html/public/
fi

mkdir -p storage/logs
mkdir -p bootstrap/cache

chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan migrate --force --no-interaction
[ -L public/storage ] || php artisan storage:link

php artisan optimize:clear

# Warm framework caches at container start
if [ "${APP_ENV}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    # php artisan view:cache
    php artisan event:cache
fi

exec "$@"