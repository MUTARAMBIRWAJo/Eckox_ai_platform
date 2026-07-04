# =============================================================================
# Eckox AI Platform — Production Dockerfile (Render-compatible)
# Multi-stage: composer builder → nginx + php-fpm runner
# HTTP: nginx listens on ${PORT:-10000}, proxies to php-fpm on 127.0.0.1:9000
# =============================================================================

# ── Stage 1: Build ────────────────────────────────────────────────────────────
FROM php:8.4-fpm-alpine AS builder

WORKDIR /app

# Build-time system deps
RUN apk add --no-cache \
    git curl unzip zip \
    libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev \
    postgresql-dev icu-dev linux-headers bash

# PHP extensions
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
       gd pdo pdo_pgsql zip bcmath sockets opcache intl

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Dependency layer cache
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Full application
COPY . .

# Clear any dev bootstrap cache
RUN rm -f bootstrap/cache/packages.php \
           bootstrap/cache/services.php \
           bootstrap/cache/config.php \
           bootstrap/cache/routes.php

# Production hardened config overrides
COPY config/database.php config/database.php
COPY config/services.php config/services.php

# Optimised autoloader
RUN composer dump-autoload --no-dev --optimize

# ── Stage 2: Runtime ──────────────────────────────────────────────────────────
FROM php:8.4-fpm-alpine AS runtime

WORKDIR /var/www/html

# Runtime deps: nginx + supervisor + envsubst (gettext)
RUN apk add --no-cache \
    nginx supervisor gettext \
    libzip libpng libjpeg-turbo freetype libpq \
    icu-libs fcgi curl bash

# Copy PHP extensions from builder
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d     /usr/local/etc/php/conf.d

# OPcache — production settings
RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.memory_consumption=256"; \
    echo "opcache.interned_strings_buffer=16"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.revalidate_freq=0"; \
    echo "opcache.validate_timestamps=0"; \
    echo "opcache.fast_shutdown=1"; \
} >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

# PHP-FPM: listen on TCP 9000 (not unix socket) for nginx compatibility
# Run as www-data
RUN sed -i \
    -e 's|listen = 9000|listen = 127.0.0.1:9000|g' \
    -e 's/;listen.owner = www-data/listen.owner = www-data/' \
    -e 's/;listen.group = www-data/listen.group = www-data/' \
    -e 's/pm.max_children = 5/pm.max_children = 20/' \
    -e 's/pm.start_servers = 2/pm.start_servers = 4/' \
    -e 's/pm.min_spare_servers = 1/pm.min_spare_servers = 2/' \
    -e 's/pm.max_spare_servers = 3/pm.max_spare_servers = 6/' \
    /usr/local/etc/php-fpm.d/www.conf

# Remove Alpine nginx default configs that conflict on port 80
RUN rm -rf /etc/nginx/http.d /etc/nginx/conf.d

# Copy application
COPY --from=builder /app /var/www/html

# Copy runtime config files
COPY docker/nginx-render.conf   /etc/nginx/nginx.conf.template
COPY docker/supervisord.conf    /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh       /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Ensure correct storage permissions
RUN mkdir -p \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/cache/data \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Document default port (Render overrides via $PORT env)
EXPOSE 10000

ENTRYPOINT ["/entrypoint.sh"]
