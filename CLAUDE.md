# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**3omar** - a pedagogical Moroccan net-salary simulator for private-sector employees, covering the **2026 fiscal year**.

Stack: **Laravel 11/12 selon la branche active (PHP 8.4)** + Blade views, **Bootstrap 5** + Bootstrap Icons (via CDN, no asset build step), **Chart.js 4** (via CDN). No database or user data storage.

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

## Multi-agent workflow

Claude must follow these repository-level coordination rules:

- Never work directly on `main`.
- One issue = one branch = one topic = one active agent.
- Never continue work on a branch already being used by another agent.
- Before making changes, inspect `git status` and stop if unexpected unrelated edits conflict with the assigned task.
- Do not mix multiple issues in one branch or one commit series.
- Avoid opportunistic refactors outside the current ticket.
- Be especially careful with shared hot files such as `app/Services/PayrollCalculatorService.php`, `config/payroll.php`, `README.md`, and the main calculator views.
- When finishing work, report exactly which files changed and which verification commands were run.

---

## Architecture

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
app/Http/Controllers/
  ├── HomeController            → home view
  ├── CalculatorController       → GET /calculateur (form, accepts ?profil= / ?s= / ?a=),
  │                                POST /calculateur/calculer, GET /calculateur/comparer
  └── DocumentationController   → /documentation (renders config/payroll.php as a rate table)
resources/views/
  ├── home.blade.php
  ├── calculator/index.blade.php   # form (Bootstrap), client-side preview JS reads
  │                                 # @json($indemnites_config) / @json($hs_labels)
  ├── calculator/result.blade.php  # full bulletin breakdown + Chart.js donut
  ├── calculator/comparison.blade.php  # scenario A vs B, deltas and changed inputs
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
6.  FP   = min(SBI × taux_fp, plafond_fp)                  (Art. 59 I-A CGI, assiette = revenu
                                                            brut imposable ; taux dépend de SBI
                                                            vs seuil, ou statut journaliste/artiste)
7.  RNI mensuel = SNC − FP
8.  RNI annuel net = (RNI × 12) − retraite_complémentaire déductible (≤ 50% SBI annuel, Art. 28-IV)
    IR annuel brut = barème progressif(RNI annuel net)     (Art. 73 CGI, 6 tranches)
    IR mensuel = IR annuel brut / 12 − charges_famille     (Art. 74 CGI, paramètres dans config/payroll.php)
9.  Indemnités exonérées : part exonérée = min(déclaré, plafond) par config('payroll.indemnites')
                                                            (Arrêté 1314-25 / BO 7443, plafond
                                                            journalier × jours_travailles si par_jour)
10. Salaire net = SBI − CNSS − AMO − CIMR − IR_net + indemnités exonérées − (autres_retenues + mutuelle_salarié)
11. Coût employeur = salaire_brut_total + CNSS_patronal + AMO_patronal + AF_patronal + TFP_patronal + mutuelle_patronale
```

The result array also includes `avertissements` (regulatory warnings, e.g. salaire de base below SMIG, CIMR rate out of range, indemnité exceeding its legal cap) and `repartition` (percentages/colors for the Chart.js donut).

---

## Adding or changing a rate, bracket, or ceiling

Edit **`config/payroll.php`** only. Both the calculator service and the documentation page read from it. Do not duplicate values in views or the service.

## Adding a new indemnité exonérée type

Add an entry to `config('payroll.indemnites')` with `label`, `base_salaire` (bool), and either `montant` (fixed MAD) or `pct` (fraction of `salaire_base`, used when `base_salaire` is true). Set `par_jour => true` when the legal ceiling is per worked day (`montant` then is the daily cap, multiplied by the `jours_travailles` input, default `config('payroll.jours_travailles_defaut')`). The calculator (`plafondIndemnite`), the form (dropdown + JS preview via `@json($indemnites_config)`), and the documentation page all pick it up automatically. No other code changes needed.

## Prefilling the calculator form

The form reads every value through `old()`. Presets (`?profil=`), restored simulations
(`?s=`) and the comparison flow therefore prefill by injecting into the old-input bag with
`session()->now('_old_input', $input)`, which lasts for the current request only. Two
consequences to respect:

- A validation redirect already populates the old input, so the controller skips the
  prefill when `session()->hasOldInput()` is true. User input always wins.
- `nb_annees_anciennete` is rendered by a `<select>` that only offers the bracket-start
  years from `config('payroll.anciennete.tranches')`. Any other value renders as
  unselected and is silently lost, so prefilled seniority must be a bracket start.

## Legal references

Every rate/rule in `config/payroll.php` is grouped under a comment naming its declared legal source or simulation assumption. When adding a rule, document whether the reference has been verified, add a boundary test, and update `docs/REGLES_GESTION.md`.

---

## Publishing a new release (Docker image on GHCR)

Versioning follows **SemVer** (`vMAJOR.MINOR.PATCH`, e.g. `v1.0.0`).

To cut a release:

```bash
gh release create vX.Y.Z --title "vX.Y.Z" --notes "..." --target main
```

Creating and publishing the release triggers `.github/workflows/docker-release.yml`
(`on: release: published`), which builds `docker/release/Dockerfile` and pushes
`ghcr.io/zakmaf/3omar` to GitHub Container Registry, tagged via `docker/metadata-action`
with all of:

- `latest`
- `vMAJOR` (e.g. `v1`)
- `vMAJOR.MINOR` (e.g. `v1.0`)
- `vMAJOR.MINOR.PATCH` (e.g. `v1.0.0`)

The workflow requires `packages: write` for `GITHUB_TOKEN`: it declares this explicitly
in its `permissions:` block, and the repo's default Actions workflow permission is also
set to read/write (`gh api repos/Zakmaf/3omar/actions/permissions/workflow`).

Watch the run with `gh run list --workflow=docker-release.yml --limit 1` and
`gh run view <run-id> --log` to confirm the four tags were pushed.

### Release notes format

Follow the release notes standard defined in `CONTRIBUTING.md` (section "Redaction des release notes"). Key rules:

1. Use the mandatory structure: Nouveautes, Ameliorations, Correctifs, Securite, Mise a jour de la stack, Migration.
2. Omit empty sections.
3. Write in French. One line per change. Start with a verb or a feature name.
4. Reference issue numbers (`#XX`) when they exist.
5. Quantify results (image size, test count, percentage).
6. Describe the user-visible effect, not the implementation detail.
7. Always include a Migration section with `docker pull` command.
8. No em dashes, no emojis.

---

## Agent routing policy

This project uses specialized Claude Code subagents. Delegate work to agents instead of handling everything in the main context.

### Available agents

| Agent | Domain |
|---|---|
| `payroll-rules-engineer` | Moroccan payroll formulas, `config/payroll.php`, `PayrollCalculatorService`, gross/net logic, employer cost, monthly/annual rules |
| `laravel-maintainer` | Laravel structure, controllers, routes, validation, middleware, security, maintainability |
| `ux-accessibility-designer` | Blade UI, mobile UX, accessibility, RTL, results page, guided forms, print/PDF layout |
| `i18n-localization-agent` | FR/EN/AR/ES translations, RTL wording, locale files, user-facing strings |
| `release-devops-agent` | Docker, GHCR, CI, deployment, production configuration, release safety |
| `test-qa-engineer` | PHPUnit coverage, regression tests, golden tests, edge cases, validation tests |
| `code-reviewer` | Final diff review before commit, PR, merge, or release |

### Default routing

- Payroll calculation changes: `payroll-rules-engineer` -> `test-qa-engineer` -> `code-reviewer`.
- Laravel implementation work: `laravel-maintainer` -> `test-qa-engineer` -> `code-reviewer`.
- UI work: `ux-accessibility-designer` -> `i18n-localization-agent` (if text changes) -> `test-qa-engineer` -> `code-reviewer`.
- Translation or wording changes: `i18n-localization-agent`. Escalate to `payroll-rules-engineer` if the wording describes legal, fiscal, social, or payroll assumptions.
- Docker, CI, deployment, or GHCR work: `release-devops-agent` -> `test-qa-engineer` (if app behavior changed) -> `code-reviewer`.
- Before any PR or merge: `code-reviewer`.

### Model selection

- **Haiku**: cheap, narrow, low-risk work (simple translation completion, backlog cleanup, issue formatting, simple documentation wording, basic search or file inspection).
- **Sonnet** (default): implementation work (PHP/Laravel code edits, payroll tests, UX changes, i18n with business wording, Docker/CI changes, refactors, code review).
- **Opus**: high-risk reasoning only (payroll engine redesign, net-to-gross solver changes, monthly/annual calculation architecture, final pre-release audit, ambiguous legal/fiscal/social interpretation, large multi-file refactors, debugging regressions where the cause is unclear).

### Effort levels

- **Low**: mechanical edits, formatting, labels, obvious docs fixes, simple translation sync.
- **Medium**: normal feature work, Laravel edits, UI edits, Docker changes, regular tests.
- **High**: payroll formulas, legal assumptions, boundary cases, regressions, architecture decisions, final reviews.
- When payroll correctness or user trust is at stake, prefer Sonnet with high effort.
- Use Opus with high effort only when the cost is justified by risk.

### Quality gates

- Any payroll behavior change must include tests.
- Any user-facing text change must update all supported locales: FR, EN, AR, ES.
- Any calculation assumption change must update documentation.
- Any UI change must consider mobile and RTL.
- Any deployment change must preserve safe production defaults.
- Any non-trivial change must pass through `code-reviewer` before being considered done.

### Execution rules

1. State which agent or agent sequence you will use before starting.
2. Keep each agent within its domain. Do not let UI agents change payroll formulas. Do not let i18n agents invent legal/payroll terminology without flagging uncertainty. Do not let DevOps changes weaken production safety.
3. After implementation, run checks: `docker compose exec app vendor/bin/phpunit` and `docker compose exec app vendor/bin/pint`.
4. If tests cannot run, explain why and state the residual risk.
5. Summarize: agents used, files changed, tests run, remaining risks, recommended next step.

---

## Writing style

- **Never use the em dash (`—`)** anywhere: not in code, commits, docs, PR descriptions, or release notes. Use a simple dash (`-`), a colon (`:`) or reformulate the sentence.
- Keep prose concise. One idea per sentence.
