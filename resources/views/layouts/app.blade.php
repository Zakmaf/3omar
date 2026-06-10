<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="3omar — Simulateur gratuit et open source de bulletin de paie marocain. Comprends chaque ligne de ta fiche de paie : CNSS, AMO, IR, CIMR — CGI 2026.">
    <title>@yield('title', '3omar — Simulateur de Paie Marocain 2026')</title>

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
            <span class="me-1" style="font-size:1.3rem">&#x2794;</span>3omar
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
                    <span class="fs-4 me-2" style="color:var(--brand-green)">&#x2794;</span>
                    <span class="fw-bold fs-5">3omar</span>
                </div>
                <p class="small text-muted mb-2">
                    Simulateur gratuit et open source de paie marocaine.
                    Parce que chaque salarié a le droit de comprendre sa fiche de paie.
                </p>
                <p class="small mb-0">
                    <i class="bi bi-person-circle me-1"></i>
                    Projet initié par <strong>Zakaria Maftah</strong>
                </p>
                <p class="small mt-1 mb-0">
                    <i class="bi bi-envelope me-1"></i>
                    <a href="mailto:contact@zakmaf.net">contact@zakmaf.net</a>
                </p>
            </div>

            {{-- Colonne 2 : Navigation --}}
            <div class="col-md-4">
                <h6 class="fw-semibold mb-2"><i class="bi bi-signpost-split me-1"></i>Navigation</h6>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2"><a href="{{ route('calculator.index') }}"><i class="bi bi-calculator me-1 text-success"></i>Simuler mon bulletin</a></li>
                    <li class="mb-2"><a href="{{ route('documentation') }}"><i class="bi bi-journal-text me-1 text-primary"></i>Documentation legale 2026</a></li>
                    <li class="mb-2"><a href="https://github.com/Zakmaf/3omar" target="_blank" rel="noopener"><i class="bi bi-github me-1"></i>Code source sur GitHub</a></li>
                    <li class="mb-2"><a href="https://github.com/Zakmaf/3omar/issues" target="_blank" rel="noopener"><i class="bi bi-bug me-1 text-warning"></i>Signaler une erreur</a></li>
                </ul>
            </div>

            {{-- Colonne 3 : Disclaimer --}}
            <div class="col-md-4">
                <h6 class="fw-semibold mb-2 text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Avertissement</h6>
                <p class="small text-muted mb-2">
                    3omar est un outil <strong>pédagogique et informatif</strong>.
                    Les résultats s'appuient sur la réglementation en vigueur (CGI 2026,
                    Dahir 1-72-184, Loi 65-00…), mais peuvent contenir des inexactitudes.
                    Pour votre bulletin officiel, consultez votre employeur ou un expert-comptable.
                </p>
                <p class="small text-muted mb-0">
                    <i class="bi bi-shield-check text-success me-1"></i>
                    <strong>Aucune donnée personnelle n'est collectée ni stockée.</strong>
                    Calcul instantané, rien n'est conservé.
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
                &copy; {{ date('Y') }} 3omar — Projet open source sous licence MIT
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
