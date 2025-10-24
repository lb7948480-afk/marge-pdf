# Multi-stage Dockerfile to build Vite assets and run Laravel

# Stage 1: Build frontend assets with Vite
FROM node:20-alpine AS assets
WORKDIR /app

# Install dependencies
COPY package*.json ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi

# Copy Vite config and sources
COPY vite.config.js ./vite.config.js
COPY resources ./resources
COPY public ./public

# Build assets to public/build
RUN npm run build

# Stage 2: PHP runtime
FROM php:8.4-fpm AS app

ARG DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html
EXPOSE 8000

# Copy full application
COPY . .

# Copy built assets from the assets stage
COPY --from=assets /app/public/build ./public/build

# Install system libs and PHP extensions, then Composer deps
RUN apt-get update && apt-get install -y unzip git libzip-dev \
 && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
 && docker-php-ext-install pdo pdo_mysql zip bcmath \
 && docker-php-ext-enable opcache \
 && git config --global --add safe.directory /var/www/html \
 && composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev --no-scripts \
 && php artisan package:discover --ansi || true \
 && chown -R www-data:www-data storage bootstrap/cache \
 && rm -rf /var/lib/apt/lists/* /var/cache/apt/archives

# Ensure PHP-FPM listens on all interfaces for Nginx upstream
RUN sed -i 's/^listen = .*/listen = 0.0.0.0:9000/' /usr/local/etc/php-fpm.d/www.conf

# Run Laravel using the provided entrypoint (serves on PORT or 8000)
CMD ["sh", "docker/entrypoint.sh"]