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
