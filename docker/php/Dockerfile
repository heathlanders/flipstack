# Multi-stage Dockerfile for Laravel Application
# Optimized for build speed with proper layer caching

# =============================================================================
# Stage 1: Base PHP image with extensions
# =============================================================================
FROM php:8.4-fpm-alpine AS base

WORKDIR /var/www/html

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev \
    postgresql-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    icu-dev \
    autoconf \
    g++ \
    make \
    linux-headers \
    supervisor \
    nginx \
    fcgi \
    procps

# Install PHP extensions
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp

RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    mbstring \
    zip \
    exif \
    pcntl \
    gd \
    bcmath \
    intl \
    opcache \
    sockets

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install igbinary (better serialization for Redis)
RUN pecl install igbinary && docker-php-ext-enable igbinary

# Install APCu for additional caching
RUN pecl install apcu && docker-php-ext-enable apcu

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# =============================================================================
# Stage 2: Frontend assets (pre-built by CI)
# =============================================================================
# Note: Frontend assets are built in GitHub Actions and synced to server
# This stage just creates a placeholder for the COPY --from directive
FROM alpine:3.19 AS frontend-builder
WORKDIR /app
COPY public/build ./public/build

# =============================================================================
# Stage 3: Development
# =============================================================================
FROM base AS development

# Install Node.js for development HMR
RUN apk add --no-cache nodejs npm

# Install Xdebug for development
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Copy PHP configuration
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# Create user for Laravel
RUN addgroup -g 1000 -S laravel && \
    adduser -u 1000 -S laravel -G laravel

# Set proper permissions
RUN chown -R laravel:laravel /var/www/html

# Switch to non-root user
USER laravel

EXPOSE 9000

CMD ["php-fpm"]

# =============================================================================
# Stage 4: Composer dependencies (cached layer)
# =============================================================================
FROM base AS composer-deps

# Copy only composer files first for layer caching
COPY composer.json composer.lock ./

# Install dependencies without scripts (no autoload yet)
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

# =============================================================================
# Stage 5: Production worker (for horizon, scheduler)
# =============================================================================
FROM base AS production-worker

# Copy PHP configuration
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Create user for Laravel
RUN addgroup -g 1000 -S laravel && \
    adduser -u 1000 -S laravel -G laravel

# Copy composer dependencies from cached layer
COPY --from=composer-deps /var/www/html/vendor ./vendor

# Copy application code
COPY --chown=laravel:laravel . .

# Copy built frontend assets
COPY --from=frontend-builder /app/public/build ./public/build

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-interaction

# Ensure Laravel storage directories exist (may be excluded by .dockerignore)
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views storage/app/public bootstrap/cache

# Set proper permissions
RUN chown -R laravel:laravel /var/www/html/storage /var/www/html/bootstrap/cache

# Switch to non-root user
USER laravel

CMD ["php-fpm"]

# =============================================================================
# Stage 6: Production (main web server)
# =============================================================================
FROM production-worker AS production

EXPOSE 9000

CMD ["php-fpm"]
