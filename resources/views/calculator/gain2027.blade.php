@extends('layouts.app')

{{-- Aucun montant dans le titre du document (historique, onglets, outils tiers). --}}
@section('title', '3omar · '.__('ui.gain2027.title'))

@push('head')
{{-- L'URL porte la saisie (payload non chiffré) : un lien publié ne doit être ni indexé ni suivi. --}}
<meta name="robots" content="noindex, nofollow">
<style>
    .gain-num {
        direction: ltr;
        unicode-bidi: isolate;
        white-space: nowrap;
        font-family: var(--f-mono);
        font-variant-numeric: tabular-nums;
    }

    .gain-headline-card {
        border-inline-start: 4px solid var(--s-succ);
    }

    .gain-headline-amount {
        font-size: clamp(1.75rem, 6vw, 2.75rem);
        font-weight: 700;
        color: var(--ink);
        line-height: 1.15;
    }

    .gain-badge {
        border: 1px solid var(--hairline-strong);
        color: var(--ink);
        background: transparent;
        font-weight: 600;
        white-space: normal;
        text-align: start;
    }

    .gain-notice {
        border: 1px solid var(--hairline);
        border-radius: var(--radius-sm);
        padding: .75rem 1rem;
        background: var(--paper);
        color: var(--ink-2);
        overflow-wrap: anywhere;
    }

    .gain-notice-warn {
        border-inline-start: 4px solid var(--s-warn);
    }

    .gain-scroll {
        overflow-x: auto;
    }

    .gain-table th,
    .gain-table td {
        vertical-align: middle;
    }

    @media print {
        .gain-scroll {
            overflow: visible;
        }
    }
</style>
@endpush

@section('content')
@php
    $mad = fn ($montant) => number_format((float) $montant, 2, ',', ' ');
    // Valeur machine des montants (data-montant) : celle du moteur, au centime.
    $machine = fn ($montant) => number_format((float) $montant, 2, '.', '');
    $reference = $gain['reference'];
    $projete = $gain['projete'];
    $resolution = $gain['resolution'];
    $shareUrl = route('calculator.gain2027', ['s' => $share_payload]);
    $avertissements = array_values(array_unique([...$reference['avertissements'], ...$projete['avertissements']]));
    // Lignes du tableau 2026 / 2027 : clé du marqueur data-gain2027 et champ du moteur.
    $lignes = [
        ['label' => 'row_gross', 'cle' => 'brut', 'champ' => 'salaire_brut_total'],
        ['label' => 'row_pro_fees', 'cle' => 'fp', 'champ' => 'frais_pro'],
        ['label' => 'row_ir', 'cle' => 'ir', 'champ' => 'ir_net'],
        ['label' => 'row_net', 'cle' => 'net', 'champ' => 'salaire_net'],
    ];
@endphp
<div class="container" data-gain2027-page data-source-exercice="{{ $gain['source']['exercice'] }}" data-mode="{{ $gain['mode'] }}">

    <header class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4">
        <div>
            <div class="eyebrow mb-1">{{ __('ui.gain2027.eyebrow') }}</div>
            <h1 class="h2 fw-bold mb-1">{{ __('ui.gain2027.title') }}</h1>
            <p class="mb-0" style="color:var(--ink-2)">{{ __('ui.gain2027.intro') }}</p>
        </div>
        <div class="d-flex flex-column flex-sm-row gap-2 no-print flex-shrink-0">
            <a href="{{ route('calculator.index', ['s' => $share_payload]) }}" class="btn text-white fw-semibold" style="background:var(--g-500)">
                <i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('ui.result.action_simulate_again') }}
            </a>
            <button type="button" onclick="window.print()" class="btn fw-semibold" style="border:1px solid var(--ink-2);color:var(--ink-2)">
                <i class="bi bi-printer me-1" aria-hidden="true"></i>{{ __('ui.result.print') }}
            </button>
        </div>
    </header>

    {{-- 1. Gain mensuel, en premier, puis pourcentage et projection annuelle indicative --}}
    <section class="section-card gain-headline-card p-3 p-md-4 mb-4" aria-labelledby="gain2027-headline-title">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <h2 id="gain2027-headline-title" class="h6 fw-bold mb-0">{{ __('ui.gain2027.headline_label') }}</h2>
            {{-- #283 n'opère aucune bascule légale : le profil 2027 reste un projet.
                 Le passage à « loi applicable » relève de #280 (preuve au Bulletin
                 officiel et validation réglementaire), pas de cette page. --}}
            <span class="badge rounded-pill gain-badge">{{ __('ui.gain2027.badge_projet') }}</span>
        </div>

        <p class="gain-headline-amount mb-1">
            <span class="gain-num" data-gain2027="gain-mensuel" data-montant="{{ $machine($gain['gain']['net_mensuel']) }}">{{ $mad($gain['gain']['net_mensuel']) }}</span>
            <span class="fs-6 fw-semibold" style="color:var(--ink-2)">{{ __('ui.result.unit_mad_month_label') }}</span>
        </p>

        @if ($gain['gain']['net_pct'] !== null)
        <p class="mb-3" style="color:var(--ink-2)">
            <span class="gain-num fw-semibold" style="color:var(--ink)" data-gain2027="gain-pct" data-montant="{{ $machine($gain['gain']['net_pct']) }}">{{ $mad($gain['gain']['net_pct']) }}</span><span class="gain-num"> %</span>
            {{ __('ui.gain2027.pct_label') }}
        </p>
        @endif

        @if ($gain['gain']['aucun_gain_ir'])
        <p class="gain-notice mb-3" role="note">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>{{ __('ui.gain2027.no_ir_gain') }}
        </p>
        @endif

        <div class="mb-0">
            <p class="fw-semibold mb-1">{{ __('ui.gain2027.annual_label') }}</p>
            <p class="mb-1">
                <span class="gain-num fw-bold fs-5" data-gain2027="gain-annuel" data-montant="{{ $machine($gain['gain']['net_annuel_indicatif']) }}">{{ $mad($gain['gain']['net_annuel_indicatif']) }}</span>
                <span style="color:var(--ink-2)">{{ __('ui.gain2027.unit_mad_year') }}</span>
                <span class="small" style="color:var(--ink-2)">({{ __('ui.gain2027.annual_formula', ['mois' => $gain['gain']['nb_mois']]) }})</span>
            </p>
            <p class="small mb-0" style="color:var(--ink-2)">{{ __('ui.gain2027.annual_indicative') }}</p>
        </div>
    </section>

    {{-- 2. Cadre de lecture : statut non acquis, brut constant, source de la saisie --}}
    <div class="d-flex flex-column gap-2 mb-4">
        <p class="gain-notice gain-notice-warn mb-0">
            <i class="bi bi-exclamation-triangle-fill me-1" style="color:var(--s-warn)" aria-hidden="true"></i>{{ __('ui.gain2027.prospective_notice') }}
        </p>
        <p class="gain-notice mb-0">{{ __('ui.gain2027.same_input_notice') }}</p>
        @if ($gain['source']['exercice'] === \App\Services\GainFiscal2027::EXERCICE_PROJETE)
        <p class="gain-notice mb-0">{{ __('ui.gain2027.source_2027_notice') }}</p>
        @endif
        @if ($resolution !== null)
        <div class="gain-notice mb-0">
            <p class="mb-2">{{ __('ui.gain2027.net_mode_notice') }}</p>
            <dl class="d-flex flex-wrap gap-3 mb-0">
                <div>
                    <dt class="small fw-semibold">{{ __('ui.gain2027.net_mode_target') }}</dt>
                    <dd class="mb-0"><span class="gain-num">{{ $mad($resolution['net_cible']) }}</span> {{ __('ui.result.unit_mad_month_label') }}</dd>
                </div>
                <div>
                    <dt class="small fw-semibold">{{ __('ui.gain2027.net_mode_base') }}</dt>
                    <dd class="mb-0"><span class="gain-num" data-gain2027="brut-fige" data-montant="{{ $machine($resolution['salaire_base']) }}">{{ $mad($resolution['salaire_base']) }}</span> {{ __('ui.result.unit_mad_month_label') }}</dd>
                </div>
            </dl>
        </div>
        @endif
    </div>

    {{-- 3. IR et frais professionnels, résultats du moteur pour chaque exercice --}}
    <section class="section-card p-3 p-md-4 mb-4" aria-labelledby="gain2027-details-title">
        <h2 id="gain2027-details-title" class="h6 fw-bold mb-3">{{ __('ui.gain2027.details_title') }}</h2>
        <div class="gain-scroll" data-table-scroll tabindex="0" role="region" aria-labelledby="gain2027-details-title">
            <table class="table table-sm gain-table mb-0">
                <caption class="visually-hidden">{{ __('ui.gain2027.details_title') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('ui.gain2027.col_item') }}</th>
                        <th scope="col" class="text-end">{{ __('ui.gain2027.col_2026') }}</th>
                        <th scope="col" class="text-end">{{ __('ui.gain2027.col_2027') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lignes as $ligne)
                    <tr>
                        <th scope="row" class="fw-{{ $ligne['cle'] === 'net' ? 'bold' : 'normal' }}">{{ __('ui.gain2027.'.$ligne['label']) }}</th>
                        <td class="text-end"><span class="gain-num" data-gain2027="{{ $ligne['cle'] }}-2026" data-montant="{{ $machine($reference[$ligne['champ']]) }}">{{ $mad($reference[$ligne['champ']]) }}</span></td>
                        <td class="text-end"><span class="gain-num" data-gain2027="{{ $ligne['cle'] }}-2027" data-montant="{{ $machine($projete[$ligne['champ']]) }}">{{ $mad($projete[$ligne['champ']]) }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="small mb-0 mt-3" style="color:var(--ink-2)">{{ __('ui.comparison.amounts_note') }}</p>
    </section>

    {{-- 4. Décomposition ordonnée : barème, puis frais professionnels --}}
    <section class="section-card p-3 p-md-4 mb-4" aria-labelledby="gain2027-decomposition-title">
        <h2 id="gain2027-decomposition-title" class="h6 fw-bold mb-2">{{ __('ui.gain2027.decomposition_title') }}</h2>
        <p class="small mb-3" style="color:var(--ink-2)">{{ __('ui.gain2027.decomposition_intro') }}</p>
        <div class="gain-scroll" data-table-scroll tabindex="0" role="region" aria-labelledby="gain2027-decomposition-title">
            <table class="table table-sm gain-table mb-0">
                <caption class="visually-hidden">{{ __('ui.gain2027.decomposition_title') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('ui.gain2027.col_step') }}</th>
                        <th scope="col" class="text-end">{{ __('ui.gain2027.col_amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th scope="row" class="fw-normal">{{ __('ui.gain2027.step_bareme') }}</th>
                        <td class="text-end"><span class="gain-num" data-gain2027="part-bareme" data-montant="{{ $machine($gain['decomposition']['bareme']) }}">{{ $mad($gain['decomposition']['bareme']) }}</span></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal">{{ __('ui.gain2027.step_intermediate') }}</th>
                        <td class="text-end"><span class="gain-num" data-gain2027="net-intermediaire" data-montant="{{ $machine($gain['decomposition']['net_intermediaire']) }}">{{ $mad($gain['decomposition']['net_intermediaire']) }}</span></td>
                    </tr>
                    <tr>
                        <th scope="row" class="fw-normal">{{ __('ui.gain2027.step_pro_fees') }}</th>
                        <td class="text-end"><span class="gain-num" data-gain2027="part-frais-pro" data-montant="{{ $machine($gain['decomposition']['frais_pro']) }}">{{ $mad($gain['decomposition']['frais_pro']) }}</span></td>
                    </tr>
                    <tr>
                        <th scope="row">{{ __('ui.gain2027.step_total') }}</th>
                        <td class="text-end fw-bold"><span class="gain-num">{{ $mad($gain['gain']['net_mensuel']) }}</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    {{-- 5. Textes appliqués : provenance de chaque exercice, lue dans config/payroll.php --}}
    <section class="section-card p-3 p-md-4 mb-4" aria-labelledby="gain2027-provenance-title">
        <h2 id="gain2027-provenance-title" class="h6 fw-bold mb-3">{{ __('ui.gain2027.provenance_title') }}</h2>
        <div class="row g-2">
            <div class="col-md-6">
                <p class="small fw-semibold mb-1" style="color:var(--ink-2)">{{ __('ui.gain2027.reference_label') }}</p>
                <x-fiscal-provenance :exercice="$reference['exercice']" :reference="$reference['reference_fiscale']" />
            </div>
            <div class="col-md-6">
                <p class="small fw-semibold mb-1" style="color:var(--ink-2)">{{ __('ui.gain2027.projected_label') }}</p>
                <x-fiscal-provenance :exercice="$projete['exercice']" :reference="$projete['reference_fiscale']" />
            </div>
        </div>
        <p class="gain-notice small mt-3 mb-0">{{ __('ui.gain2027.net_guaranteed_notice') }}</p>
    </section>

    {{-- Avertissements du moteur, jamais masqués --}}
    @if ($avertissements !== [])
    <section class="section-card p-3 p-md-4 mb-4" style="background:var(--s-warn-bg)" aria-labelledby="gain2027-warnings-title">
        <h2 id="gain2027-warnings-title" class="h6 fw-bold mb-2">
            <i class="bi bi-exclamation-triangle-fill me-2" style="color:var(--s-warn)" aria-hidden="true"></i>{{ __('ui.result.warnings_title') }}
        </h2>
        <ul class="small mb-0" style="color:var(--ink-2)">
            @foreach ($avertissements as $avertissement)
            <li>{{ $avertissement }}</li>
            @endforeach
        </ul>
    </section>
    @endif

    {{-- 6. Partage : même payload, rejoue la même comparaison --}}
    <section class="section-card p-3 p-md-4 mb-4 no-print" aria-labelledby="gain2027-share-title">
        <h2 id="gain2027-share-title" class="h6 fw-bold mb-1">
            <i class="bi bi-share me-2" style="color:var(--g-500)" aria-hidden="true"></i>{{ __('ui.gain2027.share_title') }}
        </h2>
        <p class="small mb-3" style="color:var(--ink-2)">{{ __('ui.gain2027.share_text') }}</p>

        <label for="gain2027ShareUrl" class="form-label small fw-semibold">{{ __('ui.gain2027.share_label') }}</label>
        <div class="input-group mb-2">
            <input type="text" class="form-control form-control-sm" id="gain2027ShareUrl" value="{{ $shareUrl }}" readonly
                   aria-describedby="gain2027ShareHelp" onfocus="this.select()">
            <button type="button" class="btn btn-sm" style="border:1px solid var(--g-500);color:var(--g-600)"
                    data-copy-target="gain2027ShareUrl" data-copied-label="{{ __('ui.result.share_copied') }}">
                <i class="bi bi-clipboard me-1" aria-hidden="true"></i><span>{{ __('ui.result.share_copy') }}</span>
            </button>
        </div>
        <p class="small mb-0" style="color:var(--ink-2)" id="gain2027ShareHelp">
            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ __('ui.result.share_privacy_warning') }}
        </p>
    </section>
</div>
@endsection

@push('scripts')
<script>
// Copie du lien de la comparaison 2026 / 2027 (#283), comme les autres liens de partage.
document.querySelectorAll('[data-copy-target]').forEach(button => {
    button.addEventListener('click', async () => {
        const field = document.getElementById(button.dataset.copyTarget);
        if (!field) return;

        try {
            await navigator.clipboard.writeText(field.value);
        } catch {
            field.focus();
            field.select();
            return;
        }

        const label = button.querySelector('span');
        const previous = label.textContent;
        label.textContent = button.dataset.copiedLabel;
        setTimeout(() => { label.textContent = previous; }, 2000);
    });
});
</script>
@endpush
