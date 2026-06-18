<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('app.supported_locales.'.app()->getLocale().'.dir', 'ltr') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('ui.meta_description') }}">
    <meta property="og:title" content="@yield('title', __('ui.meta_title'))">
    <meta property="og:description" content="{{ __('ui.meta_social') }}">
    <meta property="og:image" content="{{ asset('img/logo_banner_1200x630.png') }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="{{ config('app.supported_locales.'.app()->getLocale().'.og') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/app_icon_32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('img/app_icon_16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('img/app_icon_180.png') }}">
    <title>@yield('title', __('ui.meta_title'))</title>

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
            line-height: 1.6;
        }
        .skip-link {
            position: absolute;
            left: 1rem;
            top: -5rem;
            z-index: 2000;
            padding: .75rem 1rem;
            border-radius: var(--radius-sm);
            background: var(--paper);
            color: var(--g-700);
            box-shadow: var(--shadow-2);
        }
        .skip-link:focus { top: 1rem; }
        :focus-visible {
            outline: 3px solid var(--s-info);
            outline-offset: 3px;
        }
        button, .btn, .nav-link, summary {
            min-height: 44px;
        }
        summary {
            cursor: pointer;
        }
        .form-control, .form-select, .input-group-text {
            min-height: 44px;
        }
        .form-text {
            color: var(--ink-2);
        }
        .eyebrow {
            color: var(--g-600);
            font-family: var(--f-mono);
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .page-intro {
            max-width: 48rem;
        }
        .action-bar {
            background: rgba(250,248,243,.94);
            border: 1px solid var(--hairline);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-2);
            backdrop-filter: blur(12px);
        }
        .advanced-section[hidden] {
            display: none !important;
        }
        .ad-slot {
            margin-block: 1.5rem;
            overflow: hidden;
            text-align: center;
        }
        .ad-slot-horizontal {
            min-height: 90px;
        }
        .ad-slot-rectangle {
            min-height: 250px;
        }
        [dir="rtl"] body {
            font-family: var(--f-accent);
        }
        [dir="rtl"] .ms-auto { margin-right: auto !important; margin-left: 0 !important; }
        [dir="rtl"] .me-1, [dir="rtl"] .me-2 { margin-left: .5rem !important; margin-right: 0 !important; }
        [dir="rtl"] .ms-1, [dir="rtl"] .ms-2 { margin-right: .5rem !important; margin-left: 0 !important; }
        [dir="rtl"] .text-end { text-align: left !important; }
        [dir="rtl"] .row-brut td:first-child,
        [dir="rtl"] .row-cotis td:first-child,
        [dir="rtl"] .row-impot td:first-child,
        [dir="rtl"] .row-indem td:first-child,
        [dir="rtl"] .row-retenue td:first-child,
        [dir="rtl"] .row-patron td:first-child,
        [dir="rtl"] .row-net td:first-child,
        [dir="rtl"] .row-employer td:first-child {
            border-left: 0;
            border-right: 3px solid var(--g-500);
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: var(--f-display);
            color: var(--ink);
        }

        /* Navbar — fond clair, usage par défaut du logotype (charte §02) */
        .navbar {
            background: var(--paper);
            border-bottom: 1px solid var(--hairline);
            box-shadow: var(--shadow-1);
        }
        .navbar .nav-link {
            font-family: var(--f-body);
            font-weight: 500;
            color: var(--ink-2);
        }
        .navbar .nav-link:hover,
        .navbar .nav-link:focus-visible {
            color: var(--g-600);
        }
        .navbar .nav-link.active {
            color: var(--g-600);
        }
        .language-switcher {
            display: inline-flex;
            align-items: center;
            border: 1px solid var(--hairline);
            border-radius: var(--radius-sm);
            background: var(--paper);
            overflow: hidden;
        }
        .language-switcher-label {
            display: inline-flex;
            align-items: center;
            min-height: 38px;
            padding: 0 .65rem;
            color: var(--ink-3);
            border-inline-end: 1px solid var(--hairline);
        }
        .language-switcher-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.75rem;
            min-height: 38px;
            padding: 0 .55rem;
            color: var(--ink-2);
            text-decoration: none;
            font-family: var(--f-mono);
            font-size: .76rem;
            font-weight: 500;
        }
        .language-switcher-link:hover,
        .language-switcher-link:focus-visible {
            color: var(--g-600);
            background: var(--g-50);
        }
        .language-switcher-link.active {
            color: var(--paper);
            background: var(--g-500);
            font-weight: 700;
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

        @media (max-width: 576px) {
            .net-amount { font-size: 2rem; }
            .mobile-sticky {
                position: sticky;
                bottom: .75rem;
                z-index: 1020;
            }
            .mobile-sticky .btn {
                width: 100%;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
                animation-duration: .01ms !important;
            }
        }
        @media print {
            .navbar, footer, .no-print, .ad-slot { display: none !important; }
            main { padding: 0 !important; }
            body { background: #fff; }
            .section-card, .card { box-shadow: none !important; border: 1px solid var(--hairline-strong) !important; }
        }
    </style>

    @stack('head')
    @if (app()->environment('production') && config('ads.enabled') && config('ads.client'))
    <script async
            src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ config('ads.client') }}"
            crossorigin="anonymous"></script>
    @endif
</head>
<body>
<a class="skip-link" href="#main-content">{{ __('ui.skip') }}</a>


<nav class="navbar navbar-expand-lg navbar-light">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="{{ route('home') }}">
            <img src="{{ asset('img/logo_header_400x100.png') }}" alt="3omar" height="40" width="160">
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#nav"
                aria-controls="nav" aria-expanded="false" aria-label="Afficher la navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            @php($currentLocale = app()->getLocale())
            @php($supportedLocales = config('app.supported_locales'))
            <ul class="navbar-nav ms-auto gap-1 align-items-lg-center" aria-label="Navigation principale">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('home') ? 'active fw-semibold' : '' }}"
                       @if(request()->routeIs('home')) aria-current="page" @endif
                       href="{{ route('home') }}">
                        <i class="bi bi-house me-1"></i>{{ __('ui.nav.home') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('calculator.*') ? 'active fw-semibold' : '' }}"
                       @if(request()->routeIs('calculator.*')) aria-current="page" @endif
                       href="{{ route('calculator.index') }}">
                        <i class="bi bi-calculator me-1"></i>{{ __('ui.nav.calculator') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('documentation') ? 'active fw-semibold' : '' }}"
                       @if(request()->routeIs('documentation')) aria-current="page" @endif
                       href="{{ route('documentation') }}">
                        <i class="bi bi-journal-text me-1"></i>{{ __('ui.nav.documentation') }}
                    </a>
                </li>
                <li class="nav-item mt-2 mt-lg-0">
                    <div class="language-switcher" aria-label="{{ __('ui.nav.language') }}">
                        <span class="language-switcher-label" aria-hidden="true">
                            <i class="bi bi-translate"></i>
                        </span>
                        @foreach($supportedLocales as $locale => $localeConfig)
                        <a class="language-switcher-link {{ $locale === $currentLocale ? 'active' : '' }}"
                           href="{{ route('locale.update', $locale) }}"
                           lang="{{ $locale }}"
                           hreflang="{{ $locale }}"
                           title="{{ $localeConfig['label'] }}"
                           aria-label="{{ $localeConfig['label'] }}"
                           @if($locale === $currentLocale) aria-current="true" @endif>
                            {{ $localeConfig['short'] }}
                        </a>
                        @endforeach
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <x-ad-slot placement="header" />
</div>

<main class="py-4" id="main-content">
    @yield('content')
</main>

<div class="container">
    <x-ad-slot placement="footer" />
</div>

<footer class="pt-5 pb-4 mt-5">
    <div class="container">
        <div class="row g-4 mb-4">

            {{-- Colonne 1 : Identité --}}
            <div class="col-md-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <img src="{{ asset('img/app_icon_192.png') }}" alt="" width="36" height="36" style="border-radius:.5rem">
                    <span style="font-family:var(--f-display);font-weight:700;font-size:1.3rem;color:var(--cream)">3omar</span>
                </div>
                <p class="footer-body-text small mb-2" style="font-family:var(--f-body)">
                    {{ __('ui.footer.tagline') }}
                </p>
                <p class="small mb-1" style="color:var(--cream)">
                    <i class="bi bi-person-circle me-1"></i>
                    <strong>Zakaria Maftah</strong>
                </p>
                <p class="small mb-0">
                    <i class="bi bi-envelope me-1"></i>
                    <a href="mailto:support@3omar.ma">support@3omar.ma</a>
                </p>
            </div>

            {{-- Colonne 2 : Navigation --}}
            <div class="col-md-4">
                <h6 class="fw-semibold mb-3" style="font-family:var(--f-display)">
                    <i class="bi bi-signpost-split me-1"></i>{{ __('ui.footer.navigation') }}
                </h6>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2">
                        <a href="{{ route('calculator.index') }}">
                            <i class="bi bi-calculator me-1"></i>{{ __('ui.footer.simulate') }}
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{{ route('documentation') }}">
                            <i class="bi bi-journal-text me-1"></i>{{ __('ui.footer.rules') }}
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="https://github.com/Zakmaf/3omar" target="_blank" rel="noopener">
                            <i class="bi bi-github me-1"></i>{{ __('ui.footer.source') }}
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="https://github.com/Zakmaf/3omar/issues" target="_blank" rel="noopener">
                            <i class="bi bi-bug me-1"></i>{{ __('ui.footer.report') }}
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Colonne 3 : Disclaimer --}}
            <div class="col-md-4">
                <h6 class="fw-semibold mb-3" style="font-family:var(--f-display);color:var(--s-warn)">
                    <i class="bi bi-exclamation-triangle me-1"></i>{{ __('ui.footer.warning') }}
                </h6>
                <p class="footer-body-text small mb-2">
                    {{ __('ui.footer.warning_text') }}
                    {{ __('ui.footer.consult') }}
                </p>
                <p class="footer-body-text small mb-0">
                    <i class="bi bi-shield-check me-1" style="color:var(--g-300)"></i>
                    <strong>{{ __('ui.footer.privacy') }}</strong>
                    {{ __('ui.footer.privacy_detail') }}
                </p>
            </div>

        </div>

        <div class="footer-bottom pt-3 mt-3">
            <div class="small footer-body-text text-center">
                &copy; 2026 3omar · {{ __('ui.footer.license') }}
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmE3FGjEe6xcMuB/93AAnXrwEAX" crossorigin="anonymous"></script>

@stack('scripts')
@if (app()->environment('production') && config('ads.enabled') && config('ads.client'))
<script>
    document.querySelectorAll('.adsbygoogle').forEach(() => {
        (window.adsbygoogle = window.adsbygoogle || []).push({});
    });
</script>
@endif
</body>
</html>
