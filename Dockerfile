# syntax=docker/dockerfile:1

FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-dev --optimize-autoloader --no-progress --ignore-platform-reqs

FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    zlib-dev \
    zip \
    unzip

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mbstring zip xml intl bcmath

WORKDIR /var/www/html

COPY --from=vendor /app/vendor /var/www/html/vendor
COPY --from=vendor /app/composer.lock /var/www/html/composer.lock
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
