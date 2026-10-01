FROM php:8.5-cli-alpine

RUN apk add --no-cache \
        bash \
        libzip-dev \
        oniguruma-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring bcmath zip \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependencies first so this layer is cached unless composer.json/lock change.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload --optimize \
    && cp -n .env.example .env \
    && php artisan key:generate --force \
    && chmod -R ug+rwx storage bootstrap/cache \
    && chmod +x docker/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
