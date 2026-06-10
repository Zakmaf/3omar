<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="3omar — Simulateur gratuit et open source de bulletin de paie marocain. Comprends chaque ligne de ta fiche de paie : CNSS, AMO, IR, CIMR — CGI 2026.">
    <title>@yield('title', '3omar — Simulateur de Paie Marocain 2026')</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Readex+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            /* Primaires */
            --g-50: #F0F7F3; --g-100: #DCEBE3; --g-200: #B6D5C4; --g-300: #87B89F;
            --g-400: #4D9376; --g-500: #21704F; --g-600: #1A5B40; --g-700: #144632;
            --g-800: #0E3324; --g-900: #082017;
            --r-50: #FBEDED; --r-100: #F4CFD0; --r-300: #E07478; --r-500: #C1272D; --r-700: #8A1B20;

            /* Semantiques */
            --s-info: #1B5FD9; --s-cot: #6F42C1; --s-tax: #C1272D; --s-succ: #1B7A4A;
            --s-warn: #D97706; --s-neutral: #6C757D;
            --s-succ-bg: #F0F7F3; --s-warn-bg: #FFF7ED; --s-info-bg: #EEF4FE;
            --s-tax-bg: #FBEDED; --s-cot-bg: #F4EFFB;

            /* Surfaces */
            --ink: #1A2E26; --ink-2: #4A5C54; --ink-3: #7A8881;
            --cream: #FAF8F3; --paper: #FFFFFF;
            --hairline: rgba(26,46,38,0.10); --hairline-strong: rgba(26,46,38,0.18);

            /* Layout tokens */
            --radius: 0.75rem; --radius-sm: 0.5rem; --radius-lg: 1rem;
            --shadow-1: 0 1px 2px rgba(8,32,23,0.06), 0 1px 1px rgba(8,32,23,0.04);
            --shadow-2: 0 4px 16px rgba(8,32,23,0.08), 0 2px 4px rgba(8,32,23,0.04);

            /* Type */
            --f-display: "Outfit", system-ui, sans-serif;
            --f-body: "Plus Jakarta Sans", system-ui, sans-serif;
            --f-accent: "Readex Pro", system-ui, sans-serif;
            --f-mono: "JetBrains Mono", ui-monospace, monospace;

            /* Zellige pattern */
            --zellige-bg: url("data:image/svg+xml;utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 120'%3E%3Cdefs%3E%3Cg id='star'%3E%3Cpath d='M60 10 L66 38 L94 32 L74 52 L94 72 L66 66 L60 94 L54 66 L26 72 L46 52 L26 32 L54 38 Z' fill='none' stroke='%2321704F' stroke-width='1'/%3E%3C/g%3E%3C/defs%3E%3Cuse href='%23star'/%3E%3Cuse href='%23star' x='-60' y='-60'/%3E%3Cuse href='%23star' x='60' y='-60'/%3E%3Cuse href='%23star' x='-60' y='60'/%3E%3Cuse href='%23star' x='60' y='60'/%3E%3C/svg%3E");
        }

        body {
            font-family: var(--f-body);
            color: var(--ink);
            background: var(--cream);
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: var(--f-display);
            color: var(--ink);
        }

        /* Navbar */
        .navbar {
            box-shadow: var(--shadow-2);
        }
        .navbar-brand {
            font-family: var(--f-display);
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: -.3px;
        }
        .navbar .nav-link {
            font-family: var(--f-body);
            font-weight: 500;
        }

        /* Cards */
        .section-card {
            border: 1px solid var(--hairline);
            border-radius: var(--radius);
            box-shadow: var(--shadow-2);
            background: var(--paper);
        }
        .section-card .card-header {
            border-radius: var(--radius) var(--radius) 0 0 !important;
            background: var(--paper);
            border-bottom: 2px solid var(--hairline);
            font-family: var(--f-display);
            font-weight: 600;
        }

        /* Net amount */
        .net-amount {
            font-family: var(--f-display);
            font-size: 2.8rem;
            font-weight: 800;
            color: var(--s-succ);
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        /* Detail table */
        .detail-table th {
            font-family: var(--f-mono);
            font-size: 0.7rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--ink-2);
        }
        .detail-table td { vertical-align: middle; }

        /* Row colors */
        .row-brut   td:first-child { border-left: 3px solid var(--s-info); }
        .row-cotis  td:first-child { border-left: 3px solid var(--s-cot); }
        .row-impot  td:first-child { border-left: 3px solid var(--s-tax); }
        .row-indem  td:first-child { border-left: 3px solid var(--s-succ); }
        .row-retenue td:first-child { border-left: 3px solid var(--s-neutral); }
        .row-patron td:first-child { border-left: 3px solid var(--s-warn); }

        .row-net {
            background: var(--s-succ-bg);
            font-family: var(--f-display);
            font-weight: 700;
        }
        .row-net td:first-child { border-left: 3px solid var(--s-succ); }

        .row-employer {
            background: var(--s-warn-bg);
            font-family: var(--f-display);
            font-weight: 700;
        }
        .row-employer td:first-child { border-left: 3px solid var(--s-warn); }

        /* Legal badge */
        .badge-legal {
            background: var(--g-50);
            color: var(--g-700);
            border: 1px solid var(--g-200);
            font-family: var(--f-mono);
            font-size: 0.65rem;
            border-radius: 999px;
            padding: 2px 8px;
            font-weight: 400;
        }

        /* Buttons */
        .btn-success,
        .btn-success:active {
            background: var(--g-500);
            border-color: var(--g-500);
        }
        .btn-success:hover,
        .btn-success:focus-visible {
            background: var(--g-600);
            border-color: var(--g-600);
        }

        /* Alert semantic backgrounds */
        .alert-warning { background: var(--s-warn-bg); }
        .alert-danger  { background: var(--s-tax-bg); }
        .alert-info    { background: var(--s-info-bg); }

        /* Footer */
        footer {
            background: var(--g-800);
            color: var(--cream);
            font-size: .85rem;
        }
        footer .footer-body-text {
            color: rgba(255,255,255,0.78);
        }
        footer a {
            color: rgba(255,255,255,0.78);
            text-decoration: none;
            transition: color .15s;
        }
        footer a:hover {
            color: #fff;
        }
        footer h6 {
            color: var(--cream);
        }
        footer .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.12);
        }

        @media (max-width: 576px) { .net-amount { font-size: 2rem; } }
    </style>

    @stack('head')
</head>
<body>

{{-- SVG symbol definitions --}}
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <defs>
    <symbol id="logo-full" viewBox="0 0 520 180">
      <g fill="#21704F">
        <path d="M 60 50 C 60 32, 78 22, 100 22 C 122 22, 138 36, 138 52 C 138 66, 128 76, 116 78 C 128 80, 138 90, 138 104 L 138 106 C 138 124, 118 138, 96 138 C 78 138, 64 130, 58 118 L 78 108 C 82 116, 88 120, 96 120 C 108 120, 116 113, 116 104 C 116 95, 108 90, 96 90 L 88 90 L 88 72 L 96 72 C 106 72, 114 67, 114 58 C 114 49, 106 42, 96 42 C 86 42, 80 47, 78 56 Z"/>
      </g>
      <path d="M 38 100 C 38 130, 60 152, 90 152 C 110 152, 128 140, 138 122 C 130 134, 116 142, 100 142 C 76 142, 56 122, 56 98 C 56 90, 58 82, 62 76 C 48 82, 38 90, 38 100 Z" fill="#C1272D"/>
      <circle cx="200" cy="98" r="38" fill="none" stroke="#21704F" stroke-width="20"/>
      <g fill="#21704F" font-family="Outfit, sans-serif" font-weight="700">
        <path d="M 252 60 L 272 60 L 272 72 C 278 64, 286 58, 296 58 C 308 58, 316 64, 320 74 C 326 64, 334 58, 346 58 C 362 58, 374 70, 374 90 L 374 136 L 354 136 L 354 96 C 354 86, 348 78, 340 78 C 332 78, 326 86, 326 96 L 326 136 L 306 136 L 306 96 C 306 86, 300 78, 292 78 C 284 78, 278 86, 278 96 L 278 136 L 252 136 Z"/>
        <path d="M 422 58 C 442 58, 456 72, 456 92 L 456 136 L 436 136 L 436 128 C 432 134, 424 138, 414 138 C 396 138, 384 126, 384 110 C 384 94, 396 84, 414 82 L 436 82 C 434 76, 428 72, 422 72 C 414 72, 410 76, 408 80 L 390 72 C 396 64, 408 58, 422 58 Z M 420 122 C 430 122, 436 116, 436 108 L 436 96 L 420 98 C 412 100, 408 104, 408 110 C 408 117, 412 122, 420 122 Z"/>
        <path d="M 478 60 L 498 60 L 498 76 C 502 66, 510 58, 522 58 L 522 80 C 510 80, 498 84, 498 100 L 498 136 L 478 136 Z"/>
      </g>
    </symbol>
    <symbol id="logo-reverse" viewBox="0 0 520 180">
      <g fill="#FFFFFF">
        <path d="M 60 50 C 60 32, 78 22, 100 22 C 122 22, 138 36, 138 52 C 138 66, 128 76, 116 78 C 128 80, 138 90, 138 104 L 138 106 C 138 124, 118 138, 96 138 C 78 138, 64 130, 58 118 L 78 108 C 82 116, 88 120, 96 120 C 108 120, 116 113, 116 104 C 116 95, 108 90, 96 90 L 88 90 L 88 72 L 96 72 C 106 72, 114 67, 114 58 C 114 49, 106 42, 96 42 C 86 42, 80 47, 78 56 Z"/>
      </g>
      <path d="M 38 100 C 38 130, 60 152, 90 152 C 110 152, 128 140, 138 122 C 130 134, 116 142, 100 142 C 76 142, 56 122, 56 98 C 56 90, 58 82, 62 76 C 48 82, 38 90, 38 100 Z" fill="#E07478"/>
      <circle cx="200" cy="98" r="38" fill="none" stroke="#FFFFFF" stroke-width="20"/>
      <g fill="#FFFFFF">
        <path d="M 252 60 L 272 60 L 272 72 C 278 64, 286 58, 296 58 C 308 58, 316 64, 320 74 C 326 64, 334 58, 346 58 C 362 58, 374 70, 374 90 L 374 136 L 354 136 L 354 96 C 354 86, 348 78, 340 78 C 332 78, 326 86, 326 96 L 326 136 L 306 136 L 306 96 C 306 86, 300 78, 292 78 C 284 78, 278 86, 278 96 L 278 136 L 252 136 Z"/>
        <path d="M 422 58 C 442 58, 456 72, 456 92 L 456 136 L 436 136 L 436 128 C 432 134, 424 138, 414 138 C 396 138, 384 126, 384 110 C 384 94, 396 84, 414 82 L 436 82 C 434 76, 428 72, 422 72 C 414 72, 410 76, 408 80 L 390 72 C 396 64, 408 58, 422 58 Z M 420 122 C 430 122, 436 116, 436 108 L 436 96 L 420 98 C 412 100, 408 104, 408 110 C 408 117, 412 122, 420 122 Z"/>
        <path d="M 478 60 L 498 60 L 498 76 C 502 66, 510 58, 522 58 L 522 80 C 510 80, 498 84, 498 100 L 498 136 L 478 136 Z"/>
      </g>
    </symbol>
    <symbol id="mark" viewBox="0 0 200 200">
      <rect x="4" y="4" width="192" height="192" rx="36" fill="#21704F"/>
      <circle cx="100" cy="100" r="74" fill="#FAF8F3"/>
      <g transform="translate(60 36) scale(0.7)">
        <path d="M 60 50 C 60 32, 78 22, 100 22 C 122 22, 138 36, 138 52 C 138 66, 128 76, 116 78 C 128 80, 138 90, 138 104 L 138 106 C 138 124, 118 138, 96 138 C 78 138, 64 130, 58 118 L 78 108 C 82 116, 88 120, 96 120 C 108 120, 116 113, 116 104 C 116 95, 108 90, 96 90 L 88 90 L 88 72 L 96 72 C 106 72, 114 67, 114 58 C 114 49, 106 42, 96 42 C 86 42, 80 47, 78 56 Z" fill="#21704F"/>
        <path d="M 38 100 C 38 130, 60 152, 90 152 C 110 152, 128 140, 138 122 C 130 134, 116 142, 100 142 C 76 142, 56 122, 56 98 C 56 90, 58 82, 62 76 C 48 82, 38 90, 38 100 Z" fill="#C1272D"/>
      </g>
    </symbol>
  </defs>
</svg>

<nav class="navbar navbar-expand-lg navbar-dark" style="background:var(--g-500);">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
            <svg viewBox="0 0 200 200" style="width:32px;height:32px"><use href="#mark"/></svg>
            <span style="font-family:var(--f-display);font-weight:700">3omar</span>
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

            {{-- Colonne 1 : Identite --}}
            <div class="col-md-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <svg viewBox="0 0 520 180" style="width:120px;height:auto"><use href="#logo-reverse"/></svg>
                </div>
                <p class="footer-body-text small mb-2" style="font-family:var(--f-body)">
                    Simulateur gratuit de paie marocaine
                </p>
                <p class="small mb-1" style="color:var(--cream)">
                    <i class="bi bi-person-circle me-1"></i>
                    <strong>Zakaria Maftah</strong>
                </p>
                <p class="small mb-0">
                    <i class="bi bi-envelope me-1"></i>
                    <a href="mailto:contact@zakmaf.net">contact@zakmaf.net</a>
                </p>
            </div>

            {{-- Colonne 2 : Navigation --}}
            <div class="col-md-4">
                <h6 class="fw-semibold mb-3" style="font-family:var(--f-display)">
                    <i class="bi bi-signpost-split me-1"></i>Navigation
                </h6>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2">
                        <a href="{{ route('calculator.index') }}">
                            <i class="bi bi-calculator me-1"></i>Simuler mon bulletin
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('documentation') }}">
                            <i class="bi bi-journal-text me-1"></i>Documentation legale 2026
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="https://github.com/Zakmaf/3omar" target="_blank" rel="noopener">
                            <i class="bi bi-github me-1"></i>Code source GitHub
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="https://github.com/Zakmaf/3omar/issues" target="_blank" rel="noopener">
                            <i class="bi bi-bug me-1"></i>Signaler une erreur
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Colonne 3 : Disclaimer --}}
            <div class="col-md-4">
                <h6 class="fw-semibold mb-3" style="font-family:var(--f-display);color:var(--s-warn)">
                    <i class="bi bi-exclamation-triangle me-1"></i>Avertissement
                </h6>
                <p class="footer-body-text small mb-2">
                    3omar est un outil <strong>pedagogique et informatif</strong>.
                    Les resultats s'appuient sur la reglementation en vigueur (CGI 2026,
                    Dahir 1-72-184, Loi 65-00...), mais peuvent contenir des inexactitudes.
                    Pour votre bulletin officiel, consultez votre employeur ou un expert-comptable.
                </p>
                <p class="footer-body-text small mb-0">
                    <i class="bi bi-shield-check me-1" style="color:var(--g-300)"></i>
                    <strong>Aucune donnee personnelle collectee</strong> ni stockee.
                    Calcul instantane, rien n'est conserve.
                </p>
            </div>

        </div>

        <div class="footer-bottom pt-3 mt-3">
            <div class="row align-items-center">
                <div class="col-md-8 small footer-body-text">
                    <i class="bi bi-shield-check me-1" style="color:var(--g-300)"></i>
                    Exercice fiscal 2026 &middot; CGI Art. 73, 74, 59, 28 &middot; Dahir n&deg; 1-72-184 &middot; Loi n&deg; 65-00 &middot; Arrete n&deg; 1314-25 &middot; Decret n&deg; 2.25.983
                </div>
                <div class="col-md-4 text-md-end small footer-body-text mt-2 mt-md-0">
                    &copy; 2026 3omar &mdash; Projet open source sous licence MIT
                </div>
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
