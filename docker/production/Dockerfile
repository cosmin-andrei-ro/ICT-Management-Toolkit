# SPDX-License-Identifier: AGPL-3.0-or-later
# Copyright (C) 2026 ICT Management Toolkit contributors

# Production images (ADR 0070). Built from the repository root:
#   docker compose -f docker/production/compose.yaml build

FROM composer:2.9 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress --prefer-dist --ignore-platform-reqs

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
COPY resources ./resources
COPY modules ./modules
COPY --from=vendor /app/vendor ./vendor
RUN npm ci --ignore-scripts && npm run build

FROM php:8.5-fpm-alpine AS app
RUN apk add --no-cache icu-libs libldap libpq libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev linux-headers openldap-dev postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" intl ldap pcntl pdo_pgsql zip \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear
COPY docker/production/php.ini /usr/local/etc/php/conf.d/zz-toolkit.ini
COPY docker/production/fpm.conf /usr/local/etc/php-fpm.d/zz-toolkit.conf
COPY docker/production/entrypoint.sh /usr/local/bin/toolkit-entrypoint
WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY --from=vendor /usr/bin/composer /usr/local/bin/composer
# A throwaway key: these commands only publish files, and the real key comes at run time
RUN mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-scripts --no-interaction \
    && export APP_KEY="base64:$(head -c 32 /dev/urandom | base64)" APP_ENV=production \
    && php artisan package:discover --ansi \
    && php artisan vendor:publish --tag=livewire:assets --ansi --force \
    && php artisan filament:assets --ansi \
    && rm /usr/local/bin/composer \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
ENTRYPOINT ["toolkit-entrypoint"]
CMD ["php-fpm"]

# The web server serves the files of the application's public folder and hands the rest to PHP
FROM caddy:2.10-alpine AS web
COPY docker/production/Caddyfile /etc/caddy/Caddyfile
COPY --from=app /var/www/html/public /var/www/html/public
