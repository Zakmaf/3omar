@extends('layouts.app')

@section('title', '3omar · '.__('ui.documentation.title'))

@section('content')
<div class="container">

    <div class="row mb-4">
        <div class="col">
            <div class="eyebrow mb-2">{{ __('ui.documentation.eyebrow') }}</div>
            <h1 class="h2 fw-bold mb-1"><i class="bi bi-journal-text me-2" style="color:var(--g-500)" aria-hidden="true"></i>{{ __('ui.documentation.title') }}</h1>
            <p style="color:var(--ink-2)">
                {{ __('ui.documentation.intro') }}
                <span class="badge rounded-pill ms-1 px-2 py-1" style="background:var(--g-50);color:var(--g-700);border:1px solid var(--g-200)">{{ __('ui.documentation.badge') }}</span>
            </p>
            <div class="alert alert-warning small mb-0">
                {{ __('ui.documentation.warning') }}
            </div>
            <nav class="d-flex flex-wrap gap-2 mt-3" aria-label="{{ __('ui.documentation.quick_access') }}">
                <a class="btn btn-sm" href="#cotisations" style="border:1px solid var(--hairline-strong)">{{ __('ui.documentation.nav_contributions') }}</a>
                <a class="btn btn-sm" href="#impot" style="border:1px solid var(--hairline-strong)">{{ __('ui.documentation.nav_tax') }}</a>
                <a class="btn btn-sm" href="#remuneration" style="border:1px solid var(--hairline-strong)">{{ __('ui.documentation.nav_remuneration') }}</a>
                <a class="btn btn-sm" href="#indemnites" style="border:1px solid var(--hairline-strong)">{{ __('ui.documentation.nav_allowances') }}</a>
            </nav>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">

            {{-- ============================================================ --}}
            {{-- CNSS                                                          --}}
            {{-- ============================================================ --}}
            <div class="card section-card mb-4" id="cotisations">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-info)"><i class="bi bi-building" aria-hidden="true"></i></span>
                    <span>{{ __('ui.documentation.cnss_title') }}</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">{{ __('ui.documentation.reference') }} <code>{{ __('ui.documentation.cnss_ref') }}</code></p>
                    <table class="table table-sm">
                        <thead class="table-light"><tr><th>{{ __('ui.documentation.col_parameter') }}</th><th>{{ __('ui.documentation.col_employee') }}</th><th>{{ __('ui.documentation.col_employer') }}</th><th class="text-muted">{{ __('ui.documentation.col_note') }}</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>{{ __('ui.documentation.global_rate') }}</td>
                                <td class="fw-bold">{{ number_format($payroll['cnss']['taux'] * 100, 2, ',', '.') }}%</td>
                                <td class="fw-bold">{{ number_format($payroll['cnss']['taux_patronal'] * 100, 2, ',', '.') }}%</td>
                                <td class="text-muted">{{ __('ui.documentation.ct_lt') }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('ui.documentation.monthly_ceiling') }}</td>
                                <td class="fw-bold" colspan="2">{{ number_format($payroll['cnss']['plafond'], 0, ',', ' ') }} MAD</td>
                                <td class="text-muted">{{ __('ui.documentation.capped_base') }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('ui.documentation.max_employee_month') }}</td>
                                <td class="fw-bold">{{ number_format($payroll['cnss']['plafond'] * $payroll['cnss']['taux'], 2, ',', ' ') }} MAD</td>
                                <td class="text-muted">—</td>
                                <td class="text-muted">{{ number_format($payroll['cnss']['plafond'], 0, ',', ' ') }} × {{ number_format($payroll['cnss']['taux'] * 100, 2, ',', '.') }}%</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="alert alert-info py-2 small mb-0">
                        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                        {{ __('ui.documentation.cnss_ceiling_note', ['ceiling' => number_format($payroll['cnss']['plafond'], 0, ',', ' '), 'max' => number_format($payroll['cnss']['plafond'] * $payroll['cnss']['taux'], 2, ',', ' ')]) }}
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- AMO                                                           --}}
            {{-- ============================================================ --}}
            <div class="card section-card mb-4">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-cot)"><i class="bi bi-heart-pulse" aria-hidden="true"></i></span>
                    <span>{{ __('ui.documentation.amo_title') }}</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">{{ __('ui.documentation.reference') }} <code>{{ __('ui.documentation.amo_ref') }}</code></p>
                    <table class="table table-sm">
                        <thead class="table-light"><tr><th>{{ __('ui.documentation.col_parameter') }}</th><th>{{ __('ui.documentation.col_employee') }}</th><th>{{ __('ui.documentation.col_employer') }}</th><th class="text-muted">{{ __('ui.documentation.col_note') }}</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>{{ __('ui.documentation.rate') }}</td>
                                <td class="fw-bold">{{ number_format($payroll['amo']['taux'] * 100, 2, ',', '.') }}%</td>
                                <td class="fw-bold">{{ number_format($payroll['amo']['taux_patronal'] * 100, 2, ',', '.') }}%</td>
                                <td class="text-muted">{{ __('ui.documentation.no_ceiling') }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('ui.documentation.calculation_base') }}</td>
                                <td class="fw-bold" colspan="2">{{ __('ui.documentation.sbi_total') }}</td>
                                <td class="text-muted">{{ __('ui.documentation.full_sbi') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- CHARGES PATRONALES SUPPLÉMENTAIRES                            --}}
            {{-- ============================================================ --}}
            <div class="card section-card mb-4">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-warn)"><i class="bi bi-building-up" aria-hidden="true"></i></span>
                    <span>{{ __('ui.documentation.employer_charges_title') }}</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">{{ __('ui.documentation.employer_charges_intro') }}</p>
                    <table class="table table-sm">
                        <thead class="table-light"><tr><th>{{ __('ui.documentation.col_charge') }}</th><th class="text-center">{{ __('ui.documentation.col_rate') }}</th><th>{{ __('ui.documentation.col_ceiling') }}</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>{{ __('ui.documentation.family_allowances_doc') }}</td>
                                <td class="text-center fw-bold">{{ number_format($payroll['allocations_familiales']['taux_patronal'] * 100, 2, ',', '.') }}%</td>
                                <td class="text-muted">{{ __('ui.documentation.no_ceiling') }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('ui.documentation.tfp_doc') }}</td>
                                <td class="text-center fw-bold">{{ number_format($payroll['taxe_formation']['taux_patronal'] * 100, 2, ',', '.') }}%</td>
                                <td class="text-muted">{{ __('ui.documentation.no_ceiling') }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="alert alert-secondary py-2 small mb-0">
                        <strong>{{ __('ui.documentation.total_employer_formula') }}</strong> {{ __('ui.documentation.total_employer_formula_detail', ['cnss' => number_format($payroll['cnss']['taux_patronal'] * 100, 2, ',', '.'), 'amo' => number_format($payroll['amo']['taux_patronal'] * 100, 2, ',', '.'), 'af' => number_format($payroll['allocations_familiales']['taux_patronal'] * 100, 2, ',', '.'), 'tfp' => number_format($payroll['taxe_formation']['taux_patronal'] * 100, 2, ',', '.')]) }}
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- CIMR                                                          --}}
            {{-- ============================================================ --}}
            <div class="card section-card mb-4">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-succ)"><i class="bi bi-piggy-bank" aria-hidden="true"></i></span>
                    <span>{{ __('ui.documentation.cimr_title') }}</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">{{ __('ui.documentation.reference') }} <code>{{ __('ui.documentation.cimr_ref') }}</code></p>
                    <table class="table table-sm">
                        <tbody>
                            <tr><td>{{ __('ui.documentation.employee_rate_range') }}</td><td class="fw-bold">{{ __('ui.documentation.rate_range', ['min' => round($payroll['cimr']['taux_min'] * 100), 'max' => round($payroll['cimr']['taux_max'] * 100)]) }}</td><td class="text-muted">{{ __('ui.documentation.freely_chosen') }}</td></tr>
                            <tr><td>{{ __('ui.documentation.ceiling_base') }}</td><td class="fw-bold">{{ __('ui.documentation.none') }}</td><td class="text-muted">{{ __('ui.documentation.sbi_total') }}</td></tr>
                            <tr><td>{{ __('ui.documentation.ir_deductibility') }}</td><td class="fw-bold" style="color:var(--s-succ)">100%</td><td class="text-muted">{{ __('ui.documentation.fully_deducted') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- IR BAREME                                                     --}}
            {{-- ============================================================ --}}
            <div class="card section-card mb-4" id="impot">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-tax)"><i class="bi bi-percent" aria-hidden="true"></i></span>
                    <span>{{ __('ui.documentation.ir_schedule_title', ['year' => 2026]) }}</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">
                        {{ __('ui.documentation.ir_formula') }} <code>{{ __('ui.documentation.ir_formula_detail') }}</code><br>
                        <small>{{ __('ui.documentation.ir_formula_note', ['months' => $payroll['ir']['nb_mois']]) }}</small>
                    </p>
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('ui.documentation.col_rni_bracket') }}</th>
                                <th class="text-center">{{ __('ui.documentation.col_marginal_rate') }}</th>
                                <th class="text-end">{{ __('ui.documentation.col_fixed_deduction') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($baremes as $t)
                            <tr style="{{ $t['taux'] == 0 ? 'background:var(--s-succ-bg)' : ($t['taux'] >= 0.34 ? 'background:var(--s-tax-bg)' : '') }}">
                                <td>
                                    {{ number_format($t['min'], 0, ',', ' ') }}
                                    @if($t['max'] !== null)
                                        — {{ number_format($t['max'], 0, ',', ' ') }}
                                    @else
                                        {{ __('ui.documentation.and_above') }}
                                    @endif
                                </td>
                                <td class="text-center fw-bold">{{ round($t['taux'] * 100) }}%</td>
                                <td class="text-end">{{ number_format($t['deduction'], 0, ',', ' ') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="alert alert-secondary py-2 small mb-0">
                        {{ __('ui.documentation.ir_config_note') }}
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- FRAIS PRO                                                     --}}
            {{-- ============================================================ --}}
            <div class="card section-card mb-4">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-warn)"><i class="bi bi-briefcase" aria-hidden="true"></i></span>
                    <span>{{ __('ui.documentation.pro_fees_title') }}</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">{!! __('ui.documentation.pro_fees_intro', ['threshold' => number_format($payroll['frais_pro']['seuil_mensuel'], 0, ',', ' ')]) !!}</p>
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr><th>{{ __('ui.documentation.col_category') }}</th><th class="text-center">{{ __('ui.documentation.col_rate') }}</th><th class="text-end">{{ __('ui.documentation.col_monthly_ceiling') }}</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ __('ui.documentation.common_low', ['threshold' => number_format($payroll['frais_pro']['seuil_mensuel'], 0, ',', ' ')]) }}</td>
                                <td class="text-center fw-bold">{{ round($payroll['frais_pro']['commun']['bas']['taux'] * 100) }}%</td>
                                <td class="text-end">{{ number_format($payroll['frais_pro']['commun']['bas']['plafond'], 2, ',', ' ') }} MAD</td>
                            </tr>
                            <tr>
                                <td>{{ __('ui.documentation.common_high', ['threshold' => number_format($payroll['frais_pro']['seuil_mensuel'], 0, ',', ' ')]) }}</td>
                                <td class="text-center fw-bold">{{ round($payroll['frais_pro']['commun']['haut']['taux'] * 100) }}%</td>
                                <td class="text-end">{{ number_format($payroll['frais_pro']['commun']['haut']['plafond'], 2, ',', ' ') }} MAD</td>
                            </tr>
                            <tr>
                                <td>{{ __('ui.documentation.journalist') }}</td>
                                <td class="text-center fw-bold">{{ round($payroll['frais_pro']['journaliste']['taux'] * 100) }}%</td>
                                <td class="text-end">{{ number_format($payroll['frais_pro']['journaliste']['plafond'], 2, ',', ' ') }} MAD</td>
                            </tr>
                            <tr>
                                <td>{{ __('ui.documentation.artist') }}</td>
                                <td class="text-center fw-bold">{{ round($payroll['frais_pro']['artiste']['taux'] * 100) }}%</td>
                                <td class="text-end">{{ number_format($payroll['frais_pro']['artiste']['plafond'], 2, ',', ' ') }} MAD</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- ANCIENNETÉ                                                    --}}
            {{-- ============================================================ --}}
            <div class="card section-card mb-4" id="remuneration">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-info)"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span>
                    <span>{{ __('ui.documentation.seniority_title') }}</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">{{ __('ui.documentation.reference') }} <code>{{ __('ui.documentation.seniority_ref') }}</code>. {{ __('ui.documentation.seniority_intro') }}</p>
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr><th>{{ __('ui.documentation.col_seniority') }}</th><th class="text-center">{{ __('ui.documentation.col_rate') }}</th><th>{{ __('ui.documentation.col_example') }}</th></tr>
                        </thead>
                        <tbody>
                            <tr class="table-light">
                                <td>{{ __('ui.documentation.less_than_2_years') }}</td>
                                <td class="text-center fw-bold">0%</td>
                                <td class="text-muted">0,00 MAD</td>
                            </tr>
                            @foreach($payroll['anciennete']['tranches'] as $t)
                            <tr>
                                <td>
                                    @if($t['max_annees'] !== null)
                                        {{ __('ui.documentation.years_to', ['min' => $t['min_annees'], 'max' => $t['max_annees']]) }}
                                    @else
                                        {{ __('ui.documentation.years_and_above', ['min' => $t['min_annees']]) }}
                                    @endif
                                </td>
                                <td class="text-center fw-bold" style="color:var(--s-info)">{{ round($t['taux'] * 100) }}%</td>
                                <td class="text-muted">{{ number_format(5000 * $t['taux'], 2, ',', ' ') }} MAD</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- RETRAITE COMPLÉMENTAIRE                                       --}}
            {{-- ============================================================ --}}
            <div class="card section-card mb-4">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-info)"><i class="bi bi-bank" aria-hidden="true"></i></span>
                    <span>{{ __('ui.documentation.rc_title') }} · {{ $payroll['retraite_complementaire']['article'] }}</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">{{ __('ui.documentation.reference') }} <code>{{ $payroll['retraite_complementaire']['article'] }}</code></p>
                    <table class="table table-sm">
                        <tbody>
                            <tr>
                                <td>{{ __('ui.documentation.rc_deductibility') }}</td>
                                <td class="fw-bold" style="color:var(--s-succ)">{{ __('ui.documentation.rc_deductibility_value', ['pct' => round($payroll['retraite_complementaire']['deduction_ir_max_pct'] * 100)]) }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('ui.documentation.rc_ceiling_base') }}</td>
                                <td class="fw-bold">{{ __('ui.documentation.rc_ceiling_formula', ['months' => $payroll['ir']['nb_mois']]) }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('ui.documentation.rc_example') }}</td>
                                <td class="text-muted">
                                    Plafond = 8 000 × {{ $payroll['ir']['nb_mois'] }} × {{ round($payroll['retraite_complementaire']['deduction_ir_max_pct'] * 100) }}%
                                    = {{ number_format(8000 * $payroll['ir']['nb_mois'] * $payroll['retraite_complementaire']['deduction_ir_max_pct'], 0, ',', ' ') }} MAD/an
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="alert alert-info py-2 small mb-0">
                        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                        {{ __('ui.documentation.rc_note', ['pct' => round($payroll['retraite_complementaire']['deduction_ir_max_pct'] * 100)]) }}
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- INDEMNITÉS                                                    --}}
            {{-- ============================================================ --}}
            <div class="card section-card mb-4" id="indemnites">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-succ)"><i class="bi bi-gift" aria-hidden="true"></i></span>
                    <span>{{ __('ui.documentation.allowances_title') }}</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">{{ __('ui.documentation.allowances_intro') }}</p>
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr><th>{{ __('ui.documentation.col_allowance_type') }}</th><th class="text-end">{{ __('ui.documentation.col_legal_ceiling') }}</th></tr>
                        </thead>
                        <tbody>
                            @foreach($indemnites_config as $key => $cfg)
                            <tr>
                                <td>{{ $cfg['label'] }}</td>
                                <td class="text-end fw-semibold">
                                    @if($cfg['base_salaire'])
                                        {{ __('ui.documentation.pct_of_base', ['pct' => round($cfg['pct'] * 100)]) }}
                                    @elseif(!empty($cfg['par_jour']))
                                        {{ __('ui.documentation.per_working_day', ['amount' => number_format($cfg['montant'], 2, ',', ' ')]) }}
                                    @else
                                        {{ __('ui.documentation.per_month', ['amount' => number_format($cfg['montant'], 0, ',', ' ')]) }}
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- SMIG & HEURES SUP                                             --}}
            {{-- ============================================================ --}}
            <div class="card section-card mb-4">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-info)"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
                    <span>{{ __('ui.documentation.smig_title', ['year' => 2026]) }}</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">{{ __('ui.documentation.reference') }} <code>{{ __('ui.documentation.smig_ref') }}</code> · <code>{{ __('ui.documentation.overtime_ref') }}</code></p>
                    <table class="table table-sm mb-4">
                        <tbody>
                            <tr><td>{{ __('ui.documentation.smig_hourly', ['year' => 2026]) }}</td><td class="fw-bold">{{ number_format($payroll['smig']['horaire'], 2, ',', ' ') }} MAD/h</td></tr>
                            <tr><td>{{ __('ui.documentation.smig_monthly', ['hours' => $payroll['smig']['heures_legales']]) }}</td><td class="fw-bold">{{ number_format($payroll['smig']['mensuel'], 2, ',', ' ') }} MAD/mois</td></tr>
                            <tr><td>{{ __('ui.documentation.legal_hours') }}</td><td class="fw-bold">{{ $payroll['smig']['heures_legales'] }} {{ __('ui.documentation.hours_unit') }}</td></tr>
                        </tbody>
                    </table>
                    <h6 class="fw-semibold">{{ __('ui.documentation.overtime_rates_title') }}</h6>
                    <table class="table table-sm">
                        <thead class="table-light"><tr><th>{{ __('ui.documentation.col_type') }}</th><th class="text-center">{{ __('ui.documentation.col_surcharge') }}</th></tr></thead>
                        <tbody>
                            @foreach($payroll['heures_sup']['majorations'] as $type => $taux)
                            <tr>
                                <td>{{ $payroll['heures_sup']['labels'][$type] ?? $type }}</td>
                                <td class="text-center fw-bold" style="color:{{ $taux >= 1.0 ? 'var(--s-tax)' : ($taux >= 0.5 ? 'var(--s-warn)' : 'var(--s-info)') }}">
                                    +{{ round($taux * 100) }}%
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="form-text">{{ __('ui.documentation.hourly_rate_formula', ['hours' => $payroll['smig']['heures_legales']]) }}</div>
                </div>
            </div>

        </div>

        {{-- ============================================================ --}}
        {{-- SIDEBAR                                                       --}}
        {{-- ============================================================ --}}
        <div class="col-lg-4">

            <div class="card section-card mb-4">
                <div class="card-header px-4 py-3">
                    <i class="bi bi-link-45deg me-2" style="color:var(--g-500)" aria-hidden="true"></i>{{ __('ui.documentation.sidebar_references') }}
                </div>
                <div class="card-body px-4 py-3">
                    <ul class="list-unstyled mb-0">
                        @foreach([
                            ['titre' => __('ui.documentation.ref_cgi', ['year' => 2026]), 'desc' => __('ui.documentation.ref_cgi_articles')],
                            ['titre' => __('ui.documentation.ref_labor'),                 'desc' => __('ui.documentation.ref_labor_articles')],
                            ['titre' => __('ui.documentation.ref_dahir'),                 'desc' => __('ui.documentation.ref_dahir_desc')],
                            ['titre' => __('ui.documentation.ref_amo'),                   'desc' => __('ui.documentation.ref_amo_desc')],
                            ['titre' => __('ui.documentation.ref_arrete'),                'desc' => __('ui.documentation.ref_arrete_desc')],
                            ['titre' => __('ui.documentation.ref_smig'),                  'desc' => __('ui.documentation.ref_smig_desc')],
                            ['titre' => __('ui.documentation.ref_ldf'),                   'desc' => __('ui.documentation.ref_ldf_desc')],
                        ] as $ref)
                        <li class="mb-3 pb-3 border-bottom">
                            <div class="fw-semibold small">{{ $ref['titre'] }}</div>
                            <div class="text-muted small">{{ $ref['desc'] }}</div>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
