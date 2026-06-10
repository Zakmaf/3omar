@extends('layouts.app')

@section('title', '3omar — Comprends ton bulletin de paie marocain')

@section('content')
<div class="container">

    {{-- ================================================================ --}}
    {{-- HERO avec zellige subtil                                          --}}
    {{-- ================================================================ --}}
    <div class="row justify-content-center my-4 my-lg-5 position-relative" style="overflow:hidden">
        {{-- Zellige background --}}
        <div style="position:absolute;inset:0;background-image:var(--zellige-bg);background-size:280px 280px;opacity:0.04;pointer-events:none;z-index:0"></div>

        <div class="col-lg-9 text-center position-relative" style="z-index:1">
            <div class="mb-3 d-flex justify-content-center gap-2 flex-wrap">
                <span class="badge rounded-pill px-3 py-2 fs-6" style="background:var(--g-50);color:var(--g-700);border:1px solid var(--g-200)">
                    <i class="bi bi-calendar-check me-1"></i>CGI 2026
                </span>
                <span class="badge rounded-pill px-3 py-2 fs-6" style="background:var(--s-info-bg);color:var(--s-info)">
                    <i class="bi bi-unlock me-1"></i>Gratuit et open source
                </span>
                <span class="badge rounded-pill px-3 py-2 fs-6" style="background:var(--g-50);color:var(--ink-2)">
                    <i class="bi bi-shield-check me-1"></i>Aucune donnée stockée
                </span>
            </div>
            <h1 class="display-4 fw-bold mb-3" style="font-family:var(--f-display);letter-spacing:-0.035em">
                Ton <span style="color:var(--g-500);font-family:var(--f-accent)">3omar</span>, ligne par ligne.
            </h1>
            <p class="lead mb-2" style="max-width:680px;margin:0 auto;color:var(--ink-2)">
                Beaucoup de salariés reçoivent chaque mois une fiche de paie
                sans vraiment comprendre d'où vient le montant en bas de page.
            </p>
            <p class="lead mb-4" style="max-width:680px;margin:0 auto;color:var(--ink-2)">
                <strong style="color:var(--ink)">3omar</strong> est né de cette frustration : un simulateur 100 % gratuit, open source,
                qui détaille les principaux prélèvements — CNSS, AMO, IR, CIMR — et affiche les références utilisées par le simulateur.
                Pour que chaque <span style="font-family:var(--f-accent);color:var(--r-500);font-weight:600">dirham</span> soit expliqué.
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="{{ route('calculator.index') }}" class="btn btn-lg px-5 text-white fw-semibold"
                   style="background:var(--g-500);font-family:var(--f-body)">
                    <i class="bi bi-calculator me-2"></i>Simuler mon bulletin
                </a>
                <a href="{{ route('documentation') }}" class="btn btn-lg px-4 fw-semibold"
                   style="background:transparent;color:var(--g-500);border:1px solid var(--g-500);font-family:var(--f-body)">
                    <i class="bi bi-journal-text me-2"></i>Voir les taux 2026
                </a>
            </div>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- LE PROBLEME                                                       --}}
    {{-- ================================================================ --}}
    <div class="row justify-content-center mt-5">
        <div class="col-lg-10">
            <div class="section-card border-0" style="background:var(--s-warn-bg)">
                <div class="card-body px-4 py-4">
                    <div class="row align-items-center">
                        <div class="col-md-1 text-center mb-3 mb-md-0">
                            <span class="fs-1">&#129300;</span>
                        </div>
                        <div class="col-md-11">
                            <h5 class="fw-bold mb-2" style="font-family:var(--f-display)">Pourquoi c'est si opaque ?</h5>
                            <p class="mb-0" style="color:var(--ink-2)">
                                Entre la CNSS plafonnée, l'AMO sans plafond, l'IR progressif à 6 tranches,
                                les frais professionnels, les charges de famille, les indemnités exonérées
                                et les cotisations patronales invisibles — <strong style="color:var(--ink)">personne ne devrait avoir besoin
                                d'un expert-comptable pour lire sa propre fiche de paie</strong>.
                                3omar décompose le calcul, étape par étape, avec les textes de loi en face.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- CHIFFRES CLES                                                     --}}
    {{-- ================================================================ --}}
    <div class="row g-3 mt-4 text-center">
        @foreach ([
            ['val' => '10',  'unit' => 'étapes',    'label' => 'de calcul détaillées',          'icon' => 'bi-list-check',       'color' => 'var(--s-succ)'],
            ['val' => '6',   'unit' => 'tranches',   'label' => 'IR progressif — Art. 73 CGI',   'icon' => 'bi-percent',          'color' => 'var(--s-tax)'],
            ['val' => '8',   'unit' => 'types',      'label' => "d'indemnités exonérées",        'icon' => 'bi-gift',             'color' => 'var(--s-info)'],
            ['val' => '0',   'unit' => 'donnée',     'label' => 'personnelle stockée',           'icon' => 'bi-shield-lock-fill', 'color' => 'var(--s-warn)'],
        ] as $stat)
        <div class="col-6 col-md-3">
            <div class="section-card h-100 py-3 px-2">
                <div class="fs-2 mb-1" style="color:{{ $stat['color'] }}"><i class="bi {{ $stat['icon'] }}"></i></div>
                <div class="fs-4" style="font-family:var(--f-display);font-weight:700;color:var(--ink)">{{ $stat['val'] }} <small class="fs-6 fw-normal" style="color:var(--ink-3)">{{ $stat['unit'] }}</small></div>
                <div class="small" style="color:var(--ink-3)">{{ $stat['label'] }}</div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ================================================================ --}}
    {{-- POUR QUI ?                                                        --}}
    {{-- ================================================================ --}}
    <div class="row g-4 mt-4">
        <div class="col-md-4">
            <div class="section-card h-100 p-3">
                <div class="card-body">
                    <div class="fs-3 mb-2" style="color:var(--s-succ)"><i class="bi bi-person-badge"></i></div>
                    <h5 class="fw-bold" style="font-family:var(--f-display)">Pour les salariés</h5>
                    <p class="mb-0" style="color:var(--ink-2)">
                        Tu reçois ta fiche de paie mais tu ne comprends pas la différence
                        entre ton brut et ton net ? 3omar te montre où passe
                        chaque dirham : cotisations, impôts, indemnités.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="section-card h-100 p-3">
                <div class="card-body">
                    <div class="fs-3 mb-2" style="color:var(--s-info)"><i class="bi bi-people"></i></div>
                    <h5 class="fw-bold" style="font-family:var(--f-display)">Pour les DRH et managers</h5>
                    <p class="mb-0" style="color:var(--ink-2)">
                        Besoin de simuler rapidement le coût d'un recrutement ou d'une
                        augmentation ? 3omar calcule le net salarié <strong>et</strong> le coût
                        total employeur (CNSS, AMO, AF, TFP) en un clic.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="section-card h-100 p-3">
                <div class="card-body">
                    <div class="fs-3 mb-2" style="color:var(--s-warn)"><i class="bi bi-mortarboard"></i></div>
                    <h5 class="fw-bold" style="font-family:var(--f-display)">Pour les étudiants et formateurs</h5>
                    <p class="mb-0" style="color:var(--ink-2)">
                        Cours de droit social, de comptabilité ou de RH ? 3omar est un
                        support pédagogique vivant : chaque résultat cite l'article
                        de loi correspondant (CGI, Code du Travail, Dahir CNSS...).
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- CE QUE COUVRE 3OMAR                                               --}}
    {{-- ================================================================ --}}
    <div class="row justify-content-center mt-5">
        <div class="col-lg-11">
            <div class="section-card">
                <div class="card-header px-4 py-3" style="font-family:var(--f-display)">
                    <i class="bi bi-list-check me-2" style="color:var(--s-succ)"></i>
                    <span>Ce que couvre 3omar</span>
                </div>
                <div class="card-body px-4 py-3">
                    <div class="row row-cols-1 row-cols-md-2 g-2">
                        @foreach ([
                            ['icon' => 'bi-building',       'color' => 'var(--s-info)', 'text' => 'CNSS salarié 4,48 % + employeur 8,98 % — plafond 6 000 MAD'],
                            ['icon' => 'bi-heart-pulse',    'color' => 'var(--s-cot)',  'text' => 'AMO salarié 2,26 % + employeur 4,11 % — sans plafond'],
                            ['icon' => 'bi-piggy-bank',     'color' => 'var(--s-cot)',  'text' => 'CIMR 3 %–10 % — 100 % déductible IR'],
                            ['icon' => 'bi-percent',        'color' => 'var(--s-tax)',  'text' => 'IR progressif 6 tranches + déduction charges de famille'],
                            ['icon' => 'bi-briefcase',      'color' => 'var(--s-warn)', 'text' => 'Frais professionnels — 35 %/25 %/45 %/40 % selon catégorie'],
                            ['icon' => 'bi-hourglass-split','color' => 'var(--s-info)', 'text' => 'Prime d\'ancienneté — 5 % à 25 % selon années de service'],
                            ['icon' => 'bi-clock-history',  'color' => 'var(--s-info)', 'text' => 'Heures supplémentaires — +25 % à +100 % selon type'],
                            ['icon' => 'bi-gift',           'color' => 'var(--s-succ)', 'text' => 'Indemnités exonérées (transport, panier, logement...)'],
                            ['icon' => 'bi-bank',           'color' => 'var(--s-info)', 'text' => 'Retraite complémentaire — déductible IR à 50 % du SBI'],
                            ['icon' => 'bi-building-up',    'color' => 'var(--s-warn)', 'text' => 'Coût total employeur : toutes les charges patronales'],
                        ] as $item)
                        <div class="col d-flex align-items-center gap-2">
                            <i class="bi {{ $item['icon'] }} fs-5 flex-shrink-0" style="color:{{ $item['color'] }}"></i>
                            <span class="small" style="color:var(--ink-2)">{{ $item['text'] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- OPEN SOURCE                                                       --}}
    {{-- ================================================================ --}}
    <div class="row justify-content-center mt-5">
        <div class="col-lg-10">
            <div class="section-card border-0 position-relative" style="background:var(--s-succ-bg);overflow:hidden">
                {{-- Zellige subtle --}}
                <div style="position:absolute;inset:0;background-image:var(--zellige-bg);background-size:200px 200px;opacity:0.04;pointer-events:none"></div>
                <div class="card-body px-4 py-4 position-relative" style="z-index:1">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="fw-bold mb-2" style="font-family:var(--f-display)">
                                <i class="bi bi-github me-2"></i>Open source, pour de vrai
                            </h5>
                            <p class="mb-2" style="color:var(--ink-2)">
                                Le code de 3omar est public sur GitHub. Pas de compte à créer, pas de version
                                &laquo; premium &raquo;, pas de publicité. Si tu trouves une erreur dans un taux ou un
                                calcul, tu peux ouvrir une issue ou proposer un correctif directement.
                            </p>
                            <p class="mb-3" style="color:var(--ink-2)">
                                L'objectif : construire <strong style="color:var(--ink)">ensemble</strong> un outil clair pour
                                comprendre la paie au Maroc. Chaque contribution est la bienvenue.
                            </p>
                            <a href="https://github.com/Zakmaf/3omar" target="_blank" rel="noopener"
                               class="btn btn-sm px-3" style="background:var(--ink);color:#fff;font-family:var(--f-body)">
                                <i class="bi bi-github me-1"></i>Voir le code source
                            </a>
                            <a href="https://github.com/Zakmaf/3omar/issues" target="_blank" rel="noopener"
                               class="btn btn-sm px-3 ms-2" style="border:1px solid var(--ink);color:var(--ink);font-family:var(--f-body)">
                                <i class="bi bi-bug me-1"></i>Signaler une erreur
                            </a>
                        </div>
                        <div class="col-md-4 text-center mt-3 mt-md-0">
                            <div class="p-3 rounded-3 shadow-sm" style="background:var(--paper)">
                                <div class="mb-1" style="font-family:var(--f-display);font-weight:800;font-size:2rem;color:var(--s-succ)">100 %</div>
                                <div class="small" style="color:var(--ink-3)">Gratuit, sans inscription,<br>sans publicité, sans cookie</div>
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
        <h4 class="fw-bold mb-2" style="font-family:var(--f-display)">Prêt à comprendre ton bulletin ?</h4>
        <p class="mb-4" style="color:var(--ink-3)">Gratuit &middot; Sans inscription &middot; Résultat instantané</p>
        <a href="{{ route('calculator.index') }}" class="btn btn-lg px-5 text-white fw-semibold"
           style="background:var(--g-500);font-family:var(--f-body)">
            <i class="bi bi-calculator me-2"></i>Simuler mon bulletin
        </a>
        <a href="{{ route('documentation') }}" class="btn btn-lg ms-3 px-4 fw-semibold"
           style="border:1px solid var(--g-500);color:var(--g-500);font-family:var(--f-body)">
            <i class="bi bi-journal-text me-2"></i>Documentation légale
        </a>
    </div>

</div>
@endsection
