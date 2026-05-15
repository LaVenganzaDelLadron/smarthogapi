FROM dunglas/frankenphp:php8.4

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install pdo_pgsql pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . .

RUN composer install --optimize-autoloader --no-interaction

RUN mkdir -p /app/storage/framework/views /app/storage/framework/cache /app/storage/framework/sessions \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache

EXPOSE 8000
CMD ["sh", "-c", "php artisan migrate:fresh && php artisan serve --host=0.0.0.0 --port=8000"]
