# AGENTS.md : 3omar

Seule référence normative pour tout agent, humain ou automatisé, intervenant sur
ce dépôt. `CLAUDE.md` l'importe ; `CONTRIBUTING.md` y renvoie. Le bloc suivant
vient du standard commun Zakmaf/engineering et ne se modifie pas ici.

<!-- standard:core:debut (copié de Zakmaf/engineering, templates/AGENTS.core.md ; ne pas modifier ici) -->
## Règles communes (standard Zakmaf/engineering)

Ces règles s'appliquent à tout agent, humain ou automatisé. La section propre au
projet, plus bas, les complète sans les contredire.

### Préflight

1. Lire ce fichier en entier.
2. Vérifier `git status --short --branch`.
3. Identifier l'issue et sa branche `<type>/<issue>-<sujet>`.
4. Vérifier qu'aucune PR ouverte ne modifie les mêmes fichiers.

### Interdits

- Travailler sur `main`, pousser sur `main`, réécrire l'historique partagé.
- Fusionner, créer un tag ou une release, déclencher une publication, déployer,
  modifier les réglages du dépôt : réservé au propriétaire, avec son accord
  explicite pour chaque action. Une CI verte n'autorise rien de tout cela.
- Utiliser les identifiants du propriétaire. Les agents agissent sous le compte
  machine.
- Deux writers sur une même branche ou un même worktree.
- Refactor opportuniste hors du périmètre de l'issue.
- Inventer un résultat de test, de calcul ou de CI.
- Nettoyage Docker global (`system prune`, `volume prune`).
- Tiret cadratin (U+2014) et émojis, partout.

### Méthode

- Besoin, exigence, test, implémentation. Un bug se reproduit par un test rouge
  avant d'être corrigé.
- PR en brouillon jusqu'à ce que le check `gate` soit vert.
- Le compte rendu du gabarit de PR est rempli avec des résultats réels : la
  commande et sa sortie, ou le lien du run CI.

### Modèles

- Opus approuvé (identifiant exact fixé par le propriétaire) pour le travail
  conséquent ou ambigu ; Sonnet pour les tâches étroites et mécaniques.
- Aucun fallback silencieux : modèle indisponible ou quota atteint, arrêter et
  le signaler au propriétaire.
- Indiquer le modèle exact et la raison du choix dans le compte rendu.

### Fin de session

- Arrêter toute pile Docker démarrée pour la session, sauf aperçu de PR
  conservé jusqu'au verdict du propriétaire.
- Annoncer les fichiers modifiés, les vérifications exécutées et les risques
  résiduels.
<!-- standard:core:fin -->

## Règles propres à 3omar

The rules below complete the common block for this repository. They never relax it.

### Project Structure & Module Organization

This repository is a stateless Laravel 13 application (PHP 8.5, the version embedded in the development and production Docker images and the only one tested by CI) for calculating Moroccan payroll for fiscal year 2026.

- `app/Services/PayrollCalculatorService.php`: calculation engine.
- `app/Http/Controllers/`: request validation and page controllers.
- `config/payroll.php`: single source of truth for rates, brackets, ceilings, labels, and legal references.
- `resources/views/`: Blade templates grouped by page (`calculator/`, `documentation/`, and `layouts/`).
- `routes/web.php`: public HTTP routes.
- `public/img/`: static image assets.
- `docker/` and `docker-compose.yml`: PHP-FPM and Nginx development environment.

Do not duplicate payroll values in services or views. Add or update them in `config/payroll.php`.

### Build, Test, and Development Commands

Run project commands inside the `paie_maroc_app` container:

```bash
cp .env.example .env
docker compose up -d --build                 # Build and start the app at localhost:49173
docker run --rm -v "$PWD":/app -v paie_maroc_vendor:/app/vendor -w /app composer:2 composer install
docker exec paie_maroc_app php artisan key:generate
docker exec paie_maroc_app vendor/bin/pint   # Format PHP code
docker exec paie_maroc_app php artisan config:clear
docker exec paie_maroc_app php artisan view:clear
```

There is no frontend build step; Bootstrap, Bootstrap Icons, and Chart.js are loaded by CDN.

### Coding Style & Naming Conventions

Follow Laravel conventions and PSR-12, enforced by Laravel Pint. Use four-space indentation in PHP. Name classes in `PascalCase`, methods and variables in `camelCase`, and Blade files/routes in descriptive lowercase names. Keep controllers focused on validation and orchestration; place payroll calculations in the service. Preserve French domain terminology and include the relevant legal reference when adding regulatory rules.

User-facing interface text must use stable keys from `lang/{fr,en,ar,es}/ui.php`. Add each new key to all four catalogs and verify Arabic RTL rendering. Never duplicate rates or ceilings in translations.

### Testing Guidelines

PHPUnit tests live under `tests/Unit/` and `tests/Feature/`. Name files `*Test.php`, and cover boundaries such as tax brackets, ceilings, and rounding. Run tests with:

```bash
docker exec paie_maroc_app vendor/bin/phpunit
```

### Commit & Pull Request Guidelines

Commits and PR titles follow `CONTRIBUTING.md`: `type(scope): description courte en français`, one concern per commit, issue referenced. The PR body is the report from `.github/PULL_REQUEST_TEMPLATE.md`: behavioral changes, updated legal references, real verification results, linked issue, and screenshots for Blade/UI changes.

### Hot files

Avoid concurrent edits on `app/Services/PayrollCalculatorService.php`, `config/payroll.php`, `README.md`, `resources/views/calculator/index.blade.php` and `resources/views/calculator/result.blade.php`. If another open PR already touches one of them, wait for it or coordinate first.

### Quality gates

- Any payroll behavior change must include tests, including boundary cases (brackets, ceilings, rounding).
- Any user-facing text change must update all supported locales: FR, EN, AR, ES.
- Any calculation assumption change must update `docs/CALCUL.md`.
- Any UI change must consider mobile and RTL.
- Any deployment change must preserve safe production defaults.
- Any non-trivial change gets an independent review before it is considered done. The writer never validates its own work.

### Architecture

```
config/payroll.php                          # SINGLE SOURCE OF TRUTH for every 2026 rate,
                                              # bracket, ceiling and label. Nothing should be
                                              # hardcoded elsewhere.
app/Services/PayrollCalculatorService.php    # The entire calculation engine: one method,
                                              # calculer(array $input): array, returning a
                                              # large flat result array consumed by the view.
app/Services/SimulationCodec.php             # Encodes/decodes a simulation into a URL-safe
                                              # payload (share + restore). Every decoded
                                              # payload is re-validated through
                                              # PayrollValidation before use.
app/Services/SimulationComparator.php        # Deltas between two result arrays. Reads only
                                              # amounts already produced by the engine.
app/Services/SimulationProfileService.php    # Ready-to-use form presets. Regulatory amounts
                                              # are read from config/payroll.php, never
                                              # redeclared.
app/Services/GuideService.php                # Editorial guide pages (/calcul-salaire-net-maroc...).
                                              # Each example is computed by the engine from a
                                              # SimulationProfileService preset; texts in ui.guides.
app/Http/Controllers/
  ├── HomeController            → home view
  ├── CalculatorController       → GET /calculateur (form, accepts ?profil= / ?s= / ?a=),
  │                                POST /calculateur/calculer, GET /calculateur/comparer
  ├── DocumentationController   → /documentation (renders config/payroll.php as a rate table)
  └── GuideController           → one root URL per GuideService::GUIDES slug
resources/views/
  ├── home.blade.php
  ├── calculator/index.blade.php   # form (Bootstrap), client-side preview JS reads
  │                                 # @json($indemnites_config) / @json($hs_labels)
  ├── calculator/result.blade.php  # full bulletin breakdown + Chart.js donut
  ├── calculator/comparison.blade.php  # scenario A vs B, deltas and changed inputs
  └── documentation/index.blade.php
```

#### Calculation sequence (`PayrollCalculatorService::calculer`)

```
0.  Prime d'ancienneté = salaire_base × taux_tranche       (Art. 350 Code du Travail)
1.  SBI  = salaire_base + primes_imposables + heures_sup
         + excédent des indemnités au-delà des plafonds    (part imposable, Arrêté 1314-25)
2.  CNSS = min(SBI, plafond_cnss) × taux_cnss              (Dahir 1-72-184)
3.  AMO  = SBI × taux_amo                                  (Loi 65-00)
4.  CIMR = SBI × taux_cimr (si actif, 3%–10%)              (Art. 28-III CGI)
5.  SNC  = SBI − CNSS − AMO − CIMR
6.  FP   = min(SBI × taux_fp, plafond_fp)                  (Art. 59 I-A CGI, assiette = revenu
                                                            brut imposable ; taux dépend de SBI
                                                            vs seuil, ou statut journaliste/artiste)
7.  RNI mensuel = SNC − FP − retenues_exonerees_ir − mutuelle_salarié
8.  RNI annuel net = (RNI × 12) − retraite_complémentaire déductible (≤ 50% SBI annuel, Art. 28-IV)
    IR annuel brut = barème progressif(RNI annuel net)     (Art. 73 CGI, 6 tranches)
    IR mensuel = IR annuel brut / 12 − charges_famille     (Art. 74 CGI, paramètres dans config/payroll.php)
9.  Indemnités exonérées : part exonérée = min(déclaré, plafond) par config('payroll.indemnites')
                                                            (Arrêté 1314-25 / BO 7443, plafond
                                                            journalier × jours_travailles si par_jour)
10. Salaire net = SBI − CNSS − AMO − CIMR − IR_net + indemnités exonérées − total_retenues (retenues_exonerees_ir + retenues_imposees_ir + mutuelle_salarié)
11. Coût employeur = salaire_brut_total + CNSS_patronal + AMO_patronal + AF_patronal + TFP_patronal + mutuelle_patronale
```

The result array also includes `avertissements` (regulatory warnings, e.g. salaire de base below SMIG, CIMR rate out of range, indemnité exceeding its legal cap) and `repartition` (percentages/colors for the Chart.js donut).

### Adding or changing a rate, bracket, or ceiling

Edit **`config/payroll.php`** only. Both the calculator service and the documentation page read from it. Do not duplicate values in views or the service.

### Adding a new indemnité exonérée type

Add an entry to `config('payroll.indemnites')` with `label`, `base_salaire` (bool), and either `montant` (fixed MAD) or `pct` (fraction of `salaire_base`, used when `base_salaire` is true). Set `par_jour => true` when the legal ceiling is per worked day (`montant` then is the daily cap, multiplied by the `jours_travailles` input, default `config('payroll.jours_travailles_defaut')`). The calculator (`plafondIndemnite`), the form (dropdown + JS preview via `@json($indemnites_config)`), and the documentation page all pick it up automatically. No other code changes needed.

### Prefilling the calculator form

The form reads every value through `old()`. Presets (`?profil=`), restored simulations
(`?s=`) and the comparison flow therefore prefill by injecting into the old-input bag with
`session()->now('_old_input', $input)`, which lasts for the current request only. Two
consequences to respect:

- A validation redirect already populates the old input, so the controller skips the
  prefill when `session()->hasOldInput()` is true. User input always wins.
- `nb_annees_anciennete` is rendered by a `<select>` that only offers the bracket-start
  years from `config('payroll.anciennete.tranches')`. Any other value renders as
  unselected and is silently lost, so prefilled seniority must be a bracket start.

### Legal references

Every rate/rule in `config/payroll.php` is grouped under a comment naming its declared legal source or simulation assumption. When adding a rule, document whether the reference has been verified, add a boundary test, and update `docs/CALCUL.md`.

### Releases

Releases, tags, publication workflows and deployments are reserved to the repository owner (common block). The process, the publication states and the release-notes standard live in `CONTRIBUTING.md`, section « Releases ». When asked to draft release notes, follow that standard: French, mandatory sections (Nouveautés, Améliorations, Correctifs, Sécurité, Mise à jour de la stack, Migration), one line per change, user-visible effect, issue references, a Migration section with the `docker pull` command.
