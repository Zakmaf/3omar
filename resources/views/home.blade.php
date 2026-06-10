@extends('layouts.app')

@section('title', '3omar — Comprends ton bulletin de paie marocain')

@section('content')
<div class="container">

    {{-- ================================================================ --}}
    {{-- HERO                                                              --}}
    {{-- ================================================================ --}}
    <div class="row justify-content-center my-4 my-lg-5">
        <div class="col-lg-9 text-center">
            <div class="mb-3 d-flex justify-content-center gap-2 flex-wrap">
                <span class="badge rounded-pill text-bg-success px-3 py-2 fs-6">
                    <i class="bi bi-calendar-check me-1"></i>CGI 2026
                </span>
                <span class="badge rounded-pill text-bg-primary px-3 py-2 fs-6">
                    <i class="bi bi-unlock me-1"></i>Gratuit et open source
                </span>
                <span class="badge rounded-pill text-bg-secondary px-3 py-2 fs-6">
                    <i class="bi bi-shield-check me-1"></i>Aucune donnee collectee
                </span>
            </div>
            <h1 class="display-4 fw-bold mb-3">
                Ton <span style="color:var(--brand-green)">3omar</span>, ligne par ligne.
            </h1>
            <p class="lead text-muted mb-2" style="max-width:680px; margin:0 auto">
                Au Maroc, des millions de salaries recoivent chaque mois une fiche de paie
                sans vraiment comprendre d'ou vient le montant en bas de page.
            </p>
            <p class="lead text-muted mb-4" style="max-width:680px; margin:0 auto">
                <strong>3omar</strong> est ne de cette frustration : un simulateur 100 % gratuit, open source,
                qui detaille chaque prelevement — CNSS, AMO, IR, CIMR — avec la reference legale exacte.
                Pour que chaque dirham soit explique.
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="{{ route('calculator.index') }}" class="btn btn-lg px-5 text-white fw-semibold"
                   style="background:var(--brand-green)">
                    <i class="bi bi-calculator me-2"></i>Simuler mon bulletin
                </a>
                <a href="{{ route('documentation') }}" class="btn btn-lg btn-outline-secondary px-4">
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
            <div class="card section-card border-0" style="background:linear-gradient(135deg, #fff8f0 0%, #fef3f2 100%)">
                <div class="card-body px-4 py-4">
                    <div class="row align-items-center">
                        <div class="col-md-1 text-center mb-3 mb-md-0">
                            <span class="fs-1">&#129300;</span>
                        </div>
                        <div class="col-md-11">
                            <h5 class="fw-bold mb-2">Pourquoi c'est si opaque ?</h5>
                            <p class="text-muted mb-0">
                                Entre la CNSS plafonnee, l'AMO sans plafond, l'IR progressif a 6 tranches,
                                les frais professionnels, les charges de famille, les indemnites exonerees
                                et les cotisations patronales invisibles — <strong>personne ne devrait avoir besoin
                                d'un expert-comptable pour lire sa propre fiche de paie</strong>.
                                3omar decompose tout, etape par etape, avec les textes de loi en face.
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
            ['val' => '10',  'unit' => 'etapes',    'label' => 'de calcul detaillees',           'icon' => 'bi-list-check',       'color' => 'text-success'],
            ['val' => '6',   'unit' => 'tranches',   'label' => 'IR progressif — Art. 73 CGI',   'icon' => 'bi-percent',          'color' => 'text-danger'],
            ['val' => '8',   'unit' => 'types',      'label' => "d'indemnites exonerees",        'icon' => 'bi-gift',             'color' => 'text-primary'],
            ['val' => '0',   'unit' => 'donnee',     'label' => 'personnelle stockee',            'icon' => 'bi-shield-lock-fill', 'color' => 'text-warning'],
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
    {{-- POUR QUI ?                                                        --}}
    {{-- ================================================================ --}}
    <div class="row g-4 mt-4">
        <div class="col-md-4">
            <div class="card section-card h-100 p-3">
                <div class="card-body">
                    <div class="text-success fs-3 mb-2"><i class="bi bi-person-badge"></i></div>
                    <h5 class="fw-bold">Pour les salaries</h5>
                    <p class="text-muted mb-0">
                        Tu recois ta fiche de paie mais tu ne comprends pas la difference
                        entre ton brut et ton net ? 3omar te montre exactement ou passe
                        chaque dirham : cotisations, impots, indemnites.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card section-card h-100 p-3">
                <div class="card-body">
                    <div class="text-primary fs-3 mb-2"><i class="bi bi-people"></i></div>
                    <h5 class="fw-bold">Pour les DRH et managers</h5>
                    <p class="text-muted mb-0">
                        Besoin de simuler rapidement le cout d'un recrutement ou d'une
                        augmentation ? 3omar calcule le net salarie <strong>et</strong> le cout
                        total employeur (CNSS, AMO, AF, TFP) en un clic.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card section-card h-100 p-3">
                <div class="card-body">
                    <div class="text-warning fs-3 mb-2"><i class="bi bi-mortarboard"></i></div>
                    <h5 class="fw-bold">Pour les etudiants et formateurs</h5>
                    <p class="text-muted mb-0">
                        Cours de droit social, de comptabilite ou de RH ? 3omar est un
                        support pedagogique vivant : chaque resultat cite l'article
                        de loi exact (CGI, Code du Travail, Dahir CNSS…).
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
            <div class="card section-card">
                <div class="card-header px-4 py-3">
                    <i class="bi bi-list-check me-2 text-success"></i>
                    <span>Ce que couvre 3omar</span>
                </div>
                <div class="card-body px-4 py-3">
                    <div class="row row-cols-1 row-cols-md-2 g-2">
                        @foreach ([
                            ['icon' => 'bi-building',       'color' => 'text-primary', 'text' => 'CNSS salarie 4,48 % + employeur 8,98 % — plafond 6 000 MAD'],
                            ['icon' => 'bi-heart-pulse',    'color' => 'text-purple',  'text' => 'AMO salarie 2,26 % + employeur 4,11 % — sans plafond'],
                            ['icon' => 'bi-piggy-bank',     'color' => 'text-indigo',  'text' => 'CIMR 3 %–10 % — 100 % deductible IR'],
                            ['icon' => 'bi-percent',        'color' => 'text-danger',  'text' => 'IR progressif 6 tranches + deduction charges de famille'],
                            ['icon' => 'bi-briefcase',      'color' => 'text-warning', 'text' => 'Frais professionnels — 35 %/25 %/45 %/40 % selon categorie'],
                            ['icon' => 'bi-hourglass-split','color' => 'text-info',    'text' => 'Prime d\'anciennete — 5 % a 25 % selon annees de service'],
                            ['icon' => 'bi-clock-history',  'color' => 'text-info',    'text' => 'Heures supplementaires — +25 % a +100 % selon type'],
                            ['icon' => 'bi-gift',           'color' => 'text-success', 'text' => 'Indemnites exonerees (transport, panier, logement…)'],
                            ['icon' => 'bi-bank',           'color' => 'text-primary', 'text' => 'Retraite complementaire — deductible IR a 50 % du SBI'],
                            ['icon' => 'bi-building-up',    'color' => 'text-danger',  'text' => 'Cout total employeur : toutes les charges patronales'],
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
    {{-- OPEN SOURCE                                                       --}}
    {{-- ================================================================ --}}
    <div class="row justify-content-center mt-5">
        <div class="col-lg-10">
            <div class="card section-card border-0" style="background: linear-gradient(135deg, #f0fdf4 0%, #e8f4fd 100%)">
                <div class="card-body px-4 py-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="fw-bold mb-2">
                                <i class="bi bi-github me-2"></i>Open source, pour de vrai
                            </h5>
                            <p class="text-muted mb-2">
                                Le code de 3omar est public sur GitHub. Pas de compte a creer, pas de version
                                « premium », pas de publicite. Si tu trouves une erreur dans un taux ou un
                                calcul, tu peux ouvrir une issue ou proposer un correctif directement.
                            </p>
                            <p class="text-muted mb-3">
                                L'objectif : construire <strong>ensemble</strong> l'outil de reference pour
                                comprendre la paie au Maroc. Chaque contribution est la bienvenue.
                            </p>
                            <a href="https://github.com/Zakmaf/3omar" target="_blank" rel="noopener"
                               class="btn btn-dark btn-sm px-3">
                                <i class="bi bi-github me-1"></i>Voir le code source
                            </a>
                            <a href="https://github.com/Zakmaf/3omar/issues" target="_blank" rel="noopener"
                               class="btn btn-outline-dark btn-sm px-3 ms-2">
                                <i class="bi bi-bug me-1"></i>Signaler une erreur
                            </a>
                        </div>
                        <div class="col-md-4 text-center mt-3 mt-md-0">
                            <div class="p-3 rounded-3 bg-white shadow-sm">
                                <div class="fw-bold text-success mb-1" style="font-size:2rem">100 %</div>
                                <div class="text-muted small">Gratuit, sans inscription,<br>sans publicite, sans cookie</div>
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
        <h4 class="fw-bold mb-2">Pret a comprendre ton bulletin ?</h4>
        <p class="text-muted mb-4">Gratuit · Sans inscription · Resultat instantane</p>
        <a href="{{ route('calculator.index') }}" class="btn btn-lg px-5 text-white fw-semibold"
           style="background:var(--brand-green)">
            <i class="bi bi-play-circle me-2"></i>Simuler maintenant
        </a>
        <a href="{{ route('documentation') }}" class="btn btn-lg btn-outline-secondary ms-3 px-4">
            <i class="bi bi-journal-text me-2"></i>Documentation legale
        </a>
    </div>

</div>
@endsection
