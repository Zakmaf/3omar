@extends('layouts.app')

@section('title', '3omar — '.__('ui.calculator.title'))

@section('content')
<div class="container">

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

    <form method="POST" action="{{ route('calculator.calculer') }}" id="payrollForm">
        @csrf

        <div class="section-card p-3 p-md-4 mb-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <div class="fw-semibold">{{ __('ui.calculator.simple_title') }}</div>
                <div class="small" style="color:var(--ink-2)">{{ __('ui.calculator.simple_text') }}</div>
            </div>
            <button type="button" class="btn px-4 fw-semibold" id="toggleAdvanced"
                    aria-expanded="{{ $errors->any() || old('nb_annees_anciennete') || old('prime_bilan') || old('prime_rendement') || old('autres_primes') || old('heures_sup') || old('cimr_actif') || old('mutuelle_salarie') || old('mutuelle_patronale') || old('retraite_complementaire_mensuel') || old('indemnites') || old('autres_retenues') ? 'true' : 'false' }}"
                    style="border:1px solid var(--g-500);color:var(--g-600)">
                <i class="bi bi-sliders me-1"></i><span>{{ __('ui.calculator.advanced_show') }}</span>
            </button>
        </div>

        <div class="row g-4">

            {{-- ============================================================ --}}
            {{-- COLONNE GAUCHE                                                --}}
            {{-- ============================================================ --}}
            <div class="col-lg-6">

                {{-- 1. Rémunération de base --}}
                <div class="card section-card mb-4">
                    <div class="card-header px-4 py-3">
                        <i class="bi bi-cash-coin me-2" style="color:var(--s-info)"></i>Salaire de base
                    </div>
                    <div class="card-body px-4 py-3">

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="salaire_base">Salaire de base brut <span style="color:var(--s-tax)">*</span></label>
                            <div class="input-group">
                                <input type="number" name="salaire_base" id="salaire_base"
                                       class="form-control @error('salaire_base') is-invalid @enderror"
                                       value="{{ old('salaire_base', 5000) }}"
                                       min="0" step="0.01" placeholder="Ex : 8 500" required inputmode="decimal"
                                       aria-describedby="salaireBaseHelp">
                                <span class="input-group-text">MAD</span>
                            </div>
                            <div class="form-text" id="salaireBaseHelp">Montant brut mensuel avant cotisations. Repère SMIG simulé : {{ number_format(config('payroll.smig.mensuel'), 2, ',', ' ') }} MAD/mois.</div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-semibold" for="type_frais_pro">Catégorie professionnelle (frais pro)</label>
                            <select name="type_frais_pro" id="type_frais_pro" class="form-select">
                                <option value="commun"      {{ old('type_frais_pro','commun') === 'commun'      ? 'selected' : '' }}>Salarié commun (35% ou 25% selon SBI)</option>
                                <option value="journaliste" {{ old('type_frais_pro','commun') === 'journaliste' ? 'selected' : '' }}>Journaliste / Correspondant de presse (45%)</option>
                                <option value="artiste"     {{ old('type_frais_pro','commun') === 'artiste'     ? 'selected' : '' }}>Artiste / Créateur (40%)</option>
                            </select>
                            <div class="form-text">Art. 59 I-A CGI — plafond {{ number_format(config('payroll.frais_pro.commun.haut.plafond'), 2, ',', ' ') }} MAD/mois</div>
                        </div>

                    </div>
                </div>

                {{-- 2. Primes et ancienneté --}}
                <div class="card section-card mb-4 advanced-section">
                    <div class="card-header px-4 py-3">
                        <i class="bi bi-cash-stack me-2" style="color:var(--s-warn)"></i>Primes imposables
                    </div>
                    <div class="card-body px-4 py-3">

                        {{-- Ancienneté --}}
                        <div class="p-3 rounded-2 mb-3" style="background:var(--s-warn-bg); border:1px solid rgba(217,119,6,0.2);">
                            <div class="fw-semibold small mb-2">
                                <i class="bi bi-hourglass-split me-1" style="color:var(--s-warn)"></i>Prime d'ancienneté (Art. 350 Code du Travail)
                            </div>
                            <div class="row g-2 align-items-end">
                                <div class="col-7">
                                    <label class="form-label small mb-1" for="nb_annees_anciennete">Années d'ancienneté</label>
                                    <input type="number" name="nb_annees_anciennete" id="nb_annees_anciennete"
                                           class="form-control form-control-sm"
                                           value="{{ old('nb_annees_anciennete', 0) }}"
                                           min="0" max="50" step="1" placeholder="0">
                                </div>
                                <div class="col-5 text-end">
                                    <div class="small text-muted">Taux applicable :</div>
                                    <div class="fw-bold" id="anciennete_taux_label">—</div>
                                    <div class="small" id="anciennete_montant_label" style="color:var(--s-succ)"></div>
                                </div>
                            </div>
                            <div class="form-text mt-1">
                                &lt;2 ans : 0% · 2–4 ans : 5% · 5–11 ans : 10% · 12–19 ans : 15% · 20–24 ans : 20% · ≥25 ans : 25%
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small" for="prime_bilan">Prime de bilan / 13<sup>ème</sup> mois <span class="text-muted fw-normal">(équivalent mensuel)</span></label>
                            <div class="input-group">
                                <input type="number" name="prime_bilan" id="prime_bilan"
                                       class="form-control @error('prime_bilan') is-invalid @enderror"
                                       value="{{ old('prime_bilan', 0) }}"
                                       min="0" step="0.01" placeholder="0">
                                <span class="input-group-text">MAD</span>
                            </div>
                            <div class="form-text">Ex : 13<sup>ème</sup> mois annuel ÷ 12 — entièrement imposable</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small" for="prime_rendement">Prime de rendement / performance</label>
                            <div class="input-group">
                                <input type="number" name="prime_rendement" id="prime_rendement"
                                       class="form-control @error('prime_rendement') is-invalid @enderror"
                                       value="{{ old('prime_rendement', 0) }}"
                                       min="0" step="0.01" placeholder="0">
                                <span class="input-group-text">MAD</span>
                            </div>
                        </div>

                        <div class="mb-1">
                            <label class="form-label fw-semibold small" for="autres_primes">Autres primes imposables</label>
                            <div class="input-group">
                                <input type="number" name="autres_primes" id="autres_primes"
                                       class="form-control @error('autres_primes') is-invalid @enderror"
                                       value="{{ old('autres_primes', 0) }}"
                                       min="0" step="0.01" placeholder="0">
                                <span class="input-group-text">MAD</span>
                            </div>
                            <div class="form-text">Toutes autres primes soumises à cotisations sociales et IR</div>
                        </div>

                    </div>
                </div>

                {{-- 3. Heures supplémentaires --}}
                <div class="card section-card mb-4 advanced-section">
                    <div class="card-header px-4 py-3 d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-clock-history me-2" style="color:var(--s-info)"></i>Heures supplémentaires</span>
                        <button type="button" class="btn btn-sm" id="addHS" style="border:1px solid var(--s-info);color:var(--s-info)">
                            <i class="bi bi-plus-circle me-1"></i>Ajouter
                        </button>
                    </div>
                    <div class="card-body px-4 py-3">
                        <div class="form-text mb-3">Art. 201 Code du Travail (Loi n° 65-99) — Taux horaire = Salaire base / 191 h</div>
                        <div id="hsContainer">
                            @if(old('heures_sup'))
                                @foreach(old('heures_sup') as $i => $hs)
                                <div class="hs-row row g-2 mb-2 align-items-end">
                                    <div class="col-7">
                                        <label class="form-label small mb-1" for="heures_sup_{{ $i }}_type">Type</label>
                                        <select name="heures_sup[{{ $i }}][type]" id="heures_sup_{{ $i }}_type" class="form-select form-select-sm">
                                            @foreach($hs_labels as $key => $label)
                                            <option value="{{ $key }}" {{ ($hs['type'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small mb-1" for="heures_sup_{{ $i }}_nb_heures">Heures</label>
                                        <input type="number" name="heures_sup[{{ $i }}][nb_heures]" id="heures_sup_{{ $i }}_nb_heures"
                                               class="form-control form-control-sm"
                                               value="{{ $hs['nb_heures'] ?? '' }}" min="0" step="0.5">
                                    </div>
                                    <div class="col-1">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Supprimer" aria-label="Supprimer cette ligne d'heures supplémentaires">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                @endforeach
                            @endif
                        </div>
                        <p class="text-muted small mb-0" id="hsPlaceholder" {{ old('heures_sup') ? 'style=display:none' : '' }}>
                            <i class="bi bi-info-circle me-1"></i>Cliquez « Ajouter » pour saisir des heures supplémentaires.
                        </p>
                    </div>
                </div>

                {{-- 4. CIMR --}}
                <div class="card section-card mb-4 advanced-section">
                    <div class="card-header px-4 py-3">
                        <i class="bi bi-piggy-bank me-2" style="color:var(--s-cot)"></i>Retraite complémentaire (CIMR)
                    </div>
                    <div class="card-body px-4 py-3">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="cimr_actif" id="cimrActif"
                                   value="1" {{ old('cimr_actif') ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="cimrActif">Cotisant à la CIMR</label>
                        </div>
                        <div id="cimrSection" style="{{ old('cimr_actif') ? '' : 'display:none' }}">
                            <label class="form-label fw-semibold" for="cimrTaux">Taux CIMR salarié : <span id="cimrTauxVal">{{ old('cimr_taux', 5) }}%</span></label>
                            <input type="range" name="cimr_taux" id="cimrTaux" class="form-range"
                                   min="3" max="10" step="0.5" value="{{ old('cimr_taux', 5) }}">
                            <div class="d-flex justify-content-between small text-muted">
                                <span>3% (min)</span><span>10% (max)</span>
                            </div>
                            <div class="form-text mt-1">Taux librement choisi 3%–10%, 100% déductible IR (Art. 28-III CGI)</div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- ============================================================ --}}
            {{-- COLONNE DROITE                                                --}}
            {{-- ============================================================ --}}
            <div class="col-lg-6">

                {{-- 5. Charges de famille --}}
                <div class="card section-card mb-4 advanced-section">
                    <div class="card-header px-4 py-3">
                        <i class="bi bi-people-fill me-2" style="color:var(--s-succ)"></i>Charges de famille
                    </div>
                    <div class="card-body px-4 py-3">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold" for="nb_enfants">Enfants à charge</label>
                                <input type="number" name="nb_enfants" id="nb_enfants" class="form-control"
                                       value="{{ old('nb_enfants', 0) }}" min="0" max="20">
                            </div>
                            <div class="col-sm-6 d-flex align-items-end">
                                <div class="form-check pb-2">
                                    <input class="form-check-input" type="checkbox" name="conjoint_charge"
                                           id="conjointCharge" value="1" {{ old('conjoint_charge') ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="conjointCharge">Conjoint à charge</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-text mt-2">{{ number_format(config('payroll.charges_famille.par_personne'), 2, ',', ' ') }} MAD/mois × nombre de personnes, plafond {{ number_format(config('payroll.charges_famille.plafond'), 2, ',', ' ') }} MAD/mois (Art. 74 CGI)</div>
                    </div>
                </div>

                {{-- 6. Santé & Retraite complémentaire --}}
                <div class="card section-card mb-4 advanced-section">
                    <div class="card-header px-4 py-3">
                        <i class="bi bi-heart-pulse-fill me-2" style="color:var(--s-tax)"></i>Santé &amp; Retraite complémentaire
                    </div>
                    <div class="card-body px-4 py-3">

                        <div class="mb-3">
                            <label class="form-label fw-semibold small" for="mutuelle_salarie">Mutuelle — Part salarié</label>
                            <div class="input-group">
                                <input type="number" name="mutuelle_salarie" id="mutuelle_salarie"
                                       class="form-control @error('mutuelle_salarie') is-invalid @enderror"
                                       value="{{ old('mutuelle_salarie', 0) }}"
                                       min="0" step="0.01" placeholder="0">
                                <span class="input-group-text">MAD</span>
                            </div>
                            <div class="form-text">Retenue post-fiscale (déduite du net à payer)</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small" for="mutuelle_patronale">Mutuelle — Part employeur</label>
                            <div class="input-group">
                                <input type="number" name="mutuelle_patronale" id="mutuelle_patronale"
                                       class="form-control @error('mutuelle_patronale') is-invalid @enderror"
                                       value="{{ old('mutuelle_patronale', 0) }}"
                                       min="0" step="0.01" placeholder="0">
                                <span class="input-group-text">MAD</span>
                            </div>
                            <div class="form-text">Intégrée au coût total employeur</div>
                        </div>

                        <hr class="my-3">

                        <div class="mb-1">
                            <label class="form-label fw-semibold small" for="retraite_complementaire_mensuel">
                                <i class="bi bi-bank me-1" style="color:var(--s-info)"></i>
                                Retraite complémentaire bancassurance <span class="text-muted fw-normal">(mensuel)</span>
                            </label>
                            <div class="input-group">
                                <input type="number" name="retraite_complementaire_mensuel" id="retraite_complementaire_mensuel"
                                       class="form-control @error('retraite_complementaire_mensuel') is-invalid @enderror"
                                       value="{{ old('retraite_complementaire_mensuel', 0) }}"
                                       min="0" step="0.01" placeholder="0">
                                <span class="input-group-text">MAD</span>
                            </div>
                            <div class="form-text">
                                Déduction fiscale simulée dans la limite de 50% du SBI annuel.
                                Ce montant n'est pas soustrait du net à payer — Art. 28-IV CGI.
                            </div>
                        </div>

                    </div>
                </div>

                {{-- 7. Indemnités traitées comme exonérées --}}
                <div class="card section-card mb-4 advanced-section">
                    <div class="card-header px-4 py-3 d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-gift me-2" style="color:var(--s-succ)"></i>Indemnités traitées comme exonérées</span>
                        <button type="button" class="btn btn-sm" id="addIndemnite" style="border:1px solid var(--s-succ);color:var(--s-succ)">
                            <i class="bi bi-plus-circle me-1"></i>Ajouter
                        </button>
                    </div>
                    <div class="card-body px-4 py-3">
                        <div class="form-text mb-3">Le simulateur traite ces indemnités comme exonérées dans les plafonds configurés. Vérifie leur éligibilité selon ta situation ; l'excédent est réintégré au brut imposable.</div>
                        <div class="row g-2 mb-3 align-items-end">
                            <div class="col-7">
                                <label class="form-label small mb-1 fw-semibold" for="joursTravailles">Jours travaillés dans le mois</label>
                                <input type="number" name="jours_travailles" id="joursTravailles"
                                       class="form-control form-control-sm @error('jours_travailles') is-invalid @enderror"
                                       value="{{ old('jours_travailles', config('payroll.jours_travailles_defaut')) }}"
                                       min="1" max="31" step="1">
                            </div>
                            <div class="col-5">
                                <div class="form-text mb-1">Sert au plafond journalier (ex. panier)</div>
                            </div>
                        </div>
                        <div id="indemniteContainer">
                            @if(old('indemnites'))
                                @foreach(old('indemnites') as $i => $ind)
                                <div class="ind-row row g-2 mb-2 align-items-end">
                                    <div class="col-7">
                                        <label class="form-label small mb-1" for="indemnites_{{ $i }}_type">Type d'indemnité</label>
                                        <select name="indemnites[{{ $i }}][type]" id="indemnites_{{ $i }}_type" class="form-select form-select-sm ind-type-select">
                                            @foreach($indemnites_config as $key => $cfg)
                                            <option value="{{ $key }}" {{ ($ind['type'] ?? '') === $key ? 'selected' : '' }}
                                                    data-plafond="{{ $cfg['base_salaire'] ? ($cfg['pct'] * 100).'% du SB' : number_format($cfg['montant'], !empty($cfg['par_jour']) ? 2 : 0, ',', ' ').' MAD/'.(!empty($cfg['par_jour']) ? 'jour travaillé' : 'mois') }}">
                                                {{ $cfg['label'] }}
                                            </option>
                                            @endforeach
                                        </select>
                                        <div class="form-text ind-plafond-hint"></div>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small mb-1" for="indemnites_{{ $i }}_montant">Montant déclaré</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="indemnites[{{ $i }}][montant]" id="indemnites_{{ $i }}_montant"
                                                   class="form-control form-control-sm"
                                                   value="{{ $ind['montant'] ?? '' }}" min="0" step="0.01">
                                            <span class="input-group-text">MAD</span>
                                        </div>
                                    </div>
                                    <div class="col-1">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-row" aria-label="Supprimer cette indemnité">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                @endforeach
                            @endif
                        </div>
                        <p class="text-muted small mb-0" id="indemnitePlaceholder" {{ old('indemnites') ? 'style=display:none' : '' }}>
                            <i class="bi bi-info-circle me-1"></i>Clique « Ajouter » pour déclarer une indemnité.
                        </p>
                    </div>
                </div>

                {{-- 8. Autres retenues --}}
                <div class="card section-card mb-4 advanced-section">
                    <div class="card-header px-4 py-3">
                        <i class="bi bi-dash-circle me-2" style="color:var(--s-neutral)"></i>Autres retenues
                    </div>
                    <div class="card-body px-4 py-3">
                        <label class="form-label fw-semibold" for="autres_retenues">Autres retenues (montant total)</label>
                        <div class="input-group">
                            <input type="number" name="autres_retenues" id="autres_retenues" class="form-control"
                                   value="{{ old('autres_retenues', 0) }}" min="0" step="0.01" placeholder="0">
                            <span class="input-group-text">MAD</span>
                        </div>
                        <div class="form-text">Avances sur salaire, oppositions, saisies-arrêts… (post-fiscales, hors mutuelle)</div>
                    </div>
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
const ADVANCED_SHOW = @json(__('ui.calculator.advanced_show'));
const ADVANCED_HIDE = @json(__('ui.calculator.advanced_hide'));

// Tranches d'ancienneté (pour aperçu côté client)
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
    montantEl.textContent = taux > 0 ? '→ ' + montant.toLocaleString(INTL_LOCALE, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' MAD' : '';
}

document.getElementById('nb_annees_anciennete').addEventListener('input', updateAnciennete);
document.getElementById('salaire_base').addEventListener('input', updateAnciennete);
updateAnciennete(); // Init

// ---- Affichage progressif des options ----
const advancedToggle = document.getElementById('toggleAdvanced');
const advancedSections = document.querySelectorAll('.advanced-section');

function setAdvancedVisibility(visible) {
    advancedSections.forEach(section => { section.hidden = !visible; });
    advancedToggle.setAttribute('aria-expanded', visible ? 'true' : 'false');
    advancedToggle.querySelector('span').textContent = visible
        ? ADVANCED_HIDE
        : ADVANCED_SHOW;
}

advancedToggle.addEventListener('click', () => {
    setAdvancedVisibility(advancedToggle.getAttribute('aria-expanded') !== 'true');
});
setAdvancedVisibility(advancedToggle.getAttribute('aria-expanded') === 'true');

const formErrors = document.getElementById('formErrors');
if (formErrors) formErrors.focus();

// ---- Heures supplémentaires ----
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
            <label class="form-label small mb-1" for="heures_sup_${i}_type">Type</label>
            <select name="heures_sup[${i}][type]" id="heures_sup_${i}_type" class="form-select form-select-sm">${buildHsOptions('semaine_diurne')}</select>
        </div>
        <div class="col-4">
            <label class="form-label small mb-1" for="heures_sup_${i}_nb_heures">Heures</label>
            <input type="number" name="heures_sup[${i}][nb_heures]" id="heures_sup_${i}_nb_heures" class="form-control form-control-sm" value="" min="0" step="0.5" placeholder="0">
        </div>
        <div class="col-1">
            <button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Supprimer" aria-label="Supprimer cette ligne d'heures supplémentaires"><i class="bi bi-trash"></i></button>
        </div>`;
    document.getElementById('hsContainer').appendChild(row);
    document.getElementById('hsPlaceholder').style.display = 'none';
    row.querySelector('.remove-row').addEventListener('click', () => {
        row.remove();
        updateHsPlaceholder();
    });
});

function updateHsPlaceholder() {
    document.getElementById('hsPlaceholder').style.display =
        document.querySelectorAll('#hsContainer .hs-row').length ? 'none' : '';
}

// ---- Indemnités ----
let indIndex = {{ old('indemnites') ? count(old('indemnites')) : 0 }};

function buildIndOptions(selectedType) {
    return Object.entries(INDEMNITES_CONFIG).map(([k, cfg]) => {
        const plafondTxt = cfg.base_salaire
            ? `${(cfg.pct * 100).toFixed(0)}% du salaire de base`
            : cfg.par_jour
                ? `${cfg.montant.toLocaleString(INTL_LOCALE)} MAD/jour travaillé`
                : `${cfg.montant.toLocaleString(INTL_LOCALE)} MAD/mois`;
        return `<option value="${k}" ${k === selectedType ? 'selected' : ''} data-plafond="${plafondTxt}">${cfg.label}</option>`;
    }).join('');
}

function getPlafondHint(select) {
    const opt = select.options[select.selectedIndex];
    return opt ? `Plafond simulé : ${opt.dataset.plafond}` : '';
}

function createIndRow(i, selectedType) {
    const row = document.createElement('div');
    row.className = 'ind-row row g-2 mb-2 align-items-end';
    row.innerHTML = `
        <div class="col-7">
            <label class="form-label small mb-1" for="indemnites_${i}_type">Type d'indemnité</label>
            <select name="indemnites[${i}][type]" id="indemnites_${i}_type" class="form-select form-select-sm ind-type-select">${buildIndOptions(selectedType || Object.keys(INDEMNITES_CONFIG)[0])}</select>
            <div class="form-text ind-plafond-hint"></div>
        </div>
        <div class="col-4">
            <label class="form-label small mb-1" for="indemnites_${i}_montant">Montant déclaré</label>
            <div class="input-group input-group-sm">
                <input type="number" name="indemnites[${i}][montant]" id="indemnites_${i}_montant" class="form-control form-control-sm" value="" min="0" step="0.01" placeholder="0">
                <span class="input-group-text">MAD</span>
            </div>
        </div>
        <div class="col-1">
            <button type="button" class="btn btn-sm btn-outline-danger remove-row" aria-label="Supprimer cette indemnité"><i class="bi bi-trash"></i></button>
        </div>`;
    const sel = row.querySelector('.ind-type-select');
    const hint = row.querySelector('.ind-plafond-hint');
    sel.addEventListener('change', () => { hint.textContent = getPlafondHint(sel); });
    hint.textContent = getPlafondHint(sel);
    row.querySelector('.remove-row').addEventListener('click', () => {
        row.remove();
        updateIndemnitePlaceholder();
    });
    return row;
}

document.getElementById('addIndemnite').addEventListener('click', () => {
    const i = indIndex++;
    document.getElementById('indemniteContainer').appendChild(createIndRow(i, null));
    document.getElementById('indemnitePlaceholder').style.display = 'none';
});

function updateIndemnitePlaceholder() {
    document.getElementById('indemnitePlaceholder').style.display =
        document.querySelectorAll('#indemniteContainer .ind-row').length ? 'none' : '';
}

// Hints sur les lignes existantes (rechargement après erreur)
document.querySelectorAll('.ind-type-select').forEach(sel => {
    const hint = sel.closest('.col-7').querySelector('.ind-plafond-hint');
    if (hint) hint.textContent = getPlafondHint(sel);
    sel.addEventListener('change', () => { if (hint) hint.textContent = getPlafondHint(sel); });
});

// Boutons supprimer existants
document.querySelectorAll('.remove-row').forEach(btn => {
    btn.addEventListener('click', () => {
        const row = btn.closest('.hs-row, .ind-row');
        if (row) { row.remove(); updateHsPlaceholder(); updateIndemnitePlaceholder(); }
    });
});

// ---- CIMR toggle ----
const cimrActif   = document.getElementById('cimrActif');
const cimrSection = document.getElementById('cimrSection');
const cimrTaux    = document.getElementById('cimrTaux');
const cimrTauxVal = document.getElementById('cimrTauxVal');

cimrActif.addEventListener('change', () => {
    cimrSection.style.display = cimrActif.checked ? '' : 'none';
});
cimrTaux.addEventListener('input', () => {
    cimrTauxVal.textContent = cimrTaux.value + '%';
});
</script>
@endpush
