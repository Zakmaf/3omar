@extends('layouts.app')

@section('title', '3omar · '.__('ui.calculator.title'))

@push('head')
<style>
    .simulator-flow {
        counter-reset: step;
    }
    .step-section {
        overflow: hidden;
        counter-increment: step;
    }
    .step-section > summary .step-label::before {
        content: counter(step) ". ";
    }
    .step-section > summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.25rem;
        cursor: pointer;
        list-style: none;
        border-bottom: 1px solid var(--hairline);
        font-family: var(--f-display);
        font-weight: 700;
    }
    .step-section > summary::-webkit-details-marker {
        display: none;
    }
    .step-section > summary::after {
        content: "+";
        color: var(--g-600);
        font-family: var(--f-mono);
        font-size: 1.15rem;
    }
    .step-section[open] > summary::after {
        content: "\2212";
    }
    .step-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: .2rem .55rem;
        background: var(--g-50);
        color: var(--g-700);
        font-family: var(--f-mono);
        font-size: .68rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .quick-submit-panel {
        display: grid;
        gap: 1rem;
        grid-template-columns: 1fr auto;
        align-items: center;
    }
    @media (max-width: 767.98px) {
        .quick-submit-panel {
            grid-template-columns: 1fr;
        }
        .quick-submit-panel .btn {
            width: 100%;
        }
    }

    /* ---- Wizard sidebar (desktop) ---- */
    .wizard-sidebar {
        position: sticky;
        top: 80px;
        max-height: calc(100vh - 100px);
        overflow-y: auto;
        scrollbar-width: thin;
    }
    .wizard-sidebar::-webkit-scrollbar {
        width: 4px;
    }
    .wizard-sidebar::-webkit-scrollbar-thumb {
        background: var(--g-300);
        border-radius: 4px;
    }
    .wizard-mode-badge {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .5rem .75rem;
        border-radius: 8px;
        background: var(--g-50);
        border: 1px solid var(--g-200);
        font-size: .8rem;
        font-weight: 600;
        color: var(--g-700);
    }
    .wizard-mode-badge i {
        font-size: 1rem;
        color: var(--g-500);
    }
    .wizard-step-list {
        list-style: none;
        padding: 0;
        margin: 0;
        counter-reset: wizard-step;
    }
    .wizard-step {
        counter-increment: wizard-step;
    }
    .wizard-step-btn {
        display: flex;
        align-items: flex-start;
        gap: .65rem;
        width: 100%;
        padding: .55rem .6rem;
        border: none;
        background: transparent;
        border-radius: 8px;
        cursor: pointer;
        text-align: start;
        transition: background .15s;
        font-family: var(--f-body);
    }
    .wizard-step-btn:hover {
        background: var(--g-50);
    }
    .wizard-step-number {
        flex-shrink: 0;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .75rem;
        font-weight: 700;
        background: var(--g-100);
        color: var(--g-600);
        transition: background .2s, color .2s;
        font-family: var(--f-mono);
    }
    .wizard-step-number::before {
        content: counter(wizard-step);
    }
    .wizard-step.active .wizard-step-number {
        background: var(--g-500);
        color: #fff;
    }
    .wizard-step.completed .wizard-step-number {
        background: var(--s-succ);
        color: #fff;
    }
    .wizard-step.completed .wizard-step-number::before {
        content: "\2713";
    }
    .wizard-step-label {
        font-size: .8rem;
        font-weight: 600;
        color: var(--ink-1);
        line-height: 1.3;
    }
    .wizard-step-summary {
        font-size: .7rem;
        color: var(--g-500);
        line-height: 1.3;
        margin-top: .1rem;
        font-weight: 400;
    }
    .wizard-step.active .wizard-step-label {
        color: var(--g-600);
    }

    /* ---- Mobile progress bar ---- */
    .wizard-progress-mobile {
        display: flex;
        gap: .4rem;
        overflow-x: auto;
        padding: .5rem 0;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .wizard-progress-mobile::-webkit-scrollbar {
        display: none;
    }
    .wizard-progress-pill {
        flex-shrink: 0;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: 2px solid var(--g-200);
        background: transparent;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .72rem;
        font-weight: 700;
        color: var(--g-500);
        cursor: pointer;
        transition: border-color .2s, background .2s, color .2s;
        font-family: var(--f-mono);
        padding: 0;
    }
    .wizard-progress-pill.active {
        border-color: var(--g-500);
        background: var(--g-500);
        color: #fff;
    }
    .wizard-progress-pill.completed {
        border-color: var(--s-succ);
        background: var(--s-succ);
        color: #fff;
    }

    /* ---- Responsive visibility ---- */
    .wizard-sidebar-wrap {
        display: none;
    }
    .wizard-mobile-wrap {
        display: block;
    }
    @media (min-width: 992px) {
        .wizard-sidebar-wrap {
            display: block;
        }
        .wizard-mobile-wrap {
            display: none;
        }
    }

    /* ---- RTL support ---- */
    [dir="rtl"] .wizard-step-btn {
        text-align: end;
    }
</style>
@endpush

@section('content')
<div class="container">
    @php
        $mode = old('mode', 'gross_to_net');

        $wizardSteps = [
            ['key' => 'step1_title', 'icon' => 'bi-cash-coin', 'color' => 'var(--s-info)', 'required' => true],
            ['key' => 'step2_title', 'icon' => 'bi-cash-stack', 'color' => 'var(--s-warn)'],
            ['key' => 'step3_title', 'icon' => 'bi-clock-history', 'color' => 'var(--s-info)'],
            ['key' => 'step4_title', 'icon' => 'bi-piggy-bank', 'color' => 'var(--s-cot)'],
            ['key' => 'step5_title', 'icon' => 'bi-people-fill', 'color' => 'var(--s-succ)'],
            ['key' => 'step6_title', 'icon' => 'bi-heart-pulse-fill', 'color' => 'var(--s-tax)'],
            ['key' => 'step7_title', 'icon' => 'bi-gift', 'color' => 'var(--s-succ)'],
            ['key' => 'step8_title', 'icon' => 'bi-mortarboard', 'color' => 'var(--s-cot)'],
            ['key' => 'step9_title', 'icon' => 'bi-dash-circle', 'color' => 'var(--s-neutral)'],
        ];
    @endphp

    <div class="row mb-4">
        <div class="col">
            <div class="eyebrow mb-2">{{ __('ui.calculator.eyebrow') }}</div>
            <h1 class="h2 fw-bold mb-2"><i class="bi bi-calculator me-2" style="color:var(--g-500)"></i>{{ __('ui.calculator.title') }}</h1>
            <p class="page-intro mb-0" style="color:var(--ink-2)">{{ __('ui.calculator.intro') }}</p>
        </div>
    </div>

    @if ($errors->any())
    <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert" tabindex="-1" id="formErrors">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>{{ __('ui.calculator.errors') }}</strong>
        <ul class="mb-0 mt-1">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if (session('calculator_notice'))
    <div class="alert alert-info border-0 shadow-sm mb-4" role="status">
        <i class="bi bi-info-circle-fill me-2"></i>{{ __(session('calculator_notice')) }}
    </div>
    @endif

    <form method="POST" action="{{ route('calculator.calculer') }}" id="payrollForm">
        @csrf

        <div class="section-card p-3 p-md-4 mb-4 d-flex flex-column gap-3">
            <div class="quick-submit-panel">
                <div>
                    <div class="fw-semibold">{{ __('ui.calculator.journey_title') }}</div>
                    <div class="small" style="color:var(--ink-2)">{{ __('ui.calculator.journey_text') }}</div>
                    <div class="small mt-2" style="color:var(--ink-3)">{{ __('ui.calculator.submit_hint') }}</div>
                </div>
                <button type="submit" class="btn btn-lg text-white fw-bold px-4" style="background:var(--g-500); font-family:var(--f-body)">
                    <i class="bi bi-calculator-fill me-2"></i>{{ __('ui.calculator.submit') }}
                </button>
            </div>
            <div class="btn-group" role="group" aria-label="{{ __('ui.calculator.mode_label') }}">
                <input type="radio" class="btn-check" name="mode" id="modeGrossToNet" value="gross_to_net" autocomplete="off" {{ $mode === 'gross_to_net' ? 'checked' : '' }}>
                <label class="btn fw-semibold" for="modeGrossToNet" style="border:1px solid var(--g-500);color:var(--g-600)">
                    <i class="bi bi-arrow-down-circle me-1"></i>{{ __('ui.calculator.mode_gross_to_net') }}
                </label>

                <input type="radio" class="btn-check" name="mode" id="modeNetToGross" value="net_to_gross" autocomplete="off" {{ $mode === 'net_to_gross' ? 'checked' : '' }}>
                <label class="btn fw-semibold" for="modeNetToGross" style="border:1px solid var(--g-500);color:var(--g-600)">
                    <i class="bi bi-arrow-up-circle me-1"></i>{{ __('ui.calculator.mode_net_to_gross') }}
                </label>
            </div>
        </div>

        {{-- Mobile progress bar --}}
        <div class="wizard-mobile-wrap mb-3">
            <nav aria-label="{{ __('ui.calculator.wizard_nav_aria') }}">
                <div class="wizard-progress-mobile" id="wizardMobileBar">
                    @foreach($wizardSteps as $i => $ws)
                    <button type="button"
                            class="wizard-progress-pill{{ $i === 0 ? ' active' : '' }}"
                            data-wizard-mobile="{{ $i }}"
                            aria-label="{{ __('ui.calculator.'.$ws['key']) }}">{{ $i + 1 }}</button>
                    @endforeach
                </div>
            </nav>
        </div>

        <p class="text-muted small mb-3"><i class="bi bi-signpost-split me-1"></i>{{ __('ui.calculator.step_skip') }} : cliquez sur une rubrique dans la barre de navigation.</p>

        {{-- Two-column wizard layout --}}
        <div class="row g-4">
            {{-- Sidebar (desktop only) --}}
            <div class="col-lg-3 wizard-sidebar-wrap">
                <nav class="wizard-sidebar section-card p-3" aria-label="{{ __('ui.calculator.wizard_nav_aria') }}">
                    {{-- Mode badge --}}
                    <div class="wizard-mode-badge mb-3" id="wizardModeBadge">
                        <i class="bi bi-arrow-down-circle"></i>
                        <span id="wizardModeLabel">{{ $mode === 'net_to_gross' ? __('ui.calculator.wizard_mode_net') : __('ui.calculator.wizard_mode_gross') }}</span>
                    </div>

                    {{-- Step list --}}
                    <ul class="wizard-step-list">
                        @foreach($wizardSteps as $i => $ws)
                        <li class="wizard-step{{ $i === 0 ? ' active' : '' }}" data-wizard-step="{{ $i }}">
                            <button type="button" class="wizard-step-btn" data-wizard-nav="{{ $i }}">
                                <span class="wizard-step-number"></span>
                                <span>
                                    <span class="wizard-step-label">{{ __('ui.calculator.'.$ws['key']) }}</span>
                                    <div class="wizard-step-summary" data-wizard-summary="{{ $i }}"></div>
                                </span>
                            </button>
                        </li>
                        @endforeach
                    </ul>

                    {{-- Sidebar submit --}}
                    <div class="mt-3 pt-3" style="border-top:1px solid var(--hairline)">
                        <button type="submit" class="btn btn-sm text-white fw-bold w-100" style="background:var(--g-500); font-family:var(--f-body)">
                            <i class="bi bi-calculator-fill me-1"></i>{{ __('ui.calculator.submit') }}
                        </button>
                    </div>
                </nav>
            </div>

            {{-- Form column --}}
            <div class="col-lg-9">
                <div class="simulator-flow">

                {{-- 1. Remuneration de base --}}
                <details class="step-section section-card mb-3" open data-step-section>
                    <summary>
                        <span class="step-label"><i class="bi bi-cash-coin me-2" style="color:var(--s-info)"></i>{{ __('ui.calculator.step1_title') }}</span>
                        <span class="step-pill">{{ __('ui.calculator.step_required') }}</span>
                    </summary>
                    <div class="card-body px-4 py-3">

                        <div class="mb-3" id="netCibleGroup">
                            <label class="form-label fw-semibold" for="net_cible">{{ __('ui.calculator.net_target_label') }} <span style="color:var(--s-tax)">*</span></label>
                            <div class="input-group">
                                <input type="number" name="net_cible" id="net_cible"
                                       class="form-control @error('net_cible') is-invalid @enderror"
                                       value="{{ old('net_cible', 8000) }}"
                                       min="0.01" step="0.01" placeholder="Ex : 8 000" inputmode="decimal"
                                       aria-describedby="netCibleHelp">
                                <span class="input-group-text">{{ __('ui.result.unit_mad_month_label') }}</span>
                            </div>
                            <div class="form-text" id="netCibleHelp">{{ __('ui.calculator.net_target_help') }}</div>
                        </div>

                        <div class="mb-3" id="salaireBaseGroup">
                            <label class="form-label fw-semibold" for="salaire_base">{{ __('ui.calculator.base_salary_label') }} <span style="color:var(--s-tax)">*</span></label>
                            <div class="input-group">
                                <input type="number" name="salaire_base" id="salaire_base"
                                       class="form-control @error('salaire_base') is-invalid @enderror"
                                       value="{{ old('salaire_base', 5000) }}"
                                       min="0" step="0.01" placeholder="Ex : 8 500" inputmode="decimal"
                                       aria-describedby="salaireBaseHelp">
                                <span class="input-group-text">{{ __('ui.result.unit_mad_month_label') }}</span>
                            </div>
                            <div class="form-text" id="salaireBaseHelp">{{ __('ui.calculator.base_salary_help', ['smig' => number_format(config('payroll.smig.mensuel'), 2, ',', ' ')]) }}</div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-semibold" for="type_frais_pro">{{ __('ui.calculator.category_label') }}</label>
                            <select name="type_frais_pro" id="type_frais_pro" class="form-select">
                                <option value="commun"      {{ old('type_frais_pro','commun') === 'commun'      ? 'selected' : '' }}>{{ __('ui.calculator.category_common') }}</option>
                                <option value="journaliste" {{ old('type_frais_pro','commun') === 'journaliste' ? 'selected' : '' }}>{{ __('ui.calculator.category_journalist') }}</option>
                                <option value="artiste"     {{ old('type_frais_pro','commun') === 'artiste'     ? 'selected' : '' }}>{{ __('ui.calculator.category_artist') }}</option>
                            </select>
                            <div class="form-text">{{ __('ui.calculator.category_help', ['cap' => number_format(config('payroll.frais_pro.commun.haut.plafond'), 2, ',', ' ')]) }}</div>
                        </div>

                    </div>
                </details>

                {{-- 2. Primes et anciennete --}}
                <details class="step-section section-card mb-3" data-step-section {{ old('nb_annees_anciennete') || old('prime_bilan') || old('prime_rendement') || old('autres_primes') ? 'open' : '' }}>
                    <summary>
                        <span class="step-label"><i class="bi bi-cash-stack me-2" style="color:var(--s-warn)"></i>{{ __('ui.calculator.step2_title') }}</span>
                        <span class="step-pill">{{ __('ui.calculator.step_optional') }}</span>
                    </summary>
                    <div class="card-body px-4 py-3">

                        @php
                            $ancienneteTranches = config('payroll.anciennete.tranches');
                            $premiereTrancheAnciennete = $ancienneteTranches[0]['min_annees'] ?? 0;
                            $ancienneteOptions = collect([[
                                'annees' => 0,
                                'label' => '< '.$premiereTrancheAnciennete.' ans · 0%',
                            ]])->merge(collect($ancienneteTranches)->map(function ($tranche) {
                                $taux = number_format($tranche['taux'] * 100, 0, ',', ' ');
                                $label = $tranche['max_annees'] === null
                                    ? $tranche['min_annees'].' ans et plus · '.$taux.'%'
                                    : $tranche['min_annees'].' à '.$tranche['max_annees'].' ans · '.$taux.'%';

                                return [
                                    'annees' => $tranche['min_annees'],
                                    'label' => $label,
                                ];
                            }));
                            $ancienneteValue = (int) old('nb_annees_anciennete', 0);
                            $oldPrimesImposables = (float) old('autres_primes', 0) + (float) old('prime_bilan', 0) + (float) old('prime_rendement', 0);
                        @endphp

                        {{-- Anciennete --}}
                        <div class="p-3 rounded-2 mb-3" style="background:var(--s-warn-bg); border:1px solid rgba(217,119,6,0.2);">
                            <div class="fw-semibold small mb-2">
                                <i class="bi bi-hourglass-split me-1" style="color:var(--s-warn)"></i>{{ __('ui.calculator.seniority_heading') }}
                            </div>
                            <div class="row g-2 align-items-end">
                                <div class="col-7">
                                    <label class="form-label small mb-1" for="nb_annees_anciennete">{{ __('ui.calculator.seniority_bracket') }}</label>
                                    <select name="nb_annees_anciennete" id="nb_annees_anciennete" class="form-select form-select-sm">
                                        @foreach($ancienneteOptions as $option)
                                        <option value="{{ $option['annees'] }}" {{ $ancienneteValue === $option['annees'] ? 'selected' : '' }}>{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-5 text-end">
                                    <div class="small text-muted">{{ __('ui.calculator.seniority_rate') }}</div>
                                    <div class="fw-bold" id="anciennete_taux_label">-</div>
                                    <div class="small" id="anciennete_montant_label" style="color:var(--s-succ)"></div>
                                </div>
                            </div>
                            <div class="form-text mt-1">{{ __('ui.calculator.seniority_help') }}</div>
                        </div>

                        <input type="hidden" name="prime_bilan" value="0">
                        <input type="hidden" name="prime_rendement" value="0">

                        <div class="mb-1">
                            <label class="form-label fw-semibold small" for="autres_primes">{{ __('ui.calculator.taxable_bonuses_label') }} <span class="text-muted fw-normal">{{ __('ui.calculator.taxable_bonuses_sub') }}</span></label>
                            <div class="input-group">
                                <input type="number" name="autres_primes" id="autres_primes"
                                       class="form-control @error('autres_primes') is-invalid @enderror"
                                       value="{{ $oldPrimesImposables }}"
                                       min="0" step="0.01" placeholder="0">
                                <span class="input-group-text">{{ __('ui.result.unit_mad_month_label') }}</span>
                            </div>
                            <div class="form-text">{{ __('ui.calculator.taxable_bonuses_help') }}</div>
                        </div>

                    </div>
                </details>

                {{-- 3. Heures supplementaires --}}
                <details class="step-section section-card mb-3" data-step-section {{ old('heures_sup') ? 'open' : '' }}>
                    <summary>
                        <span class="step-label"><i class="bi bi-clock-history me-2" style="color:var(--s-info)"></i>{{ __('ui.calculator.step3_title') }}</span>
                        <span class="step-pill">{{ __('ui.calculator.step_optional') }}</span>
                    </summary>
                    <div class="card-body px-4 py-3">
                        <div class="d-flex justify-content-end mb-3">
                        <button type="button" class="btn btn-sm" id="addHS" style="border:1px solid var(--s-info);color:var(--s-info)">
                            <i class="bi bi-plus-circle me-1"></i>{{ __('ui.calculator.add') }}
                        </button>
                        </div>
                        <div class="form-text mb-3">{{ __('ui.calculator.overtime_help') }}</div>
                        <div id="hsContainer">
                            @if(old('heures_sup'))
                                @foreach(old('heures_sup') as $i => $hs)
                                <div class="hs-row row g-2 mb-2 align-items-end">
                                    <div class="col-7">
                                        <label class="form-label small mb-1" for="heures_sup_{{ $i }}_type">{{ __('ui.calculator.type_label') }}</label>
                                        <select name="heures_sup[{{ $i }}][type]" id="heures_sup_{{ $i }}_type" class="form-select form-select-sm">
                                            @foreach($hs_labels as $key => $label)
                                            <option value="{{ $key }}" {{ ($hs['type'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small mb-1" for="heures_sup_{{ $i }}_nb_heures">{{ __('ui.calculator.hours_label') }}</label>
                                        <input type="number" name="heures_sup[{{ $i }}][nb_heures]" id="heures_sup_{{ $i }}_nb_heures"
                                               class="form-control form-control-sm"
                                               value="{{ $hs['nb_heures'] ?? '' }}" min="0" step="0.5">
                                    </div>
                                    <div class="col-1">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-row" title="{{ __('ui.calculator.remove') }}" aria-label="{{ __('ui.calculator.remove_overtime_aria') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                @endforeach
                            @endif
                        </div>
                        <p class="text-muted small mb-0" id="hsPlaceholder" {{ old('heures_sup') ? 'style=display:none' : '' }}>
                            <i class="bi bi-info-circle me-1"></i>{{ __('ui.calculator.overtime_placeholder') }}
                        </p>
                    </div>
                </details>

                {{-- 4. CIMR & epargne retraite --}}
                <details class="step-section section-card mb-3" data-step-section {{ old('cimr_taux') || old('retraite_complementaire_mensuel') ? 'open' : '' }}>
                    <summary>
                        <span class="step-label"><i class="bi bi-piggy-bank me-2" style="color:var(--s-cot)"></i>{{ __('ui.calculator.step4_title') }}</span>
                        <span class="step-pill">{{ __('ui.calculator.step_optional') }}</span>
                    </summary>
                    <div class="card-body px-4 py-3">

                        <div class="fw-semibold small mb-2">
                            <i class="bi bi-bank me-1" style="color:var(--s-cot)"></i>{{ __('ui.calculator.cimr_section_heading') }}
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold small" for="cimrTaux">{{ __('ui.calculator.cimr_employee_rate') }}</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="cimr_taux" id="cimrTaux"
                                           class="form-control @error('cimr_taux') is-invalid @enderror"
                                           value="{{ old('cimr_taux', 0) }}"
                                           min="0" max="50" step="0.5" placeholder="0" inputmode="decimal">
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="form-text">{{ __('ui.calculator.cimr_employee_help') }}</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold small" for="cimrTauxEmployeur">{{ __('ui.calculator.cimr_employer_rate') }}</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="cimr_taux_employeur" id="cimrTauxEmployeur"
                                           class="form-control @error('cimr_taux_employeur') is-invalid @enderror"
                                           value="{{ old('cimr_taux_employeur', 0) }}"
                                           min="0" max="50" step="0.5" placeholder="0" inputmode="decimal">
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="form-text">{{ __('ui.calculator.cimr_employer_help') }}</div>
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="fw-semibold small mb-1">
                            <i class="bi bi-shield-check me-1" style="color:var(--s-info)"></i>{{ __('ui.calculator.rc_section_heading') }}
                        </div>
                        <div class="form-text mb-3">{{ __('ui.calculator.rc_section_help') }}</div>

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold small" for="retraite_complementaire_mensuel">
                                    {{ __('ui.calculator.rc_employee_label') }} <span class="text-muted fw-normal">{{ __('ui.calculator.monthly') }}</span>
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="retraite_complementaire_mensuel" id="retraite_complementaire_mensuel"
                                           class="form-control @error('retraite_complementaire_mensuel') is-invalid @enderror"
                                           value="{{ old('retraite_complementaire_mensuel', 0) }}"
                                           min="0" step="0.01" placeholder="0">
                                    <span class="input-group-text">{{ __('ui.result.unit_mad_month_label') }}</span>
                                </div>
                                <div class="form-text">{{ __('ui.calculator.rc_employee_help') }}</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold small" for="rc_part_employeur">
                                    {{ __('ui.calculator.rc_employer_label') }} <span class="text-muted fw-normal">{{ __('ui.calculator.monthly') }}</span>
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="rc_part_employeur" id="rc_part_employeur"
                                           class="form-control @error('rc_part_employeur') is-invalid @enderror"
                                           value="{{ old('rc_part_employeur', 0) }}"
                                           min="0" step="0.01" placeholder="0">
                                    <span class="input-group-text">{{ __('ui.result.unit_mad_month_label') }}</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </details>

                {{-- 5. Charges de famille --}}
                <details class="step-section section-card mb-3" data-step-section {{ old('nb_enfants') || old('conjoint_charge') ? 'open' : '' }}>
                    <summary>
                        <span class="step-label"><i class="bi bi-people-fill me-2" style="color:var(--s-succ)"></i>{{ __('ui.calculator.step5_title') }}</span>
                        <span class="step-pill">{{ __('ui.calculator.step_optional') }}</span>
                    </summary>
                    <div class="card-body px-4 py-3">
                        @php
                            $maxPersonnesCharge = (int) floor(config('payroll.charges_famille.plafond') / config('payroll.charges_famille.par_personne'));
                            $maxEnfantsCharge = max(0, $maxPersonnesCharge);
                            $conjointChecked = (bool) old('conjoint_charge');
                            $selectedNbEnfants = min((int) old('nb_enfants', 0), $conjointChecked ? max(0, $maxEnfantsCharge - 1) : $maxEnfantsCharge);
                        @endphp
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold" for="nb_enfants">{{ __('ui.calculator.children_label') }}</label>
                                <select name="nb_enfants" id="nb_enfants" class="form-select" data-max-personnes="{{ $maxPersonnesCharge }}">
                                    @for($i = 0; $i <= $maxEnfantsCharge; $i++)
                                    <option value="{{ $i }}" {{ $selectedNbEnfants === $i ? 'selected' : '' }}>{{ $i === $maxEnfantsCharge ? $i.' '.__('ui.calculator.children_or_more') : $i }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                        <div class="mt-3 p-3 rounded-2" style="background:var(--g-50); border:1px solid var(--g-200)">
                            <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="conjoint_charge"
                                           id="conjointCharge" value="1" {{ $conjointChecked ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="conjointCharge">{{ __('ui.calculator.spouse_label') }}</label>
                                    <div class="form-text">{{ __('ui.calculator.spouse_help') }}</div>
                            </div>
                        </div>
                        <div class="form-text mt-2">{{ number_format(config('payroll.charges_famille.par_personne'), 2, ',', ' ') }} MAD/mois x nombre de personnes, plafond {{ number_format(config('payroll.charges_famille.plafond'), 2, ',', ' ') }} MAD/mois (Art. 74 CGI)</div>
                    </div>
                </details>

                {{-- 6. Mutuelle sante --}}
                <details class="step-section section-card mb-3" data-step-section {{ old('mutuelle_salarie') || old('mutuelle_patronale') ? 'open' : '' }}>
                    <summary>
                        <span class="step-label"><i class="bi bi-heart-pulse-fill me-2" style="color:var(--s-tax)"></i>{{ __('ui.calculator.step6_title') }}</span>
                        <span class="step-pill">{{ __('ui.calculator.step_optional') }}</span>
                    </summary>
                    <div class="card-body px-4 py-3">

                        <div class="mb-3">
                            <label class="form-label fw-semibold small" for="mutuelle_salarie">{{ __('ui.calculator.mutual_employee_label') }}</label>
                            <div class="input-group">
                                <input type="number" name="mutuelle_salarie" id="mutuelle_salarie"
                                       class="form-control @error('mutuelle_salarie') is-invalid @enderror"
                                       value="{{ old('mutuelle_salarie', 0) }}"
                                       min="0" step="0.01" placeholder="0">
                                <span class="input-group-text">{{ __('ui.result.unit_mad_month_label') }}</span>
                            </div>
                            <div class="form-text">{{ __('ui.calculator.mutual_employee_help') }}</div>
                        </div>

                        <div class="mb-1">
                            <label class="form-label fw-semibold small" for="mutuelle_patronale">{{ __('ui.calculator.mutual_employer_label') }}</label>
                            <div class="input-group">
                                <input type="number" name="mutuelle_patronale" id="mutuelle_patronale"
                                       class="form-control @error('mutuelle_patronale') is-invalid @enderror"
                                       value="{{ old('mutuelle_patronale', 0) }}"
                                       min="0" step="0.01" placeholder="0">
                                <span class="input-group-text">{{ __('ui.result.unit_mad_month_label') }}</span>
                            </div>
                            <div class="form-text">{{ __('ui.calculator.mutual_employer_help') }}</div>
                        </div>

                    </div>
                </details>

                {{-- 7. Indemnites traitees comme exonerees --}}
                <details class="step-section section-card mb-3" data-step-section {{ old('indemnites') ? 'open' : '' }}>
                    <summary>
                        <span class="step-label"><i class="bi bi-gift me-2" style="color:var(--s-succ)"></i>{{ __('ui.calculator.step7_title') }}</span>
                        <span class="step-pill">{{ __('ui.calculator.step_optional') }}</span>
                    </summary>
                    <div class="card-body px-4 py-3">
                        <div class="d-flex justify-content-end mb-3">
                        <button type="button" class="btn btn-sm" id="addIndemnite" style="border:1px solid var(--s-succ);color:var(--s-succ)">
                            <i class="bi bi-plus-circle me-1"></i>{{ __('ui.calculator.add') }}
                        </button>
                        </div>
                        <div class="form-text mb-3">{{ __('ui.calculator.allowance_help') }}</div>
                        <div class="row g-2 mb-3 align-items-end">
                            <div class="col-7">
                                <label class="form-label small mb-1 fw-semibold" for="joursTravailles">{{ __('ui.calculator.working_days_label') }}</label>
                                <input type="number" name="jours_travailles" id="joursTravailles"
                                       class="form-control form-control-sm @error('jours_travailles') is-invalid @enderror"
                                       value="{{ old('jours_travailles', config('payroll.jours_travailles_defaut')) }}"
                                       min="1" max="31" step="1">
                            </div>
                            <div class="col-5">
                                <div class="form-text mb-1">{{ __('ui.calculator.working_days_help') }}</div>
                            </div>
                        </div>
                        <div id="indemniteContainer">
                            @if(old('indemnites'))
                                @foreach(old('indemnites') as $i => $ind)
                                <div class="ind-row row g-2 mb-2 align-items-end">
                                    <div class="col-7">
                                        <label class="form-label small mb-1" for="indemnites_{{ $i }}_type">{{ __('ui.calculator.allowance_type_label') }}</label>
                                        <select name="indemnites[{{ $i }}][type]" id="indemnites_{{ $i }}_type" class="form-select form-select-sm ind-type-select">
                                            @foreach($indemnites_config as $key => $cfg)
                                            <option value="{{ $key }}" {{ ($ind['type'] ?? '') === $key ? 'selected' : '' }}
                                                    data-plafond="{{ $cfg['base_salaire'] ? ($cfg['pct'] * 100).'% du SB' : number_format($cfg['montant'], !empty($cfg['par_jour']) ? 2 : 0, ',', ' ').' MAD/'.(!empty($cfg['par_jour']) ? 'jour travaille' : 'mois') }}">
                                                {{ $cfg['label'] }}
                                            </option>
                                            @endforeach
                                        </select>
                                        <div class="form-text ind-plafond-hint"></div>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small mb-1" for="indemnites_{{ $i }}_montant">{{ __('ui.calculator.allowance_amount_label') }}</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="indemnites[{{ $i }}][montant]" id="indemnites_{{ $i }}_montant"
                                                   class="form-control form-control-sm"
                                                   value="{{ $ind['montant'] ?? '' }}" min="0" step="0.01">
                                            <span class="input-group-text">{{ __('ui.result.unit_mad_month_label') }}</span>
                                        </div>
                                    </div>
                                    <div class="col-1">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-row" aria-label="{{ __('ui.calculator.remove_allowance_aria') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                @endforeach
                            @endif
                        </div>
                        <p class="text-muted small mb-0" id="indemnitePlaceholder" {{ old('indemnites') ? 'style=display:none' : '' }}>
                            <i class="bi bi-info-circle me-1"></i>{{ __('ui.calculator.allowance_placeholder') }}
                        </p>
                    </div>
                </details>

                {{-- 8. Avantages CNSS exoneres --}}
                <details class="step-section section-card mb-3" data-step-section {{ old('prime_scolarite') || old('prime_aid') || old('autres_avantages_cnss') ? 'open' : '' }}>
                    <summary>
                        <span class="step-label"><i class="bi bi-mortarboard me-2" style="color:var(--s-cot)"></i>{{ __('ui.calculator.step8_title') }}</span>
                        <span class="step-pill">{{ __('ui.calculator.step_optional') }}</span>
                    </summary>
                    <div class="card-body px-4 py-3">
                        <div class="form-text mb-3">{{ __('ui.calculator.cnss_exempt_help') }}</div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small" for="prime_scolarite">{{ __('ui.calculator.school_bonus_label') }}</label>
                            <div class="input-group">
                                <input type="number" name="prime_scolarite" id="prime_scolarite"
                                       class="form-control @error('prime_scolarite') is-invalid @enderror"
                                       value="{{ old('prime_scolarite', 0) }}"
                                       min="0" step="0.01" placeholder="0">
                                <span class="input-group-text">{{ __('ui.result.unit_mad_month_label') }}</span>
                            </div>
                            <div class="form-text">{{ __('ui.calculator.school_bonus_help') }}</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small" for="prime_aid">{{ __('ui.calculator.eid_bonus_label') }}</label>
                            <div class="input-group">
                                <input type="number" name="prime_aid" id="prime_aid"
                                       class="form-control @error('prime_aid') is-invalid @enderror"
                                       value="{{ old('prime_aid', 0) }}"
                                       min="0" step="0.01" placeholder="0">
                                <span class="input-group-text">{{ __('ui.result.unit_mad_month_label') }}</span>
                            </div>
                            <div class="form-text">{{ __('ui.calculator.eid_bonus_help') }}</div>
                        </div>

                        <div class="mb-1">
                            <label class="form-label fw-semibold small" for="autres_avantages_cnss">{{ __('ui.calculator.other_cnss_exempt_label') }}</label>
                            <div class="input-group">
                                <input type="number" name="autres_avantages_cnss" id="autres_avantages_cnss"
                                       class="form-control @error('autres_avantages_cnss') is-invalid @enderror"
                                       value="{{ old('autres_avantages_cnss', 0) }}"
                                       min="0" step="0.01" placeholder="0">
                                <span class="input-group-text">{{ __('ui.result.unit_mad_month_label') }}</span>
                            </div>
                        </div>

                    </div>
                </details>

                {{-- 9. Autres retenues --}}
                <details class="step-section section-card mb-3" data-step-section {{ old('autres_retenues') ? 'open' : '' }}>
                    <summary>
                        <span class="step-label"><i class="bi bi-dash-circle me-2" style="color:var(--s-neutral)"></i>{{ __('ui.calculator.step9_title') }}</span>
                        <span class="step-pill">{{ __('ui.calculator.step_optional') }}</span>
                    </summary>
                    <div class="card-body px-4 py-3">
                        <label class="form-label fw-semibold" for="autres_retenues">{{ __('ui.calculator.other_deductions_label') }}</label>
                        <div class="input-group">
                            <input type="number" name="autres_retenues" id="autres_retenues" class="form-control"
                                   value="{{ old('autres_retenues', 0) }}" min="0" step="0.01" placeholder="0">
                            <span class="input-group-text">{{ __('ui.result.unit_mad_month_label') }}</span>
                        </div>
                        <div class="form-text">{{ __('ui.calculator.other_deductions_help') }}</div>
                    </div>
                </details>

                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="row mb-5 mobile-sticky">
            <div class="col d-flex flex-column flex-sm-row justify-content-center gap-2 action-bar p-3">
                <button type="submit" class="btn btn-lg px-5 text-white fw-bold"
                        style="background:var(--g-500); min-width:240px; font-family:var(--f-body)">
                    <i class="bi bi-calculator-fill me-2"></i>{{ __('ui.calculator.submit') }}
                </button>
                <a href="{{ route('calculator.index') }}" class="btn btn-lg px-4" style="border:1px solid var(--ink-3);color:var(--ink-2);font-family:var(--f-body)">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('ui.calculator.reset') }}
                </a>
            </div>
        </div>

    </form>
</div>
@endsection

@push('scripts')
<script>
const INDEMNITES_CONFIG = @json($indemnites_config);
const HS_LABELS = @json($hs_labels);
const INTL_LOCALE = @json(config('app.supported_locales.'.app()->getLocale().'.intl'));
const MODE_GROSS_TO_NET = 'gross_to_net';
const MODE_NET_TO_GROSS = 'net_to_gross';
const TRANS_TYPE = @json(__('ui.calculator.type_label'));
const TRANS_HOURS = @json(__('ui.calculator.hours_label'));
const TRANS_REMOVE = @json(__('ui.calculator.remove'));
const TRANS_REMOVE_OVERTIME = @json(__('ui.calculator.remove_overtime_aria'));
const TRANS_ALLOWANCE_TYPE = @json(__('ui.calculator.allowance_type_label'));
const TRANS_ALLOWANCE_AMOUNT = @json(__('ui.calculator.allowance_amount_label'));
const TRANS_REMOVE_ALLOWANCE = @json(__('ui.calculator.remove_allowance_aria'));
const TRANS_OR_MORE = @json(__('ui.calculator.children_or_more'));
const TRANS_CEILING_SIMULATED = @json(__('ui.calculator.ceiling_simulated'));
const TRANS_WIZARD_MODE_GROSS = @json(__('ui.calculator.wizard_mode_gross'));
const TRANS_WIZARD_MODE_NET = @json(__('ui.calculator.wizard_mode_net'));

const salaireBaseGroup = document.getElementById('salaireBaseGroup');
const salaireBaseInput = document.getElementById('salaire_base');
const netCibleGroup = document.getElementById('netCibleGroup');
const netCibleInput = document.getElementById('net_cible');
const modeInputs = document.querySelectorAll('input[name="mode"]');

function updateCalculationMode() {
    const selectedMode = document.querySelector('input[name="mode"]:checked')?.value || MODE_GROSS_TO_NET;
    const isNetToGross = selectedMode === MODE_NET_TO_GROSS;

    salaireBaseGroup.hidden = isNetToGross;
    salaireBaseInput.required = !isNetToGross;
    netCibleGroup.hidden = !isNetToGross;
    netCibleInput.required = isNetToGross;

    // Update wizard mode badge
    const badgeIcon = document.querySelector('#wizardModeBadge i');
    const badgeLabel = document.getElementById('wizardModeLabel');
    if (badgeIcon && badgeLabel) {
        badgeIcon.className = isNetToGross ? 'bi bi-arrow-up-circle' : 'bi bi-arrow-down-circle';
        badgeLabel.textContent = isNetToGross ? TRANS_WIZARD_MODE_NET : TRANS_WIZARD_MODE_GROSS;
    }
}

modeInputs.forEach(input => input.addEventListener('change', updateCalculationMode));
updateCalculationMode();

// Tranches d'anciennete (pour apercu cote client)
const ANCIENNETE_TRANCHES = @json(config('payroll.anciennete.tranches'));

function getTauxAnciennete(annees) {
    if (annees < 2) return 0;
    for (const t of ANCIENNETE_TRANCHES) {
        if (annees >= t.min_annees && (t.max_annees === null || annees <= t.max_annees)) return t.taux * 100;
    }
    return 0;
}

function updateAnciennete() {
    const annees = parseInt(document.getElementById('nb_annees_anciennete').value) || 0;
    const salaire = parseFloat(document.getElementById('salaire_base').value) || 0;
    const taux = getTauxAnciennete(annees);
    const montant = salaire * taux / 100;
    const labelEl = document.getElementById('anciennete_taux_label');
    const montantEl = document.getElementById('anciennete_montant_label');
    labelEl.textContent = taux > 0 ? taux + '%' : '0% (< 2 ans)';
    montantEl.textContent = taux > 0 ? '→ ' + montant.toLocaleString(INTL_LOCALE, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' {{ __('ui.result.unit_mad_month_label') }}' : '';
}

document.getElementById('nb_annees_anciennete').addEventListener('change', updateAnciennete);
document.getElementById('salaire_base').addEventListener('input', updateAnciennete);
updateAnciennete(); // Init

const nbEnfantsSelect = document.getElementById('nb_enfants');
const conjointChargeInput = document.getElementById('conjointCharge');

function updateChildrenOptions() {
    const maxPersonnes = parseInt(nbEnfantsSelect.dataset.maxPersonnes, 10) || 0;
    const maxEnfants = conjointChargeInput.checked ? Math.max(0, maxPersonnes - 1) : maxPersonnes;
    const currentValue = Math.min(parseInt(nbEnfantsSelect.value, 10) || 0, maxEnfants);

    nbEnfantsSelect.innerHTML = '';
    for (let i = 0; i <= maxEnfants; i++) {
        const option = document.createElement('option');
        option.value = i;
        option.textContent = i === maxEnfants ? i + ' ' + TRANS_OR_MORE : i;
        option.selected = i === currentValue;
        nbEnfantsSelect.appendChild(option);
    }
}

conjointChargeInput.addEventListener('change', updateChildrenOptions);
updateChildrenOptions();

// ---- Wizard navigation ----
const stepSections = Array.from(document.querySelectorAll('[data-step-section]'));
const wizardStepItems = document.querySelectorAll('[data-wizard-step]');
const wizardMobilePills = document.querySelectorAll('[data-wizard-mobile]');
const wizardSummaryEls = document.querySelectorAll('[data-wizard-summary]');

function formatMAD(val) {
    if (!val || val <= 0) return '';
    return val.toLocaleString(INTL_LOCALE, {maximumFractionDigits: 0}) + ' MAD';
}

function getStepSummary(index) {
    const section = stepSections[index];
    if (!section) return '';

    switch (index) {
        case 0: {
            const mode = document.querySelector('input[name="mode"]:checked')?.value || MODE_GROSS_TO_NET;
            const isNet = mode === MODE_NET_TO_GROSS;
            const val = parseFloat((isNet ? netCibleInput : salaireBaseInput).value) || 0;
            return val > 0 ? formatMAD(val) : '';
        }
        case 1: {
            const parts = [];
            const annees = parseInt(document.getElementById('nb_annees_anciennete').value) || 0;
            const taux = getTauxAnciennete(annees);
            if (taux > 0) parts.push(taux + '% anc.');
            const primes = parseFloat(document.getElementById('autres_primes').value) || 0;
            if (primes > 0) parts.push(formatMAD(primes));
            return parts.join(' + ');
        }
        case 2: {
            const rows = section.querySelectorAll('#hsContainer .hs-row');
            const count = rows.length;
            return count > 0 ? count + ' ligne' + (count > 1 ? 's' : '') + ' HS' : '';
        }
        case 3: {
            const parts = [];
            const taux = parseFloat(document.getElementById('cimrTaux').value) || 0;
            if (taux > 0) parts.push('CIMR ' + taux + '%');
            const rc = parseFloat(document.getElementById('retraite_complementaire_mensuel').value) || 0;
            if (rc > 0) parts.push('RC ' + formatMAD(rc));
            return parts.join(' + ');
        }
        case 4: {
            const parts = [];
            const nb = parseInt(document.getElementById('nb_enfants').value) || 0;
            if (nb > 0) parts.push(nb + ' enf.');
            if (document.getElementById('conjointCharge').checked) parts.push('conjoint');
            return parts.join(' + ');
        }
        case 5: {
            const mut = parseFloat(document.getElementById('mutuelle_salarie').value) || 0;
            return mut > 0 ? 'Mut. ' + formatMAD(mut) : '';
        }
        case 6: {
            const rows = section.querySelectorAll('#indemniteContainer .ind-row');
            const count = rows.length;
            return count > 0 ? count + ' indemnite' + (count > 1 ? 's' : '') : '';
        }
        case 7: {
            const s = parseFloat(document.getElementById('prime_scolarite').value) || 0;
            const a = parseFloat(document.getElementById('prime_aid').value) || 0;
            const o = parseFloat(document.getElementById('autres_avantages_cnss').value) || 0;
            const total = s + a + o;
            return total > 0 ? 'Av. CNSS ' + formatMAD(total) : '';
        }
        case 8: {
            const val = parseFloat(document.getElementById('autres_retenues').value) || 0;
            return val > 0 ? 'Ret. ' + formatMAD(val) : '';
        }
        default:
            return '';
    }
}

function updateWizardState() {
    stepSections.forEach((section, i) => {
        const isOpen = section.open;
        const summary = getStepSummary(i);
        const hasContent = summary.length > 0;

        // Update desktop sidebar
        const stepItem = document.querySelector(`[data-wizard-step="${i}"]`);
        if (stepItem) {
            stepItem.classList.toggle('active', isOpen);
            stepItem.classList.toggle('completed', !isOpen && hasContent);
        }

        // Update summary text
        const summaryEl = document.querySelector(`[data-wizard-summary="${i}"]`);
        if (summaryEl) {
            summaryEl.textContent = summary;
        }

        // Update mobile pills
        const pill = document.querySelector(`[data-wizard-mobile="${i}"]`);
        if (pill) {
            pill.classList.toggle('active', isOpen);
            pill.classList.toggle('completed', !isOpen && hasContent);
            pill.innerHTML = (!isOpen && hasContent) ? '✓' : String(i + 1);
        }
    });
}

function navigateToStep(index) {
    const target = stepSections[index];
    if (!target) return;

    stepSections.forEach((section, i) => {
        if (i !== index) section.open = false;
    });
    target.open = true;
    target.scrollIntoView({behavior: 'smooth', block: 'start'});
    updateWizardState();
}

// Sidebar click handlers
document.querySelectorAll('[data-wizard-nav]').forEach(btn => {
    btn.addEventListener('click', () => {
        navigateToStep(parseInt(btn.dataset.wizardNav, 10));
    });
});

// Mobile pill click handlers
wizardMobilePills.forEach(pill => {
    pill.addEventListener('click', () => {
        navigateToStep(parseInt(pill.dataset.wizardMobile, 10));
    });
});

// Listen to toggle events on details to sync wizard state
stepSections.forEach(section => {
    section.addEventListener('toggle', () => {
        updateWizardState();
    });
});

// Listen to input/change events to update summaries
document.getElementById('payrollForm').addEventListener('input', () => {
    updateWizardState();
});
document.getElementById('payrollForm').addEventListener('change', () => {
    updateWizardState();
});

// Initialize wizard state
updateWizardState();

const formErrors = document.getElementById('formErrors');
if (formErrors) formErrors.focus();

// ---- Heures supplementaires ----
let hsIndex = {{ old('heures_sup') ? count(old('heures_sup')) : 0 }};

function buildHsOptions(selectedType) {
    return Object.entries(HS_LABELS).map(([k, v]) =>
        `<option value="${k}" ${k === selectedType ? 'selected' : ''}>${v}</option>`
    ).join('');
}

document.getElementById('addHS').addEventListener('click', () => {
    const i = hsIndex++;
    const row = document.createElement('div');
    row.className = 'hs-row row g-2 mb-2 align-items-end';
    row.innerHTML = `
        <div class="col-7">
            <label class="form-label small mb-1" for="heures_sup_${i}_type">${TRANS_TYPE}</label>
            <select name="heures_sup[${i}][type]" id="heures_sup_${i}_type" class="form-select form-select-sm">${buildHsOptions('semaine_diurne')}</select>
        </div>
        <div class="col-4">
            <label class="form-label small mb-1" for="heures_sup_${i}_nb_heures">${TRANS_HOURS}</label>
            <input type="number" name="heures_sup[${i}][nb_heures]" id="heures_sup_${i}_nb_heures" class="form-control form-control-sm" value="" min="0" step="0.5" placeholder="0">
        </div>
        <div class="col-1">
            <button type="button" class="btn btn-sm btn-outline-danger remove-row" title="${TRANS_REMOVE}" aria-label="${TRANS_REMOVE_OVERTIME}"><i class="bi bi-trash"></i></button>
        </div>`;
    document.getElementById('hsContainer').appendChild(row);
    document.getElementById('hsPlaceholder').style.display = 'none';
    row.querySelector('.remove-row').addEventListener('click', () => {
        row.remove();
        updateHsPlaceholder();
        updateWizardState();
    });
    updateWizardState();
});

function updateHsPlaceholder() {
    document.getElementById('hsPlaceholder').style.display =
        document.querySelectorAll('#hsContainer .hs-row').length ? 'none' : '';
}

// ---- Indemnites ----
let indIndex = {{ old('indemnites') ? count(old('indemnites')) : 0 }};

function buildIndOptions(selectedType) {
    return Object.entries(INDEMNITES_CONFIG).map(([k, cfg]) => {
        const plafondTxt = cfg.base_salaire
            ? `${(cfg.pct * 100).toFixed(0)}% du salaire de base`
            : cfg.par_jour
                ? `${cfg.montant.toLocaleString(INTL_LOCALE)} MAD/jour travaille`
                : `${cfg.montant.toLocaleString(INTL_LOCALE)} MAD/mois`;
        return `<option value="${k}" ${k === selectedType ? 'selected' : ''} data-plafond="${plafondTxt}">${cfg.label}</option>`;
    }).join('');
}

function getPlafondHint(select) {
    const opt = select.options[select.selectedIndex];
    return opt ? `${TRANS_CEILING_SIMULATED} ${opt.dataset.plafond}` : '';
}

function createIndRow(i, selectedType) {
    const row = document.createElement('div');
    row.className = 'ind-row row g-2 mb-2 align-items-end';
    row.innerHTML = `
        <div class="col-7">
            <label class="form-label small mb-1" for="indemnites_${i}_type">${TRANS_ALLOWANCE_TYPE}</label>
            <select name="indemnites[${i}][type]" id="indemnites_${i}_type" class="form-select form-select-sm ind-type-select">${buildIndOptions(selectedType || Object.keys(INDEMNITES_CONFIG)[0])}</select>
            <div class="form-text ind-plafond-hint"></div>
        </div>
        <div class="col-4">
            <label class="form-label small mb-1" for="indemnites_${i}_montant">${TRANS_ALLOWANCE_AMOUNT}</label>
            <div class="input-group input-group-sm">
                <input type="number" name="indemnites[${i}][montant]" id="indemnites_${i}_montant" class="form-control form-control-sm" value="" min="0" step="0.01" placeholder="0">
                <span class="input-group-text">{{ __('ui.result.unit_mad_month_label') }}</span>
            </div>
        </div>
        <div class="col-1">
            <button type="button" class="btn btn-sm btn-outline-danger remove-row" aria-label="${TRANS_REMOVE_ALLOWANCE}"><i class="bi bi-trash"></i></button>
        </div>`;
    const sel = row.querySelector('.ind-type-select');
    const hint = row.querySelector('.ind-plafond-hint');
    sel.addEventListener('change', () => { hint.textContent = getPlafondHint(sel); });
    hint.textContent = getPlafondHint(sel);
    row.querySelector('.remove-row').addEventListener('click', () => {
        row.remove();
        updateIndemnitePlaceholder();
        updateWizardState();
    });
    return row;
}

document.getElementById('addIndemnite').addEventListener('click', () => {
    const i = indIndex++;
    document.getElementById('indemniteContainer').appendChild(createIndRow(i, null));
    document.getElementById('indemnitePlaceholder').style.display = 'none';
    updateWizardState();
});

function updateIndemnitePlaceholder() {
    document.getElementById('indemnitePlaceholder').style.display =
        document.querySelectorAll('#indemniteContainer .ind-row').length ? 'none' : '';
}

// Hints sur les lignes existantes (rechargement apres erreur)
document.querySelectorAll('.ind-type-select').forEach(sel => {
    const hint = sel.closest('.col-7').querySelector('.ind-plafond-hint');
    if (hint) hint.textContent = getPlafondHint(sel);
    sel.addEventListener('change', () => { if (hint) hint.textContent = getPlafondHint(sel); });
});

// Boutons supprimer existants
document.querySelectorAll('.remove-row').forEach(btn => {
    btn.addEventListener('click', () => {
        const row = btn.closest('.hs-row, .ind-row');
        if (row) { row.remove(); updateHsPlaceholder(); updateIndemnitePlaceholder(); updateWizardState(); }
    });
});

</script>
@endpush
