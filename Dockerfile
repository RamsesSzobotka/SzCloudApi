FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]