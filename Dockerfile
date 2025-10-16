# Dockerfile
FROM php:8.4-fpm

ARG DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html
EXPOSE 9000

# COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .

RUN apt update && apt install -y unzip git libzip-dev \
 && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
 && docker-php-ext-install pdo pdo_mysql zip bcmath \
 && docker-php-ext-enable opcache \
 && git config --global --add safe.directory /var/www/html \
 && composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts \
 && composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts \
 && php artisan package:discover --ansi || true \
 && chown -R www-data:www-data storage bootstrap/cache \
 && rm -rf /var/cache/apt/archives /var/lib/apt/lists/*