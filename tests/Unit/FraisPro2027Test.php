<?php

namespace Tests\Unit;

use App\Services\ExercicesFiscaux;
use App\Services\PayrollCalculatorService;
use Tests\TestCase;

/**
 * Frais professionnels proposés pour l'exercice 2027 (issue #282).
 *
 * Source : projet de loi de finances n° 60-26 pour 2027, texte déposé (publié
 * le 23/10/2026), ni voté ni promulgué, susceptible d'amendements.
 * - Art. 7-I, CGI art. 59-I modifié (page « - 8 - » du PDF) :
 *   « A. – 45% pour les personnes ne relevant pas de la catégorie
 *   professionnelle visée au C ci-après, sans que cette déduction puisse
 *   excéder trente-cinq mille (35 000) dirhams ; B. – (abrogé) ; C. – ...
 *   maritime. »
 * - Art. 7-IV-5 : art. 59-I applicable aux revenus acquis à compter du
 *   1er janvier 2027.
 * - Art. 7-IV-4 : art. 57-1° modifié applicable à compter du 1er janvier 2031
 *   seulement ; les indemnités restent régies par le texte en vigueur au
 *   31/12/2026 jusqu'au 31/12/2030.
 *
 * Conséquences testées :
 * - 2027 : taux unique de 45 %, sans seuil de salaire, plafond canonique annuel
 *   de 35 000 MAD, mensualisé à round(35 000 / 12, 2) = 2 916,67 MAD comme en
 *   2026 (méthode existante) ;
 * - journaliste et artiste relevaient en 2026 du B, abrogé par le projet : ils
 *   relèvent du A en 2027, par une déclaration explicite du profil (jamais par
 *   repli silencieux) ; une catégorie non déclarée par le profil est refusée ;
 * - 2026 inchangé ; 2028 à 2031 restent non calculables (hors #282) ;
 * - indemnités exonérées inchangées en 2027.
 */
class FraisPro2027Test extends TestCase
{
    private PayrollCalculatorService $moteur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moteur = app(PayrollCalculatorService::class);
    }

    /**
     * Salaire de base seul : sans ancienneté ni prime, le SBI vaut le salaire.
     */
    private function calcul(float $salaire, int $exercice, string $categorie = 'commun', array $extra = []): array
    {
        return $this->moteur->calculer(array_merge([
            'salaire_base' => $salaire,
            'type_frais_pro' => $categorie,
            'exercice' => $exercice,
        ], $extra));
    }

    private function plafondMensuel2027(): float
    {
        return round(35000 / 12, 2);
    }

    // ------------------------------------------------------------------
    // Paramètres du profil
    // ------------------------------------------------------------------

    public function test_le_profil_2027_declare_le_taux_et_le_plafond_annuel_canonique(): void
    {
        $fraisPro = config('payroll.exercices.2027.frais_pro');

        $this->assertIsArray($fraisPro, '2027 : frais professionnels paramétrés (#282).');
        $this->assertSame(0.45, $fraisPro['taux']);
        $this->assertSame(35000, $fraisPro['plafond_annuel'], 'Le plafond annuel du texte est la seule source.');
        $this->assertArrayNotHasKey('plafond', $fraisPro, 'Aucun plafond mensuel recopié : il est dérivé.');
        $this->assertArrayNotHasKey('seuil_mensuel', $fraisPro, 'Le projet supprime le seuil de 78 000 MAD annuels.');
        $this->assertSame(['commun', 'journaliste', 'artiste'], $fraisPro['categories'], 'Catégories explicitement rattachées au A.');
        $this->assertStringContainsString('59-I-A', $fraisPro['reference']);
        $this->assertStringContainsString('projet', $fraisPro['reference']);
        $this->assertStringContainsString('60-26', $fraisPro['reference']);
    }

    public function test_2027_devient_calculable_sans_changer_de_statut(): void
    {
        $exercices = app(ExercicesFiscaux::class);

        $this->assertTrue($exercices->calculable(2027));
        $this->assertSame('projet', config('payroll.exercices.2027.statut'));
        $this->assertNull(config('payroll.exercices.2027.validation_legale'));
    }

    public function test_les_exercices_2028_a_2031_restent_non_calculables(): void
    {
        foreach ([2028, 2029, 2030, 2031] as $annee) {
            $this->assertNull(config("payroll.exercices.{$annee}.frais_pro"), "{$annee} : hors périmètre #282");
            $this->assertFalse(app(ExercicesFiscaux::class)->calculable($annee), "{$annee} : non calculable");
        }
    }

    // ------------------------------------------------------------------
    // Régime commun
    // ------------------------------------------------------------------

    public function test_exemple_de_l_issue_10000_mad(): void
    {
        $r2026 = $this->calcul(10000, 2026);
        $r2027 = $this->calcul(10000, 2027);

        $this->assertSame(2500.0, $r2026['frais_pro'], '2026 : 25 % au-delà du seuil.');
        $this->assertSame(2916.67, $r2027['frais_pro'], '2027 : 45 % plafonnés.');
        $this->assertTrue($r2027['fp_plafonne']);
        $this->assertSame(0.45, $r2027['taux_fp']);
        $this->assertSame($this->plafondMensuel2027(), $r2027['plafond_fp']);
        $this->assertSame('commun_unique', $r2027['regime_fp']);
    }

    public function test_sous_l_ancien_seuil_le_taux_passe_de_35_a_45(): void
    {
        $r2026 = $this->calcul(5000, 2026);
        $r2027 = $this->calcul(5000, 2027);

        $this->assertSame('commun_bas', $r2026['regime_fp']);
        $this->assertSame(1750.0, $r2026['frais_pro']);
        $this->assertSame(2250.0, $r2027['frais_pro']);
        $this->assertFalse($r2027['fp_plafonne']);
    }

    public function test_aucun_seuil_de_salaire_en_2027(): void
    {
        foreach ([6500.0, 6500.01] as $salaire) {
            $r = $this->calcul($salaire, 2027);

            $this->assertSame('commun_unique', $r['regime_fp'], "{$salaire} : même régime de part et d'autre de l'ancien seuil");
            $this->assertSame(0.45, $r['taux_fp'], "{$salaire} : taux unique");
            $this->assertSame($this->plafondMensuel2027(), $r['frais_pro'], "{$salaire} : 45 % dépassent déjà le plafond");
        }
    }

    public function test_sous_et_au_dela_du_plafond_2027(): void
    {
        $sous = $this->calcul(6000, 2027);
        $this->assertSame(2700.0, $sous['frais_pro']);
        $this->assertFalse($sous['fp_plafonne']);

        $dessus = $this->calcul(7000, 2027);
        $this->assertSame(2916.67, $dessus['frais_pro']);
        $this->assertTrue($dessus['fp_plafonne']);

        $eleve = $this->calcul(50000, 2027);
        $this->assertSame(2916.67, $eleve['frais_pro'], 'Plafond indépendant du niveau de salaire.');
    }

    public function test_le_plafond_mensuel_suit_le_plafond_annuel_configure(): void
    {
        config(['payroll.exercices.2027.frais_pro.plafond_annuel' => 24000]);

        $r = $this->calcul(10000, 2027);

        $this->assertSame(2000.0, $r['plafond_fp'], 'Plafond mensuel dérivé de la configuration, jamais codé en dur.');
        $this->assertSame(2000.0, $r['frais_pro']);
    }

    public function test_les_frais_professionnels_reduisent_l_assiette_ir_et_ne_sont_pas_verses(): void
    {
        $r2026 = $this->calcul(10000, 2026);
        $r2027 = $this->calcul(10000, 2027);

        foreach (['sbi', 'salaire_brut_total', 'cotisation_cnss', 'cotisation_amo', 'snc', 'total_indemnites'] as $cle) {
            $this->assertSame($r2026[$cle], $r2027[$cle], "{$cle} : indépendant des frais professionnels");
        }

        $this->assertSame(round($r2027['snc'] - $r2027['frais_pro'], 2), $r2027['rni']);
        $this->assertSame(
            round($r2027['sbi'] - $r2027['cotisation_cnss'] - $r2027['cotisation_amo'] - $r2027['cotisation_cimr'] - $r2027['ir_net'] + $r2027['total_indemnites'] - $r2027['total_retenues'], 2),
            $r2027['salaire_net'],
            'Le net ne contient aucune somme de frais professionnels.',
        );
    }

    // ------------------------------------------------------------------
    // Catégories particulières
    // ------------------------------------------------------------------

    public function test_journaliste_et_artiste_gardent_leurs_taux_2026(): void
    {
        $journaliste = $this->calcul(5000, 2026, 'journaliste');
        $this->assertSame('journaliste', $journaliste['regime_fp']);
        $this->assertSame(config('payroll.frais_pro.journaliste.taux'), $journaliste['taux_fp']);
        $this->assertSame(0.45, $journaliste['taux_fp']);

        $artiste = $this->calcul(5000, 2026, 'artiste');
        $this->assertSame('artiste', $artiste['regime_fp']);
        $this->assertSame(config('payroll.frais_pro.artiste.taux'), $artiste['taux_fp']);
        $this->assertSame(0.40, $artiste['taux_fp']);
    }

    /**
     * Art. 59-I-B abrogé par le projet : ces catégories relèvent du A en 2027,
     * parce que le profil 2027 les y rattache explicitement.
     */
    public function test_journaliste_et_artiste_relevent_du_regime_general_en_2027(): void
    {
        $commun = $this->calcul(5000, 2027);

        foreach (['journaliste', 'artiste'] as $categorie) {
            $r = $this->calcul(5000, 2027, $categorie);

            $this->assertSame('commun_unique', $r['regime_fp'], "{$categorie} : régime A du projet");
            $this->assertSame(0.45, $r['taux_fp'], $categorie);
            $this->assertSame($commun['frais_pro'], $r['frais_pro'], $categorie);
            $this->assertSame($commun['salaire_net'], $r['salaire_net'], $categorie);
            $this->assertSame($categorie, $r['input']['type_frais_pro'], "{$categorie} : saisie conservée");
        }

        $this->assertSame(2916.67, $this->calcul(10000, 2027, 'journaliste')['frais_pro'], 'Plafond A appliqué au journaliste.');
    }

    public function test_une_categorie_non_rattachee_par_le_profil_est_refusee_sans_repli(): void
    {
        config(['payroll.exercices.2027.frais_pro.categories' => ['commun', 'journaliste']]);

        $message = __('ui.validation.category_not_supported', ['exercice' => 2027]);
        $this->assertNotSame('ui.validation.category_not_supported', $message, 'Message traduit attendu.');

        try {
            $this->calcul(5000, 2027, 'artiste');
            $this->fail('Une catégorie non rattachée au profil 2027 doit être refusée.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame($message, $e->getMessage());
        }

        try {
            $this->moteur->resoudreDepuisNet(['net_cible' => 8000, 'type_frais_pro' => 'artiste', 'exercice' => 2027]);
            $this->fail('Le solveur doit refuser la même catégorie.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame($message, $e->getMessage());
        }

        $this->assertSame('commun_unique', $this->calcul(5000, 2027, 'journaliste')['regime_fp']);
    }

    // ------------------------------------------------------------------
    // Indemnités (art. 57-1° modifié seulement à compter de 2031)
    // ------------------------------------------------------------------

    public function test_les_indemnites_exonerees_sont_inchangees_en_2027(): void
    {
        $indemnites = ['indemnites' => [
            ['type' => 'transport', 'montant' => 700],
            ['type' => 'panier', 'montant' => 500],
            ['type' => 'representation', 'montant' => 300],
        ]];

        $r2026 = $this->calcul(10000, 2026, 'commun', $indemnites);
        $r2027 = $this->calcul(10000, 2027, 'commun', $indemnites);

        $this->assertGreaterThan(0, $r2027['total_indemnites']);
        $this->assertGreaterThan(0, $r2027['excedent_indemnites'], 'Le cas couvre aussi un dépassement de plafond.');
        $this->assertSame($r2026['total_indemnites'], $r2027['total_indemnites']);
        $this->assertSame($r2026['excedent_indemnites'], $r2027['excedent_indemnites']);
        $this->assertSame($r2026['detail_indemnites'], $r2027['detail_indemnites']);
    }

    // ------------------------------------------------------------------
    // Net vers brut
    // ------------------------------------------------------------------

    public function test_le_solveur_applique_les_frais_professionnels_de_l_exercice_demande(): void
    {
        $r = $this->moteur->resoudreDepuisNet(['net_cible' => 8000, 'type_frais_pro' => 'commun', 'exercice' => 2027]);

        $this->assertTrue($r['resolution_net']['converge']);
        $this->assertSame(2027, $r['exercice']);
        $this->assertSame('commun_unique', $r['regime_fp']);
        $this->assertSame(0.45, $r['taux_fp']);
        $this->assertEqualsWithDelta(8000, $r['salaire_net'], 0.01);

        $recalcul = $this->calcul($r['input']['salaire_base'], 2027);
        $this->assertSame($r['salaire_net'], $recalcul['salaire_net']);
        $this->assertSame($r['frais_pro'], $recalcul['frais_pro']);
    }
}
