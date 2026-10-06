# Développement

## Prérequis

- Docker et Docker Compose
- Git

## Lancer l'environnement

```bash
cp .env.example .env
docker compose up -d --build
docker run --rm \
  -v "$PWD":/app \
  -v paie_maroc_vendor:/app/vendor \
  -w /app composer:2.7 composer install
docker compose exec app php artisan key:generate
```

L'application est disponible sur **http://localhost:49173**.

Il n'existe pas d'étape de build frontend : Bootstrap 5, Bootstrap Icons et les polices Google sont chargés par CDN.

> Le fichier `docker-compose.v1.1.yml` est un vestige de la période de développement parallèle V1.0/V1.1 et n'est plus le workflow courant. Le seul environnement à utiliser aujourd'hui est `docker-compose.yml`, documenté ci-dessus.

## Commandes courantes

```bash
# Vider les caches (config, routes, vues)
docker compose exec app php artisan optimize:clear

# Formater le code (Laravel Pint)
docker compose exec app vendor/bin/pint

# Lancer les tests
docker compose exec app vendor/bin/phpunit
```

## Tests navigateur (Playwright + axe)

Les contrats de mise en page et d'accessibilité s'exécutent dans un navigateur réel.
Il n'y a pas d'étape de build front : `node_modules` sert uniquement à l'outillage de test.

```bash
# 1. Installer les dépendances PHP de ce dépôt dans un volume dédié
docker run --rm -v "$PWD":/app -v 3omar_browser_vendor:/app/vendor -w /app \
  composer:2.7 composer install --no-interaction --ignore-platform-req=php

# 2. Servir le dépôt sur une instance locale dédiée (boucle locale uniquement).
#    Serveur PHP intégré plutôt que `php artisan serve` : ce dernier ne transmet
#    pas APP_KEY à ses processus. La clé est jetable et n'est écrite nulle part.
docker run -d --name 3omar-browser-app --entrypoint php \
  -v "$PWD":/var/www/html -v 3omar_browser_vendor:/var/www/html/vendor \
  -w /var/www/html/public -p 127.0.0.1:49222:8000 \
  -e APP_ENV=local -e APP_KEY="base64:$(head -c32 /dev/urandom | base64)" \
  -e APP_URL=http://127.0.0.1:49222 -e CACHE_STORE=array -e SESSION_DRIVER=file \
  -e PHP_CLI_SERVER_WORKERS=4 \
  3omar-app:latest -S 0.0.0.0:8000 -t /var/www/html/public \
  /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php

# 3. Installer les dépendances npm épinglées dans un volume dédié
docker run --rm -v "$PWD":/work -v 3omar_browser_node_modules:/work/node_modules -w /work \
  mcr.microsoft.com/playwright:v1.61.1-noble npm ci

# 4. Lancer la suite
./tests/browser/run.sh tests/browser/contracts

# Cibler un seul fichier ou un seul contrat
./tests/browser/run.sh tests/browser/contracts/result-responsive.spec.js
./tests/browser/run.sh tests/browser/contracts --grep "zoom 200"

# Régénérer les captures durables de docs/ux/
./tests/browser/run.sh tests/browser/captures

# Accepter une évolution voulue du squelette de la page
./tests/browser/run.sh tests/browser/contracts/result-structure.spec.js --update-snapshots
```

`APP_CONTAINER` et `NODE_MODULES_VOLUME` permettent de cibler un autre conteneur ou un
autre volume, par exemple pour faire tourner plusieurs worktrees en parallèle.

`tests/browser/run.sh` refuse de démarrer tant qu'il n'a pas vérifié que le conteneur
applicatif monte bien **ce** dépôt : un contrat de mise en page exécuté contre une autre
copie du code ne prouve rien. Les versions de Playwright et d'axe sont figées par
`package-lock.json` et l'image Playwright est épinglée : aucune version n'est téléchargée
implicitement.

→ Matrice de vérification, captures et principes : [UX.md](UX.md)

## Architecture

```
app/
  Http/Controllers/       Validation des entrées, orchestration HTTP
  Http/Middleware/        SetLocale — applique la langue de session
  Services/
    PayrollCalculatorService.php
                          calculer()           brut → net
                          resoudreDepuisNet()  net → brut (dichotomie)
config/
  payroll.php             Taux, plafonds, tranches IR, SMIG — source unique
  app.php                 Locales supportées, timezone
  ads.php                 Paramètres Google AdSense
lang/{fr,en,ar,es}/
  ui.php                  Tous les messages d'interface
resources/views/
  layouts/app.blade.php   Layout principal (navbar, footer, ad-slots)
  calculator/             Formulaire (index) et résultat (result)
  documentation/          Page documentation générée depuis config/payroll.php
  home.blade.php          Page d'accueil
public/img/               Identité visuelle approuvée
docker/
  php/                    Dockerfile dev + php.ini + entrypoint
  nginx/                  Configuration Nginx dev
  release/                Image de production (Dockerfile, nginx, supervisor, entrypoint)
```

### Règle d'évolution

Toute modification réglementaire se fait dans `config/payroll.php` avec sa référence légale. La page documentation se régénère automatiquement. Couvrir les nouvelles valeurs par un test de limite dans `tests/Unit/PayrollCalculatorServiceTest.php`.

→ Formules et hypothèses détaillées : [CALCUL.md](CALCUL.md)  
→ Conventions de traduction : [I18N.md](I18N.md)
