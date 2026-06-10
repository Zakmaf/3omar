@extends('layouts.app')

@section('title', 'Résultats — Bulletin de Paie 2026')

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@endpush

@section('content')
<div class="container">

    {{-- Avertissements --}}
    @if(!empty($r['avertissements']))
    <div class="alert alert-warning border-0 shadow-sm mb-4">
        <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>Avertissements réglementaires</h6>
        <ul class="mb-0">
            @foreach($r['avertissements'] as $w)
            <li>{{ $w }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- ================================================================ --}}
    {{-- CARTES SYNTHÈSE — Ligne 1 : cotisations & IR                     --}}
    {{-- ================================================================ --}}
    <div class="row g-3 mb-3">

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 text-center py-3 px-2">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Salaire Brut Imposable</div>
                <div class="fs-4 fw-bold text-primary">{{ number_format($r['sbi'], 2, ',', ' ') }} <small class="fs-6">MAD</small></div>
                @if($r['total_indemnites'] > 0)
                <div class="text-muted small mt-1">+ {{ number_format($r['total_indemnites'], 2, ',', ' ') }} indemnités</div>
                @endif
                <div class="text-muted small">Base imposable mensuelle</div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 text-center py-3 px-2">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Cotisations Salariales</div>
                <div class="fs-4 fw-bold" style="color:#6f42c1">{{ number_format($r['total_sociales'], 2, ',', ' ') }} <small class="fs-6">MAD</small></div>
                <div class="text-muted small mt-1">CNSS + AMO{{ $r['cimr_actif'] ? ' + CIMR' : '' }}</div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 text-center py-3 px-2">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Impôt sur le Revenu</div>
                <div class="fs-4 fw-bold text-danger">{{ number_format($r['ir_net'], 2, ',', ' ') }} <small class="fs-6">MAD</small></div>
                <div class="text-muted small mt-1">Tranche {{ round($r['tranche_ir']['taux'] * 100) }}% — Art. 73 CGI</div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 text-center py-3 px-2 bg-success bg-opacity-10">
                <div class="text-muted small text-uppercase fw-semibold mb-1">NET À PAYER</div>
                <div class="net-amount">{{ number_format($r['salaire_net'], 2, ',', ' ') }}</div>
                <div class="text-muted small">MAD / mois</div>
            </div>
        </div>

    </div>

    {{-- ================================================================ --}}
    {{-- CARTES SYNTHÈSE — Ligne 2 : net comptable & coût employeur       --}}
    {{-- ================================================================ --}}
    <div class="row g-3 mb-4">

        <div class="col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100 text-center py-3 px-2">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Salaire Net Comptable</div>
                <div class="fs-4 fw-bold text-secondary">{{ number_format($r['snc'], 2, ',', ' ') }} <small class="fs-6">MAD</small></div>
                <div class="text-muted small mt-1">SBI − cotisations sociales</div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100 text-center py-3 px-2" style="background:#fff8f0">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Cotisations Patronales</div>
                <div class="fs-4 fw-bold text-warning">{{ number_format($r['total_patronal'], 2, ',', ' ') }} <small class="fs-6">MAD</small></div>
                <div class="text-muted small mt-1">CNSS + AMO + AF + TFP{{ $r['mutuelle_patronale'] > 0 ? ' + Mutuelle' : '' }}</div>
            </div>
        </div>

        <div class="col-sm-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100 text-center py-3 px-2" style="background:#fff3e0">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Coût Total Employeur</div>
                <div class="fs-4 fw-bold" style="color:#e65100">{{ number_format($r['cout_total_employeur'], 2, ',', ' ') }} <small class="fs-6">MAD</small></div>
                <div class="text-muted small mt-1">SBI + cotisations patronales</div>
            </div>
        </div>

    </div>

    {{-- ================================================================ --}}
    {{-- TABLEAU DÉTAILLÉ + GRAPHIQUE                                      --}}
    {{-- ================================================================ --}}
    <div class="row g-4">

        {{-- Tableau détaillé --}}
        <div class="col-lg-7">
            <div class="card section-card">
                <div class="card-header px-4 py-3">
                    <i class="bi bi-table me-2 text-success"></i>Détail complet du calcul
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 detail-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-3 py-2" style="width:45%">Poste</th>
                                    <th class="text-end px-3 py-2" style="width:25%">Base / Assiette</th>
                                    <th class="text-end px-3 py-2" style="width:15%">Taux</th>
                                    <th class="text-end px-3 py-2" style="width:15%">Montant</th>
                                </tr>
                            </thead>
                            <tbody>

                                {{-- GAINS --}}
                                <tr class="row-brut">
                                    <td class="px-3 py-2">Salaire de base</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-primary">{{ number_format($r['input']['salaire_base'], 2, ',', ' ') }}</td>
                                </tr>

                                @if($r['prime_anciennete'] > 0)
                                <tr class="row-brut">
                                    <td class="px-3 py-2">
                                        Prime d'ancienneté — {{ $r['nb_annees_anciennete'] }} ans
                                        <span class="badge text-bg-light badge-legal ms-1">Art. 350 CT</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['input']['salaire_base'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ round($r['taux_anciennete'] * 100) }}%</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-primary">{{ number_format($r['prime_anciennete'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                @if($r['prime_bilan'] > 0)
                                <tr class="row-brut">
                                    <td class="px-3 py-2">Prime de bilan / 13ème mois</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-primary">{{ number_format($r['prime_bilan'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                @if($r['prime_rendement'] > 0)
                                <tr class="row-brut">
                                    <td class="px-3 py-2">Prime de rendement</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-primary">{{ number_format($r['prime_rendement'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                @if($r['autres_primes'] > 0)
                                <tr class="row-brut">
                                    <td class="px-3 py-2">Autres primes imposables</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-primary">{{ number_format($r['autres_primes'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                @foreach($r['detail_hs'] as $hs)
                                <tr class="row-brut">
                                    <td class="px-3 py-2">Heures sup. {{ $hs['label'] }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ $hs['nb_heures'] }}h × {{ number_format($hs['taux_horaire'], 2, ',', '.') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">×{{ number_format(1 + $hs['majoration'], 2, ',', '.') }}</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-primary">{{ number_format($hs['montant'], 2, ',', ' ') }}</td>
                                </tr>
                                @endforeach

                                @if($r['excedent_indemnites'] > 0)
                                <tr class="row-brut">
                                    <td class="px-3 py-2">
                                        Excédent d'indemnités (part imposable)
                                        <span class="badge text-bg-light badge-legal ms-1">Arrêté 1314-25</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-primary">{{ number_format($r['excedent_indemnites'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                <tr class="table-light">
                                    <td class="px-3 py-2 fw-bold" colspan="3">Salaire Brut Imposable (SBI)</td>
                                    <td class="text-end px-3 py-2 fw-bold text-primary">{{ number_format($r['sbi'], 2, ',', ' ') }}</td>
                                </tr>

                                {{-- COTISATIONS SALARIALES --}}
                                <tr class="row-cotis">
                                    <td class="px-3 py-2">
                                        CNSS salarié
                                        <span class="badge text-bg-light badge-legal ms-1">Dahir 1-72-184</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['assiette_cnss'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format(config('payroll.cnss.taux') * 100, 2, ',', '.') }}%</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-danger">− {{ number_format($r['cotisation_cnss'], 2, ',', ' ') }}</td>
                                </tr>

                                <tr class="row-cotis">
                                    <td class="px-3 py-2">
                                        AMO salarié
                                        <span class="badge text-bg-light badge-legal ms-1">Loi 65-00</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['sbi'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format(config('payroll.amo.taux') * 100, 2, ',', '.') }}%</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-danger">− {{ number_format($r['cotisation_amo'], 2, ',', ' ') }}</td>
                                </tr>

                                @if($r['cimr_actif'] && $r['cotisation_cimr'] > 0)
                                <tr class="row-cotis">
                                    <td class="px-3 py-2">
                                        CIMR
                                        <span class="badge text-bg-light badge-legal ms-1">Art. 28-III CGI</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['sbi'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ rtrim(rtrim(number_format($r['cimr_taux'] * 100, 2, ',', ' '), '0'), ',') }}%</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-danger">− {{ number_format($r['cotisation_cimr'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                <tr class="table-light">
                                    <td class="px-3 py-2 fw-semibold" colspan="3">Salaire Net Comptable (SNC)</td>
                                    <td class="text-end px-3 py-2 fw-semibold">{{ number_format($r['snc'], 2, ',', ' ') }}</td>
                                </tr>

                                {{-- FRAIS PRO & RNI --}}
                                <tr class="row-impot">
                                    <td class="px-3 py-2">
                                        Frais professionnels (déd. IR)
                                        <br><small class="text-muted">{{ $r['desc_fp'] }}{{ $r['fp_plafonne'] ? ' — plafonné' : '' }}</small>
                                        <span class="badge text-bg-light badge-legal ms-1">Art. 59 CGI</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['sbi'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ round($r['taux_fp'] * 100) }}%</td>
                                    <td class="text-end px-3 py-2 text-muted fst-italic">− {{ number_format($r['frais_pro'], 2, ',', ' ') }}</td>
                                </tr>

                                @if($r['rc_deduite'] > 0)
                                <tr class="row-impot">
                                    <td class="px-3 py-2">
                                        Retraite complémentaire (déd. IR)
                                        <br><small class="text-muted">{{ number_format($r['rc_annuel'], 0, ',', ' ') }} MAD/an{{ $r['rc_annuel'] > $r['rc_deduite'] ? ' — plafonné' : '' }}</small>
                                        <span class="badge text-bg-light badge-legal ms-1">Art. 28-IV CGI</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">annuel</td>
                                    <td class="text-end px-3 py-2 text-muted fst-italic">− {{ number_format($r['rc_deduite'] / 12, 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                <tr class="table-light">
                                    <td class="px-3 py-2 fw-semibold" colspan="3">RNI mensuel (× 12 = {{ number_format($r['rni_annuel_net'], 2, ',', ' ') }} MAD/an imposable)</td>
                                    <td class="text-end px-3 py-2 fw-semibold">{{ number_format($r['rni'], 2, ',', ' ') }}</td>
                                </tr>

                                {{-- IR --}}
                                <tr class="row-impot">
                                    <td class="px-3 py-2">
                                        Impôt sur le Revenu (IR brut)
                                        <br><small class="text-muted">{{ round($r['tranche_ir']['taux'] * 100) }}% × {{ number_format($r['rni_annuel_net'], 0, ',', ' ') }} − {{ number_format($r['tranche_ir']['deduction'], 0, ',', ' ') }} = {{ number_format($r['ir_annuel_brut'], 2, ',', ' ') }} MAD/an</small>
                                        <span class="badge text-bg-light badge-legal ms-1">Art. 73 CGI</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['rni'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ round($r['tranche_ir']['taux'] * 100) }}%</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-danger">{{ number_format($r['ir_mensuel_brut'], 2, ',', ' ') }}</td>
                                </tr>

                                @if($r['charges_famille'] > 0)
                                <tr class="row-impot">
                                    <td class="px-3 py-2">
                                        Déd. charges de famille
                                        <br><small class="text-muted">{{ $r['nb_personnes'] }} personne(s) × {{ number_format(config('payroll.charges_famille.par_personne'), 0, ',', ' ') }} MAD</small>
                                        <span class="badge text-bg-light badge-legal ms-1">Art. 74 CGI</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-success">+ {{ number_format($r['charges_famille'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                <tr class="row-impot">
                                    <td class="px-3 py-2 fw-semibold">IR net retenu à la source</td>
                                    <td class="text-end px-3 py-2 text-muted" colspan="2">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-danger">− {{ number_format($r['ir_net'], 2, ',', ' ') }}</td>
                                </tr>

                                {{-- Indemnités --}}
                                @foreach($r['detail_indemnites'] as $ind)
                                <tr class="row-indem">
                                    <td class="px-3 py-2">
                                        Indemnité — {{ $ind['label'] }}
                                        @if($ind['depasse'])
                                        <span class="badge text-bg-warning ms-1">Plafonné à {{ number_format($ind['plafond'], 0, ',', ' ') }} MAD</span>
                                        <br><small class="text-muted">Excédent {{ number_format($ind['excedent'], 2, ',', ' ') }} MAD réintégré au brut imposable</small>
                                        @endif
                                        <span class="badge text-bg-light badge-legal ms-1">Arrêté 1314-25</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">Plafond: {{ number_format($ind['plafond'], 0, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">exo.</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-success">+ {{ number_format($ind['retenu'], 2, ',', ' ') }}</td>
                                </tr>
                                @endforeach

                                {{-- Retenues diverses --}}
                                @if($r['mutuelle_salarie'] > 0)
                                <tr class="row-retenue">
                                    <td class="px-3 py-2">Mutuelle — part salarié</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-danger">− {{ number_format($r['mutuelle_salarie'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                @if($r['autres_retenues'] > 0)
                                <tr class="row-retenue">
                                    <td class="px-3 py-2">Autres retenues (avances, saisies…)</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-danger">− {{ number_format($r['autres_retenues'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                {{-- NET --}}
                                <tr class="row-net">
                                    <td class="px-3 py-3" colspan="3">
                                        <i class="bi bi-check-circle-fill text-success me-1"></i>NET À PAYER
                                    </td>
                                    <td class="text-end px-3 py-3 fs-5">{{ number_format($r['salaire_net'], 2, ',', ' ') }} MAD</td>
                                </tr>

                                {{-- COTISATIONS PATRONALES --}}
                                <tr class="table-light">
                                    <td class="px-3 py-2 fw-semibold text-muted small" colspan="4">
                                        <i class="bi bi-building-up me-1 text-warning"></i>Cotisations patronales (charge employeur)
                                    </td>
                                </tr>

                                <tr class="row-patron">
                                    <td class="px-3 py-2">
                                        CNSS employeur
                                        <span class="badge text-bg-light badge-legal ms-1">Dahir 1-72-184</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['assiette_cnss'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format(config('payroll.cnss.taux_patronal') * 100, 2, ',', '.') }}%</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-warning">{{ number_format($r['cout_cnss_patronal'], 2, ',', ' ') }}</td>
                                </tr>

                                <tr class="row-patron">
                                    <td class="px-3 py-2">
                                        AMO employeur
                                        <span class="badge text-bg-light badge-legal ms-1">Loi 65-00</span>
                                    </td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['sbi'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format(config('payroll.amo.taux_patronal') * 100, 2, ',', '.') }}%</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-warning">{{ number_format($r['cout_amo_patronal'], 2, ',', ' ') }}</td>
                                </tr>

                                <tr class="row-patron">
                                    <td class="px-3 py-2">Allocations familiales</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['sbi'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format(config('payroll.allocations_familiales.taux_patronal') * 100, 2, ',', '.') }}%</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-warning">{{ number_format($r['cout_af_patronal'], 2, ',', ' ') }}</td>
                                </tr>

                                <tr class="row-patron">
                                    <td class="px-3 py-2">Taxe de formation professionnelle (TFP)</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format($r['sbi'], 2, ',', ' ') }}</td>
                                    <td class="text-end px-3 py-2 text-muted">{{ number_format(config('payroll.taxe_formation.taux_patronal') * 100, 2, ',', '.') }}%</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-warning">{{ number_format($r['cout_tfp_patronal'], 2, ',', ' ') }}</td>
                                </tr>

                                @if($r['mutuelle_patronale'] > 0)
                                <tr class="row-patron">
                                    <td class="px-3 py-2">Mutuelle — part employeur</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 text-muted">—</td>
                                    <td class="text-end px-3 py-2 fw-semibold text-warning">{{ number_format($r['mutuelle_patronale'], 2, ',', ' ') }}</td>
                                </tr>
                                @endif

                                {{-- COÛT TOTAL EMPLOYEUR --}}
                                <tr class="row-employer">
                                    <td class="px-3 py-3" colspan="3">
                                        <i class="bi bi-building-up text-warning me-1"></i>COÛT TOTAL EMPLOYEUR
                                        <small class="text-muted fw-normal ms-2">(SBI + charges patronales)</small>
                                    </td>
                                    <td class="text-end px-3 py-3 fs-5" style="color:#e65100">{{ number_format($r['cout_total_employeur'], 2, ',', ' ') }} MAD</td>
                                </tr>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Graphique + Récap --}}
        <div class="col-lg-5">

            {{-- Donut --}}
            <div class="card section-card mb-4">
                <div class="card-header px-4 py-3">
                    <i class="bi bi-pie-chart-fill me-2 text-primary"></i>Répartition du salaire brut
                </div>
                <div class="card-body text-center py-3">
                    <canvas id="payrollChart" style="max-height:260px"></canvas>
                    <div class="mt-3" id="chartLegend"></div>
                </div>
            </div>

            {{-- Récap IR --}}
            <div class="card section-card mb-4">
                <div class="card-header px-4 py-3">
                    <i class="bi bi-percent me-2 text-danger"></i>Barème IR — Tranche applicable
                </div>
                <div class="card-body px-4 py-3">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted">RNI mensuel</td>
                                <td class="fw-semibold text-end">{{ number_format($r['rni'], 2, ',', ' ') }} MAD</td>
                            </tr>
                            <tr>
                                <td class="text-muted">RNI annualisé</td>
                                <td class="fw-semibold text-end">{{ number_format($r['rni_annuel'], 2, ',', ' ') }} MAD</td>
                            </tr>
                            @if($r['rc_deduite'] > 0)
                            <tr>
                                <td class="text-muted">− Retraite complémentaire</td>
                                <td class="fw-semibold text-end text-success">− {{ number_format($r['rc_deduite'], 2, ',', ' ') }} MAD</td>
                            </tr>
                            <tr>
                                <td class="text-muted">RNI imposable</td>
                                <td class="fw-semibold text-end">{{ number_format($r['rni_annuel_net'], 2, ',', ' ') }} MAD</td>
                            </tr>
                            @endif
                            <tr class="table-danger">
                                <td>Taux marginal</td>
                                <td class="fw-bold text-end text-danger">{{ round($r['tranche_ir']['taux'] * 100) }}%</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Déduction fixe</td>
                                <td class="fw-semibold text-end">{{ number_format($r['tranche_ir']['deduction'], 0, ',', ' ') }} MAD</td>
                            </tr>
                            <tr>
                                <td class="text-muted">IR annuel brut</td>
                                <td class="fw-semibold text-end">{{ number_format($r['ir_annuel_brut'], 2, ',', ' ') }} MAD</td>
                            </tr>
                            @if($r['charges_famille'] > 0)
                            <tr>
                                <td class="text-muted">Charges famille</td>
                                <td class="fw-semibold text-end text-success">− {{ number_format($r['charges_famille'], 2, ',', ' ') }} MAD</td>
                            </tr>
                            @endif
                            <tr class="table-warning">
                                <td class="fw-bold">IR mensuel net</td>
                                <td class="fw-bold text-end">{{ number_format($r['ir_net'], 2, ',', ' ') }} MAD</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Récap employeur --}}
            <div class="card section-card mb-4" style="border-left:3px solid #fd7e14">
                <div class="card-header px-4 py-3">
                    <i class="bi bi-building-up me-2 text-warning"></i>Coût employeur détaillé
                </div>
                <div class="card-body px-4 py-3">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr><td class="text-muted">SBI</td><td class="fw-semibold text-end">{{ number_format($r['sbi'], 2, ',', ' ') }} MAD</td></tr>
                            <tr><td class="text-muted">CNSS patronal ({{ number_format(config('payroll.cnss.taux_patronal') * 100, 2, ',', '.') }}%)</td><td class="fw-semibold text-end text-warning">+ {{ number_format($r['cout_cnss_patronal'], 2, ',', ' ') }}</td></tr>
                            <tr><td class="text-muted">AMO patronale ({{ number_format(config('payroll.amo.taux_patronal') * 100, 2, ',', '.') }}%)</td><td class="fw-semibold text-end text-warning">+ {{ number_format($r['cout_amo_patronal'], 2, ',', ' ') }}</td></tr>
                            <tr><td class="text-muted">All. familiales ({{ number_format(config('payroll.allocations_familiales.taux_patronal') * 100, 2, ',', '.') }}%)</td><td class="fw-semibold text-end text-warning">+ {{ number_format($r['cout_af_patronal'], 2, ',', ' ') }}</td></tr>
                            <tr><td class="text-muted">TFP ({{ number_format(config('payroll.taxe_formation.taux_patronal') * 100, 2, ',', '.') }}%)</td><td class="fw-semibold text-end text-warning">+ {{ number_format($r['cout_tfp_patronal'], 2, ',', ' ') }}</td></tr>
                            @if($r['mutuelle_patronale'] > 0)
                            <tr><td class="text-muted">Mutuelle employeur</td><td class="fw-semibold text-end text-warning">+ {{ number_format($r['mutuelle_patronale'], 2, ',', ' ') }}</td></tr>
                            @endif
                            <tr class="table-warning">
                                <td class="fw-bold">Coût total employeur</td>
                                <td class="fw-bold text-end" style="color:#e65100">{{ number_format($r['cout_total_employeur'], 2, ',', ' ') }} MAD</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- CTA --}}
            <div class="d-flex gap-2">
                <a href="{{ route('calculator.index') }}" class="btn btn-success flex-fill">
                    <i class="bi bi-arrow-left me-1"></i>Nouveau calcul
                </a>
                <button onclick="window.print()" class="btn btn-outline-secondary flex-fill">
                    <i class="bi bi-printer me-1"></i>Imprimer
                </button>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
const repartition = @json($r['repartition']);

const labels = {
    net:      'Net à payer',
    cnss:     'CNSS ({{ number_format(config('payroll.cnss.taux') * 100, 2, ',', '.') }}%)',
    amo:      'AMO ({{ number_format(config('payroll.amo.taux') * 100, 2, ',', '.') }}%)',
    cimr:     'CIMR',
    ir:       'IR retenu',
    retenues: 'Autres retenues',
};

const activeKeys = Object.keys(repartition).filter(k => repartition[k].montant > 0);
const data       = activeKeys.map(k => repartition[k].montant);
const colors     = activeKeys.map(k => repartition[k].color);
const chartLabels = activeKeys.map(k => labels[k]);

const ctx = document.getElementById('payrollChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: chartLabels,
        datasets: [{ data: data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }]
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
                        const amt = repartition[k].montant.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        return ` ${amt} MAD (${pct}%)`;
                    }
                }
            }
        }
    }
});

const legend = document.getElementById('chartLegend');
legend.innerHTML = activeKeys.map((k, i) => {
    const pct = repartition[k].pct;
    const amt = repartition[k].montant.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return `<div class="d-flex align-items-center justify-content-between mb-1 small">
        <span><span style="display:inline-block;width:12px;height:12px;background:${colors[i]};border-radius:2px;margin-right:6px"></span>${chartLabels[i]}</span>
        <span class="text-muted">${amt} MAD <strong>(${pct}%)</strong></span>
    </div>`;
}).join('');
</script>
@endpush
