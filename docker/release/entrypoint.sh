#!/bin/sh
set -e

cd /var/www/html

# Générer une clé d'application si aucune n'a été fournie.
# Recommandé en production : définir APP_KEY via l'environnement pour
# que les sessions survivent aux redémarrages/réplicas
# (générer avec : docker run --rm ghcr.io/zakmaf/3omar php artisan key:generate --show)
if [ -z "$APP_KEY" ]; then
    echo "[entrypoint] APP_KEY non défini — génération d'une clé éphémère pour ce conteneur."
    php artisan key:generate --force --ansi
fi

# Mettre en cache config/routes/vues pour la production, sinon vider les caches
if [ "${APP_ENV:-production}" = "local" ]; then
    php artisan config:clear --ansi
    php artisan route:clear --ansi
    php artisan view:clear --ansi
else
    php artisan config:cache --ansi
    php artisan route:cache --ansi
    php artisan view:cache --ansi
fi

# Permissions après génération du cache — les fichiers créés par root
# doivent être lisibles/modifiables par www-data (PHP-FPM)
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo "[entrypoint] 3omar prêt — démarrage de PHP-FPM + Nginx..."
exec "$@"
