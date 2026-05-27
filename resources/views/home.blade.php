@extends('layouts.app')

@section('title', 'Accueil — Simulateur de Bulletin de Paie Marocain 2026')

@section('content')
<div class="container">

    {{-- ================================================================ --}}
    {{-- HERO                                                              --}}
    {{-- ================================================================ --}}
    <div class="row justify-content-center my-4 my-lg-5">
        <div class="col-lg-9 text-center">
            <div class="mb-3 d-flex justify-content-center gap-2 flex-wrap">
                <span class="badge rounded-pill text-bg-success px-3 py-2 fs-6">
                    <i class="bi bi-calendar-check me-1"></i>CGI 2026 — Loi de Finances 50-25
                </span>
                <span class="badge rounded-pill text-bg-primary px-3 py-2 fs-6">
                    <i class="bi bi-unlock me-1"></i>Open Source &amp; Gratuit
                </span>
                <span class="badge rounded-pill text-bg-secondary px-3 py-2 fs-6">
                    <i class="bi bi-shield-check me-1"></i>Aucune donnée collectée
                </span>
            </div>
            <h1 class="display-5 fw-bold mb-3">
                Le simulateur de paie marocain<br>
                <span style="color:var(--brand-green)">le plus transparent du marché</span>
            </h1>
            <p class="lead text-muted mb-4">
                Calculez votre salaire net avec précision : CNSS, AMO, CIMR, IR progressif,
                ancienneté, coût employeur — <strong>chaque ligne expliquée et référencée</strong>.
                Conçu pour les salariés, DRH et comptables du secteur privé marocain.
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="{{ route('calculator.index') }}" class="btn btn-lg px-5 text-white fw-semibold"
                   style="background:var(--brand-green)">
                    <i class="bi bi-calculator me-2"></i>Calculer mon bulletin
                </a>
                <a href="{{ route('documentation') }}" class="btn btn-lg btn-outline-secondary px-4">
                    <i class="bi bi-journal-text me-2"></i>Documentation légale
                </a>
            </div>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- STATISTIQUES RAPIDES                                              --}}
    {{-- ================================================================ --}}
    <div class="row g-3 my-2 text-center">
        @foreach ([
            ['val' => '8',  'unit' => 'postes',   'label' => 'de calcul détaillés',        'icon' => 'bi-list-check',       'color' => 'text-success'],
            ['val' => '6',  'unit' => 'tranches',  'label' => 'IR barème Art. 73 CGI',      'icon' => 'bi-percent',          'color' => 'text-danger'],
            ['val' => '8',  'unit' => 'types',     'label' => "d'indemnités exonérées",     'icon' => 'bi-gift',             'color' => 'text-primary'],
            ['val' => '0',  'unit' => 'donnée',    'label' => 'personnelle stockée',         'icon' => 'bi-shield-lock-fill', 'color' => 'text-warning'],
        ] as $stat)
        <div class="col-6 col-md-3">
            <div class="card section-card h-100 py-3 px-2">
                <div class="{{ $stat['color'] }} fs-2 mb-1"><i class="bi {{ $stat['icon'] }}"></i></div>
                <div class="fw-bold fs-4">{{ $stat['val'] }} <small class="fs-6 fw-normal text-muted">{{ $stat['unit'] }}</small></div>
                <div class="small text-muted">{{ $stat['label'] }}</div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ================================================================ --}}
    {{-- CE QUI NOUS DISTINGUE                                            --}}
    {{-- ================================================================ --}}
    <div class="row g-4 mt-3">
        <div class="col-md-4">
            <div class="card section-card h-100 p-3">
                <div class="card-body">
                    <div class="text-success fs-3 mb-2"><i class="bi bi-patch-check-fill"></i></div>
                    <h5 class="fw-bold">Précision réglementaire</h5>
                    <p class="text-muted mb-0">
                        Chaque taux et plafond est sourcé : CGI 2026, Dahir n° 1-72-184, Loi n° 65-00,
                        Arrêté n° 1314-25, Décret n° 2.25.983. Les références légales sont affichées
                        sur chaque ligne de résultat.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card section-card h-100 p-3">
                <div class="card-body">
                    <div class="text-primary fs-3 mb-2"><i class="bi bi-diagram-3-fill"></i></div>
                    <h5 class="fw-bold">Pédagogie au cœur</h5>
                    <p class="text-muted mb-0">
                        Séquence de calcul complète affichée : SBI, CNSS, AMO, CIMR, frais pro, RNI,
                        IR progressif, charges famille, indemnités exonérées, <strong>coût total employeur</strong>.
                        Comprendre, pas seulement calculer.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card section-card h-100 p-3">
                <div class="card-body">
                    <div class="text-warning fs-3 mb-2"><i class="bi bi-people-fill"></i></div>
                    <h5 class="fw-bold">Open Source &amp; Collaboratif</h5>
                    <p class="text-muted mb-0">
                        Code source ouvert, librement auditable. Aucune inscription, aucune publicité,
                        aucune donnée collectée. Développé en <strong>Laravel + Docker</strong>
                        pour faciliter le déploiement et la contribution.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- NOUVELLES FONCTIONNALITÉS 2026                                   --}}
    {{-- ================================================================ --}}
    <div class="row justify-content-center mt-5">
        <div class="col-lg-11">
            <div class="card section-card border-0" style="background: linear-gradient(135deg, #f0fdf4 0%, #e8f4fd 100%)">
                <div class="card-body px-4 py-4">
                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-stars me-2 text-warning"></i>Fonctionnalités avancées
                    </h5>
                    <div class="row g-3">
                        @foreach ([
                            ['icon' => 'bi-hourglass-split',  'color' => 'text-info',    'titre' => 'Prime d\'ancienneté automatique', 'desc' => 'Saisir les années de service → taux légal calculé automatiquement (5%, 10%, 15%, 20%, 25%)'],
                            ['icon' => 'bi-building-up',      'color' => 'text-danger',  'titre' => 'Coût total employeur', 'desc' => 'CNSS patronal (8,98%), AMO patronale (4,11%), Allocations familiales (6,40%), TFP (1,60%)'],
                            ['icon' => 'bi-bank',             'color' => 'text-primary', 'titre' => 'Retraite complémentaire', 'desc' => 'Contrats bancassurance déductibles à hauteur de 50% du SBI annuel (Art. 28-IV CGI)'],
                            ['icon' => 'bi-heart-pulse-fill', 'color' => 'text-success', 'titre' => 'Mutuelle santé', 'desc' => 'Part salarié (retenue nette) et part employeur (intégré au coût total)'],
                            ['icon' => 'bi-cash-stack',       'color' => 'text-warning', 'titre' => 'Primes spécifiques', 'desc' => 'Prime de bilan (13ème mois), prime de rendement, autres primes — chacune tracée séparément'],
                            ['icon' => 'bi-graph-up-arrow',   'color' => 'text-purple',  'titre' => 'Visualisation enrichie', 'desc' => '7 indicateurs clés, graphique donut, tableau détaillé avec badges légaux sur chaque ligne'],
                        ] as $feat)
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex gap-3">
                                <div class="{{ $feat['color'] }} fs-4 flex-shrink-0"><i class="bi {{ $feat['icon'] }}"></i></div>
                                <div>
                                    <div class="fw-semibold small">{{ $feat['titre'] }}</div>
                                    <div class="text-muted small">{{ $feat['desc'] }}</div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- CE QUE COUVRE LE SIMULATEUR                                      --}}
    {{-- ================================================================ --}}
    <div class="row justify-content-center mt-5">
        <div class="col-lg-11">
            <div class="card section-card">
                <div class="card-header px-4 py-3">
                    <i class="bi bi-list-check me-2 text-success"></i>
                    <span>Couverture réglementaire complète</span>
                </div>
                <div class="card-body px-4 py-3">
                    <div class="row row-cols-1 row-cols-md-2 g-2">
                        @foreach ([
                            ['icon' => 'bi-building',       'color' => 'text-primary', 'text' => 'CNSS salarié 4,48% + employeur 8,98% — plafond 6 000 MAD'],
                            ['icon' => 'bi-heart-pulse',    'color' => 'text-purple',  'text' => 'AMO salarié 2,26% + employeur 4,11% (Loi n° 65-00)'],
                            ['icon' => 'bi-piggy-bank',     'color' => 'text-indigo',  'text' => 'CIMR 3%–10% — 100% déductible IR (Art. 28-III CGI)'],
                            ['icon' => 'bi-percent',        'color' => 'text-danger',  'text' => 'IR progressif Art. 73 CGI — 6 tranches + déduction famille'],
                            ['icon' => 'bi-briefcase',      'color' => 'text-warning', 'text' => 'Frais pro Art. 59 — 35%/25%/45%/40% selon catégorie'],
                            ['icon' => 'bi-hourglass-split','color' => 'text-info',    'text' => 'Ancienneté Art. 350 — 5% à 25% selon années de service'],
                            ['icon' => 'bi-clock-history',  'color' => 'text-info',    'text' => 'Heures sup. Art. 201 — +25% à +100% selon type'],
                            ['icon' => 'bi-gift',           'color' => 'text-success', 'text' => 'Indemnités exonérées Arrêté n° 1314-25 (8 types)'],
                            ['icon' => 'bi-bank',           'color' => 'text-primary', 'text' => 'Retraite complémentaire — déductible IR Art. 28-IV CGI'],
                            ['icon' => 'bi-building-up',    'color' => 'text-danger',  'text' => 'Allocations familiales 6,40% + TFP 1,60% (patronales)'],
                        ] as $item)
                        <div class="col d-flex align-items-center gap-2">
                            <i class="bi {{ $item['icon'] }} {{ $item['color'] }} fs-5 flex-shrink-0"></i>
                            <span class="text-muted small">{{ $item['text'] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- STACK TECHNIQUE                                                  --}}
    {{-- ================================================================ --}}
    <div class="row justify-content-center mt-5">
        <div class="col-lg-11">
            <div class="card section-card">
                <div class="card-header px-4 py-3">
                    <i class="bi bi-code-slash me-2 text-primary"></i>
                    <span>Architecture technique — Laravel + Docker</span>
                </div>
                <div class="card-body px-4 py-3">
                    <div class="row g-4 align-items-center">
                        <div class="col-md-8">
                            <p class="text-muted mb-3">
                                Application <strong>100% stateless</strong> : aucune base de données, aucun cookie de traçage.
                                Chaque simulation est calculée côté serveur à la demande et n'est jamais persistée.
                            </p>
                            <div class="row g-2">
                                @foreach ([
                                    ['tech' => 'Laravel 11',    'role' => 'Framework MVC — routing, validation, services',      'color' => 'text-danger'],
                                    ['tech' => 'PHP 8.3',       'role' => 'Moteur de calcul — types stricts, performance',      'color' => 'text-primary'],
                                    ['tech' => 'Blade / Bootstrap 5', 'role' => 'Templates côté serveur — rendu SSR',           'color' => 'text-success'],
                                    ['tech' => 'Chart.js 4',    'role' => 'Graphiques interactifs — donut de répartition',      'color' => 'text-warning'],
                                    ['tech' => 'Docker Compose','role' => 'Nginx + PHP-FPM — déploiement reproductible',        'color' => 'text-info'],
                                    ['tech' => 'config/payroll.php', 'role' => 'Source unique de vérité — taux et barèmes 2026','color' => 'text-secondary'],
                                ] as $t)
                                <div class="col-sm-6">
                                    <div class="d-flex align-items-start gap-2 mb-1">
                                        <i class="bi bi-check-circle-fill {{ $t['color'] }} flex-shrink-0 mt-1"></i>
                                        <div>
                                            <strong class="small">{{ $t['tech'] }}</strong>
                                            <div class="text-muted small">{{ $t['role'] }}</div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="p-3 rounded-3 bg-dark text-start" style="font-family:monospace; font-size:.8rem;">
                                <div class="text-success">$ docker compose up -d</div>
                                <div class="text-muted mt-1"># Nginx → port 80</div>
                                <div class="text-muted"># PHP-FPM → port 9000</div>
                                <div class="text-warning mt-2">→ localhost/calculateur</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- CTA FINAL                                                        --}}
    {{-- ================================================================ --}}
    <div class="text-center mt-5 mb-4 py-4">
        <h4 class="fw-bold mb-2">Prêt à simuler votre bulletin ?</h4>
        <p class="text-muted mb-4">Gratuit · Sans inscription · Sans publicité · Résultat instantané</p>
        <a href="{{ route('calculator.index') }}" class="btn btn-lg px-5 text-white fw-semibold"
           style="background:var(--brand-green)">
            <i class="bi bi-play-circle me-2"></i>Commencer le calcul
        </a>
        <a href="{{ route('documentation') }}" class="btn btn-lg btn-outline-secondary ms-3 px-4">
            <i class="bi bi-journal-text me-2"></i>Voir la documentation
        </a>
    </div>

</div>
@endsection
