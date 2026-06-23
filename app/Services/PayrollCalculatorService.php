<?php

namespace App\Services;

/**
 * Service de calcul du bulletin de paie marocain — Exercice 2026
 *
 * Séquence réglementaire :
 *  0. Ancienneté = salaire_base × taux (Art. 350 Code du Travail)
 *  1. SBI = salaire_base + primes + heures_sup
 *         + excédent des indemnités au-delà des plafonds (part imposable)
 *  2. CNSS salarié = min(SBI, plafond) × 4,48%      (Dahir 1-72-184)
 *  3. AMO salarié  = SBI × 2,26%                    (Loi 65-00)
 *  4. CIMR salarié = SBI × taux_choisi               (Art. 28-III CGI)
 *     CIMR employeur = SBI × taux_employeur          (charge patronale)
 *  5. SNC  = SBI − CNSS − AMO − CIMR_salarié
 *  6. FP   = min(SBI × taux_fp, plafond_fp)          (Art. 59 I-A CGI — assiette = revenu brut imposable)
 *  7. RNI mensuel = SNC − FP
 *  8. IR   = barème(RNI × 12 − retraite_comp)       (Art. 73 CGI)
 *          − déduction charges de famille             (Art. 74 CGI)
 *  9. Indemnités exonérées (dans la limite des plafonds — Arrêté 1314-25)
 * 10. Avantages CNSS exonérés : imposables IR, exclus de CNSS/AMO
 * 11. Net  = SBI − CNSS − AMO − CIMR_salarié − IR + Indemnités exonérées − Retenues
 * 12. Coût employeur = brut total versé + cotisations patronales + CIMR employeur
 */
class PayrollCalculatorService
{
    private const NET_RESOLUTION_PRECISION = 0.01;

    private const NET_RESOLUTION_MAX_ITERATIONS = 80;

    private const NET_RESOLUTION_MAX_BASE = 10000000.0;

    private function r2(float $v): float
    {
        return round($v, 2);
    }

    private function calculerIRAnnuelBrut(float $rniAnnuel): array
    {
        if ($rniAnnuel <= 0) {
            $baremes = config('payroll.ir.baremes');

            return ['montant' => 0.0, 'tranche' => $baremes[0]];
        }

        $baremes = config('payroll.ir.baremes');
        $tranche = $baremes[count($baremes) - 1];

        foreach ($baremes as $t) {
            if ($t['max'] === null || $rniAnnuel <= $t['max']) {
                $tranche = $t;
                break;
            }
        }

        $ir = max(0.0, $rniAnnuel * $tranche['taux'] - $tranche['deduction']);

        return ['montant' => $this->r2($ir), 'tranche' => $tranche];
    }

    private function plafondIndemnite(string $type, float $salaireBase, int $joursTravailles): float
    {
        $config = config("payroll.indemnites.{$type}");
        if (! $config) {
            return 0.0;
        }
        if ($config['base_salaire']) {
            return $this->r2($salaireBase * $config['pct']);
        }
        if (! empty($config['par_jour'])) {
            return $this->r2($config['montant'] * $joursTravailles);
        }

        return (float) $config['montant'];
    }

    public function resoudreDepuisNet(array $input): array
    {
        $netCible = (float) ($input['net_cible'] ?? 0);

        if ($netCible <= 0) {
            throw new \InvalidArgumentException('Le net cible doit être strictement positif.');
        }

        $solverInput = $input;
        $borneBasse = 0.0;
        $resultatBas = $this->calculer(array_merge($solverInput, ['salaire_base' => $borneBasse]));
        $meilleurResultat = $resultatBas;
        $meilleurEcart = abs($resultatBas['salaire_net'] - $netCible);
        $iterations = 0;

        if ($resultatBas['salaire_net'] >= $netCible) {
            return $this->avecResolutionNet(
                $meilleurResultat,
                $netCible,
                $meilleurEcart,
                $iterations,
                $borneBasse,
                $borneBasse,
                $meilleurEcart <= self::NET_RESOLUTION_PRECISION,
                'Le net cible est déjà atteint ou dépassé avec un salaire de base nul. Vérifiez les indemnités et retenues fixes saisies.'
            );
        }

        $borneHaute = max($netCible * 2, (float) config('payroll.smig.mensuel'), 1000.0);
        $resultatHaut = $this->calculer(array_merge($solverInput, ['salaire_base' => $borneHaute]));

        while ($resultatHaut['salaire_net'] < $netCible && $borneHaute < self::NET_RESOLUTION_MAX_BASE) {
            $ecartHaut = abs($resultatHaut['salaire_net'] - $netCible);
            if ($ecartHaut < $meilleurEcart) {
                $meilleurEcart = $ecartHaut;
                $meilleurResultat = $resultatHaut;
            }

            $borneBasse = $borneHaute;
            $borneHaute *= 2;
            $resultatHaut = $this->calculer(array_merge($solverInput, ['salaire_base' => $borneHaute]));
        }

        $ecartHaut = abs($resultatHaut['salaire_net'] - $netCible);
        if ($ecartHaut < $meilleurEcart) {
            $meilleurEcart = $ecartHaut;
            $meilleurResultat = $resultatHaut;
        }

        if ($resultatHaut['salaire_net'] < $netCible) {
            return $this->avecResolutionNet(
                $meilleurResultat,
                $netCible,
                $meilleurEcart,
                $iterations,
                $borneBasse,
                $borneHaute,
                false,
                "Impossible d'encadrer le net cible dans le plafond technique du simulateur."
            );
        }

        for ($iterations = 1; $iterations <= self::NET_RESOLUTION_MAX_ITERATIONS; $iterations++) {
            $candidat = ($borneBasse + $borneHaute) / 2;
            $resultat = $this->calculer(array_merge($solverInput, ['salaire_base' => $candidat]));
            $ecart = abs($resultat['salaire_net'] - $netCible);

            if ($ecart < $meilleurEcart) {
                $meilleurEcart = $ecart;
                $meilleurResultat = $resultat;
            }

            if ($ecart <= self::NET_RESOLUTION_PRECISION) {
                break;
            }

            if ($resultat['salaire_net'] < $netCible) {
                $borneBasse = $candidat;
            } else {
                $borneHaute = $candidat;
            }
        }

        return $this->avecResolutionNet(
            $meilleurResultat,
            $netCible,
            $meilleurEcart,
            $iterations,
            $borneBasse,
            $borneHaute,
            $meilleurEcart <= self::NET_RESOLUTION_PRECISION,
            null
        );
    }

    private function avecResolutionNet(
        array $resultat,
        float $netCible,
        float $ecart,
        int $iterations,
        float $borneBasse,
        float $borneHaute,
        bool $converge,
        ?string $avertissement
    ): array {
        $ecartArrondi = $this->r2($ecart);

        $resultat['mode'] = 'net_to_gross';
        $resultat['resolution_net'] = [
            'net_cible' => $this->r2($netCible),
            'net_obtenu' => $resultat['salaire_net'],
            'ecart' => $ecartArrondi,
            'iterations' => $iterations,
            'precision' => self::NET_RESOLUTION_PRECISION,
            'borne_basse' => $this->r2($borneBasse),
            'borne_haute' => $this->r2($borneHaute),
            'converge' => $converge,
        ];

        if (! $converge && $avertissement === null) {
            $avertissement = "La résolution net vers brut n'a pas atteint la précision cible de ".self::NET_RESOLUTION_PRECISION.' MAD.';
        }

        if ($avertissement !== null) {
            $resultat['avertissements'][] = $avertissement;
        }

        return $resultat;
    }

    public function calculer(array $input): array
    {
        // --- Extraction des entrées ---
        $salaireBase = (float) ($input['salaire_base'] ?? 0);
        $nbAnneesAnciennete = (int) ($input['nb_annees_anciennete'] ?? 0);
        $primeBilan = (float) ($input['prime_bilan'] ?? 0);
        $primeRendement = (float) ($input['prime_rendement'] ?? 0);
        $autresPrimes = (float) ($input['autres_primes'] ?? 0);
        $heuresSup = $input['heures_sup'] ?? [];
        $indemnites = $input['indemnites'] ?? [];
        // Ne pas arrondir le ratio : round(0.035, 2) donnerait 0.04 (3,5% → 4%).
        // Seul le montant de la cotisation est arrondi.
        $cimrTaux = ((float) ($input['cimr_taux'] ?? 0)) / 100;
        $nbEnfants = (int) ($input['nb_enfants'] ?? 0);
        $conjointCharge = ! empty($input['conjoint_charge']);
        $typeFraisPro = $input['type_frais_pro'] ?? 'commun';
        // Retenues scindées : exonérées d'IR (pré-fiscales, réduisent le RNI)
        // et imposées à l'IR (post-fiscales, réduisent le net après IR).
        // Compatibilité ascendante : l'ancien champ autres_retenues mappe sur retenues_imposees_ir.
        $retenuesExonereesIr = (float) ($input['retenues_exonerees_ir'] ?? 0);
        $retenuesImposeesIr = (float) ($input['retenues_imposees_ir'] ?? $input['autres_retenues'] ?? 0);
        $joursTravailles = (int) ($input['jours_travailles'] ?? config('payroll.jours_travailles_defaut'));
        $mutuelleSalarie = (float) ($input['mutuelle_salarie'] ?? 0);
        $retraiteComplementaireMensuel = (float) ($input['retraite_complementaire_mensuel'] ?? 0);

        // Champs patronaux pouvant être « inconnus » (null) plutôt que 0.
        // Un drapeau _inconnu = 1 (ou une valeur null explicite) signifie « non renseigné ».
        $coutEmployeurPartiel = false;
        $champInconnu = function (string $champ) use ($input, &$coutEmployeurPartiel): bool {
            $inconnu = ! empty($input[$champ.'_inconnu']) || (array_key_exists($champ, $input) && $input[$champ] === null);
            if ($inconnu) {
                $coutEmployeurPartiel = true;
            }

            return $inconnu;
        };

        $mutuellePatronaleInconnue = $champInconnu('mutuelle_patronale');
        $mutuellePatronale = $mutuellePatronaleInconnue ? 0.0 : (float) ($input['mutuelle_patronale'] ?? 0);

        $rcPartEmployeurInconnu = $champInconnu('rc_part_employeur');
        $rcPartEmployeur = $rcPartEmployeurInconnu ? 0.0 : (float) ($input['rc_part_employeur'] ?? 0);

        $cimrTauxEmployeurInconnu = $champInconnu('cimr_taux_employeur');
        // Ne pas arrondir le ratio : seul le montant de la cotisation est arrondi.
        $cimrTauxEmployeur = $cimrTauxEmployeurInconnu ? 0.0 : ((float) ($input['cimr_taux_employeur'] ?? 0)) / 100;

        $assuranceAtInconnue = $champInconnu('assurance_at_taux');
        $tauxAT = $assuranceAtInconnue ? 0.0 : ((float) ($input['assurance_at_taux'] ?? 0)) / 100;

        $assuranceRcProInconnue = $champInconnu('assurance_rc_pro');
        $assuranceRCPro = $assuranceRcProInconnue ? 0.0 : (float) ($input['assurance_rc_pro'] ?? 0);

        $avantagesCnss = (float) ($input['avantages_cnss'] ?? 0);

        $avertissements = [];

        // =====================================================================
        // ÉTAPE 0 — Prime d'ancienneté (Art. 350 Code du Travail)
        // =====================================================================
        $tauxAnciennete = 0.0;
        $primeAnciennete = 0.0;

        if ($nbAnneesAnciennete > 0) {
            foreach (config('payroll.anciennete.tranches') as $tranche) {
                if ($nbAnneesAnciennete >= $tranche['min_annees'] &&
                    ($tranche['max_annees'] === null || $nbAnneesAnciennete <= $tranche['max_annees'])) {
                    $tauxAnciennete = $tranche['taux'];
                    break;
                }
            }
            $primeAnciennete = $this->r2($salaireBase * $tauxAnciennete);
        }

        $totalPrimesImposables = $this->r2($primeAnciennete + $primeBilan + $primeRendement + $autresPrimes);

        // =====================================================================
        // ÉTAPE 1 — Salaire Brut Imposable (SBI)
        // =====================================================================
        $heuresLegales = config('payroll.smig.heures_legales');
        $tauxHoraire = $salaireBase > 0 ? $this->r2($salaireBase / $heuresLegales) : 0.0;

        $detailHS = [];
        $totalHS = 0.0;
        $majorations = config('payroll.heures_sup.majorations');
        $hsLabels = config('payroll.heures_sup.labels');

        foreach ($heuresSup as $hs) {
            $type = $hs['type'] ?? 'semaine_diurne';
            $nbH = (float) ($hs['nb_heures'] ?? 0);
            if ($nbH <= 0 || ! isset($majorations[$type])) {
                continue;
            }
            $majoPct = $majorations[$type];
            $montant = $this->r2($nbH * $tauxHoraire * (1 + $majoPct));
            $detailHS[] = [
                'type' => $type,
                'label' => $hsLabels[$type] ?? $type,
                'nb_heures' => $nbH,
                'taux_horaire' => $tauxHoraire,
                'majoration' => $majoPct,
                'montant' => $montant,
            ];
            $totalHS = $this->r2($totalHS + $montant);
        }

        // ---------------------------------------------------------------------
        // Indemnités (Arrêté n° 1314-25) — traitées avant le SBI car l'excédent
        // au-delà du plafond légal est imposable : il est réintégré dans le SBI
        // (soumis à CNSS/AMO/IR), seule la part plafonnée reste exonérée.
        // ---------------------------------------------------------------------
        $indemnitesConfig = config('payroll.indemnites');
        $detailIndemnites = [];
        $totalIndemnites = 0.0;
        $excedentIndemnites = 0.0;
        $indemnitesParType = [];

        foreach ($indemnites as $ind) {
            $type = $ind['type'] ?? '';
            $montantDeclare = (float) ($ind['montant'] ?? 0);
            if ($montantDeclare <= 0 || ! isset($indemnitesConfig[$type])) {
                continue;
            }
            $indemnitesParType[$type] = $this->r2(($indemnitesParType[$type] ?? 0.0) + $montantDeclare);
        }

        foreach ($indemnitesParType as $type => $montantDeclare) {
            $plafond = $this->plafondIndemnite($type, $salaireBase, $joursTravailles);
            $montantExo = $this->r2(min($montantDeclare, $plafond));
            $excedent = $this->r2(max(0.0, $montantDeclare - $plafond));
            $cfg = $indemnitesConfig[$type];

            $detailIndemnites[] = [
                'type' => $type,
                'label' => $cfg['label'],
                'declare' => $montantDeclare,
                'plafond' => $plafond,
                'retenu' => $montantExo,
                'excedent' => $excedent,
                'depasse' => $excedent > 0,
            ];
            $totalIndemnites = $this->r2($totalIndemnites + $montantExo);
            $excedentIndemnites = $this->r2($excedentIndemnites + $excedent);

            if ($excedent > 0) {
                $label = $cfg['label'];
                $avertissements[] = "Indemnité « {$label} » : {$montantDeclare} MAD déclarés, plafond légal {$plafond} MAD. Seul le plafond est exonéré ; l'excédent ({$excedent} MAD) est réintégré au salaire brut imposable (Arrêté n° 1314-25).";
            }
        }

        // Avantages CNSS exonérés : imposables IR, exclus de l'assiette CNSS/AMO
        $totalAvantagesCnssExoneres = $this->r2($avantagesCnss);

        $sbi = $this->r2($salaireBase + $totalPrimesImposables + $totalHS + $excedentIndemnites + $totalAvantagesCnssExoneres);

        // Assiette CNSS/AMO : SBI hors avantages CNSS exonérés
        $assietteSociale = $this->r2($sbi - $totalAvantagesCnssExoneres);

        // =====================================================================
        // ÉTAPE 2 — CNSS salarié (Dahir n° 1-72-184)
        // =====================================================================
        $assietteCNSS = min($assietteSociale, config('payroll.cnss.plafond'));
        $cotisationCNSS = $this->r2($assietteCNSS * config('payroll.cnss.taux'));

        // =====================================================================
        // ÉTAPE 3 — AMO salarié (Loi n° 65-00)
        // =====================================================================
        $cotisationAMO = $this->r2($assietteSociale * config('payroll.amo.taux'));

        // =====================================================================
        // ÉTAPE 4 — CIMR (Art. 28-III CGI)
        // Répartition : salarié seul, employeur seul, ou partagé
        // =====================================================================
        $cotisationCIMR = $cimrTaux > 0 ? $this->r2($sbi * $cimrTaux) : 0.0;
        $cotisationCIMRPatronale = $cimrTauxEmployeur > 0 ? $this->r2($sbi * $cimrTauxEmployeur) : 0.0;

        $totalSociales = $this->r2($cotisationCNSS + $cotisationAMO + $cotisationCIMR);

        // =====================================================================
        // ÉTAPE 5 — Salaire Net Comptable (SNC)
        // =====================================================================
        $snc = $this->r2($sbi - $totalSociales);

        // =====================================================================
        // ÉTAPE 6 — Frais Professionnels (Art. 59 I-A CGI)
        // Assiette = revenu brut imposable (SBI), pas le SNC.
        // =====================================================================
        if ($typeFraisPro === 'journaliste') {
            $fpConfig = config('payroll.frais_pro.journaliste');
            $tauxFP = $fpConfig['taux'];
            $plafondFP = $fpConfig['plafond'];
            $descFP = 'Journaliste/correspondant — taux majoré '.($tauxFP * 100).'%';
        } elseif ($typeFraisPro === 'artiste') {
            $fpConfig = config('payroll.frais_pro.artiste');
            $tauxFP = $fpConfig['taux'];
            $plafondFP = $fpConfig['plafond'];
            $descFP = 'Artiste/créateur — taux majoré '.($tauxFP * 100).'%';
        } else {
            if ($sbi <= config('payroll.frais_pro.seuil_mensuel')) {
                $fpConfig = config('payroll.frais_pro.commun.bas');
                $descFP = 'SBI ≤ '.config('payroll.frais_pro.seuil_mensuel').' MAD → taux '.($fpConfig['taux'] * 100).'%';
            } else {
                $fpConfig = config('payroll.frais_pro.commun.haut');
                $descFP = 'SBI > '.config('payroll.frais_pro.seuil_mensuel').' MAD → taux '.($fpConfig['taux'] * 100).'%';
            }
            $tauxFP = $fpConfig['taux'];
            $plafondFP = $fpConfig['plafond'];
        }

        $fpCalc = $this->r2($sbi * $tauxFP);
        $fraisPro = $this->r2(min($fpCalc, $plafondFP));
        $fpPlaf = $fpCalc > $plafondFP;

        // =====================================================================
        // ÉTAPE 7 — RNI mensuel
        // Les retenues exonérées d'IR (pré-fiscales) réduisent le RNI avant le barème.
        // =====================================================================
        $rni = $this->r2($snc - $fraisPro - $retenuesExonereesIr);

        // =====================================================================
        // ÉTAPE 8 — IR (Art. 73 CGI) avec déduction retraite complémentaire
        // =====================================================================
        $nbMois = config('payroll.ir.nb_mois');
        $rniAnnuel = $this->r2($rni * $nbMois);

        // Déduction retraite complémentaire (bancassurance) — Art. 28-IV CGI
        $rcAnnuel = $this->r2($retraiteComplementaireMensuel * $nbMois);
        $rcPlafond = $this->r2($sbi * $nbMois * config('payroll.retraite_complementaire.deduction_ir_max_pct'));
        $rcDeduite = $this->r2(min($rcAnnuel, $rcPlafond));
        $rniAnnuelNet = $this->r2(max(0.0, $rniAnnuel - $rcDeduite));

        $irResult = $this->calculerIRAnnuelBrut($rniAnnuelNet);
        $irAnnuelBrut = $irResult['montant'];
        $trancheIR = $irResult['tranche'];
        $irMensuelBrut = $this->r2($irAnnuelBrut / $nbMois);

        // Déduction charges de famille (Art. 74 CGI)
        $nbPersonnes = $nbEnfants + ($conjointCharge ? 1 : 0);
        $chargesCalc = $this->r2($nbPersonnes * config('payroll.charges_famille.par_personne'));
        $chargesFamille = $this->r2(min($chargesCalc, config('payroll.charges_famille.plafond')));
        $irNet = $this->r2(max(0.0, $irMensuelBrut - $chargesFamille));

        // =====================================================================
        // ÉTAPE 9 — Indemnités exonérées : déjà calculées avant le SBI
        // ($totalIndemnites = parts exonérées, $excedentIndemnites dans le SBI)
        // =====================================================================

        // =====================================================================
        // ÉTAPE 10 — Net à payer
        // =====================================================================
        // Net : retenues post-fiscales (imposées IR) + mutuelle salarié.
        // Les retenues exonérées d'IR n'impactent que l'assiette IR (réduisent déjà le RNI).
        $totalRetenues = $this->r2($retenuesImposeesIr + $mutuelleSalarie);
        $salaireNet = $this->r2($sbi - $cotisationCNSS - $cotisationAMO - $cotisationCIMR - $irNet + $totalIndemnites - $totalRetenues);
        $salaireBrutTotal = $this->r2($sbi + $totalIndemnites);

        // =====================================================================
        // COTISATIONS PATRONALES — Coût total employeur
        // =====================================================================
        $assietteCNSSPatronal = min($assietteSociale, config('payroll.cnss.plafond'));
        $coutCNSSPatronal = $this->r2($assietteCNSSPatronal * config('payroll.cnss.taux_patronal'));
        $coutAMOPatronal = $this->r2($assietteSociale * config('payroll.amo.taux_patronal'));
        $coutAFPatronal = $this->r2($assietteSociale * config('payroll.allocations_familiales.taux_patronal'));
        $coutTFPPatronal = $this->r2($assietteSociale * config('payroll.taxe_formation.taux_patronal'));

        // Assurances employeur (taux variable par contrat, hors config)
        $assuranceAT = $tauxAT > 0 ? $this->r2($sbi * $tauxAT) : 0.0;

        $totalPatronal = $this->r2($coutCNSSPatronal + $coutAMOPatronal + $coutAFPatronal + $coutTFPPatronal + $mutuellePatronale + $cotisationCIMRPatronale + $rcPartEmployeur + $assuranceAT + $assuranceRCPro);
        $coutTotalEmployeur = $this->r2($salaireBrutTotal + $totalPatronal);

        // =====================================================================
        // Avertissements réglementaires
        // =====================================================================
        $smig = config('payroll.smig.mensuel');
        if ($salaireBase < $smig && $salaireBase > 0) {
            $smigFmt = number_format($smig, 2, ',', ' ');
            $avertissements[] = "Le salaire de base ({$salaireBase} MAD) est inférieur au SMIG 2026 ({$smigFmt} MAD) — Décret n° 2.25.983.";
        }

        $cimrMax = config('payroll.cimr.taux_max');
        if ($cimrTaux > $cimrMax) {
            $pct = round($cimrTaux * 100, 2);
            $avertissements[] = "Le taux CIMR salarie ({$pct}%) depasse le plafond de ".($cimrMax * 100).'% (Art. 28-III CGI).';
        }
        if ($cimrTauxEmployeur > $cimrMax) {
            $pct = round($cimrTauxEmployeur * 100, 2);
            $avertissements[] = "Le taux CIMR employeur ({$pct}%) depasse le plafond de ".($cimrMax * 100).'% (Art. 28-III CGI).';
        }

        $maxPersonnes = (int) (config('payroll.charges_famille.plafond') / config('payroll.charges_famille.par_personne'));
        if ($nbPersonnes > $maxPersonnes) {
            $cap = config('payroll.charges_famille.plafond');
            $avertissements[] = "Le nombre de personnes à charge ({$nbPersonnes}) dépasse {$maxPersonnes} : déduction plafonnée à {$cap} MAD/mois (Art. 74 CGI).";
        }

        if ($rcAnnuel > 0 && $rcAnnuel > $rcPlafond) {
            $rcDeclareFmt = number_format($rcAnnuel, 2, ',', ' ');
            $rcPlafondFmt = number_format($rcPlafond, 2, ',', ' ');
            $avertissements[] = "Retraite complémentaire ({$rcDeclareFmt} MAD/an) : dépasse le plafond de déductibilité ({$rcPlafondFmt} MAD = 50% du SBI annuel). Seul le plafond est déduit (Art. 28-IV CGI).";
        }

        if ($coutEmployeurPartiel) {
            $avertissements[] = "Coût employeur partiel : certaines cotisations patronales n'ont pas été renseignées et sont considérées comme nulles dans le total affiché.";
        }

        // =====================================================================
        // Données graphique (Chart.js)
        // =====================================================================
        $base = $salaireBrutTotal > 0 ? $salaireBrutTotal : 1;
        $repartition = [
            'net' => ['montant' => $salaireNet,      'pct' => $this->r2($salaireNet / $base * 100),      'color' => '#1B7A4A'],
            'cnss' => ['montant' => $cotisationCNSS,  'pct' => $this->r2($cotisationCNSS / $base * 100),  'color' => '#1B5FD9'],
            'amo' => ['montant' => $cotisationAMO,   'pct' => $this->r2($cotisationAMO / $base * 100),   'color' => '#6F42C1'],
            'cimr' => ['montant' => $cotisationCIMR,  'pct' => $this->r2($cotisationCIMR / $base * 100),  'color' => '#4D9376'],
            'ir' => ['montant' => $irNet,           'pct' => $this->r2($irNet / $base * 100),           'color' => '#C1272D'],
            'retenues' => ['montant' => $totalRetenues,   'pct' => $this->r2($totalRetenues / $base * 100),   'color' => '#6C757D'],
        ];

        // =====================================================================
        // Retour structuré complet
        // =====================================================================
        return [
            // — Masses salariales —
            'sbi' => $sbi,
            'salaire_brut_total' => $salaireBrutTotal,
            'total_indemnites' => $totalIndemnites,
            'excedent_indemnites' => $excedentIndemnites,
            'jours_travailles' => $joursTravailles,

            // — Primes détaillées —
            'prime_anciennete' => $primeAnciennete,
            'taux_anciennete' => $tauxAnciennete,
            'nb_annees_anciennete' => $nbAnneesAnciennete,
            'prime_bilan' => $primeBilan,
            'prime_rendement' => $primeRendement,
            'autres_primes' => $autresPrimes,
            'total_primes' => $totalPrimesImposables,

            // — Avantages CNSS exonérés —
            'avantages_cnss' => $this->r2($avantagesCnss),
            'total_avantages_cnss_exoneres' => $totalAvantagesCnssExoneres,
            'assiette_sociale' => $assietteSociale,

            // — Cotisations salariales —
            'assiette_cnss' => $assietteCNSS,
            'cotisation_cnss' => $cotisationCNSS,
            'cotisation_amo' => $cotisationAMO,
            'cimr_taux' => $cimrTaux,
            'cimr_taux_employeur' => $cimrTauxEmployeur,
            'cotisation_cimr' => $cotisationCIMR,
            'cotisation_cimr_patronale' => $cotisationCIMRPatronale,
            'total_sociales' => $totalSociales,

            // — Cotisations patronales —
            'cout_cnss_patronal' => $coutCNSSPatronal,
            'cout_amo_patronal' => $coutAMOPatronal,
            'cout_af_patronal' => $coutAFPatronal,
            'cout_tfp_patronal' => $coutTFPPatronal,
            'mutuelle_patronale' => $mutuellePatronale,
            'mutuelle_patronale_inconnue' => $mutuellePatronaleInconnue,
            'rc_part_employeur' => $rcPartEmployeur,
            'rc_part_employeur_inconnu' => $rcPartEmployeurInconnu,
            'cimr_taux_employeur_inconnu' => $cimrTauxEmployeurInconnu,
            'assurance_at' => $assuranceAT,
            'assurance_at_taux' => $tauxAT,
            'assurance_at_inconnue' => $assuranceAtInconnue,
            'assurance_rc_pro' => $assuranceRCPro,
            'assurance_rc_pro_inconnue' => $assuranceRcProInconnue,
            'cout_employeur_partiel' => $coutEmployeurPartiel,
            'total_patronal' => $totalPatronal,
            'cout_total_employeur' => $coutTotalEmployeur,

            // — Frais pro & RNI —
            'snc' => $snc,
            'taux_fp' => $tauxFP,
            'plafond_fp' => $plafondFP,
            'desc_fp' => $descFP,
            'frais_pro' => $fraisPro,
            'fp_plafonne' => $fpPlaf,
            'rni' => $rni,

            // — IR —
            'rni_annuel' => $rniAnnuel,
            'rc_annuel' => $rcAnnuel,
            'rc_deduite' => $rcDeduite,
            'rni_annuel_net' => $rniAnnuelNet,
            'ir_annuel_brut' => $irAnnuelBrut,
            'ir_mensuel_brut' => $irMensuelBrut,
            'tranche_ir' => $trancheIR,
            'nb_personnes' => $nbPersonnes,
            'charges_famille' => $chargesFamille,
            'ir_net' => $irNet,

            // — Net —
            'mutuelle_salarie' => $mutuelleSalarie,
            'retenues_exonerees_ir' => $this->r2($retenuesExonereesIr),
            'retenues_imposees_ir' => $this->r2($retenuesImposeesIr),
            'total_retenues' => $totalRetenues,
            'salaire_net' => $salaireNet,

            // — Détails pédagogiques —
            'detail_hs' => $detailHS,
            'detail_indemnites' => $detailIndemnites,
            'repartition' => $repartition,
            'avertissements' => $avertissements,

            // — Entrées (ré-affichage) —
            'input' => $input,
        ];
    }
}
