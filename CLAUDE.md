# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Mon Bulletin de Paie Marocain** — a pedagogical Moroccan net-salary (bulletin de paie) simulator for private-sector employees, covering the **2026 fiscal year** (CGI 2026 / Loi de Finances 50-25, CNSS, AMO, IR, CIMR, frais professionnels, heures supplémentaires, prime d'ancienneté, indemnités exonérées, coût employeur).

Stack: **Laravel 11 (PHP 8.3)** + Blade views, **Bootstrap 5** + Bootstrap Icons (via CDN, no asset build step), **Chart.js 4** (via CDN) for the salary breakdown donut chart. 100% stateless — no database, no user data stored (the `sqlite` DB config in `.env` is an unused Laravel placeholder).

---

## Commands

All commands run inside the PHP-FPM container (`paie_maroc_app`).

```bash
# First-time setup
cp .env.example .env
docker-compose up -d --build
docker exec paie_maroc_app composer install
docker exec paie_maroc_app php artisan key:generate

# App available at http://localhost:8080

# After editing config/ or routes/ (Laravel caches these)
docker exec paie_maroc_app php artisan config:clear
docker exec paie_maroc_app php artisan view:clear

# Code style (Laravel Pint)
docker exec paie_maroc_app vendor/bin/pint

# Tinker (REPL)
docker exec paie_maroc_app php artisan tinker
```

There is no `tests/` directory yet — no test command exists. PHPUnit/Pest are available as dev dependencies if tests are added.

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
2.  CNSS = min(SBI, plafond_cnss) × taux_cnss              (Dahir 1-72-184)
3.  AMO  = SBI × taux_amo                                  (Loi 65-00)
4.  CIMR = SBI × taux_cimr (si actif, 3%–10%)              (Art. 28-III CGI)
5.  SNC  = SBI − CNSS − AMO − CIMR
6.  FP   = min(SNC × taux_fp, plafond_fp)                  (Art. 59 I-A CGI — taux dépend
                                                            de SBI vs seuil, ou statut
                                                            journaliste/artiste)
7.  RNI mensuel = SNC − FP
8.  RNI annuel net = (RNI × 12) − retraite_complémentaire déductible (≤ 50% SBI annuel, Art. 28-IV)
    IR annuel brut = barème progressif(RNI annuel net)     (Art. 73 CGI, 6 tranches)
    IR mensuel = IR annuel brut / 12 − charges_famille     (Art. 74 CGI, 50 MAD/pers., plafond 300)
9.  Indemnités exonérées : chaque type plafonné par config('payroll.indemnites')
                                                            (Arrêté 1314-25 / BO 7443)
10. Salaire net = SBI − CNSS − AMO − CIMR − IR_net + indemnités − (autres_retenues + mutuelle_salarié)
11. Coût employeur = SBI + CNSS_patronal + AMO_patronal + AF_patronal + TFP_patronal + mutuelle_patronale
```

The result array also includes `avertissements` (regulatory warnings, e.g. SBI below SMIG, CIMR rate out of range, indemnité exceeding its legal cap) and `repartition` (percentages/colors for the Chart.js donut).

---

## Adding or changing a rate, bracket, or ceiling

Edit **`config/payroll.php`** only — both the calculator service and the documentation page read from it. Do not duplicate values in views or the service.

## Adding a new indemnité exonérée type

Add an entry to `config('payroll.indemnites')` with `label`, `base_salaire` (bool), and either `montant` (fixed MAD) or `pct` (fraction of `salaire_base`, used when `base_salaire` is true). The calculator (`plafondIndemnite`), the form (dropdown + JS preview via `@json($indemnites_config)`), and the documentation page all pick it up automatically — no other code changes needed.

## Legal references

Every rate/rule in `config/payroll.php` is grouped under a comment naming its legal source (Dahir, CGI article, Arrêté, Décret). When adding a new rule, include its legal reference in the same way — these are surfaced directly in the documentation page and in `avertissements` messages.
