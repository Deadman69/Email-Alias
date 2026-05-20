FROM php:8.4-fpm-alpine

# Extensions PHP nécessaires
RUN apk add --no-cache \
    postgresql-dev \
    nodejs \
    npm \
    git \
    unzip \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        pcntl \
        bcmath \
        opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dépendances PHP d'abord (layer cache)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Dépendances JS
COPY package.json package-lock.json ./
RUN npm ci

# Code source
COPY . .

# Finaliser Composer + build assets
RUN composer dump-autoload --optimize \
    && npm run build \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
