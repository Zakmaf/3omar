# Repository Guidelines

## Project Structure & Module Organization

This repository is a stateless Laravel 11 application for calculating Moroccan payroll for fiscal year 2026.

- `app/Services/PayrollCalculatorService.php`: calculation engine.
- `app/Http/Controllers/`: request validation and page controllers.
- `config/payroll.php`: single source of truth for rates, brackets, ceilings, labels, and legal references.
- `resources/views/`: Blade templates grouped by page (`calculator/`, `documentation/`, and `layouts/`).
- `routes/web.php`: public HTTP routes.
- `public/img/`: static image assets.
- `docker/` and `docker-compose.yml`: PHP-FPM and Nginx development environment.

Do not duplicate payroll values in services or views. Add or update them in `config/payroll.php`.

## Build, Test, and Development Commands

Run project commands inside the `paie_maroc_app` container:

```bash
cp .env.example .env
docker compose up -d --build                 # Build and start the app at localhost:49173
docker run --rm -v "$PWD":/app -v paie_maroc_vendor:/app/vendor -w /app composer:2.7 composer install
docker exec paie_maroc_app php artisan key:generate
docker exec paie_maroc_app vendor/bin/pint   # Format PHP code
docker exec paie_maroc_app php artisan config:clear
docker exec paie_maroc_app php artisan view:clear
```

There is no frontend build step; Bootstrap, Bootstrap Icons, and Chart.js are loaded by CDN.

## Coding Style & Naming Conventions

Follow Laravel conventions and PSR-12, enforced by Laravel Pint. Use four-space indentation in PHP. Name classes in `PascalCase`, methods and variables in `camelCase`, and Blade files/routes in descriptive lowercase names. Keep controllers focused on validation and orchestration; place payroll calculations in the service. Preserve French domain terminology and include the relevant legal reference when adding regulatory rules.

User-facing interface text must use stable keys from `lang/{fr,en,ar,es}/ui.php`. Add each new key to all four catalogs and verify Arabic RTL rendering. Never duplicate rates or ceilings in translations.

## Testing Guidelines

PHPUnit 11 tests live under `tests/Unit/` and `tests/Feature/`. Name files `*Test.php`, and cover boundaries such as tax brackets, ceilings, and rounding. Run tests with:

```bash
docker exec paie_maroc_app vendor/bin/phpunit
```

## Commit & Pull Request Guidelines

Recent commits use short, imperative summaries, with optional conventional prefixes such as `docs:`. Keep each commit scoped to one concern. Pull requests should explain behavioral changes, identify updated legal references, list verification commands, link related issues, and include screenshots for Blade/UI changes.

## Multi-Agent Coordination

When Codex, Claude Code, or a human work in parallel on this repository, follow these rules strictly:

- Never work directly on `main`.
- One issue = one branch = one topic = one active agent.
- Do not let two agents work on the same branch.
- Do not reuse an active branch for a different subject.
- Prefer branch names that reference the issue number when one exists.
- Check `git status` before starting; if unrelated edits are present in files needed for the task, stop and coordinate first.
- Avoid concurrent edits on hot files such as `app/Services/PayrollCalculatorService.php`, `config/payroll.php`, `README.md`, `resources/views/calculator/index.blade.php`, and `resources/views/calculator/result.blade.php`.
- No opportunistic refactors outside the assigned issue.
- Every delivery should state the files changed and the verification commands run.

## Writing Style

- **Never use the em dash (`—`)**. Use a simple dash (`-`), a colon (`:`), or split into two sentences.
- No emojis in code, docs, commits, or release notes.

## Release Notes

When drafting release notes, follow the standard in `CONTRIBUTING.md`. Summary:

- Structure: Nouveautes, Ameliorations, Correctifs, Securite, Mise a jour de la stack, Migration. Omit empty sections.
- French. One line per change. Start with a verb (`Ajoute`, `Corrige`, `Passe`) or a feature name.
- Reference issues (`#XX`). Quantify results when possible.
- Describe the user-visible effect, not the implementation.
- Always include a Migration section with `docker pull` command.
- No em dashes, no emojis.
