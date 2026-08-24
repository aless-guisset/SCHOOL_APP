FROM php:8.4-cli-bookworm AS build

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libonig-dev ca-certificates curl gnupg \
    && docker-php-ext-install pdo_mysql zip mbstring \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN composer dump-autoload --no-dev --optimize \
    && npm run build \
    && rm -rf node_modules

FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev libpng-dev libonig-dev \
    && docker-php-ext-install pdo_mysql zip mbstring \
    && rm -rf /var/lib/apt/lists/*

RUN printf 'upload_max_filesize=12M\npost_max_size=16M\n' > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /app
COPY --from=build /app /app

RUN php artisan storage:link || true

EXPOSE 8080
CMD php artisan migrate --force \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan serve --host 0.0.0.0 --port ${PORT:-8080}
