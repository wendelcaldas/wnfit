FROM node:22-bookworm-slim AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.3-fpm-bookworm AS application

ENV APP_ENV=production \
    APP_DEBUG=false \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y --no-install-recommends \
        gettext-base \
        libicu-dev \
        libzip-dev \
        nginx \
        supervisor \
        unzip \
    && docker-php-ext-install -j"$(nproc)" bcmath intl opcache pcntl pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/* /var/cache/apt/* \
    && rm -f /etc/nginx/sites-enabled/default

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts

COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && sed -i 's/\r$//' docker/entrypoint.sh \
    && chmod +x docker/entrypoint.sh

COPY docker/nginx.conf.template /etc/nginx/templates/default.conf.template
COPY docker/php.ini /usr/local/etc/php/conf.d/99-wnfit.ini
COPY docker/opcache.ini /usr/local/etc/php/conf.d/10-opcache.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 8080

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
