@extends('layouts.app')

@section('title', '3omar · '.__('ui.result.title'))

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<style>
    .result-section {
        margin-bottom: 1.5rem;
    }

    .result-section-title {
        font-family: var(--f-display);
        font-weight: 700;
    }

    .kpi-card {
        min-height: 150px;
        border-top: 4px solid transparent;
    }

    .kpi-card-info {
        border-top-color: var(--s-info);
    }

    .kpi-card-primary {
        background: var(--s-succ-bg);
        border-top-color: var(--s-succ);
    }

    .kpi-card-employer {
        background: var(--s-warn-bg);
        border-top-color: var(--s-warn);
    }

    .result-formula {
        display: grid;
        gap: .75rem;
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }

    .result-formula-item {
        min-height: 92px;
        border: 1px solid var(--hairline);
        border-radius: var(--radius-sm);
        background: var(--paper);
        padding: .85rem;
    }

    .result-formula-symbol {
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

    .result-step {
        border-left: 3px solid var(--hairline-strong);
        padding-left: 1rem;
    }

    [dir="rtl"] .result-step {
        border-left: 0;
        border-right: 3px solid var(--hairline-strong);
        padding-left: 0;
        padding-right: 1rem;
    }

    @media (max-width: 991.98px) {
        .result-formula {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="container">
    @php
        $madMonth = fn ($amount) => number_format($amount, 2, ',', ' ').__('ui.result.unit_mad_month');
        $madYear = fn ($amount) => number_format($amount, 2, ',', ' ').__('ui.result.unit_mad_year');
        $signedMadMonth = fn ($amount) => ($amount >= 0 ? '+ ' : '- ').$madMonth(abs($amount));
        $chartLabels = [
            'net' => __('ui.result.chart_net'),
            'cnss' => __('ui.result.chart_cnss', ['rate' => number_format(config('payroll.cnss.taux') * 100, 2, ',', '.')]),
            'amo' => __('ui.result.chart_amo', ['rate' => number_format(config('payroll.amo.taux') * 100, 2, ',', '.')]),
            'cimr' => __('ui.result.chart_cimr'),
            'ir' => __('ui.result.chart_ir'),
            'retenues' => __('ui.result.chart_deductions'),
        ];
    @endphp

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <div class="eyebrow mb-1">{{ __('ui.result.eyebrow') }}</div>
            <h1 class="h2 fw-bold mb-1">{{ __('ui.result.title') }}</h1>
            <p class="mb-0" style="color:var(--ink-2)">{{ __('ui.result.intro') }}</p>
        </div>
        <div class="d-flex flex-column flex-sm-row gap-2 no-print">
            <a href="{{ route('calculator.index') }}" class="btn text-white fw-semibold" style="background:var(--g-500)">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('ui.result.edit') }}
            </a>
            <button type="button" onclick="window.print()" class="btn fw-semibold" style="border:1px solid var(--ink-3);color:var(--ink-2)">
                <i class="bi bi-printer me-1" aria-hidden="true"></i>{{ __('ui.result.print') }}
            </button>
        </div>
    </div>

    {{-- Privacy trust banner --}}
    <div class="d-flex align-items-center gap-2 rounded-3 px-3 py-2 mb-4 no-print" style="background:var(--s-succ-bg);border:1px solid var(--hairline)">
        <i class="bi bi-shield-check-fill flex-shrink-0" style="color:var(--s-succ)" aria-hidden="true"></i>
        <p class="small mb-0" style="color:var(--ink-2)">
            {{ __('ui.result.trust_banner_text') }}
            <a href="{{ route('trust') }}" class="ms-1" style="color:var(--s-succ)">{{ __('ui.result.trust_banner_link') }}</a>
        </p>
    </div>

    @if(($r['mode'] ?? 'gross_to_net') === 'net_to_gross')
    <div class="section-card p-3 p-md-4 mb-4" style="background:var(--s-info-bg)">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <div class="eyebrow mb-1">{{ __('ui.result.net_to_gross_badge') }}</div>
                <h2 class="h5 fw-bold mb-1">{{ __('ui.result.net_to_gross_title') }}</h2>
                <p class="mb-0 small" style="color:var(--ink-2)">{{ __('ui.result.net_to_gross_intro') }}</p>
            </div>
            <div class="row g-3 flex-grow-1">
                <div class="col-sm-6 col-xl-3">
                    <div class="small text-uppercase fw-semibold" style="color:var(--ink-3)">{{ __('ui.result.net_target') }}</div>
                    <div class="fs-5 fw-bold">{{ $madMonth($r['resolution_net']['net_cible']) }}</div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="small text-uppercase fw-semibold" style="color:var(--ink-3)">{{ __('ui.result.net_resolved') }}</div>
                    <div class="fs-5 fw-bold">{{ $madMonth($r['resolution_net']['net_obtenu']) }}</div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="small text-uppercase fw-semibold" style="color:var(--ink-3)">{{ __('ui.result.resolved_base_salary') }}</div>
                    <div class="fs-5 fw-bold" style="color:var(--s-info)">{{ $madMonth($r['input']['salaire_base']) }}</div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="small text-uppercase fw-semibold" style="color:var(--ink-3)">{{ __('ui.result.resolution_gap') }}</div>
                    <div class="fs-5 fw-bold" style="color:{{ $r['resolution_net']['converge'] ? 'var(--s-succ)' : 'var(--s-tax)' }}">{{ $madMonth($r['resolution_net']['ecart']) }}</div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Avertissements --}}
    @if(!empty($r['avertissements']))
    <div class="alert alert-warning border-0 shadow-sm mb-4">
        <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ __('ui.result.warnings_title') }}</h6>
        <ul class="mb-0">
            @foreach($r['avertissements'] as $w)
            <li>{{ $w }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Verdict / Diagnostic cards --}}
    @php
        $totalDeductions = $r['total_sociales'] + $r['ir_net'];
        $effectiveRate = $r['salaire_brut_total'] > 0 ? round($totalDeductions / $r['salaire_brut_total'] * 100, 1) : 0;
        $netRatio = $r['salaire_brut_total'] > 0 ? round($r['salaire_net'] / $r['salaire_brut_total'] * 100, 1) : 0;
        $employerOverhead = $r['salaire_brut_total'] > 0 ? round(($r['cout_total_employeur'] - $r['salaire_brut_total']) / $r['salaire_brut_total'] * 100, 1) : 0;
    @endphp
    <section class="result-section" aria-labelledby="result-verdict-title">
        <div class="mb-3">
            <div class="eyebrow mb-1">{{ __('ui.result.verdict_title') }}</div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-sm-4">
                <div class="section-card p-3 text-center h-100" style="border-top:3px solid var(--s-tax)">
                    <div class="small text-uppercase fw-semibold mb-1" style="color:var(--ink-3)">{{ __('ui.result.verdict_effective_rate') }}</div>
                    <div class="fs-2 fw-bold" style="color:var(--s-tax);font-family:var(--f-display)">{{ $effectiveRate }}%</div>
                    <div class="small mt-1" style="color:var(--ink-3)">{{ __('ui.result.verdict_effective_rate_help') }}</div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="section-card p-3 text-center h-100" style="border-top:3px solid var(--s-succ)">
                    <div class="small text-uppercase fw-semibold mb-1" style="color:var(--ink-3)">{{ __('ui.result.verdict_net_ratio') }}</div>
                    <div class="fs-2 fw-bold" style="color:var(--s-succ);font-family:var(--f-display)">{{ $netRatio }}%</div>
                    <div class="small mt-1" style="color:var(--ink-3)">{{ $madMonth($r['salaire_net']) }}</div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="section-card p-3 text-center h-100" style="border-top:3px solid var(--s-warn)">
                    <div class="small text-uppercase fw-semibold mb-1" style="color:var(--ink-3)">{{ __('ui.result.verdict_employer_overhead') }}</div>
                    <div class="fs-2 fw-bold" style="color:var(--s-warn);font-family:var(--f-display)">+{{ $employerOverhead }}%</div>
                    <div class="small mt-1" style="color:var(--ink-3)">{{ __('ui.result.verdict_employer_overhead_help') }}</div>
                </div>
            </div>
        </div>
    </section>

    {{-- Key takeaways --}}
    @php
        $marginalRate = round($r['tranche_ir']['taux'] * 100);
        $cnssPlafond = config('payroll.cnss.plafond');
        $takeaways = [];

        if ($r['ir_net'] <= 0) {
            $takeaways[] = ['icon' => 'bi-check-circle-fill', 'color' => 'var(--s-succ)',
                'text' => __('ui.result.takeaway_no_ir'),
                'cta_label' => __('ui.result.takeaway_cta_ir_schedule'), 'cta_href' => route('documentation').'#impot'];
        } elseif ($marginalRate >= 30) {
            $takeaways[] = ['icon' => 'bi-percent', 'color' => 'var(--s-warn)',
                'text' => __('ui.result.takeaway_high_marginal_ir', ['rate' => $marginalRate]),
                'cta_label' => __('ui.result.takeaway_cta_cimr'), 'cta_href' => route('calculator.index').'#step-cimr'];
        }

        if ($r['sbi'] >= $cnssPlafond) {
            $takeaways[] = ['icon' => 'bi-shield-check', 'color' => 'var(--s-info)',
                'text' => __('ui.result.takeaway_cnss_capped', ['amount' => number_format(round($cnssPlafond * config('payroll.cnss.taux'), 2), 2, ',', ' ')]),
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
            $takeaways[] = ['icon' => 'bi-exclamation-triangle', 'color' => 'var(--s-warn)',
                'text' => __('ui.result.takeaway_cost_underestimated'),
                'cta_label' => __('ui.result.takeaway_cta_employer_values'), 'cta_href' => route('calculator.index').'#step-mutuelle'];
        }
    @endphp
    @if (!empty($takeaways))
    <section class="result-section" aria-labelledby="result-takeaways-title">
        <div class="mb-3">
            <div class="eyebrow mb-1" id="result-takeaways-title">{{ __('ui.result.takeaways_title') }}</div>
        </div>
        <div class="row g-3">
            @foreach ($takeaways as $t)
            <div class="col-md-6 col-xl-4">
                <div class="d-flex flex-column gap-2 p-3 rounded-3 h-100" style="background:var(--paper);border:1px solid var(--hairline);box-shadow:var(--shadow-1)">
                    <div class="d-flex gap-3">
                        <i class="bi {{ $t['icon'] }} fs-5 flex-shrink-0 mt-1" style="color:{{ $t['color'] }}" aria-hidden="true"></i>
                        <p class="small mb-0" style="color:var(--ink-2)">{{ $t['text'] }}</p>
                    </div>
                    <a href="{{ $t['cta_href'] }}" class="small d-inline-flex align-items-center gap-1 ms-4 ps-1 no-print">
                        <i class="bi bi-arrow-right-short" aria-hidden="true"></i>{{ $t['cta_label'] }}
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    <section class="result-section" aria-labelledby="result-summary-title">
        <div class="mb-3">
            <div class="eyebrow mb-1">{{ __('ui.result.summary_eyebrow') }}</div>
            <h2 class="h4 result-section-title mb-0" id="result-summary-title">{{ __('ui.result.summary_title') }}</h2>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="section-card kpi-card kpi-card-info h-100 py-3 px-3">
                    <div class="small text-uppercase fw-semibold mb-2" style="color:var(--ink-3)">{{ __('ui.result.gross_salary') }}</div>
                    <div class="fs-3 fw-bold" style="color:var(--s-info)">{{ $madMonth($r['salaire_brut_total']) }}</div>
                    <div class="text-muted small mt-2">{{ __('ui.result.gross_salary_help') }}</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="section-card kpi-card kpi-card-primary h-100 py-3 px-3">
                    <div class="small text-uppercase fw-semibold mb-2" style="color:var(--ink-3)">{{ __('ui.result.net_pay') }}</div>
                    <div class="fs-3 fw-bold" style="color:var(--s-succ)">{{ $madMonth($r['salaire_net']) }}</div>
                    <div class="text-muted small mt-2">{{ __('ui.result.net_pay_help') }}</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="section-card kpi-card kpi-card-employer h-100 py-3 px-3">
                    <div class="small text-uppercase fw-semibold mb-2" style="color:var(--ink-3)">{{ __('ui.result.total_employer_cost') }}</div>
                    <div class="fs-3 fw-bold" style="color:var(--r-500)">{{ $madMonth($r['cout_total_employeur']) }}</div>
                    <div class="text-muted small mt-2">{{ __('ui.result.total_employer_cost_help') }}</div>
                </div>
            </div>
        </div>

        <div class="section-card p-3 p-md-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
                <div>
                    <h3 class="h6 fw-bold mb-1">{{ __('ui.result.net_formula_title') }}</h3>
                    <p class="small mb-0" style="color:var(--ink-2)">{{ __('ui.result.net_formula_intro') }}</p>
                </div>
                <div class="small fw-semibold" style="color:var(--s-tax)">{{ __('ui.result.ir_bracket', ['rate' => round($r['tranche_ir']['taux'] * 100)]) }}</div>
            </div>

            <div class="result-formula">
                <div class="result-formula-item">
                    <div class="small text-uppercase fw-semibold mb-1" style="color:var(--ink-3)">{{ __('ui.result.taxable_gross_salary') }}</div>
                    <div class="fw-bold" style="color:var(--s-info)">{{ $madMonth($r['sbi']) }}</div>
                    <div class="small text-muted mt-1">{{ __('ui.result.monthly_taxable_base') }}</div>
                    <a href="{{ route('calculator.index') }}#step-remuneration" class="small d-inline-flex align-items-center gap-1 mt-1 no-print">
                        <i class="bi bi-pencil-square" aria-hidden="true"></i>{{ __('ui.result.formula_cta_sbi') }}
                    </a>
                </div>
                <div class="result-formula-item">
                    <span class="result-formula-symbol mb-2">−</span>
                    <div class="small text-uppercase fw-semibold mb-1" style="color:var(--ink-3)">{{ __('ui.result.employee_contributions') }}</div>
                    <div class="fw-bold" style="color:var(--s-cot)">{{ $madMonth($r['total_sociales']) }}</div>
                    <a href="{{ route('documentation') }}#cotisations" class="small d-inline-flex align-items-center gap-1 mt-1 no-print">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>{{ __('ui.result.formula_cta_cotisations') }}
                    </a>
                </div>
                <div class="result-formula-item">
                    <span class="result-formula-symbol mb-2">−</span>
                    <div class="small text-uppercase fw-semibold mb-1" style="color:var(--ink-3)">{{ __('ui.result.income_tax') }}</div>
                    <div class="fw-bold" style="color:var(--s-tax)">{{ $madMonth($r['ir_net']) }}</div>
                    <a href="{{ route('documentation') }}#impot" class="small d-inline-flex align-items-center gap-1 mt-1 no-print">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>{{ __('ui.result.formula_cta_ir') }}
                    </a>
                </div>
                <div class="result-formula-item">
                    <span class="result-formula-symbol mb-2">=</span>
                    <div class="small text-uppercase fw-semibold mb-1" style="color:var(--ink-3)">{{ __('ui.result.net_pay') }}</div>
                    <div class="fw-bold" style="color:var(--s-succ)">{{ $madMonth($r['salaire_net']) }}</div>
                </div>
                <div class="result-formula-item" style="background:var(--s-warn-bg)">
                    <div class="small text-uppercase fw-semibold mb-1" style="color:var(--ink-3)">{{ __('ui.result.employer_contributions') }}</div>
                    <div class="fw-bold" style="color:var(--s-warn)">{{ $signedMadMonth($r['total_patronal']) }}</div>
                    <div class="small text-muted mt-1">{{ __('ui.result.employer_formula_hint') }}</div>
                    <a href="{{ route('documentation') }}#charges-patronales" class="small d-inline-flex align-items-center gap-1 mt-1 no-print">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>{{ __('ui.result.formula_cta_employer') }}
                    </a>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4 align-items-start">

        {{-- Tableau détaillé --}}
        <section class="col-12 col-xl-7 order-2 order-xl-1" aria-labelledby="result-detail-title">
            <div class="eyebrow mb-1">{{ __('ui.result.detail_eyebrow') }}</div>
            <h2 class="h4 result-section-title mb-3" id="result-detail-title">{{ __('ui.result.detail_title') }}</h2>
            <details class="section-card overflow-hidden" id="calculationDetails">
                <summary class="px-4 py-3 fw-semibold" style="font-family:var(--f-display)">
                    <i class="bi bi-table me-2" style="color:var(--s-succ)" aria-hidden="true"></i>{{ __('ui.result.details') }}
                </summary>
            <div class="card section-card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 detail-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-3 py-2" style="width:45%">{{ __('ui.result.detail_col_item') }}</th>
                                    <th class="text-end px-3 py-2" style="width:25%">{{ __('ui.result.detail_col_base') }}</th>
                                    <th class="text-end px-3 py-2" style="width:15%">{{ __('ui.result.detail_col_rate') }}</th>
                                    <th class="text-end px-3 py-2" style="width:15%">{{ __('ui.result.detail_col_amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>

                                {{-- GAINS --}}
                                <tr class="row-brut">
                                    <td class="px-3 py-2">{{ __('ui.result.base_salary') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-info)">{{ number_format($r['input']['salaire_base'], 2, ',', ' ') }}</td>
                                </tr>

                                @if($r['prime_anciennete'] > 0)
                                <tr class="row-brut">
                                    <td class="px-3 py-2">
                                        {{ __('ui.result.seniority_bonus', ['years' => $r['nb_annees_anciennete']]) }}
                                        <span class="badge text-bg-light badge-legal ms-1">Art. 350 CT</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['input']['salaire_base'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ round($r['taux_anciennete'] * 100) }}%</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-info)">{{ number_format($r['prime_anciennete'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                @if($r['prime_bilan'] > 0)
                                <tr class="row-brut">
                                    <td class="px-3 py-2">{{ __('ui.result.year_bonus') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-info)">{{ number_format($r['prime_bilan'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                @if($r['prime_rendement'] > 0)
                                <tr class="row-brut">
                                    <td class="px-3 py-2">{{ __('ui.result.performance_bonus') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-info)">{{ number_format($r['prime_rendement'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                @if($r['autres_primes'] > 0)
                                <tr class="row-brut">
                                    <td class="px-3 py-2">{{ __('ui.result.other_taxable_bonuses') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-info)">{{ number_format($r['autres_primes'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                @foreach($r['detail_hs'] as $hs)
                                <tr class="row-brut">
                                    <td class="px-3 py-2">{{ __('ui.result.overtime_line', ['label' => $hs['label']]) }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ $hs['nb_heures'] }}h × {{ number_format($hs['taux_horaire'], 2, ',', '.') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">×{{ number_format(1 + $hs['majoration'], 2, ',', '.') }}</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-info)">{{ number_format($hs['montant'], 2, ',', ' ') }}</td>
                                </tr>
                                @endforeach

                                @if($r['excedent_indemnites'] > 0)
                                <tr class="row-brut">
                                    <td class="px-3 py-2">
                                        {{ __('ui.result.excess_allowances') }}
                                        <span class="badge text-bg-light badge-legal ms-1">Arrêté 1314-25</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-info)">{{ number_format($r['excedent_indemnites'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                @if($r['total_avantages_cnss_exoneres'] > 0)
                                <tr class="row-brut">
                                    <td class="px-3 py-2">{{ __('ui.result.cnss_exempt_line') }} <span class="badge text-bg-info text-white ms-1">{{ __('ui.result.cnss_exempt_badge') }}</span></td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-info)">{{ number_format($r['total_avantages_cnss_exoneres'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                <tr class="table-light">
                                    <td class="px-3 py-2 fw-bold" colspan="3">{{ __('ui.result.sbi_label') }}</td>
                                    <td class="text-end px-3 py-2 fw-bold" style="color:var(--s-info)">{{ number_format($r['sbi'], 2, ',', ' ') }}</td>
                                </tr>

                                {{-- COTISATIONS SALARIALES --}}
                                <tr class="row-cotis">
                                    <td class="px-3 py-2">
                                        {{ __('ui.result.cnss_employee') }}
                                        <span class="badge text-bg-light badge-legal ms-1">Dahir 1-72-184</span>
                                        @if($r['total_avantages_cnss_exoneres'] > 0)
                                        <br><small class="text-muted">{{ __('ui.result.cnss_base_note') }}</small>
                                        @endif
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['assiette_cnss'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format(config('payroll.cnss.taux') * 100, 2, ',', '.') }}%</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-cot)">− {{ number_format($r['cotisation_cnss'], 2, ',', ' ') }}</td>
                                </tr>

                                <tr class="row-cotis">
                                    <td class="px-3 py-2">
                                        {{ __('ui.result.amo_employee') }}
                                        <span class="badge text-bg-light badge-legal ms-1">Loi 65-00</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['assiette_sociale'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format(config('payroll.amo.taux') * 100, 2, ',', '.') }}%</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-cot)">− {{ number_format($r['cotisation_amo'], 2, ',', ' ') }}</td>
                                </tr>

                                @if($r['cotisation_cimr'] > 0)
                                <tr class="row-cotis">
                                    <td class="px-3 py-2">
                                        {{ __('ui.result.cimr_employee') }}
                                        <span class="badge text-bg-light badge-legal ms-1">Art. 28-III CGI</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['sbi'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ rtrim(rtrim(number_format($r['cimr_taux'] * 100, 2, ',', ' '), '0'), ',') }}%</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-cot)">− {{ number_format($r['cotisation_cimr'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                <tr class="table-light">
                                    <td class="px-3 py-2 fw-semibold" colspan="3">{{ __('ui.result.snc_label') }}</td>
                                    <td class="text-end px-3 py-2 fw-semibold">{{ number_format($r['snc'], 2, ',', ' ') }}</td>
                                </tr>

                                {{-- FRAIS PRO & RNI --}}
                                <tr class="row-impot">
                                    <td class="px-3 py-2">
                                        {{ __('ui.result.pro_fees') }}
                                        <br><small class="text-muted">{{ $r['desc_fp'] }}{{ $r['fp_plafonne'] ? ' — '.__('ui.result.pro_fees_capped') : '' }}</small>
                                        <span class="badge text-bg-light badge-legal ms-1">Art. 59 CGI</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['sbi'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ round($r['taux_fp'] * 100) }}%</td>
                                    <td class="text-end px-3 py-2 text-muted fst-italic">− {{ number_format($r['frais_pro'], 2, ',', ' ') }}</td>
                                </tr>

                                @if($r['rc_deduite'] > 0)
                                <tr class="row-impot">
                                    <td class="px-3 py-2">
                                        {{ __('ui.result.rc_ir_deduction') }}
                                        <br><small class="text-muted">{{ number_format($r['rc_annuel'], 0, ',', ' ') }} MAD/an{{ $r['rc_annuel'] > $r['rc_deduite'] ? ' — '.__('ui.result.rc_capped') : '' }}</small>
                                        <span class="badge text-bg-light badge-legal ms-1">Art. 28-IV CGI</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ __('ui.result.annual') }}</td>
                                    <td class="text-end px-3 py-2 text-muted fst-italic">− {{ number_format($r['rc_deduite'] / 12, 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                @if($r['retenues_exonerees_ir'] > 0)
                                <tr class="row-impot">
                                    <td class="px-3 py-2">{{ __('ui.result.retenues_exonerees_ir_line') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted fst-italic">− {{ number_format($r['retenues_exonerees_ir'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                <tr class="table-light">
                                    <td class="px-3 py-2 fw-semibold" colspan="3">{{ __('ui.result.rni_label', ['annual' => number_format($r['rni_annuel_net'], 2, ',', ' ')]) }}</td>
                                    <td class="text-end px-3 py-2 fw-semibold">{{ number_format($r['rni'], 2, ',', ' ') }}</td>
                                </tr>

                                {{-- IR --}}
                                <tr class="row-impot">
                                    <td class="px-3 py-2">
                                        {{ __('ui.result.ir_gross') }}
                                        <br><small class="text-muted">{{ round($r['tranche_ir']['taux'] * 100) }}% × {{ number_format($r['rni_annuel_net'], 0, ',', ' ') }} − {{ number_format($r['tranche_ir']['deduction'], 0, ',', ' ') }} = {{ number_format($r['ir_annuel_brut'], 2, ',', ' ') }} MAD/an</small>
                                        <span class="badge text-bg-light badge-legal ms-1">Art. 73 CGI</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['rni'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ round($r['tranche_ir']['taux'] * 100) }}%</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-tax)">{{ number_format($r['ir_mensuel_brut'], 2, ',', ' ') }}</td>
                                </tr>

                                @if($r['charges_famille'] > 0)
                                <tr class="row-impot">
                                    <td class="px-3 py-2">
                                        {{ __('ui.result.family_deduction') }}
                                        <br><small class="text-muted">{{ __('ui.result.family_deduction_detail', ['count' => $r['nb_personnes'], 'amount' => number_format(config('payroll.charges_famille.par_personne'), 0, ',', ' ')]) }}</small>
                                        <span class="badge text-bg-light badge-legal ms-1">Art. 74 CGI</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2" style="color:var(--s-succ)">+ {{ number_format($r['charges_famille'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                <tr class="row-impot">
                                    <td class="px-3 py-2 fw-semibold">{{ __('ui.result.ir_net_withheld') }}</td>
                                    <td class="text-end px-3 py-2 text-muted" colspan="2">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-tax)">− {{ number_format($r['ir_net'], 2, ',', ' ') }}</td>
                                </tr>

                                {{-- Indemnités --}}
                                @foreach($r['detail_indemnites'] as $ind)
                                <tr class="row-indem">
                                    <td class="px-3 py-2">
                                        {{ __('ui.result.allowance_line', ['label' => $ind['label']]) }}
                                        @if($ind['depasse'])
                                        <span class="badge text-bg-warning ms-1">{{ __('ui.result.capped_at', ['cap' => number_format($ind['plafond'], 0, ',', ' ')]) }}</span>
                                        <br><small class="text-muted">{{ __('ui.result.excess_reintegrated', ['amount' => number_format($ind['excedent'], 2, ',', ' ')]) }}</small>
                                        @endif
                                        <span class="badge text-bg-light badge-legal ms-1">Arrêté 1314-25</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">{{ __('ui.result.ceiling_label') }} {{ number_format($ind['plafond'], 0, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ __('ui.result.exempt') }}</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-succ)">+ {{ number_format($ind['retenu'], 2, ',', ' ') }}</td>
                                </tr>
                                @endforeach

                                {{-- Retenues diverses --}}
                                @if($r['mutuelle_salarie'] > 0)
                                <tr class="row-retenue">
                                    <td class="px-3 py-2">{{ __('ui.result.mutual_employee') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-tax)">− {{ number_format($r['mutuelle_salarie'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                @if($r['retenues_imposees_ir'] > 0)
                                <tr class="row-retenue">
                                    <td class="px-3 py-2">{{ __('ui.result.other_deductions_line') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-tax)">− {{ number_format($r['retenues_imposees_ir'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                {{-- NET --}}
                                <tr class="row-net">
                                    <td class="px-3 py-3" colspan="3">
                                        <i class="bi bi-check-circle-fill me-1" style="color:var(--s-succ)" aria-hidden="true"></i>{{ __('ui.result.net_to_pay') }}
                                    </td>
                                    <td class="text-end px-3 py-3 fs-5">{{ $madMonth($r['salaire_net']) }}</td>
                                </tr>

                                {{-- COTISATIONS PATRONALES --}}
                                <tr class="table-light">
                                    <td class="px-3 py-2 fw-semibold text-muted small" colspan="4">
                                        <i class="bi bi-building-up me-1" style="color:var(--s-warn)" aria-hidden="true"></i>{{ __('ui.result.employer_contributions_heading') }}
                                    </td>
                                </tr>

                                <tr class="row-patron">
                                    <td class="px-3 py-2">
                                        {{ __('ui.result.cnss_employer') }}
                                        <span class="badge text-bg-light badge-legal ms-1">Dahir 1-72-184</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['assiette_cnss'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format(config('payroll.cnss.taux_patronal') * 100, 2, ',', '.') }}%</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-warn)">{{ number_format($r['cout_cnss_patronal'], 2, ',', ' ') }}</td>
                                </tr>

                                <tr class="row-patron">
                                    <td class="px-3 py-2">
                                        {{ __('ui.result.amo_employer') }}
                                        <span class="badge text-bg-light badge-legal ms-1">Loi 65-00</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['sbi'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format(config('payroll.amo.taux_patronal') * 100, 2, ',', '.') }}%</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-warn)">{{ number_format($r['cout_amo_patronal'], 2, ',', ' ') }}</td>
                                </tr>

                                <tr class="row-patron">
                                    <td class="px-3 py-2">{{ __('ui.result.family_allowances') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['sbi'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format(config('payroll.allocations_familiales.taux_patronal') * 100, 2, ',', '.') }}%</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-warn)">{{ number_format($r['cout_af_patronal'], 2, ',', ' ') }}</td>
                                </tr>

                                <tr class="row-patron">
                                    <td class="px-3 py-2">{{ __('ui.result.tfp') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['sbi'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format(config('payroll.taxe_formation.taux_patronal') * 100, 2, ',', '.') }}%</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-warn)">{{ number_format($r['cout_tfp_patronal'], 2, ',', ' ') }}</td>
                                </tr>

                                @if($r['cotisation_cimr_patronale'] > 0 || ($r['cimr_taux_employeur_inconnu'] ?? false))
                                <tr class="row-patron">
                                    <td class="px-3 py-2">
                                        {{ __('ui.result.cimr_employer') }}
                                        <span class="badge text-bg-light badge-legal ms-1">Art. 28-III CGI</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['sbi'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ ($r['cimr_taux_employeur_inconnu'] ?? false) ? '—' : rtrim(rtrim(number_format($r['cimr_taux_employeur'] * 100, 2, ',', ' '), '0'), ',').'%' }}</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-warn)">{{ ($r['cimr_taux_employeur_inconnu'] ?? false) ? __('ui.result.not_provided') : number_format($r['cotisation_cimr_patronale'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                @if($r['mutuelle_patronale'] > 0 || ($r['mutuelle_patronale_inconnue'] ?? false))
                                <tr class="row-patron">
                                    <td class="px-3 py-2">{{ __('ui.result.mutual_employer') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-warn)">{{ ($r['mutuelle_patronale_inconnue'] ?? false) ? __('ui.result.not_provided') : number_format($r['mutuelle_patronale'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                @if($r['rc_part_employeur'] > 0 || ($r['rc_part_employeur_inconnu'] ?? false))
                                <tr class="row-patron">
                                    <td class="px-3 py-2">
                                        {{ __('ui.result.rc_employer') }}
                                        <span class="badge text-bg-light badge-legal ms-1">Art. 28-IV CGI</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-warn)">{{ ($r['rc_part_employeur_inconnu'] ?? false) ? __('ui.result.not_provided') : number_format($r['rc_part_employeur'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                @if(($r['assurance_at'] ?? 0) > 0 || ($r['assurance_at_inconnue'] ?? false))
                                <tr class="row-patron">
                                    <td class="px-3 py-2">{{ __('ui.result.assurance_at_line') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ ($r['assurance_at_inconnue'] ?? false) ? '—' : number_format($r['sbi'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ ($r['assurance_at_inconnue'] ?? false) ? '—' : rtrim(rtrim(number_format(($r['assurance_at_taux'] ?? 0) * 100, 2, ',', ' '), '0'), ',').'%' }}</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-warn)">{{ ($r['assurance_at_inconnue'] ?? false) ? __('ui.result.not_provided') : number_format($r['assurance_at'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                @if(($r['assurance_rc_pro'] ?? 0) > 0 || ($r['assurance_rc_pro_inconnue'] ?? false))
                                <tr class="row-patron">
                                    <td class="px-3 py-2">{{ __('ui.result.assurance_rc_pro_line') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold" style="color:var(--s-warn)">{{ ($r['assurance_rc_pro_inconnue'] ?? false) ? __('ui.result.not_provided') : number_format($r['assurance_rc_pro'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif
                                {{-- COÛT TOTAL EMPLOYEUR --}}
                                <tr class="row-employer">
                                    <td class="px-3 py-3" colspan="3">
                                        <i class="bi bi-building-up me-1" style="color:var(--s-warn)" aria-hidden="true"></i>{{ __('ui.result.total_employer_cost_label') }}
                                        <small class="text-muted fw-normal ms-2">{{ __('ui.result.total_employer_cost_sub') }}</small>
                                    </td>
                                    <td class="text-end px-3 py-3 fs-5" style="color:var(--r-500)">{{ $madMonth($r['cout_total_employeur']) }}</td>
                                </tr>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            </details>
        </section>

        {{-- Graphique + Récap --}}
        <section class="col-12 col-xl-5 order-1 order-xl-2" aria-labelledby="result-explanation-title">
            <div class="eyebrow mb-1">{{ __('ui.result.explanation_eyebrow') }}</div>
            <h2 class="h4 result-section-title mb-3" id="result-explanation-title">{{ __('ui.result.explanation_title') }}</h2>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card section-card mb-4 h-100">
                        <div class="card-body px-4 py-3">
                            <div class="result-step mb-3">
                                <div class="fw-semibold">{{ __('ui.result.step_gross_title') }}</div>
                                <div class="small text-muted">{{ __('ui.result.step_gross_text', ['amount' => $madMonth($r['sbi'])]) }}</div>
                            </div>
                            <div class="result-step mb-3">
                                <div class="fw-semibold">{{ __('ui.result.step_contributions_title') }}</div>
                                <div class="small text-muted">{{ __('ui.result.step_contributions_text', ['amount' => $madMonth($r['total_sociales'])]) }}</div>
                            </div>
                            <div class="result-step mb-3">
                                <div class="fw-semibold">{{ __('ui.result.step_tax_title') }}</div>
                                <div class="small text-muted">{{ __('ui.result.step_tax_text', ['amount' => $madMonth($r['ir_net'])]) }}</div>
                            </div>
                            <div class="result-step">
                                <div class="fw-semibold">{{ __('ui.result.step_employer_title') }}</div>
                                <div class="small text-muted mb-0">{{ __('ui.result.step_employer_text', ['amount' => $madMonth($r['cout_total_employeur'])]) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">

            {{-- Donut --}}
            <div class="card section-card mb-4">
                <div class="card-header px-4 py-3">
                    <i class="bi bi-pie-chart-fill me-2" style="color:var(--s-info)" aria-hidden="true"></i>{{ __('ui.result.chart_title') }}
                </div>
                <div class="card-body text-center py-3">
                    <p class="small text-muted mb-3" id="payrollChartHelp">{{ __('ui.result.chart_a11y_help') }}</p>
                    <canvas id="payrollChart" style="max-height:260px" aria-hidden="true"></canvas>
                    <div class="mt-3 no-print" id="chartLegend" aria-hidden="true"></div>
                    <div class="table-responsive mt-3" aria-describedby="payrollChartHelp">
                        <table class="table table-sm align-middle mb-0">
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
                                            <th scope="row" class="fw-semibold">{{ $chartLabels[$key] ?? __('ui.result.chart_deductions') }}</th>
                                            <td class="text-end">{{ $madMonth($part['montant']) }}</td>
                                            <td class="text-end">{{ number_format($part['pct'], 1, ',', ' ') }}%</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Récap IR --}}
            <div class="card section-card mb-4">
                <div class="card-header px-4 py-3">
                    <i class="bi bi-percent me-2" style="color:var(--s-tax)" aria-hidden="true"></i>{{ __('ui.result.ir_bracket_title') }}
                </div>
                <div class="card-body px-4 py-3">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted">{{ __('ui.result.rni_monthly') }}</td>
                                <td class="fw-semibold text-end">{{ $madMonth($r['rni']) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ __('ui.result.rni_annual') }}</td>
                                <td class="fw-semibold text-end">{{ $madYear($r['rni_annuel']) }}</td>
                            </tr>
                            @if($r['rc_deduite'] > 0)
                            <tr>
                                <td class="text-muted">{{ __('ui.result.minus_rc') }}</td>
                                <td class="fw-semibold text-end" style="color:var(--s-succ)">− {{ $madYear($r['rc_deduite']) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ __('ui.result.rni_taxable') }}</td>
                                <td class="fw-semibold text-end">{{ $madYear($r['rni_annuel_net']) }}</td>
                            </tr>
                            @endif
                            <tr class="table-danger">
                                <td>{{ __('ui.result.marginal_rate') }}</td>
                                <td class="fw-bold text-end" style="color:var(--s-tax)">{{ round($r['tranche_ir']['taux'] * 100) }}%</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ __('ui.result.fixed_deduction') }}</td>
                                <td class="fw-semibold text-end">{{ number_format($r['tranche_ir']['deduction'], 0, ',', ' ') }} {{ __('ui.result.unit_mad_year_label') }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">{{ __('ui.result.ir_annual_gross') }}</td>
                                <td class="fw-semibold text-end">{{ $madYear($r['ir_annuel_brut']) }}</td>
                            </tr>
                            @if($r['charges_famille'] > 0)
                            <tr>
                                <td class="text-muted">{{ __('ui.result.family_charges') }}</td>
                                <td class="fw-semibold text-end" style="color:var(--s-succ)">− {{ $madYear($r['charges_famille']) }}</td>
                            </tr>
                            @endif
                            <tr class="table-warning">
                                <td class="fw-bold">{{ __('ui.result.ir_monthly_net') }}</td>
                                <td class="fw-bold text-end">{{ $madMonth($r['ir_net']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Récap employeur --}}
            <div class="card section-card mb-4" style="border-left:3px solid var(--s-warn)">
                <div class="card-header px-4 py-3">
                    <i class="bi bi-building-up me-2" style="color:var(--s-warn)" aria-hidden="true"></i>{{ __('ui.result.employer_detail_title') }}
                </div>
                <div class="card-body px-4 py-3">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr><td class="text-muted">Salaire brut total versé</td><td class="fw-semibold text-end">{{ $madMonth($r['salaire_brut_total']) }}</td></tr>
                            <tr><td class="text-muted">CNSS patronal ({{ number_format(config('payroll.cnss.taux_patronal') * 100, 2, ',', '.') }}%)</td><td class="fw-semibold text-end" style="color:var(--s-warn)">+ {{ $madMonth($r['cout_cnss_patronal']) }}</td></tr>
                            <tr><td class="text-muted">AMO patronale ({{ number_format(config('payroll.amo.taux_patronal') * 100, 2, ',', '.') }}%)</td><td class="fw-semibold text-end" style="color:var(--s-warn)">+ {{ $madMonth($r['cout_amo_patronal']) }}</td></tr>
                            <tr><td class="text-muted">All. familiales ({{ number_format(config('payroll.allocations_familiales.taux_patronal') * 100, 2, ',', '.') }}%)</td><td class="fw-semibold text-end" style="color:var(--s-warn)">+ {{ $madMonth($r['cout_af_patronal']) }}</td></tr>
                            <tr><td class="text-muted">TFP ({{ number_format(config('payroll.taxe_formation.taux_patronal') * 100, 2, ',', '.') }}%)</td><td class="fw-semibold text-end" style="color:var(--s-warn)">+ {{ $madMonth($r['cout_tfp_patronal']) }}</td></tr>
                            @if($r['cotisation_cimr_patronale'] > 0 || ($r['cimr_taux_employeur_inconnu'] ?? false))
                            <tr><td class="text-muted">CIMR employeur</td><td class="fw-semibold text-end" style="color:var(--s-warn)">{{ ($r['cimr_taux_employeur_inconnu'] ?? false) ? __('ui.result.not_provided') : '+ '.$madMonth($r['cotisation_cimr_patronale']) }}</td></tr>
                            @endif
                            @if($r['mutuelle_patronale'] > 0 || ($r['mutuelle_patronale_inconnue'] ?? false))
                            <tr><td class="text-muted">Mutuelle employeur</td><td class="fw-semibold text-end" style="color:var(--s-warn)">{{ ($r['mutuelle_patronale_inconnue'] ?? false) ? __('ui.result.not_provided') : '+ '.$madMonth($r['mutuelle_patronale']) }}</td></tr>
                            @endif
                            @if($r['rc_part_employeur'] > 0 || ($r['rc_part_employeur_inconnu'] ?? false))
                            <tr><td class="text-muted">Retraite compl. employeur</td><td class="fw-semibold text-end" style="color:var(--s-warn)">{{ ($r['rc_part_employeur_inconnu'] ?? false) ? __('ui.result.not_provided') : '+ '.$madMonth($r['rc_part_employeur']) }}</td></tr>
                            @endif
                            @if(($r['assurance_at'] ?? 0) > 0 || ($r['assurance_at_inconnue'] ?? false))
                            <tr><td class="text-muted">{{ __('ui.result.assurance_at_line') }}</td><td class="fw-semibold text-end" style="color:var(--s-warn)">{{ ($r['assurance_at_inconnue'] ?? false) ? __('ui.result.not_provided') : '+ '.$madMonth($r['assurance_at']) }}</td></tr>
                            @endif
                            @if(($r['assurance_rc_pro'] ?? 0) > 0 || ($r['assurance_rc_pro_inconnue'] ?? false))
                            <tr><td class="text-muted">{{ __('ui.result.assurance_rc_pro_line') }}</td><td class="fw-semibold text-end" style="color:var(--s-warn)">{{ ($r['assurance_rc_pro_inconnue'] ?? false) ? __('ui.result.not_provided') : '+ '.$madMonth($r['assurance_rc_pro']) }}</td></tr>
                            @endif
                            <tr class="table-warning">
                                <td class="fw-bold">Coût total employeur</td>
                                <td class="fw-bold text-end" style="color:var(--r-500)">{{ $madMonth($r['cout_total_employeur']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

                </div>
            </div>

        </section>

    </div>

    {{-- Next actions CTA section --}}
    <section class="mt-5 no-print" aria-labelledby="next-actions-title">
        <h2 id="next-actions-title" class="h5 fw-bold mb-3">{{ __('ui.result.next_actions_title') }}</h2>
        <div class="row g-3">
            <div class="col-sm-6 col-lg-3">
                <a href="{{ route('calculator.index') }}" class="section-card d-flex align-items-center gap-3 p-3 text-decoration-none" style="color:var(--ink)">
                    <i class="bi bi-arrow-repeat fs-4 flex-shrink-0" style="color:var(--g-500)" aria-hidden="true"></i>
                    <span class="small fw-semibold">{{ __('ui.result.action_simulate_again') }}</span>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="{{ route('documentation') }}" class="section-card d-flex align-items-center gap-3 p-3 text-decoration-none" style="color:var(--ink)">
                    <i class="bi bi-journal-text fs-4 flex-shrink-0" style="color:var(--s-info)" aria-hidden="true"></i>
                    <span class="small fw-semibold">{{ __('ui.result.action_see_rules') }}</span>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="{{ route('trust') }}" class="section-card d-flex align-items-center gap-3 p-3 text-decoration-none" style="color:var(--ink)">
                    <i class="bi bi-shield-check fs-4 flex-shrink-0" style="color:var(--s-succ)" aria-hidden="true"></i>
                    <span class="small fw-semibold">{{ __('ui.result.action_trust') }}</span>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="{{ route('api.documentation') }}" class="section-card d-flex align-items-center gap-3 p-3 text-decoration-none" style="color:var(--ink)">
                    <i class="bi bi-braces fs-4 flex-shrink-0" style="color:var(--s-cot)" aria-hidden="true"></i>
                    <span class="small fw-semibold">{{ __('ui.result.action_api') }}</span>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <button type="button" onclick="window.print()" class="section-card d-flex align-items-center gap-3 p-3 w-100 border-0 text-start" style="color:var(--ink);background:var(--paper);cursor:pointer">
                    <i class="bi bi-printer fs-4 flex-shrink-0" style="color:var(--ink-3)" aria-hidden="true"></i>
                    <span class="small fw-semibold">{{ __('ui.result.action_print') }}</span>
                </button>
            </div>
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

const ctx = document.getElementById('payrollChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: chartLabels,
        datasets: [{ data: data, backgroundColor: colors, borderWidth: 2, borderColor: getComputedStyle(document.documentElement).getPropertyValue('--paper').trim() || '#fff' }]
    },
    options: {
        cutout: '65%',
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

const legend = document.getElementById('chartLegend');
legend.innerHTML = activeKeys.map((k, i) => {
    const pct = repartition[k].pct;
    const amt = repartition[k].montant.toLocaleString(intlLocale, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return `<div class="d-flex align-items-center justify-content-between mb-1 small">
        <span><span style="display:inline-block;width:12px;height:12px;background:${colors[i]};border-radius:2px;margin-right:6px"></span>${chartLabels[i]}</span>
        <span class="text-muted">${amt} {{ __('ui.result.unit_mad_month_label') }} <strong>(${pct}%)</strong></span>
    </div>`;
}).join('');
</script>
@endpush
