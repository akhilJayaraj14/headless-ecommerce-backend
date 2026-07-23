# Multi-Stage Production Dockerfile for Enterprise Laravel eCommerce API
FROM php:8.4-fpm-alpine as base

# Install System Dependencies & Extensions
RUN apk add --no-cache \
    curl \
    git \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    oniguruma-dev \
    icu-dev \
    linux-headers

RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd intl opcache

# Install Redis Extension via PECL / Alpine
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy Application Code
COPY . .

# Install PHP Production Dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set File Permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
