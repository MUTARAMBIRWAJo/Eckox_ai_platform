FROM php:8.4-fpm-alpine AS composer-builder

WORKDIR /app

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    postgresql-dev \
    bash \
    icu-dev \
    linux-headers

# Install PHP extensions
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_pgsql zip bcmath sockets opcache intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy dependencies definitions
COPY composer.json composer.lock ./

# Install dependencies (production optimized)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy code
COPY . .

# Clear bootstrap cache files from local development to prevent loading dev-only packages
RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php bootstrap/cache/routes.php

# Generate optimized autoload files
RUN composer dump-autoload --no-dev --optimize

# ----------------------------------------------------
# Production runner image
# ----------------------------------------------------
FROM php:8.4-fpm-alpine AS runner

WORKDIR /var/www/html

# Install system utilities and runtime dependencies
RUN apk add --no-cache \
    libzip \
    libpng \
    libjpeg-turbo \
    freetype \
    libpq \
    icu-libs \
    shadow \
    su-exec \
    fcgi

# Copy extensions from builder stage
COPY --from=composer-builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=composer-builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

# Enable custom OPcache configuration
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.fast_shutdown=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

# Copy application files from composer-builder stage
COPY --from=composer-builder /app /var/www/html

# Override specific config files with production-hardened versions
# (These are copied from build context after composer-builder to ensure
#  Supabase pgBouncer compatibility and EASE service config are baked in)
COPY config/database.php /var/www/html/config/database.php
COPY config/services.php /var/www/html/config/services.php

# Create required directories and set proper permissions
RUN mkdir -p bootstrap/cache storage/framework/sessions storage/framework/views storage/framework/cache storage/logs \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Tune PHP-FPM pool configuration for production load
RUN sed -i 's/pm.max_children = 5/pm.max_children = 100/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/pm.start_servers = 2/pm.start_servers = 20/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/pm.min_spare_servers = 1/pm.min_spare_servers = 10/g' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/pm.max_spare_servers = 3/pm.max_spare_servers = 30/g' /usr/local/etc/php-fpm.d/www.conf

EXPOSE 9000

CMD ["php-fpm"]
