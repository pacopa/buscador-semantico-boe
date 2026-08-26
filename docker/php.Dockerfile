FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS git unzip curl libzip-dev libicu-dev libonig-dev libxml2-dev libssl-dev \
    && docker-php-ext-install dom intl mbstring pcntl zip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .

RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
