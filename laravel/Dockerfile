# ── Stage 1: builder ──────────────────────────────────────────────────────────
# Contains the full toolchain (Node, npm, git, composer) to compile assets and
# install dependencies. None of this ends up in the production image.
FROM php:8.4-fpm-alpine AS builder

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

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP deps first (layer cache hit on most rebuilds)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Install JS deps + compile assets
COPY package.json package-lock.json ./
RUN npm ci

# Copy full source and finalize
COPY . .
RUN composer dump-autoload --optimize && npm run build

# ── Stage 2: runtime ──────────────────────────────────────────────────────────
# Lean image: only PHP runtime + extensions + compiled application.
# No Node, npm, git, or build toolchain.
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        pcntl \
        bcmath \
        opcache

# Production OPcache configuration
COPY .docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Copy compiled application from builder, already owned by www-data
COPY --from=builder --chown=www-data:www-data /var/www/html /var/www/html

# Entrypoint: warms framework caches at runtime so env vars are always fresh
COPY --chown=www-data:www-data docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Run as non-root for defense-in-depth
USER www-data

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
