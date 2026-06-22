# =========================================================================
# Stage 1 — Installer les dépendances Composer (image légère)
# =========================================================================
FROM composer:2.10 AS composer-stage

WORKDIR /app

# Copier uniquement les fichiers nécessaires à composer install
COPY composer.json composer.lock* ./

# Installer les dépendances (--no-dev pour la prod, --ignore-platform-reqs
# évite les erreurs liées aux extensions PHP manquantes dans l'image composer)
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-autoloader \
        --no-scripts \
        --prefer-dist \
        --ignore-platform-reqs

# Copier le reste du code et optimiser l'autoloader
COPY . .
RUN composer dump-autoload --optimize --no-scripts

# =========================================================================
# Stage 2 — Image PHP-FPM 8.5 Alpine
# =========================================================================
FROM php:8.5-fpm-alpine

# ---- Dépendances de build (temporaires) ----
RUN apk add --no-cache --virtual .build-deps \
        libzip-dev \
        oniguruma-dev \
        libxml2-dev \
    && docker-php-ext-install \
        pdo \
        pdo_sqlite \
        mbstring \
        xml \
        zip \
        opcache \
    && apk del .build-deps

# ---- Dépendances runtime ----
RUN apk add --no-cache \
        libzip \
        oniguruma \
        libxml2 \
        curl

# ---- Configuration PHP optimisée ----
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# ---- Code applicatif ----
WORKDIR /var/www/html

# Copier les dépendances installées par Composer depuis le stage 1
COPY --from=composer-stage /app /var/www/html

# ---- .env ----
# Fournit une configuration locale par défaut ; générer APP_KEY au déploiement.
RUN cp .env.example .env

# ---- Créer les répertoires Laravel (non inclus dans Composer) ----
RUN mkdir -p bootstrap/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/cache/data \
        storage/logs \
        storage/app/public

# ---- Permissions ----
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# ---- Entrypoint ----
COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
