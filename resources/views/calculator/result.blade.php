@extends('layouts.app')

@section('title', '3omar · '.__('ui.result.title'))

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<style>
    /*
     * Page résultat (#122). La composition repose sur des rangées flexibles
     * (.result-wrap) plutôt que sur des colonnes Bootstrap imbriquées : chaque
     * bloc reçoit une largeur minimale cohérente avec sa densité, et une rangée
     * incomplète s'étire au lieu de laisser une colonne vide.
     */
    /* Variantes foncées des couleurs sémantiques : contraste AA sur fonds teintés. */
    .result-page {
        --res-gap: 1.5rem;
        --res-warn-ink: #B45309;
        --res-tax-ink: var(--r-700);
    }

    [data-bs-theme="dark"] .result-page {
        --res-warn-ink: var(--s-warn);
        --res-tax-ink: var(--s-tax);
    }

    .result-page p,
    .result-page li {
        max-width: 75ch;
    }

    /* Bootstrap est chargé en LTR uniquement : alignements logiques pour le RTL. */
    .result-page .text-end { text-align: end !important; }
    .result-page .text-start { text-align: start !important; }

    .result-section {
        margin-bottom: 2.5rem;
    }

    .result-section-title {
        font-family: var(--f-display);
        font-weight: 700;
    }

    .result-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .result-wrap > * {
        flex: 1 1 var(--res-basis, 16rem);
        min-width: 0;
    }

    .result-muted {
        color: var(--ink-2);
    }

    .result-label {
        color: var(--ink-2);
        font-size: .8rem;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    /*
     * Un montant ne se coupe jamais. Il est isolé en LTR : dans un paragraphe
     * arabe, l'espace séparateur de milliers inverserait sinon l'ordre des
     * groupes de chiffres ("254,50 14" au lieu de "14 254,50").
     */
    .num {
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
        direction: ltr;
        unicode-bidi: isolate;
    }

    .result-warn-ink {
        color: var(--res-warn-ink);
    }

    /* 1. Montants clés */
    .result-kpi {
        border-top: 4px solid transparent;
        padding: 1.25rem;
    }

    .result-kpi-value {
        font-family: var(--f-display);
        font-size: clamp(1.5rem, 1.1rem + 1.2vw, 2.1rem);
        font-weight: 700;
        line-height: 1.15;
    }

    .result-kpi-value .result-unit {
        font-size: .95rem;
        font-weight: 600;
    }

    .result-kpi-gross { border-top-color: var(--s-info); }
    .result-kpi-net { border-top-color: var(--s-succ); background: var(--s-succ-bg); }
    .result-kpi-employer { border-top-color: var(--s-warn); background: var(--s-warn-bg); }

    .result-kpi-net .result-kpi-value {
        font-size: clamp(1.75rem, 1.2rem + 1.6vw, 2.6rem);
    }

    /* 2. Diagnostic */
    .result-ratio {
        padding: 1rem 1.25rem;
        border-inline-start: 4px solid var(--res-ratio-color, var(--hairline-strong));
    }

    .result-ratio-value {
        font-family: var(--f-display);
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1.1;
    }

    /* 3. Du brut au net */
    .result-flow {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
        counter-reset: none;
    }

    .result-flow > li {
        flex: 1 1 11rem;
        max-width: none;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: .25rem;
        border: 1px solid var(--hairline);
        border-radius: var(--radius-sm);
        background: var(--paper);
        padding: .85rem 1rem;
    }

    .result-flow-op {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 1.75rem;
        border-radius: 999px;
        background: var(--g-50);
        color: var(--g-700);
        font-family: var(--f-mono);
        font-weight: 700;
    }

    .result-flow-amount {
        font-family: var(--f-display);
        font-size: 1.15rem;
        font-weight: 700;
    }

    .result-flow > li.result-flow-total {
        background: var(--s-succ-bg);
        border-color: var(--s-succ);
    }

    .result-employer-note {
        background: var(--s-warn-bg);
        border-inline-start: 4px solid var(--s-warn);
    }

    .result-employer-note a {
        color: var(--s-info);
    }

    @media (max-width: 767.98px) {
        .result-flow {
            flex-direction: column;
        }

        .result-flow > li {
            flex: 0 0 auto;
        }
    }

    /* 4. Répartition */
    .result-chart {
        position: relative;
        width: min(100%, 16rem);
    }

    .result-swatch {
        display: inline-block;
        flex-shrink: 0;
        width: .8rem;
        height: .8rem;
        border-radius: 2px;
    }

    /* 5. Tableaux détaillés */
    .result-table-scroll {
        overflow-x: auto;
        border-radius: 0 0 var(--radius) var(--radius);
    }

    .result-table-wide {
        min-width: 36rem;
    }

    .result-table-wide td:first-child {
        min-width: 14rem;
    }

    /*
     * Sous 768 px (téléphone, tablette portrait, zoom 200 %), les tableaux à
     * 4 colonnes passent en mode compact pour que la colonne Montant reste
     * visible sans défilement. La zone défilante reste le dernier recours si un
     * libellé très long l'impose.
     */
    @media (max-width: 767.98px) {
        .result-table-wide {
            min-width: 0;
            font-size: .8125rem;
        }

        .result-table-wide td:first-child {
            min-width: 5rem;
        }

        .result-table-wide > :not(caption) > * > * {
            padding-inline: .3rem !important;
        }

        .result-table-wide th {
            letter-spacing: 0;
            font-size: .62rem;
        }

        .result-table-wide .fs-5 {
            font-size: 1rem !important;
        }
    }


    @media print {
        .result-section {
            margin-bottom: 1.25rem;
        }

        .result-section,
        .result-flow > li,
        .section-card {
            break-inside: avoid;
        }

        .result-table-scroll {
            overflow: visible;
        }

        .result-table-wide {
            min-width: 0;
        }

        .result-flow {
            flex-direction: row;
        }
    }
</style>
@endpush

@section('content')
<div class="container result-page">
    @php
        $fmt = fn ($amount, $decimals = 2) => number_format($amount, $decimals, ',', ' ');
        $pct = fn ($rate) => number_format($rate * 100, 2, ',', '.').'%';
        $trimPct = fn ($rate) => rtrim(rtrim(number_format($rate * 100, 2, ',', ' '), '0'), ',').'%';
        // Montant insécable suivi de son unité : le nombre ne se coupe jamais,
        // l'unité peut passer à la ligne si la largeur manque.
        $money = fn ($amount, $unitKey = 'ui.result.unit_mad_month_label', $sign = '') => new \Illuminate\Support\HtmlString(
            '<span class="num">'.e($sign.$fmt($amount)).'</span> <span class="result-unit">'.e(__($unitKey)).'</span>'
        );
        // Traduction dont les paramètres numériques sont isolés comme des montants.
        $transNum = function (string $key, array $numbers) {
            $placeholders = [];
            foreach (array_keys($numbers) as $i => $name) {
                $placeholders[$name] = "\u{E000}{$i}\u{E000}";
            }
            $html = e(__($key, $placeholders));
            foreach (array_values($numbers) as $i => $value) {
                $html = str_replace("\u{E000}{$i}\u{E000}", '<span class="num">'.e($value).'</span>', $html);
            }

            return new \Illuminate\Support\HtmlString($html);
        };
        $chartLabels = [
            'net' => __('ui.result.chart_net'),
            'cnss' => __('ui.result.chart_cnss', ['rate' => number_format(config('payroll.cnss.taux') * 100, 2, ',', '.')]),
            'amo' => __('ui.result.chart_amo', ['rate' => number_format(config('payroll.amo.taux') * 100, 2, ',', '.')]),
            'cimr' => __('ui.result.chart_cimr'),
            'ir' => __('ui.result.chart_ir'),
            'retenues' => __('ui.result.chart_deductions'),
        ];
        $marginalRate = round($r['tranche_ir']['taux'] * 100);
    @endphp

    {{-- En-tête : contexte de la simulation --}}
    <header class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-3">
        <div>
            <div class="eyebrow mb-1">{{ __('ui.result.eyebrow') }}</div>
            <h1 class="h2 fw-bold mb-1">{{ __('ui.result.title') }}</h1>
            <p class="mb-0 result-muted">{{ __('ui.result.intro') }}</p>
        </div>
        <div class="d-flex flex-column flex-sm-row gap-2 no-print flex-shrink-0">
            <a href="{{ route('calculator.index') }}" class="btn text-white fw-semibold" style="background:var(--g-500)">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('ui.result.edit') }}
            </a>
            <button type="button" onclick="window.print()" class="btn fw-semibold" style="border:1px solid var(--ink-2);color:var(--ink-2)">
                <i class="bi bi-printer me-1" aria-hidden="true"></i>{{ __('ui.result.print') }}
            </button>
        </div>
    </header>

    <div class="d-flex align-items-center gap-2 rounded-3 px-3 py-2 mb-4 no-print" style="background:var(--s-succ-bg);border:1px solid var(--hairline)">
        <i class="bi bi-shield-check-fill flex-shrink-0" style="color:var(--s-succ)" aria-hidden="true"></i>
        <p class="small mb-0 result-muted">
            {{ __('ui.result.trust_banner_text') }}
            <a href="{{ route('trust') }}" class="ms-1" style="color:var(--s-succ)">{{ __('ui.result.trust_banner_link') }}</a>
        </p>
    </div>

    {{-- 1. Montants clés --}}
    <section class="result-section" data-result-section="synthese" aria-labelledby="result-summary-title">
        @if(($r['mode'] ?? 'gross_to_net') === 'net_to_gross')
        <div class="section-card p-3 p-md-4 mb-3" style="background:var(--s-info-bg)" data-result-block="net-to-gross">
            <div class="eyebrow mb-1">{{ __('ui.result.net_to_gross_badge') }}</div>
            <h2 class="h5 fw-bold mb-1">{{ __('ui.result.net_to_gross_title') }}</h2>
            <p class="mb-3 small result-muted">{{ __('ui.result.net_to_gross_intro') }}</p>
            <dl class="result-wrap mb-0" style="--res-basis:11rem">
                <div>
                    <dt class="result-label">{{ __('ui.result.net_target') }}</dt>
                    <dd class="fs-5 fw-bold mb-0">{{ $money($r['resolution_net']['net_cible']) }}</dd>
                </div>
                <div>
                    <dt class="result-label">{{ __('ui.result.net_resolved') }}</dt>
                    <dd class="fs-5 fw-bold mb-0">{{ $money($r['resolution_net']['net_obtenu']) }}</dd>
                </div>
                <div>
                    <dt class="result-label">{{ __('ui.result.resolved_base_salary') }}</dt>
                    <dd class="fs-5 fw-bold mb-0" style="color:var(--s-info)">{{ $money($r['input']['salaire_base']) }}</dd>
                </div>
                <div>
                    <dt class="result-label">{{ __('ui.result.resolution_gap') }}</dt>
                    <dd class="fs-5 fw-bold mb-0" style="color:{{ $r['resolution_net']['converge'] ? 'var(--s-succ)' : 'var(--s-tax)' }}">{{ $money($r['resolution_net']['ecart']) }}</dd>
                </div>
            </dl>
        </div>
        @endif

        <div class="eyebrow mb-1">{{ __('ui.result.summary_eyebrow') }}</div>
        <h2 class="h4 result-section-title mb-3" id="result-summary-title">{{ __('ui.result.summary_title') }}</h2>

        <div class="result-wrap" style="--res-basis:14rem" data-result-block="kpis">
            <div class="section-card result-kpi result-kpi-gross">
                <div class="result-label mb-2">{{ __('ui.result.gross_salary') }}</div>
                <p class="result-kpi-value mb-2" style="color:var(--s-info)">{{ $money($r['salaire_brut_total']) }}</p>
                <p class="small mb-0 result-muted">{{ __('ui.result.gross_salary_help') }}</p>
            </div>
            <div class="section-card result-kpi result-kpi-net">
                <div class="result-label mb-2">{{ __('ui.result.net_pay') }}</div>
                <p class="result-kpi-value mb-2" style="color:var(--s-succ)">{{ $money($r['salaire_net']) }}</p>
                <p class="small mb-0 result-muted">{{ __('ui.result.net_pay_help') }}</p>
            </div>
            <div class="section-card result-kpi result-kpi-employer">
                <div class="result-label mb-2">{{ __('ui.result.total_employer_cost') }}</div>
                <p class="result-kpi-value mb-2" style="color:var(--r-500)">{{ $money($r['cout_total_employeur']) }}</p>
                <p class="small mb-0 result-muted">{{ __('ui.result.total_employer_cost_help') }}</p>
            </div>
        </div>
    </section>

    {{-- 2. Diagnostic et avertissements --}}
    @php
        $totalDeductions = $r['total_sociales'] + $r['ir_net'];
        $effectiveRate = $r['salaire_brut_total'] > 0 ? round($totalDeductions / $r['salaire_brut_total'] * 100, 1) : 0;
        $netRatio = $r['salaire_brut_total'] > 0 ? round($r['salaire_net'] / $r['salaire_brut_total'] * 100, 1) : 0;
        $employerOverhead = $r['salaire_brut_total'] > 0 ? round(($r['cout_total_employeur'] - $r['salaire_brut_total']) / $r['salaire_brut_total'] * 100, 1) : 0;

        $cnssPlafond = config('payroll.cnss.plafond');
        $takeaways = [];

        if ($r['ir_net'] <= 0) {
            $takeaways[] = ['icon' => 'bi-check-circle-fill', 'color' => 'var(--s-succ)',
                'text' => __('ui.result.takeaway_no_ir'),
                'cta_label' => __('ui.result.takeaway_cta_ir_schedule'), 'cta_href' => route('documentation').'#impot'];
        } elseif ($marginalRate >= 30) {
            $takeaways[] = ['icon' => 'bi-percent', 'color' => 'var(--res-warn-ink)',
                'text' => __('ui.result.takeaway_high_marginal_ir', ['rate' => $marginalRate]),
                'cta_label' => __('ui.result.takeaway_cta_cimr'), 'cta_href' => route('calculator.index').'#step-cimr'];
        }

        if ($r['sbi'] >= $cnssPlafond) {
            $takeaways[] = ['icon' => 'bi-shield-check', 'color' => 'var(--s-info)',
                'text' => $transNum('ui.result.takeaway_cnss_capped', ['amount' => number_format(round($cnssPlafond * config('payroll.cnss.taux'), 2), 2, ',', ' ')]),
                'cta_label' => __('ui.result.takeaway_cta_cnss_rule'), 'cta_href' => route('documentation').'#cotisations'];
        }

        if (($r['cimr_taux'] ?? 0) == 0 && $marginalRate >= 20 && count($takeaways) < 3) {
            $takeaways[] = ['icon' => 'bi-piggy-bank', 'color' => 'var(--s-cot)',
                'text' => __('ui.result.takeaway_no_cimr', ['rate' => $marginalRate]),
                'cta_label' => __('ui.result.takeaway_cta_cimr'), 'cta_href' => route('calculator.index').'#step-cimr'];
        }

        $hasUnknown = ($r['cimr_taux_employeur_inconnu'] ?? false)
            || ($r['mutuelle_patronale_inconnue'] ?? false)
            || ($r['assurance_at_inconnue'] ?? false)
            || ($r['rc_part_employeur_inconnu'] ?? false);
        if ($hasUnknown && count($takeaways) < 3) {
            $takeaways[] = ['icon' => 'bi-exclamation-triangle', 'color' => 'var(--res-warn-ink)',
                'text' => __('ui.result.takeaway_cost_underestimated'),
                'cta_label' => __('ui.result.takeaway_cta_employer_values'), 'cta_href' => route('calculator.index').'#step-mutuelle'];
        }
    @endphp
    <section class="result-section" data-result-section="diagnostic" aria-labelledby="result-verdict-title">
        <h2 class="h4 result-section-title mb-3" id="result-verdict-title">{{ __('ui.result.verdict_title') }}</h2>

        @if(!empty($r['avertissements']))
        <div class="alert alert-warning border-0 shadow-sm mb-3" data-result-block="warnings">
            <h3 class="h6 fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ __('ui.result.warnings_title') }}</h3>
            <ul class="mb-0">
                @foreach($r['avertissements'] as $w)
                <li>{{ $w }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="result-wrap mb-3" style="--res-basis:13rem" data-result-block="ratios">
            <div class="section-card result-ratio" style="--res-ratio-color:var(--s-tax)">
                <div class="result-label mb-1">{{ __('ui.result.verdict_effective_rate') }}</div>
                <p class="result-ratio-value mb-1" style="color:var(--s-tax)"><span class="num">{{ $effectiveRate }}%</span></p>
                <p class="small mb-0 result-muted">{{ __('ui.result.verdict_effective_rate_help') }}</p>
            </div>
            <div class="section-card result-ratio" style="--res-ratio-color:var(--s-succ)">
                <div class="result-label mb-1">{{ __('ui.result.verdict_net_ratio') }}</div>
                <p class="result-ratio-value mb-1" style="color:var(--s-succ)"><span class="num">{{ $netRatio }}%</span></p>
                <p class="small mb-0 result-muted">{{ $money($r['salaire_net']) }}</p>
            </div>
            <div class="section-card result-ratio" style="--res-ratio-color:var(--s-warn)">
                <div class="result-label mb-1">{{ __('ui.result.verdict_employer_overhead') }}</div>
                <p class="result-ratio-value mb-1 result-warn-ink"><span class="num">+{{ $employerOverhead }}%</span></p>
                <p class="small mb-0 result-muted">{{ __('ui.result.verdict_employer_overhead_help') }}</p>
            </div>
        </div>

        @if (!empty($takeaways))
        <h3 class="eyebrow mb-2" id="result-takeaways-title">{{ __('ui.result.takeaways_title') }}</h3>
        <div class="result-wrap" style="--res-basis:18rem" data-result-block="takeaways">
            @foreach ($takeaways as $t)
            <div class="d-flex flex-column gap-2 p-3 rounded-3" style="background:var(--paper);border:1px solid var(--hairline);box-shadow:var(--shadow-1)">
                <div class="d-flex gap-3">
                    <i class="bi {{ $t['icon'] }} fs-5 flex-shrink-0" style="color:{{ $t['color'] }}" aria-hidden="true"></i>
                    <p class="small mb-0 result-muted">{{ $t['text'] }}</p>
                </div>
                <a href="{{ $t['cta_href'] }}" class="small d-inline-flex align-items-center gap-1 no-print">
                    <i class="bi bi-arrow-right-short" aria-hidden="true"></i>{{ $t['cta_label'] }}
                </a>
            </div>
            @endforeach
        </div>
        @endif
    </section>

    {{-- 3. Du brut au net, étape par étape --}}
    <section class="result-section" data-result-section="explication" aria-labelledby="result-explanation-title">
        <div class="eyebrow mb-1">{{ __('ui.result.explanation_eyebrow') }}</div>
        <h2 class="h4 result-section-title mb-1" id="result-explanation-title">{{ __('ui.result.explanation_title') }}</h2>
        <p class="small mb-3 result-muted">{{ __('ui.result.flow_intro') }}</p>

        <ol class="result-flow mb-3" data-result-block="flow">
            <li>
                <span class="result-label">{{ __('ui.result.taxable_gross_salary') }}</span>
                <span class="result-flow-amount" style="color:var(--s-info)">{{ $money($r['sbi']) }}</span>
                <a href="{{ route('calculator.index') }}#step-remuneration" class="small mt-auto no-print">{{ __('ui.result.formula_cta_sbi') }}</a>
            </li>
            <li>
                <span class="result-flow-op">−</span>
                <span class="result-label">{{ __('ui.result.employee_contributions') }}</span>
                <span class="result-flow-amount" style="color:var(--s-cot)">{{ $money($r['total_sociales']) }}</span>
                <a href="{{ route('documentation') }}#cotisations" class="small mt-auto no-print">{{ __('ui.result.formula_cta_cotisations') }}</a>
            </li>
            <li>
                <span class="result-flow-op">−</span>
                <span class="result-label">{{ __('ui.result.income_tax') }}</span>
                <span class="result-flow-amount" style="color:var(--s-tax)">{{ $money($r['ir_net']) }}</span>
                <span class="small result-muted">{{ __('ui.result.ir_bracket', ['rate' => $marginalRate]) }}</span>
                <a href="{{ route('documentation') }}#impot" class="small mt-auto no-print">{{ __('ui.result.formula_cta_ir') }}</a>
            </li>
            @if(($r['total_indemnites'] ?? 0) > 0)
            <li>
                <span class="result-flow-op">+</span>
                <span class="result-label">{{ __('ui.result.flow_allowances') }}</span>
                <span class="result-flow-amount" style="color:var(--s-succ)">{{ $money($r['total_indemnites']) }}</span>
            </li>
            @endif
            @if(($r['total_retenues'] ?? 0) > 0)
            <li>
                <span class="result-flow-op">−</span>
                <span class="result-label">{{ __('ui.result.flow_deductions') }}</span>
                <span class="result-flow-amount result-muted">{{ $money($r['total_retenues']) }}</span>
            </li>
            @endif
            <li class="result-flow-total">
                <span class="result-flow-op">=</span>
                <span class="result-label">{{ __('ui.result.net_pay') }}</span>
                <span class="result-flow-amount" style="color:var(--s-succ)">{{ $money($r['salaire_net']) }}</span>
            </li>
        </ol>

        <div class="section-card result-employer-note p-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2" data-result-block="employer-note">
            <div>
                <div class="fw-semibold">{{ __('ui.result.flow_employer_title') }}</div>
                <p class="small mb-0 result-muted">{{ __('ui.result.employer_formula_hint') }}</p>
            </div>
            <div class="d-flex flex-wrap align-items-baseline gap-2 gap-md-3">
                <span class="fw-bold result-warn-ink">{{ $money($r['total_patronal'], 'ui.result.unit_mad_month_label', '+ ') }}</span>
                <span aria-hidden="true" class="result-muted">→</span>
                <span class="fw-bold" style="color:var(--r-500)">{{ $money($r['cout_total_employeur']) }}</span>
                <a href="{{ route('documentation') }}#charges-patronales" class="small no-print">{{ __('ui.result.formula_cta_employer') }}</a>
            </div>
        </div>
    </section>

    {{-- 4. Répartition du brut --}}
    <section class="result-section" data-result-section="repartition" aria-labelledby="result-chart-title">
        <h2 class="h4 result-section-title mb-1" id="result-chart-title">{{ __('ui.result.chart_title') }}</h2>
        <p class="small mb-3 result-muted" id="payrollChartHelp">{{ __('ui.result.chart_a11y_help') }}</p>

        <div class="section-card p-3 p-md-4">
            <div class="result-wrap" style="gap:var(--res-gap)">
                <div style="flex:0 1 16rem;display:grid;place-items:center">
                    <div class="result-chart">
                        <canvas id="payrollChart" aria-hidden="true"></canvas>
                    </div>
                </div>
                <div style="flex:1 1 22rem">
                    <table class="table table-sm align-middle mb-0" aria-describedby="payrollChartHelp">
                        <caption class="visually-hidden">{{ __('ui.result.chart_table_caption') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="text-start">{{ __('ui.result.chart_col_category') }}</th>
                                <th scope="col" class="text-end">{{ __('ui.result.chart_col_amount') }}</th>
                                <th scope="col" class="text-end">{{ __('ui.result.chart_col_share') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($r['repartition'] as $key => $part)
                                @if(($part['montant'] ?? 0) > 0)
                                <tr>
                                    <th scope="row" class="fw-semibold">
                                        <span class="d-inline-flex align-items-center gap-2">
                                            <span class="result-swatch" style="background:{{ $part['color'] }}" aria-hidden="true"></span>
                                            {{ $chartLabels[$key] ?? __('ui.result.chart_deductions') }}
                                        </span>
                                    </th>
                                    <td class="text-end">{{ $money($part['montant']) }}</td>
                                    <td class="text-end"><span class="num">{{ number_format($part['pct'], 1, ',', ' ') }}%</span></td>
                                </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    {{-- 5. Tableaux détaillés --}}
    <section class="result-section" data-result-section="details" aria-labelledby="result-detail-title">
        <div class="eyebrow mb-1">{{ __('ui.result.detail_eyebrow') }}</div>
        <h2 class="h4 result-section-title mb-3" id="result-detail-title">{{ __('ui.result.detail_title') }}</h2>

        {{-- Bulletin du salarié --}}
        <div class="section-card mb-3" data-result-block="employee-table">
            <div class="card-header px-3 px-md-4 py-3">
                <h3 class="h6 fw-bold mb-0" id="result-employee-table-title">
                    <i class="bi bi-person-vcard me-2" style="color:var(--s-succ)" aria-hidden="true"></i>{{ __('ui.result.detail_employee_title') }}
                </h3>
            </div>
            <div class="result-table-scroll" data-table-scroll tabindex="0" role="region" aria-labelledby="result-employee-table-title">
                <table class="table table-sm mb-0 detail-table result-table-wide">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="px-3 py-2">{{ __('ui.result.detail_col_item') }}</th>
                            <th scope="col" class="text-end px-3 py-2">{{ __('ui.result.detail_col_base') }}</th>
                            <th scope="col" class="text-end px-3 py-2">{{ __('ui.result.detail_col_rate') }}</th>
                            <th scope="col" class="text-end px-3 py-2">{{ __('ui.result.detail_col_amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>

                        {{-- GAINS --}}
                        <tr class="row-brut">
                            <td class="px-3 py-2">{{ __('ui.result.base_salary') }}</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-info)"><span class="num">{{ $fmt($r['input']['salaire_base']) }}</span></td>
                        </tr>

                        @if($r['prime_anciennete'] > 0)
                        <tr class="row-brut">
                            <td class="px-3 py-2">
                                {{ __('ui.result.seniority_bonus', ['years' => $r['nb_annees_anciennete']]) }}
                                <span class="badge text-bg-light badge-legal ms-1">Art. 350 CT</span>
                            </td>
                            <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $fmt($r['input']['salaire_base']) }}</span></td>
                            <td class="text-end px-3 py-2 result-muted"><span class="num">{{ round($r['taux_anciennete'] * 100) }}%</span></td>
                            <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-info)"><span class="num">{{ $fmt($r['prime_anciennete']) }}</span></td>
                        </tr>
                        @endif

                        @if($r['prime_bilan'] > 0)
                        <tr class="row-brut">
                            <td class="px-3 py-2">{{ __('ui.result.year_bonus') }}</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-info)"><span class="num">{{ $fmt($r['prime_bilan']) }}</span></td>
                        </tr>
                        @endif

                        @if($r['prime_rendement'] > 0)
                        <tr class="row-brut">
                            <td class="px-3 py-2">{{ __('ui.result.performance_bonus') }}</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-info)"><span class="num">{{ $fmt($r['prime_rendement']) }}</span></td>
                        </tr>
                        @endif

                        @if($r['autres_primes'] > 0)
                        <tr class="row-brut">
                            <td class="px-3 py-2">{{ __('ui.result.other_taxable_bonuses') }}</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-info)"><span class="num">{{ $fmt($r['autres_primes']) }}</span></td>
                        </tr>
                        @endif

                        @foreach($r['detail_hs'] as $hs)
                        <tr class="row-brut">
                            <td class="px-3 py-2">{{ __('ui.result.overtime_line', ['label' => $hs['label']]) }}</td>
                            <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $hs['nb_heures'] }}h × {{ number_format($hs['taux_horaire'], 2, ',', '.') }}</span></td>
                            <td class="text-end px-3 py-2 result-muted"><span class="num">×{{ number_format(1 + $hs['majoration'], 2, ',', '.') }}</span></td>
                            <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-info)"><span class="num">{{ $fmt($hs['montant']) }}</span></td>
                        </tr>
                        @endforeach

                        @if($r['excedent_indemnites'] > 0)
                        <tr class="row-brut">
                            <td class="px-3 py-2">
                                {{ __('ui.result.excess_allowances') }}
                                <span class="badge text-bg-light badge-legal ms-1">Arrêté 1314-25</span>
                            </td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-info)"><span class="num">{{ $fmt($r['excedent_indemnites']) }}</span></td>
                        </tr>
                        @endif

                        @if($r['total_avantages_cnss_exoneres'] > 0)
                        <tr class="row-brut">
                            <td class="px-3 py-2">{{ __('ui.result.cnss_exempt_line') }} <span class="badge text-bg-info text-white ms-1">{{ __('ui.result.cnss_exempt_badge') }}</span></td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-info)"><span class="num">{{ $fmt($r['total_avantages_cnss_exoneres']) }}</span></td>
                        </tr>
                        @endif

                        <tr class="table-light">
                            <td class="px-3 py-2 fw-bold" colspan="3">{{ __('ui.result.sbi_label') }}</td>
                            <td class="text-end px-3 py-2 fw-bold" style="color:var(--s-info)"><span class="num">{{ $fmt($r['sbi']) }}</span></td>
                        </tr>

                        {{-- COTISATIONS SALARIALES --}}
                        <tr class="row-cotis">
                            <td class="px-3 py-2">
                                {{ __('ui.result.cnss_employee') }}
                                <span class="badge text-bg-light badge-legal ms-1">Dahir 1-72-184</span>
                                @if($r['total_avantages_cnss_exoneres'] > 0)
                                <br><small class="result-muted">{{ __('ui.result.cnss_base_note') }}</small>
                                @endif
                            </td>
                            <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $fmt($r['assiette_cnss']) }}</span></td>
                            <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $pct(config('payroll.cnss.taux')) }}</span></td>
                            <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-cot)"><span class="num">− {{ $fmt($r['cotisation_cnss']) }}</span></td>
                        </tr>

                        <tr class="row-cotis">
                            <td class="px-3 py-2">
                                {{ __('ui.result.amo_employee') }}
                                @if($r['amo_taux_salarie_personnalise'])
                                <span class="badge text-bg-warning-subtle text-warning-emphasis badge-legal ms-1">{{ __('ui.result.amo_custom_rate_badge') }}</span>
                                @else
                                <span class="badge text-bg-light badge-legal ms-1">Loi 65-00</span>
                                @endif
                            </td>
                            <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $fmt($r['assiette_sociale']) }}</span></td>
                            <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $pct($r['amo_taux_salarie']) }}</span></td>
                            <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-cot)"><span class="num">− {{ $fmt($r['cotisation_amo']) }}</span></td>
                        </tr>

                        @if($r['cotisation_cimr'] > 0)
                        <tr class="row-cotis">
                            <td class="px-3 py-2">
                                {{ __('ui.result.cimr_employee') }}
                                <span class="badge text-bg-light badge-legal ms-1">Art. 28-III CGI</span>
                            </td>
                            <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $fmt($r['sbi']) }}</span></td>
                            <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $trimPct($r['cimr_taux']) }}</span></td>
                            <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-cot)"><span class="num">− {{ $fmt($r['cotisation_cimr']) }}</span></td>
                        </tr>
                        @endif

                        <tr class="table-light">
                            <td class="px-3 py-2 fw-semibold" colspan="3">{{ __('ui.result.snc_label') }}</td>
                            <td class="text-end px-3 py-2 fw-semibold"><span class="num">{{ $fmt($r['snc']) }}</span></td>
                        </tr>

                        {{-- FRAIS PRO & RNI --}}
                        <tr class="row-impot">
                            <td class="px-3 py-2">
                                {{ __('ui.result.pro_fees') }}
                                <br><small class="result-muted">{{ $r['desc_fp'] }}{{ $r['fp_plafonne'] ? ' - '.__('ui.result.pro_fees_capped') : '' }}</small>
                                <span class="badge text-bg-light badge-legal ms-1">Art. 59 CGI</span>
                            </td>
                            <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $fmt($r['sbi']) }}</span></td>
                            <td class="text-end px-3 py-2 result-muted"><span class="num">{{ round($r['taux_fp'] * 100) }}%</span></td>
                            <td class="text-end px-3 py-2 result-muted fst-italic"><span class="num">− {{ $fmt($r['frais_pro']) }}</span></td>
                        </tr>

                        @if($r['rc_deduite'] > 0)
                        <tr class="row-impot">
                            <td class="px-3 py-2">
                                {{ __('ui.result.rc_ir_deduction') }}
                                <br><small class="result-muted"><span class="num">{{ $fmt($r['rc_annuel'], 0) }}</span> {{ __('ui.result.unit_mad_year_label') }}{{ $r['rc_annuel'] > $r['rc_deduite'] ? ' - '.__('ui.result.rc_capped') : '' }}</small>
                                <span class="badge text-bg-light badge-legal ms-1">Art. 28-IV CGI</span>
                            </td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 result-muted">{{ __('ui.result.annual') }}</td>
                            <td class="text-end px-3 py-2 result-muted fst-italic"><span class="num">− {{ $fmt($r['rc_deduite'] / 12) }}</span></td>
                        </tr>
                        @endif

                        @if($r['retenues_exonerees_ir'] > 0)
                        <tr class="row-impot">
                            <td class="px-3 py-2">{{ __('ui.result.retenues_exonerees_ir_line') }}</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 result-muted fst-italic"><span class="num">− {{ $fmt($r['retenues_exonerees_ir']) }}</span></td>
                        </tr>
                        @endif

                        <tr class="table-light">
                            <td class="px-3 py-2 fw-semibold" colspan="3">{{ $transNum('ui.result.rni_label', ['annual' => $fmt($r['rni_annuel_net'])]) }}</td>
                            <td class="text-end px-3 py-2 fw-semibold"><span class="num">{{ $fmt($r['rni']) }}</span></td>
                        </tr>

                        {{-- IR --}}
                        <tr class="row-impot">
                            <td class="px-3 py-2">
                                {{ __('ui.result.ir_gross') }}
                                <br><small class="result-muted"><span class="num">{{ $marginalRate }}% × {{ $fmt($r['rni_annuel_net'], 0) }}</span> − <span class="num">{{ $fmt($r['tranche_ir']['deduction'], 0) }}</span> = {{ $money($r['ir_annuel_brut'], 'ui.result.unit_mad_year_label') }}</small>
                                <span class="badge text-bg-light badge-legal ms-1">Art. 73 CGI</span>
                            </td>
                            <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $fmt($r['rni']) }}</span></td>
                            <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $marginalRate }}%</span></td>
                            <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-tax)"><span class="num">{{ $fmt($r['ir_mensuel_brut']) }}</span></td>
                        </tr>

                        @if($r['charges_famille'] > 0)
                        <tr class="row-impot">
                            <td class="px-3 py-2">
                                {{ __('ui.result.family_deduction') }}
                                <br><small class="result-muted">{{ $transNum('ui.result.family_deduction_detail', ['count' => $r['nb_personnes'], 'amount' => $fmt(config('payroll.charges_famille.par_personne'), 0)]) }}</small>
                                <span class="badge text-bg-light badge-legal ms-1">Art. 74 CGI</span>
                            </td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2" style="color:var(--s-succ)"><span class="num">+ {{ $fmt($r['charges_famille']) }}</span></td>
                        </tr>
                        @endif

                        <tr class="row-impot">
                            <td class="px-3 py-2 fw-semibold">{{ __('ui.result.ir_net_withheld') }}</td>
                            <td class="text-end px-3 py-2 result-muted" colspan="2">-</td>
                            <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-tax)"><span class="num">− {{ $fmt($r['ir_net']) }}</span></td>
                        </tr>

                        {{-- Indemnités --}}
                        @foreach($r['detail_indemnites'] as $ind)
                        <tr class="row-indem">
                            <td class="px-3 py-2">
                                {{ __('ui.result.allowance_line', ['label' => $ind['label']]) }}
                                @if($ind['depasse'])
                                <span class="badge text-bg-warning ms-1">{{ $transNum('ui.result.capped_at', ['cap' => $fmt($ind['plafond'], 0)]) }}</span>
                                <br><small class="result-muted">{{ $transNum('ui.result.excess_reintegrated', ['amount' => $fmt($ind['excedent'])]) }}</small>
                                @endif
                                <span class="badge text-bg-light badge-legal ms-1">Arrêté 1314-25</span>
                            </td>
                            <td class="text-end px-3 py-2 result-muted"><span class="num">{{ __('ui.result.ceiling_label') }} {{ $fmt($ind['plafond'], 0) }}</span></td>
                            <td class="text-end px-3 py-2 result-muted">{{ __('ui.result.exempt') }}</td>
                            <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-succ)"><span class="num">+ {{ $fmt($ind['retenu']) }}</span></td>
                        </tr>
                        @endforeach

                        {{-- Retenues diverses --}}
                        @if($r['mutuelle_salarie'] > 0)
                        <tr class="row-retenue">
                            <td class="px-3 py-2">{{ __('ui.result.mutual_employee') }}</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-tax)"><span class="num">− {{ $fmt($r['mutuelle_salarie']) }}</span></td>
                        </tr>
                        @endif

                        @if($r['retenues_exonerees_ir'] > 0)
                        <tr class="row-retenue">
                            <td class="px-3 py-2">{{ __('ui.result.retenues_exonerees_ir_withheld_line') }}</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-tax)"><span class="num">− {{ $fmt($r['retenues_exonerees_ir']) }}</span></td>
                        </tr>
                        @endif

                        @if($r['retenues_imposees_ir'] > 0)
                        <tr class="row-retenue">
                            <td class="px-3 py-2">{{ __('ui.result.other_deductions_line') }}</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 result-muted">-</td>
                            <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-tax)"><span class="num">− {{ $fmt($r['retenues_imposees_ir']) }}</span></td>
                        </tr>
                        @endif

                        {{-- NET --}}
                        <tr class="row-net">
                            <td class="px-3 py-3" colspan="3">
                                <i class="bi bi-check-circle-fill me-1" style="color:var(--s-succ)" aria-hidden="true"></i>{{ __('ui.result.net_to_pay') }}
                            </td>
                            <td class="text-end px-3 py-3 fs-5"><span class="num">{{ $fmt($r['salaire_net']) }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="result-wrap" style="gap:1rem">
            {{-- Coût employeur --}}
            <div class="section-card" style="flex:3 1 36rem" data-result-block="employer-table">
                <div class="card-header px-3 px-md-4 py-3">
                    <h3 class="h6 fw-bold mb-0" id="result-employer-table-title">
                        <i class="bi bi-building-up me-2 result-warn-ink" aria-hidden="true"></i>{{ __('ui.result.employer_detail_title') }}
                    </h3>
                    </div>
                <div class="result-table-scroll" data-table-scroll tabindex="0" role="region" aria-labelledby="result-employer-table-title">
                    <table class="table table-sm mb-0 detail-table result-table-wide">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="px-3 py-2">{{ __('ui.result.detail_col_item') }}</th>
                                <th scope="col" class="text-end px-3 py-2">{{ __('ui.result.detail_col_base') }}</th>
                                <th scope="col" class="text-end px-3 py-2">{{ __('ui.result.detail_col_rate') }}</th>
                                <th scope="col" class="text-end px-3 py-2">{{ __('ui.result.detail_col_amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="row-brut">
                                <td class="px-3 py-2">{{ __('ui.result.gross_salary_paid') }}</td>
                                <td class="text-end px-3 py-2 result-muted">-</td>
                                <td class="text-end px-3 py-2 result-muted">-</td>
                                <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-info)"><span class="num">{{ $fmt($r['salaire_brut_total']) }}</span></td>
                            </tr>

                            <tr class="row-patron">
                                <td class="px-3 py-2">
                                    {{ __('ui.result.cnss_employer') }}
                                    <span class="badge text-bg-light badge-legal ms-1">Dahir 1-72-184</span>
                                </td>
                                <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $fmt($r['assiette_cnss']) }}</span></td>
                                <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $pct(config('payroll.cnss.taux_patronal')) }}</span></td>
                                <td class="text-end px-3 py-2 fw-semibold result-warn-ink"><span class="num">+ {{ $fmt($r['cout_cnss_patronal']) }}</span></td>
                            </tr>

                            <tr class="row-patron">
                                <td class="px-3 py-2">
                                    {{ __('ui.result.amo_employer') }}
                                    <span class="badge text-bg-light badge-legal ms-1">Loi 65-00</span>
                                </td>
                                <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $fmt($r['sbi']) }}</span></td>
                                <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $pct(config('payroll.amo.taux_patronal')) }}</span></td>
                                <td class="text-end px-3 py-2 fw-semibold result-warn-ink"><span class="num">+ {{ $fmt($r['cout_amo_patronal']) }}</span></td>
                            </tr>

                            <tr class="row-patron">
                                <td class="px-3 py-2">{{ __('ui.result.family_allowances') }}</td>
                                <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $fmt($r['sbi']) }}</span></td>
                                <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $pct(config('payroll.allocations_familiales.taux_patronal')) }}</span></td>
                                <td class="text-end px-3 py-2 fw-semibold result-warn-ink"><span class="num">+ {{ $fmt($r['cout_af_patronal']) }}</span></td>
                            </tr>

                            <tr class="row-patron">
                                <td class="px-3 py-2">{{ __('ui.result.tfp') }}</td>
                                <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $fmt($r['sbi']) }}</span></td>
                                <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $pct(config('payroll.taxe_formation.taux_patronal')) }}</span></td>
                                <td class="text-end px-3 py-2 fw-semibold result-warn-ink"><span class="num">+ {{ $fmt($r['cout_tfp_patronal']) }}</span></td>
                            </tr>

                            @if($r['cotisation_cimr_patronale'] > 0 || ($r['cimr_taux_employeur_inconnu'] ?? false))
                            <tr class="row-patron">
                                <td class="px-3 py-2">
                                    {{ __('ui.result.cimr_employer') }}
                                    <span class="badge text-bg-light badge-legal ms-1">Art. 28-III CGI</span>
                                </td>
                                <td class="text-end px-3 py-2 result-muted"><span class="num">{{ $fmt($r['sbi']) }}</span></td>
                                <td class="text-end px-3 py-2 result-muted"><span class="num">{{ ($r['cimr_taux_employeur_inconnu'] ?? false) ? '-' : $trimPct($r['cimr_taux_employeur']) }}</span></td>
                                <td class="text-end px-3 py-2 fw-semibold result-warn-ink"><span class="num">{{ ($r['cimr_taux_employeur_inconnu'] ?? false) ? __('ui.result.not_provided') : '+ '.$fmt($r['cotisation_cimr_patronale']) }}</span></td>
                            </tr>
                            @endif

                            @if($r['mutuelle_patronale'] > 0 || ($r['mutuelle_patronale_inconnue'] ?? false))
                            <tr class="row-patron">
                                <td class="px-3 py-2">{{ __('ui.result.mutual_employer') }}</td>
                                <td class="text-end px-3 py-2 result-muted">-</td>
                                <td class="text-end px-3 py-2 result-muted">-</td>
                                <td class="text-end px-3 py-2 fw-semibold result-warn-ink"><span class="num">{{ ($r['mutuelle_patronale_inconnue'] ?? false) ? __('ui.result.not_provided') : '+ '.$fmt($r['mutuelle_patronale']) }}</span></td>
                            </tr>
                            @endif

                            @if($r['rc_part_employeur'] > 0 || ($r['rc_part_employeur_inconnu'] ?? false))
                            <tr class="row-patron">
                                <td class="px-3 py-2">
                                    {{ __('ui.result.rc_employer') }}
                                    <span class="badge text-bg-light badge-legal ms-1">Art. 28-IV CGI</span>
                                </td>
                                <td class="text-end px-3 py-2 result-muted">-</td>
                                <td class="text-end px-3 py-2 result-muted">-</td>
                                <td class="text-end px-3 py-2 fw-semibold result-warn-ink"><span class="num">{{ ($r['rc_part_employeur_inconnu'] ?? false) ? __('ui.result.not_provided') : '+ '.$fmt($r['rc_part_employeur']) }}</span></td>
                            </tr>
                            @endif

                            @if(($r['assurance_at'] ?? 0) > 0 || ($r['assurance_at_inconnue'] ?? false))
                            <tr class="row-patron">
                                <td class="px-3 py-2">{{ __('ui.result.assurance_at_line') }}</td>
                                <td class="text-end px-3 py-2 result-muted"><span class="num">{{ ($r['assurance_at_inconnue'] ?? false) ? '-' : $fmt($r['sbi']) }}</span></td>
                                <td class="text-end px-3 py-2 result-muted"><span class="num">{{ ($r['assurance_at_inconnue'] ?? false) ? '-' : $trimPct($r['assurance_at_taux'] ?? 0) }}</span></td>
                                <td class="text-end px-3 py-2 fw-semibold result-warn-ink"><span class="num">{{ ($r['assurance_at_inconnue'] ?? false) ? __('ui.result.not_provided') : '+ '.$fmt($r['assurance_at']) }}</span></td>
                            </tr>
                            @endif

                            @if(($r['assurance_rc_pro'] ?? 0) > 0 || ($r['assurance_rc_pro_inconnue'] ?? false))
                            <tr class="row-patron">
                                <td class="px-3 py-2">{{ __('ui.result.assurance_rc_pro_line') }}</td>
                                <td class="text-end px-3 py-2 result-muted">-</td>
                                <td class="text-end px-3 py-2 result-muted">-</td>
                                <td class="text-end px-3 py-2 fw-semibold result-warn-ink"><span class="num">{{ ($r['assurance_rc_pro_inconnue'] ?? false) ? __('ui.result.not_provided') : '+ '.$fmt($r['assurance_rc_pro']) }}</span></td>
                            </tr>
                            @endif

                            <tr class="row-employer">
                                <td class="px-3 py-3" colspan="3">
                                    {{ __('ui.result.total_employer_cost_line') }}
                                    <small class="result-muted fw-normal ms-2">{{ __('ui.result.total_employer_cost_sub') }}</small>
                                </td>
                                <td class="text-end px-3 py-3 fs-5" style="color:var(--r-500)"><span class="num">{{ $fmt($r['cout_total_employeur']) }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Barème IR --}}
            <div class="section-card" style="flex:2 1 20rem" data-result-block="ir-table">
                <div class="card-header px-3 px-md-4 py-3">
                    <h3 class="h6 fw-bold mb-0" id="result-ir-table-title">
                        <i class="bi bi-percent me-2" style="color:var(--s-tax)" aria-hidden="true"></i>{{ __('ui.result.ir_bracket_title') }}
                    </h3>
                </div>
                <div class="px-3 px-md-4 py-2">
                    <table class="table table-sm mb-0" aria-labelledby="result-ir-table-title">
                        <tbody>
                            <tr>
                                <th scope="row" class="fw-normal result-muted">{{ __('ui.result.rni_monthly') }}</th>
                                <td class="fw-semibold text-end">{{ $money($r['rni']) }}</td>
                            </tr>
                            <tr>
                                <th scope="row" class="fw-normal result-muted">{{ __('ui.result.rni_annual') }}</th>
                                <td class="fw-semibold text-end">{{ $money($r['rni_annuel'], 'ui.result.unit_mad_year_label') }}</td>
                            </tr>
                            @if($r['rc_deduite'] > 0)
                            <tr>
                                <th scope="row" class="fw-normal result-muted">{{ __('ui.result.minus_rc') }}</th>
                                <td class="fw-semibold text-end" style="color:var(--s-succ)">{{ $money($r['rc_deduite'], 'ui.result.unit_mad_year_label', '− ') }}</td>
                            </tr>
                            <tr>
                                <th scope="row" class="fw-normal result-muted">{{ __('ui.result.rni_taxable') }}</th>
                                <td class="fw-semibold text-end">{{ $money($r['rni_annuel_net'], 'ui.result.unit_mad_year_label') }}</td>
                            </tr>
                            @endif
                            <tr class="table-danger">
                                <th scope="row" class="fw-semibold">{{ __('ui.result.marginal_rate') }}</th>
                                <td class="fw-bold text-end" style="color:var(--res-tax-ink)"><span class="num">{{ $marginalRate }}%</span></td>
                            </tr>
                            <tr>
                                <th scope="row" class="fw-normal result-muted">{{ __('ui.result.fixed_deduction') }}</th>
                                <td class="fw-semibold text-end">{{ $money($r['tranche_ir']['deduction'], 'ui.result.unit_mad_year_label') }}</td>
                            </tr>
                            <tr>
                                <th scope="row" class="fw-normal result-muted">{{ __('ui.result.ir_annual_gross') }}</th>
                                <td class="fw-semibold text-end">{{ $money($r['ir_annuel_brut'], 'ui.result.unit_mad_year_label') }}</td>
                            </tr>
                            @if($r['charges_famille'] > 0)
                            <tr>
                                <th scope="row" class="fw-normal result-muted">{{ __('ui.result.family_charges') }}</th>
                                <td class="fw-semibold text-end" style="color:var(--s-succ)">{{ $money($r['charges_famille'], 'ui.result.unit_mad_year_label', '− ') }}</td>
                            </tr>
                            @endif
                            <tr class="table-warning">
                                <th scope="row" class="fw-bold">{{ __('ui.result.ir_monthly_net') }}</th>
                                <td class="fw-bold text-end">{{ $money($r['ir_net']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    {{-- 6. Actions secondaires --}}
    <section class="result-section no-print" data-result-section="actions" aria-labelledby="next-actions-title">
        <h2 id="next-actions-title" class="h4 result-section-title mb-3">{{ __('ui.result.next_actions_title') }}</h2>

        <div class="result-wrap" style="gap:var(--res-gap)">
            {{-- Reprendre, partager et comparer la simulation (#50, #47) --}}
            @if (! empty($share_payload))
            @php
                $shareUrl = route('calculator.index', ['s' => $share_payload]);
                $compareUrl = route('calculator.index', ['s' => $share_payload, 'a' => $share_payload]);
            @endphp
            <div class="section-card p-3 p-md-4" style="flex:3 1 28rem" data-result-block="share">
                <h3 id="share-title" class="h6 fw-bold mb-1">
                    <i class="bi bi-share me-2" style="color:var(--g-500)" aria-hidden="true"></i>{{ __('ui.result.share_title') }}
                </h3>
                <p class="small mb-3 result-muted">{{ __('ui.result.share_text') }}</p>

                <label for="shareUrl" class="form-label small fw-semibold">{{ __('ui.result.share_link_label') }}</label>
                <div class="input-group mb-2">
                    <input type="text" class="form-control form-control-sm" id="shareUrl" value="{{ $shareUrl }}" readonly
                           aria-describedby="shareUrlHelp" onfocus="this.select()">
                    <button type="button" class="btn btn-sm" id="shareUrlCopy" style="border:1px solid var(--g-500);color:var(--g-600)"
                            data-copy-target="shareUrl" data-copied-label="{{ __('ui.result.share_copied') }}">
                        <i class="bi bi-clipboard me-1" aria-hidden="true"></i><span>{{ __('ui.result.share_copy') }}</span>
                    </button>
                </div>
                <p class="small mb-3 result-warn-ink" id="shareUrlHelp">
                    <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ __('ui.result.share_privacy_warning') }}
                </p>

                <a href="{{ $compareUrl }}" class="btn btn-sm fw-semibold" style="background:var(--g-500);color:#fff">
                    <i class="bi bi-columns-gap me-1" aria-hidden="true"></i>{{ __('ui.result.compare_cta') }}
                </a>
                <p class="small mt-2 mb-0 result-muted">{{ __('ui.result.compare_hint') }}</p>
            </div>
            @endif

            <nav class="d-flex flex-column gap-2" style="flex:2 1 18rem" aria-labelledby="next-actions-title" data-result-block="links">
                <a href="{{ route('calculator.index') }}" class="section-card d-flex align-items-center gap-3 p-3 text-decoration-none" style="color:var(--ink)">
                    <i class="bi bi-arrow-repeat fs-4 flex-shrink-0" style="color:var(--g-500)" aria-hidden="true"></i>
                    <span class="small fw-semibold">{{ __('ui.result.action_simulate_again') }}</span>
                </a>
                <a href="{{ route('documentation') }}" class="section-card d-flex align-items-center gap-3 p-3 text-decoration-none" style="color:var(--ink)">
                    <i class="bi bi-journal-text fs-4 flex-shrink-0" style="color:var(--s-info)" aria-hidden="true"></i>
                    <span class="small fw-semibold">{{ __('ui.result.action_see_rules') }}</span>
                </a>
                <a href="{{ route('trust') }}" class="section-card d-flex align-items-center gap-3 p-3 text-decoration-none" style="color:var(--ink)">
                    <i class="bi bi-shield-check fs-4 flex-shrink-0" style="color:var(--s-succ)" aria-hidden="true"></i>
                    <span class="small fw-semibold">{{ __('ui.result.action_trust') }}</span>
                </a>
                <a href="{{ route('api.documentation') }}" class="section-card d-flex align-items-center gap-3 p-3 text-decoration-none" style="color:var(--ink)">
                    <i class="bi bi-braces fs-4 flex-shrink-0" style="color:var(--s-cot)" aria-hidden="true"></i>
                    <span class="small fw-semibold">{{ __('ui.result.action_api') }}</span>
                </a>
                <button type="button" onclick="window.print()" class="section-card d-flex align-items-center gap-3 p-3 w-100 text-start" style="color:var(--ink);background:var(--paper)">
                    <i class="bi bi-printer fs-4 flex-shrink-0" style="color:var(--ink-2)" aria-hidden="true"></i>
                    <span class="small fw-semibold">{{ __('ui.result.action_print') }}</span>
                </button>
            </nav>
        </div>
    </section>

</div>
@endsection

@push('scripts')
<script>
const repartition = @json($r['repartition']);
const intlLocale = @json(config('app.supported_locales.'.app()->getLocale().'.intl'));

const labels = @json($chartLabels);

const activeKeys = Object.keys(repartition).filter(k => repartition[k].montant > 0);
const data       = activeKeys.map(k => repartition[k].montant);
const colors     = activeKeys.map(k => repartition[k].color);
const chartLabels = activeKeys.map(k => labels[k]);

// La légende est le tableau accessible de la section : le graphique reste décoratif.
const ctx = document.getElementById('payrollChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: chartLabels,
        datasets: [{ data: data, backgroundColor: colors, borderWidth: 2, borderColor: getComputedStyle(document.documentElement).getPropertyValue('--paper').trim() || '#fff' }]
    },
    options: {
        cutout: '65%',
        animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : undefined,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => {
                        const k = activeKeys[ctx.dataIndex];
                        const pct = repartition[k].pct;
                        const amt = repartition[k].montant.toLocaleString(intlLocale, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        return ` ${amt} {{ __('ui.result.unit_mad_month_label') }} (${pct}%)`;
                    }
                }
            }
        }
    }
});

// Copie du lien de reprise/partage de la simulation (#50)
document.querySelectorAll('[data-copy-target]').forEach(button => {
    button.addEventListener('click', async () => {
        const field = document.getElementById(button.dataset.copyTarget);
        if (!field) return;

        try {
            await navigator.clipboard.writeText(field.value);
        } catch {
            // Navigateur sans accès au presse-papiers : on sélectionne le texte
            // pour que l'utilisateur puisse copier manuellement.
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
