# =============================================================================
# Eckox AI Platform — Production Dockerfile (Render-compatible)
# Multi-stage: composer builder → nginx + php-fpm runner
# Port: injected by Render via $PORT env variable at runtime
# =============================================================================

# ── Stage 1: Build — install composer deps & generate autoloader ──────────────
FROM php:8.4-fpm-alpine AS composer-builder

WORKDIR /app

# Install build-time system dependencies
RUN apk add --no-cache \
    git curl unzip zip \
    libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev \
    postgresql-dev icu-dev linux-headers bash

# Install PHP extensions
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
       gd pdo pdo_pgsql zip bcmath sockets opcache intl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy dependency definitions first (layer caching)
COPY composer.json composer.lock ./

# Install production dependencies (no scripts/autoload yet)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy full application source
COPY . .

# Clear dev bootstrap cache files
RUN rm -f bootstrap/cache/packages.php \
           bootstrap/cache/services.php \
           bootstrap/cache/config.php \
           bootstrap/cache/routes.php

# Override with production-hardened config files
# (ensures pgBouncer compatibility is baked in)
COPY config/database.php config/database.php
COPY config/services.php config/services.php

# Generate optimised autoloader
RUN composer dump-autoload --no-dev --optimize

# ── Stage 2: Production runner — nginx + php-fpm + supervisor ─────────────────
FROM php:8.4-fpm-alpine AS runner

WORKDIR /var/www/html

# Install runtime dependencies (nginx + supervisor for process management)
RUN apk add --no-cache \
    nginx supervisor gettext \
    libzip libpng libjpeg-turbo freetype libpq \
    icu-libs shadow su-exec fcgi curl

# Copy PHP extensions from builder stage
COPY --from=composer-builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=composer-builder /usr/local/etc/php/conf.d     /usr/local/etc/php/conf.d

# Production OPcache configuration
RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.memory_consumption=256"; \
    echo "opcache.interned_strings_buffer=16"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.revalidate_freq=0"; \
    echo "opcache.validate_timestamps=0"; \
    echo "opcache.fast_shutdown=1"; \
} >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

# Copy built application from composer-builder
COPY --from=composer-builder /app /var/www/html

# Copy Render-specific config files
# nginx config uses __PORT__ placeholder — substituted at runtime by entrypoint.sh
COPY docker/nginx-render.conf   /etc/nginx/nginx.conf.template
COPY docker/supervisord.conf    /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh       /entrypoint.sh

RUN chmod +x /entrypoint.sh

# Set storage & cache directory permissions
RUN mkdir -p \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache/data \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Tune PHP-FPM pool for production load
RUN sed -i \
    -e 's/pm.max_children = 5/pm.max_children = 50/' \
    -e 's/pm.start_servers = 2/pm.start_servers = 10/' \
    -e 's/pm.min_spare_servers = 1/pm.min_spare_servers = 5/' \
    -e 's/pm.max_spare_servers = 3/pm.max_spare_servers = 15/' \
    /usr/local/etc/php-fpm.d/www.conf

# Render injects $PORT — we expose the default fallback for documentation
EXPOSE 10000

ENTRYPOINT ["/entrypoint.sh"]
