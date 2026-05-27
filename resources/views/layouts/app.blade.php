<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Simulateur pédagogique de bulletin de paie pour le secteur privé marocain — CGI 2026, CNSS, AMO, IR, CIMR, coût employeur">
    <title>@yield('title', 'Mon Bulletin de Paie Marocain 2026')</title>

    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --bs-body-bg: #f8f9fa;
            --brand-green: #21704f;
            --brand-red:   #c1272d;
        }
        body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }

        .navbar-brand { font-weight: 700; font-size: 1.15rem; letter-spacing: -.3px; }
        .navbar { box-shadow: 0 2px 8px rgba(0,0,0,.08); }

        .section-card { border: none; border-radius: .75rem; box-shadow: 0 2px 12px rgba(0,0,0,.07); }
        .section-card .card-header {
            border-radius: .75rem .75rem 0 0 !important;
            background: #fff;
            border-bottom: 2px solid #e9ecef;
            font-weight: 600;
        }

        .net-amount { font-size: 2.8rem; font-weight: 800; color: var(--brand-green); line-height: 1; }

        .detail-table th { font-size: .78rem; text-transform: uppercase; letter-spacing: .5px; color: #6c757d; }
        .detail-table td { vertical-align: middle; }
        .row-brut     td:first-child { border-left: 3px solid #0d6efd; }
        .row-cotis    td:first-child { border-left: 3px solid #6f42c1; }
        .row-impot    td:first-child { border-left: 3px solid #dc3545; }
        .row-indem    td:first-child { border-left: 3px solid #198754; }
        .row-retenue  td:first-child { border-left: 3px solid #6c757d; }
        .row-patron   td:first-child { border-left: 3px solid #fd7e14; }
        .row-net      { background: #f0fdf4; font-weight: 700; }
        .row-net      td:first-child { border-left: 3px solid #198754; }
        .row-employer { background: #fff8f0; font-weight: 700; }
        .row-employer td:first-child { border-left: 3px solid #fd7e14; }

        .badge-legal { font-size: .7rem; font-weight: 400; opacity: .85; }

        /* Footer enrichi */
        footer {
            font-size: .85rem;
            color: #6c757d;
            border-top: 1px solid #dee2e6;
            background: #f1f3f5;
        }
        footer a { color: #6c757d; text-decoration: none; }
        footer a:hover { color: var(--brand-green); }

        @media (max-width: 576px) { .net-amount { font-size: 2rem; } }
    </style>

    @stack('head')
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark" style="background:var(--brand-green);">
    <div class="container">
        <a class="navbar-brand" href="{{ route('home') }}">
            <i class="bi bi-receipt-cutoff me-2"></i>Bulletin de Paie Maroc
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto gap-1">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('home') ? 'active fw-semibold' : '' }}"
                       href="{{ route('home') }}">
                        <i class="bi bi-house me-1"></i>Accueil
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('calculator.*') ? 'active fw-semibold' : '' }}"
                       href="{{ route('calculator.index') }}">
                        <i class="bi bi-calculator me-1"></i>Calculateur
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('documentation') ? 'active fw-semibold' : '' }}"
                       href="{{ route('documentation') }}">
                        <i class="bi bi-journal-text me-1"></i>Documentation
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="py-4">
    @yield('content')
</main>

<footer class="pt-5 pb-4 mt-5">
    <div class="container">
        <div class="row g-4 mb-4">

            {{-- Colonne 1 : Identité --}}
            <div class="col-md-4">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-receipt-cutoff fs-4 me-2" style="color:var(--brand-green)"></i>
                    <span class="fw-bold">Bulletin de Paie Maroc</span>
                </div>
                <p class="small text-muted mb-2">
                    Simulateur pédagogique de paie pour le secteur privé marocain.
                    Conformité <strong>CGI 2026</strong> — Loi de Finances 50-25.
                </p>
                <p class="small mb-0">
                    <i class="bi bi-person-circle me-1"></i>
                    Développé par <strong>Zakaria Maftah</strong>
                </p>
                <p class="small mt-1 mb-0">
                    <i class="bi bi-envelope me-1"></i>
                    <a href="mailto:support-bulletindepaie@zakmaf.net">support-bulletindepaie@zakmaf.net</a>
                </p>
            </div>

            {{-- Colonne 2 : Stack technique --}}
            <div class="col-md-4">
                <h6 class="fw-semibold mb-2"><i class="bi bi-code-slash me-1"></i>Stack technique</h6>
                <ul class="list-unstyled small text-muted mb-0">
                    <li class="mb-1"><i class="bi bi-box me-1 text-danger"></i><strong>Laravel 11</strong> — Framework PHP (backend MVC)</li>
                    <li class="mb-1"><i class="bi bi-filetype-php me-1 text-primary"></i><strong>PHP 8.3</strong> — Moteur de calcul</li>
                    <li class="mb-1"><i class="bi bi-layout-text-window me-1 text-success"></i><strong>Bootstrap 5</strong> + Blade — Interface</li>
                    <li class="mb-1"><i class="bi bi-pie-chart-fill me-1 text-warning"></i><strong>Chart.js 4</strong> — Visualisation</li>
                    <li class="mb-1"><i class="bi bi-docker me-1 text-info"></i><strong>Docker</strong> — Nginx + PHP-FPM</li>
                    <li class="mb-1"><i class="bi bi-unlock me-1 text-success"></i><strong>Open Source</strong> — Gratuit & collaboratif</li>
                </ul>
            </div>

            {{-- Colonne 3 : Disclaimer --}}
            <div class="col-md-4">
                <h6 class="fw-semibold mb-2 text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Avertissement légal</h6>
                <p class="small text-muted mb-2">
                    Cet outil est fourni <strong>à titre purement informatif et pédagogique</strong>.
                    Bien que nous nous efforcions de refléter la réglementation en vigueur (CGI 2026,
                    Dahir 1-72-184, Loi 65-00…), les résultats peuvent contenir des inexactitudes.
                </p>
                <p class="small text-muted mb-0">
                    <strong>Aucune donnée personnelle n'est collectée ou stockée.</strong>
                    Les calculs sont effectués en temps réel et ne sont pas conservés.
                    Pour votre bulletin officiel, consultez votre employeur ou un expert-comptable.
                </p>
            </div>

        </div>

        <hr class="my-3">

        <div class="row align-items-center">
            <div class="col-md-8 small text-muted">
                <i class="bi bi-shield-check text-success me-1"></i>
                Exercice fiscal 2026 · CGI Art. 73, 74, 59, 28 · Dahir n° 1-72-184 · Loi n° 65-00 · Arrêté n° 1314-25 · Décret n° 2.25.983
            </div>
            <div class="col-md-4 text-md-end small text-muted mt-2 mt-md-0">
                &copy; {{ date('Y') }} Zakaria Maftah — Licence MIT
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmE3FGjEe6xcMuB/93AAnXrwEAX" crossorigin="anonymous"></script>

@stack('scripts')
</body>
</html>
