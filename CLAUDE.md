# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**3omar** — a pedagogical Moroccan net-salary simulator for private-sector employees, covering the **2026 fiscal year**.

Stack: **Laravel 11 (PHP 8.4)** + Blade views, **Bootstrap 5** + Bootstrap Icons (via CDN, no asset build step), **Chart.js 4** (via CDN). No database or user data storage.

---

## Commands

All commands run inside the PHP-FPM container (`paie_maroc_app`).

```bash
# First-time setup
cp .env.example .env
docker-compose up -d --build
docker run --rm -v "$PWD":/app -v paie_maroc_vendor:/app/vendor -w /app composer:2.7 composer install
docker exec paie_maroc_app php artisan key:generate

# App available at http://localhost:49173

# After editing config/ or routes/ (Laravel caches these)
docker exec paie_maroc_app php artisan config:clear
docker exec paie_maroc_app php artisan view:clear

# Code style (Laravel Pint)
docker exec paie_maroc_app vendor/bin/pint

# Tinker (REPL)
docker exec paie_maroc_app php artisan tinker
```

Run tests with `docker compose exec -T app vendor/bin/phpunit`.

---

## Architecture

```
config/payroll.php                          # SINGLE SOURCE OF TRUTH for every 2026 rate,
                                              # bracket, ceiling and label. Nothing should be
                                              # hardcoded elsewhere.
app/Services/PayrollCalculatorService.php    # The entire calculation engine — one method,
                                              # calculer(array $input): array, returning a
                                              # large flat result array consumed by the view.
app/Http/Controllers/
  ├── HomeController            → home view
  ├── CalculatorController       → GET /calculateur (form), POST /calculateur/calculer
  └── DocumentationController   → /documentation (renders config/payroll.php as a rate table)
resources/views/
  ├── home.blade.php
  ├── calculator/index.blade.php   # form (Bootstrap), client-side preview JS reads
  │                                 # @json($indemnites_config) / @json($hs_labels)
  ├── calculator/result.blade.php  # full bulletin breakdown + Chart.js donut
  └── documentation/index.blade.php
```

### Calculation sequence (`PayrollCalculatorService::calculer`)

```
0.  Prime d'ancienneté = salaire_base × taux_tranche       (Art. 350 Code du Travail)
1.  SBI  = salaire_base + primes_imposables + heures_sup
         + excédent des indemnités au-delà des plafonds    (part imposable, Arrêté 1314-25)
2.  CNSS = min(SBI, plafond_cnss) × taux_cnss              (Dahir 1-72-184)
3.  AMO  = SBI × taux_amo                                  (Loi 65-00)
4.  CIMR = SBI × taux_cimr (si actif, 3%–10%)              (Art. 28-III CGI)
5.  SNC  = SBI − CNSS − AMO − CIMR
6.  FP   = min(SBI × taux_fp, plafond_fp)                  (Art. 59 I-A CGI — assiette = revenu
                                                            brut imposable ; taux dépend de SBI
                                                            vs seuil, ou statut journaliste/artiste)
7.  RNI mensuel = SNC − FP
8.  RNI annuel net = (RNI × 12) − retraite_complémentaire déductible (≤ 50% SBI annuel, Art. 28-IV)
    IR annuel brut = barème progressif(RNI annuel net)     (Art. 73 CGI, 6 tranches)
    IR mensuel = IR annuel brut / 12 − charges_famille     (Art. 74 CGI, paramètres dans config/payroll.php)
9.  Indemnités exonérées : part exonérée = min(déclaré, plafond) par config('payroll.indemnites')
                                                            (Arrêté 1314-25 / BO 7443 — plafond
                                                            journalier × jours_travailles si par_jour)
10. Salaire net = SBI − CNSS − AMO − CIMR − IR_net + indemnités exonérées − (autres_retenues + mutuelle_salarié)
11. Coût employeur = salaire_brut_total + CNSS_patronal + AMO_patronal + AF_patronal + TFP_patronal + mutuelle_patronale
```

The result array also includes `avertissements` (regulatory warnings, e.g. salaire de base below SMIG, CIMR rate out of range, indemnité exceeding its legal cap) and `repartition` (percentages/colors for the Chart.js donut).

---

## Adding or changing a rate, bracket, or ceiling

Edit **`config/payroll.php`** only — both the calculator service and the documentation page read from it. Do not duplicate values in views or the service.

## Adding a new indemnité exonérée type

Add an entry to `config('payroll.indemnites')` with `label`, `base_salaire` (bool), and either `montant` (fixed MAD) or `pct` (fraction of `salaire_base`, used when `base_salaire` is true). Set `par_jour => true` when the legal ceiling is per worked day (`montant` then is the daily cap, multiplied by the `jours_travailles` input, default `config('payroll.jours_travailles_defaut')`). The calculator (`plafondIndemnite`), the form (dropdown + JS preview via `@json($indemnites_config)`), and the documentation page all pick it up automatically — no other code changes needed.

## Legal references

Every rate/rule in `config/payroll.php` is grouped under a comment naming its declared legal source or simulation assumption. When adding a rule, document whether the reference has been verified, add a boundary test, and update `docs/REGLES_GESTION.md`.
