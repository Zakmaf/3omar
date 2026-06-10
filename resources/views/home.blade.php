@extends('layouts.app')

@section('title', '3omar — Ton bulletin, ligne par ligne')

@section('content')
<div class="container">
    <section class="row align-items-center g-4 my-4 my-lg-5" aria-labelledby="hero-title">
        <div class="col-lg-6">
            <div class="d-flex gap-2 flex-wrap mb-3">
                <span class="badge rounded-pill px-3 py-2" style="background:var(--g-50);color:var(--g-700);border:1px solid var(--g-200)">
                    <i class="bi bi-calendar-check me-1"></i>Exercice 2026
                </span>
                <span class="badge rounded-pill px-3 py-2" style="background:var(--s-info-bg);color:var(--s-info)">
                    <i class="bi bi-unlock me-1"></i>Gratuit et open source
                </span>
            </div>
            <h1 id="hero-title" class="display-4 fw-bold mb-3" style="letter-spacing:-0.035em">
                Ton <span style="color:var(--g-500);font-family:var(--f-accent)">3omar</span>, ligne par ligne.
            </h1>
            <p class="lead mb-4" style="color:var(--ink-2)">
                Simule ton bulletin de paie marocain et comprends les principaux calculs :
                salaire brut, cotisations, impôt, indemnités et coût employeur.
                Le résultat distingue les montants clés, le détail des calculs et les hypothèses utilisées.
            </p>
            <div class="d-flex flex-wrap gap-3">
                <a href="{{ route('calculator.index') }}" class="btn btn-lg px-5 text-white fw-semibold" style="background:var(--g-500)">
                    <i class="bi bi-calculator me-2"></i>Simuler mon bulletin
                </a>
                <a href="{{ route('documentation') }}" class="btn btn-lg px-4 fw-semibold" style="color:var(--g-500);border:1px solid var(--g-500)">
                    <i class="bi bi-journal-text me-2"></i>Consulter les règles 2026
                </a>
            </div>
            <p class="small mt-3 mb-0" style="color:var(--ink-3)">
                <i class="bi bi-shield-check me-1"></i>Aucune donnée personnelle n'est stockée. Chaque simulation est calculée à la demande.
            </p>
        </div>
        <div class="col-lg-6">
            <img src="{{ asset('img/3omar-social-preview.png') }}"
                 class="img-fluid rounded-4 shadow-sm"
                 alt="Illustration expliquant le passage du salaire brut au salaire net">
        </div>
    </section>

    <section class="row g-4 mt-4" aria-labelledby="benefits-title">
        <div class="col-12">
            <h2 id="benefits-title" class="h3 fw-bold">Comprendre, pas seulement calculer</h2>
        </div>
        @foreach ([
            ['icon' => 'bi-list-check', 'color' => 'var(--s-info)', 'title' => 'Calculs expliqués', 'text' => 'Chaque étape présente son assiette, son taux et son montant.'],
            ['icon' => 'bi-journal-text', 'color' => 'var(--s-tax)', 'title' => 'Hypothèses explicites', 'text' => 'Les paramètres et références déclarées restent visibles et vérifiables.'],
            ['icon' => 'bi-github', 'color' => 'var(--s-succ)', 'title' => 'Code vérifiable', 'text' => 'Le moteur de calcul et ses paramètres sont publics et ouverts aux contributions.'],
        ] as $item)
        <div class="col-md-4">
            <article class="section-card h-100 p-4">
                <i class="bi {{ $item['icon'] }} fs-2" style="color:{{ $item['color'] }}"></i>
                <h3 class="h5 fw-bold mt-3">{{ $item['title'] }}</h3>
                <p class="mb-0" style="color:var(--ink-2)">{{ $item['text'] }}</p>
            </article>
        </div>
        @endforeach
    </section>

    <section class="section-card p-4 p-lg-5 mt-5 position-relative overflow-hidden" aria-labelledby="coverage-title">
        <div style="position:absolute;inset:0;background-image:var(--zellige-bg);background-size:220px;opacity:.04;pointer-events:none"></div>
        <div class="position-relative">
            <h2 id="coverage-title" class="h3 fw-bold">Ce que couvre 3omar</h2>
            <div class="row row-cols-1 row-cols-md-2 g-3 mt-2">
                @foreach ([
                    'CNSS et AMO, parts salarié et employeur',
                    'IR progressif et charges de famille',
                    'CIMR et retraite complémentaire',
                    'Frais professionnels et indemnités traitées comme exonérées',
                    "Prime d'ancienneté et heures supplémentaires",
                    'Salaire net et coût total employeur',
                ] as $item)
                <div class="col d-flex gap-2">
                    <i class="bi bi-check-circle-fill flex-shrink-0" style="color:var(--s-succ)"></i>
                    <span>{{ $item }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="text-center my-5 py-4" aria-labelledby="final-cta-title">
        <h2 id="final-cta-title" class="h3 fw-bold">Prêt à comprendre ton bulletin ?</h2>
        <p style="color:var(--ink-3)">Simulation pédagogique, gratuite et sans inscription.</p>
        <a href="{{ route('calculator.index') }}" class="btn btn-lg px-5 text-white fw-semibold" style="background:var(--g-500)">
            <i class="bi bi-calculator me-2"></i>Simuler mon bulletin
        </a>
    </section>
</div>
@endsection
