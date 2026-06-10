#!/bin/sh
set -e

cd /var/www/html

# Créer .env depuis .env.example si absent (cas du volume bind-mount en dev)
if [ ! -f .env ]; then
    echo "[entrypoint] .env absent — copie depuis .env.example"
    cp .env.example .env
fi

# Créer les répertoires Laravel si absents (utile quand le volume est vide)
mkdir -p bootstrap/cache \
         storage/framework/sessions \
         storage/framework/views \
         storage/framework/cache/data \
         storage/logs \
         storage/app/public

# Permissions
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache

# Vider les caches Blade/config au démarrage en mode dev
if [ "${APP_ENV}" = "local" ]; then
    php artisan view:clear  --ansi 2>/dev/null || true
    php artisan config:clear --ansi 2>/dev/null || true
fi

echo "[entrypoint] Application prête — démarrage de PHP-FPM 8.4..."
exec "$@"
