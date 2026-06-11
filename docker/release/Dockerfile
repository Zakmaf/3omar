# =========================================================================
# Stage 1 — Installer les dépendances Composer (image légère)
# =========================================================================
FROM composer:2.7 AS composer-stage

WORKDIR /app

COPY composer.json composer.lock* ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-autoloader \
        --no-scripts \
        --prefer-dist \
        --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-scripts

# =========================================================================
# Stage 2 — Image de production : PHP-FPM 8.4 + Nginx + Supervisor
# =========================================================================
FROM php:8.4-fpm

# ---- Dépendances système ----
RUN apt-get update -qq && apt-get install -y -q --no-install-recommends \
        nginx \
        supervisor \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        unzip \
        curl \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        xml \
        zip \
        opcache \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default /etc/nginx/sites-available/default

# ---- Configuration PHP optimisée ----
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
RUN mkdir -p /var/log/php

# ---- Configuration Nginx & Supervisor ----
COPY docker/release/nginx.conf /etc/nginx/conf.d/app.conf
COPY docker/release/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# ---- Code applicatif ----
WORKDIR /var/www/html

COPY --from=composer-stage /app /var/www/html

# ---- .env (valeurs de production sûres par défaut) ----
COPY docker/release/env.production .env

# ---- Répertoires Laravel & permissions ----
RUN mkdir -p bootstrap/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/cache/data \
        storage/logs \
        storage/app/public \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# ---- Entrypoint ----
COPY docker/release/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
