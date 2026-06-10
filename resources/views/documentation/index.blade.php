@extends('layouts.app')

@section('title', '3omar — Documentation legale 2026')

@section('content')
<div class="container">

    <div class="row mb-4">
        <div class="col">
            <h2 class="fw-bold mb-1"><i class="bi bi-journal-text me-2" style="color:var(--g-500)"></i>Documentation legale 2026</h2>
            <p style="color:var(--ink-2)">
                Tous les taux, plafonds et baremes utilises par 3omar — Secteur prive marocain.
                <span class="badge rounded-pill ms-1 px-2 py-1" style="background:var(--g-50);color:var(--g-700);border:1px solid var(--g-200)">Taux a jour : CGI 2026 — Loi de Finances 50-25</span>
            </p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">

            {{-- ============================================================ --}}
            {{-- CNSS                                                          --}}
            {{-- ============================================================ --}}
            <div class="card section-card mb-4">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-info)"><i class="bi bi-building"></i></span>
                    <span>CNSS — Caisse Nationale de Sécurité Sociale</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">Référence : <code>Dahir portant loi n° 1-72-184 du 27 juillet 1972</code></p>
                    <table class="table table-sm">
                        <thead class="table-light"><tr><th>Paramètre</th><th>Salarié</th><th>Employeur</th><th class="text-muted">Note</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Taux global</td>
                                <td class="fw-bold">{{ number_format($payroll['cnss']['taux'] * 100, 2, ',', '.') }}%</td>
                                <td class="fw-bold">{{ number_format($payroll['cnss']['taux_patronal'] * 100, 2, ',', '.') }}%</td>
                                <td class="text-muted">CT + LT</td>
                            </tr>
                            <tr>
                                <td>Plafond mensuel</td>
                                <td class="fw-bold" colspan="2">{{ number_format($payroll['cnss']['plafond'], 0, ',', ' ') }} MAD</td>
                                <td class="text-muted">Assiette plafonnée</td>
                            </tr>
                            <tr>
                                <td>Cotisation max salarié/mois</td>
                                <td class="fw-bold">{{ number_format($payroll['cnss']['plafond'] * $payroll['cnss']['taux'], 2, ',', ' ') }} MAD</td>
                                <td class="text-muted">—</td>
                                <td class="text-muted">{{ number_format($payroll['cnss']['plafond'], 0, ',', ' ') }} × {{ number_format($payroll['cnss']['taux'] * 100, 2, ',', '.') }}%</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="alert alert-info py-2 small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Le plafond s'applique sur le SBI : si SBI &gt; {{ number_format($payroll['cnss']['plafond'], 0, ',', ' ') }} MAD,
                        la cotisation salarié est fixe à {{ number_format($payroll['cnss']['plafond'] * $payroll['cnss']['taux'], 2, ',', ' ') }} MAD/mois.
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- AMO                                                           --}}
            {{-- ============================================================ --}}
            <div class="card section-card mb-4">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-cot)"><i class="bi bi-heart-pulse"></i></span>
                    <span>AMO — Assurance Maladie Obligatoire</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">Référence : <code>Loi n° 65-00 portant code de la couverture médicale de base</code></p>
                    <table class="table table-sm">
                        <thead class="table-light"><tr><th>Paramètre</th><th>Salarié</th><th>Employeur</th><th class="text-muted">Note</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Taux</td>
                                <td class="fw-bold">{{ number_format($payroll['amo']['taux'] * 100, 2, ',', '.') }}%</td>
                                <td class="fw-bold">{{ number_format($payroll['amo']['taux_patronal'] * 100, 2, ',', '.') }}%</td>
                                <td class="text-muted">Sans plafond</td>
                            </tr>
                            <tr>
                                <td>Base de calcul</td>
                                <td class="fw-bold" colspan="2">SBI total</td>
                                <td class="text-muted">Intégralité du SBI</td>
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
                    <span class="badge rounded-circle p-2" style="background:var(--s-warn)"><i class="bi bi-building-up"></i></span>
                    <span>Charges patronales complémentaires</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">Charges exclusivement à la charge de l'employeur (non prélevées sur le salarié).</p>
                    <table class="table table-sm">
                        <thead class="table-light"><tr><th>Charge</th><th class="text-center">Taux</th><th>Plafond</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Allocations familiales</td>
                                <td class="text-center fw-bold">{{ number_format($payroll['allocations_familiales']['taux_patronal'] * 100, 2, ',', '.') }}%</td>
                                <td class="text-muted">Sans plafond</td>
                            </tr>
                            <tr>
                                <td>Taxe de Formation Professionnelle (TFP)</td>
                                <td class="text-center fw-bold">{{ number_format($payroll['taxe_formation']['taux_patronal'] * 100, 2, ',', '.') }}%</td>
                                <td class="text-muted">Sans plafond</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="alert alert-secondary py-2 small mb-0">
                        <strong>Coût total employeur</strong> = SBI
                        + CNSS patronal ({{ number_format($payroll['cnss']['taux_patronal'] * 100, 2, ',', '.') }}%)
                        + AMO patronale ({{ number_format($payroll['amo']['taux_patronal'] * 100, 2, ',', '.') }}%)
                        + AF ({{ number_format($payroll['allocations_familiales']['taux_patronal'] * 100, 2, ',', '.') }}%)
                        + TFP ({{ number_format($payroll['taxe_formation']['taux_patronal'] * 100, 2, ',', '.') }}%)
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- CIMR                                                          --}}
            {{-- ============================================================ --}}
            <div class="card section-card mb-4">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-succ)"><i class="bi bi-piggy-bank"></i></span>
                    <span>CIMR — Caisse Interprofessionnelle Marocaine de Retraite</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">Référence : <code>Article 28-III CGI — Loi n° 64-12</code></p>
                    <table class="table table-sm">
                        <tbody>
                            <tr><td>Fourchette de taux salarié</td><td class="fw-bold">{{ round($payroll['cimr']['taux_min'] * 100) }}% à {{ round($payroll['cimr']['taux_max'] * 100) }}%</td><td class="text-muted">Librement choisi</td></tr>
                            <tr><td>Plafond d'assiette</td><td class="fw-bold">Aucun</td><td class="text-muted">SBI total</td></tr>
                            <tr><td>Déductibilité IR</td><td class="fw-bold" style="color:var(--s-succ)">100%</td><td class="text-muted">Déduit intégralement de l'assiette</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- IR BAREME                                                     --}}
            {{-- ============================================================ --}}
            <div class="card section-card mb-4">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-tax)"><i class="bi bi-percent"></i></span>
                    <span>Barème IR annuel 2026 — Article 73 CGI</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">
                        Formule : <code>IR annuel = RNI_annuel × taux − déduction_fixe</code><br>
                        <small>Le RNI mensuel est annualisé (× {{ $payroll['ir']['nb_mois'] }}) pour déterminer la tranche, puis l'IR annuel est divisé par {{ $payroll['ir']['nb_mois'] }}.</small>
                    </p>
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Tranche de RNI annuel (MAD)</th>
                                <th class="text-center">Taux marginal</th>
                                <th class="text-end">Déduction fixe (MAD)</th>
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
                                        et plus
                                    @endif
                                </td>
                                <td class="text-center fw-bold">{{ round($t['taux'] * 100) }}%</td>
                                <td class="text-end">{{ number_format($t['deduction'], 0, ',', ' ') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="alert alert-secondary py-2 small mb-0">
                        <strong>Exemple :</strong> RNI annuel = 90 000 MAD →
                        IR = 90 000 × 30% − 18 000 = <strong>9 000 MAD/an</strong> → 750 MAD/mois
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- FRAIS PRO                                                     --}}
            {{-- ============================================================ --}}
            <div class="card section-card mb-4">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-warn)"><i class="bi bi-briefcase"></i></span>
                    <span>Frais Professionnels — Article 59 I-A CGI</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">Déductibles de l'assiette IR (non remboursés au salarié). Calculés sur le salaire brut imposable (SBI), dans la limite du plafond mensuel. Seuil mensuel : <strong>{{ number_format($payroll['frais_pro']['seuil_mensuel'], 0, ',', ' ') }} MAD</strong>.</p>
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr><th>Catégorie</th><th class="text-center">Taux</th><th class="text-end">Plafond mensuel</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Salarié commun (SBI ≤ {{ number_format($payroll['frais_pro']['seuil_mensuel'], 0, ',', ' ') }} MAD)</td>
                                <td class="text-center fw-bold">{{ round($payroll['frais_pro']['commun']['bas']['taux'] * 100) }}%</td>
                                <td class="text-end">{{ number_format($payroll['frais_pro']['commun']['bas']['plafond'], 2, ',', ' ') }} MAD</td>
                            </tr>
                            <tr>
                                <td>Salarié commun (SBI &gt; {{ number_format($payroll['frais_pro']['seuil_mensuel'], 0, ',', ' ') }} MAD)</td>
                                <td class="text-center fw-bold">{{ round($payroll['frais_pro']['commun']['haut']['taux'] * 100) }}%</td>
                                <td class="text-end">{{ number_format($payroll['frais_pro']['commun']['haut']['plafond'], 2, ',', ' ') }} MAD</td>
                            </tr>
                            <tr>
                                <td>Journaliste / Correspondant de presse</td>
                                <td class="text-center fw-bold">{{ round($payroll['frais_pro']['journaliste']['taux'] * 100) }}%</td>
                                <td class="text-end">{{ number_format($payroll['frais_pro']['journaliste']['plafond'], 2, ',', ' ') }} MAD</td>
                            </tr>
                            <tr>
                                <td>Artiste / Créateur</td>
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
            <div class="card section-card mb-4">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-info)"><i class="bi bi-hourglass-split"></i></span>
                    <span>Prime d'ancienneté — Article 350 Code du Travail</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">Référence : <code>Loi n° 65-99 (Code du Travail) — Art. 350</code> — Calculée sur le salaire de base mensuel brut.</p>
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr><th>Ancienneté</th><th class="text-center">Taux</th><th>Exemple (SB = 5 000 MAD)</th></tr>
                        </thead>
                        <tbody>
                            <tr class="table-light">
                                <td>Moins de 2 ans</td>
                                <td class="text-center fw-bold">0%</td>
                                <td class="text-muted">0,00 MAD</td>
                            </tr>
                            @foreach($payroll['anciennete']['tranches'] as $t)
                            <tr>
                                <td>
                                    {{ $t['min_annees'] }}
                                    @if($t['max_annees'] !== null)
                                        à {{ $t['max_annees'] }} ans
                                    @else
                                        ans et plus
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
                    <span class="badge rounded-circle p-2" style="background:var(--s-info)"><i class="bi bi-bank"></i></span>
                    <span>Retraite complémentaire (Bancassurance) — {{ $payroll['retraite_complementaire']['article'] }}</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">Référence : <code>{{ $payroll['retraite_complementaire']['article'] }}</code></p>
                    <table class="table table-sm">
                        <tbody>
                            <tr>
                                <td>Déductibilité IR (plafond)</td>
                                <td class="fw-bold" style="color:var(--s-succ)">{{ round($payroll['retraite_complementaire']['deduction_ir_max_pct'] * 100) }}% du SBI annuel</td>
                            </tr>
                            <tr>
                                <td>Base de calcul du plafond</td>
                                <td class="fw-bold">SBI mensuel × {{ $payroll['ir']['nb_mois'] }}</td>
                            </tr>
                            <tr>
                                <td>Exemple (SBI = 8 000 MAD/mois)</td>
                                <td class="text-muted">
                                    Plafond = 8 000 × {{ $payroll['ir']['nb_mois'] }} × {{ round($payroll['retraite_complementaire']['deduction_ir_max_pct'] * 100) }}%
                                    = {{ number_format(8000 * $payroll['ir']['nb_mois'] * $payroll['retraite_complementaire']['deduction_ir_max_pct'], 0, ',', ' ') }} MAD/an
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="alert alert-info py-2 small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        La cotisation annuelle versée à un contrat de retraite complémentaire (assurance-vie, PERCO bancaire…)
                        est déductible de l'assiette IR à hauteur de {{ round($payroll['retraite_complementaire']['deduction_ir_max_pct'] * 100) }}%
                        du salaire brut annuel. L'excédent n'est pas déductible.
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- INDEMNITÉS                                                    --}}
            {{-- ============================================================ --}}
            <div class="card section-card mb-4">
                <div class="card-header px-4 py-3 d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2" style="background:var(--s-succ)"><i class="bi bi-gift"></i></span>
                    <span>Indemnités exonérées — Arrêté n° 1314-25 / BO n° 7443 du 29/09/2025</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">Ces indemnités sont exonérées à la fois de CNSS et d'IR dans les limites légales. Tout excédent au-delà du plafond est réintégré au salaire brut imposable.</p>
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr><th>Type d'indemnité</th><th class="text-end">Plafond légal</th></tr>
                        </thead>
                        <tbody>
                            @foreach($indemnites_config as $key => $cfg)
                            <tr>
                                <td>{{ $cfg['label'] }}</td>
                                <td class="text-end fw-semibold">
                                    @if($cfg['base_salaire'])
                                        {{ round($cfg['pct'] * 100) }}% du salaire de base
                                    @elseif(!empty($cfg['par_jour']))
                                        {{ number_format($cfg['montant'], 2, ',', ' ') }} MAD/jour travaillé
                                    @else
                                        {{ number_format($cfg['montant'], 0, ',', ' ') }} MAD/mois
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
                    <span class="badge rounded-circle p-2" style="background:var(--s-info)"><i class="bi bi-clock-history"></i></span>
                    <span>SMIG 2026 &amp; Heures supplémentaires</span>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted mb-3">Référence : <code>Décret n° 2.25.983</code> · <code>Art. 196 &amp; 201 Code du Travail (Loi 65-99)</code></p>
                    <table class="table table-sm mb-4">
                        <tbody>
                            <tr><td>SMIG horaire 2026</td><td class="fw-bold">{{ number_format($payroll['smig']['horaire'], 2, ',', ' ') }} MAD/h</td></tr>
                            <tr><td>SMIG mensuel ({{ $payroll['smig']['heures_legales'] }}h)</td><td class="fw-bold">{{ number_format($payroll['smig']['mensuel'], 2, ',', ' ') }} MAD/mois</td></tr>
                            <tr><td>Heures légales/mois</td><td class="fw-bold">{{ $payroll['smig']['heures_legales'] }} heures</td></tr>
                        </tbody>
                    </table>
                    <h6 class="fw-semibold">Majorations heures supplémentaires</h6>
                    <table class="table table-sm">
                        <thead class="table-light"><tr><th>Type</th><th class="text-center">Majoration</th></tr></thead>
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
                    <div class="form-text">Taux horaire = Salaire de base mensuel ÷ {{ $payroll['smig']['heures_legales'] }} heures</div>
                </div>
            </div>

        </div>

        {{-- ============================================================ --}}
        {{-- SIDEBAR                                                       --}}
        {{-- ============================================================ --}}
        <div class="col-lg-4">

            <div class="card section-card mb-4 sticky-top" style="top:80px">
                <div class="card-header px-4 py-3">
                    <i class="bi bi-link-45deg me-2" style="color:var(--g-500)"></i>Sources officielles
                </div>
                <div class="card-body px-4 py-3">
                    <ul class="list-unstyled mb-0">
                        @foreach([
                            ['titre' => 'Code Général des Impôts (CGI) 2026', 'desc' => 'Art. 28-III, 28-IV, 59, 73, 74'],
                            ['titre' => 'Code du Travail — Loi n° 65-99',     'desc' => 'Art. 196, 201, 350'],
                            ['titre' => 'Dahir n° 1-72-184 du 27/07/1972',    'desc' => 'CNSS — Sécurité sociale'],
                            ['titre' => 'Loi n° 65-00',                        'desc' => 'AMO — Couverture médicale'],
                            ['titre' => 'Arrêté n° 1314-25',                   'desc' => 'BO n° 7443 du 29/09/2025 — Indemnités'],
                            ['titre' => 'Décret n° 2.25.983',                  'desc' => 'SMIG 2026'],
                            ['titre' => 'Loi de Finances 50-25',               'desc' => 'CGI 2026 — Exercice fiscal'],
                        ] as $ref)
                        <li class="mb-3 pb-3 border-bottom">
                            <div class="fw-semibold small">{{ $ref['titre'] }}</div>
                            <div class="text-muted small">{{ $ref['desc'] }}</div>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="card section-card mb-4" style="border-color:var(--s-warn)">
                <div class="card-body px-4 py-3">
                    <h6 class="fw-bold" style="color:var(--s-warn)"><i class="bi bi-exclamation-triangle me-1"></i>Avertissement</h6>
                    <p class="small text-muted mb-0">
                        Ce simulateur est fourni à titre pédagogique uniquement.
                        Les résultats peuvent différer du calcul réel effectué par votre employeur
                        ou votre logiciel de paie. Consultez un expert-comptable pour votre situation exacte.
                    </p>
                </div>
            </div>

            <div class="card section-card">
                <div class="card-body px-4 py-3">
                    <h6 class="fw-semibold mb-2"><i class="bi bi-gear me-1" style="color:var(--s-succ)"></i>Transparence totale</h6>
                    <p class="small text-muted mb-2">
                        Tous les taux affiches ici et utilises dans le simulateur proviennent d'une
                        source unique. Le code est <a href="https://github.com/Zakmaf/3omar" target="_blank" rel="noopener">open source</a> :
                        chaque valeur est verifiable et corrigeable par la communaute.
                    </p>
                    <div class="rounded p-2" style="background:var(--g-800); font-family:var(--f-mono); font-size:.75rem; color:var(--cream)">
                        <span style="color:var(--ink-3)">// Exercice {{ $payroll['year'] }}</span><br>
                        <span style="color:var(--g-300)">'cnss.taux' => {{ $payroll['cnss']['taux'] }}</span><br>
                        <span style="color:var(--g-300)">'amo.taux' => {{ $payroll['amo']['taux'] }}</span><br>
                        <span style="color:var(--g-300)">'smig.mensuel' => {{ $payroll['smig']['mensuel'] }}</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
