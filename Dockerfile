FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js tsconfig.json ./
RUN npm run build


FROM php:8.3-cli-alpine AS application

RUN apk add --no-cache \
        libpq-dev \
        libxml2-dev \
    && docker-php-ext-install \
        dom \
        opcache \
        pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
    && php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 10000

CMD ["sh", "-c", "php artisan migrate --isolated --force && php artisan permission:cache-reset && php artisan optimize && exec php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]
