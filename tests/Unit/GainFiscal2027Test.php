<?php

namespace Tests\Unit;

use App\Services\GainFiscal2027;
use App\Services\PayrollCalculatorService;
use App\Services\SimulationComparator;
use Tests\TestCase;

/**
 * Gain de salaire net au 1er janvier 2027 face à 2026 (issue #283).
 *
 * Une seule saisie est calculée par le moteur sous deux exercices : 2026 (règles
 * en vigueur) et 2027 (projet de loi de finances n° 60-26, statut projet, non
 * voté). Le service GainFiscal2027 orchestre PayrollCalculatorService et
 * SimulationComparator ; il ne contient aucune formule de paie.
 *
 * Contrat de sortie de GainFiscal2027::comparer(array $saisie): array
 * - mode : mode de la saisie (gross_to_net ici) ;
 * - exercices : ['reference' => 2026, 'projete' => 2027] ;
 * - reference, projete : résultats complets du moteur pour chaque exercice,
 *   à saisie identique (seul le champ exercice diffère) ;
 * - metrics : écarts produits par SimulationComparator::metrics(reference, projete) ;
 * - gain : net_mensuel (écart de salaire_net du comparateur), net_pct (écart
 *   relatif du comparateur), nb_mois et net_annuel_indicatif (nb_mois x
 *   net_mensuel, douze mois identiques, indicatif) ;
 * - decomposition : ordre explicite ['bareme', 'frais_pro'] ; net_intermediaire
 *   est le net du moteur avec le barème 2027 et les frais professionnels 2026 ;
 *   bareme = net_intermediaire - net 2026, frais_pro = net 2027 -
 *   net_intermediaire. Leur somme vaut exactement net_mensuel, sans double
 *   comptage.
 *
 * Cas d'acceptation de l'issue (données synthétiques) : 20 000 MAD/mois, profil
 * ordinaire sans personne à charge. 2026 : net 15 508,40 MAD. Les frais
 * professionnels sont déjà plafonnés (2 916,67 MAD/mois) dans les deux
 * exercices : le gain vient du seul barème, 500,00 MAD/mois.
 */
class GainFiscal2027Test extends TestCase
{
    private const SAISIE = ['mode' => 'gross_to_net', 'salaire_base' => 20000, 'type_frais_pro' => 'commun'];

    private function metric(array $metrics, string $cle): array
    {
        foreach ($metrics as $metric) {
            if ($metric['key'] === $cle) {
                return $metric;
            }
        }

        $this->fail("Indicateur {$cle} absent de la comparaison.");
    }

    public function test_brut_20000_compare_2026_et_2027_a_saisie_identique(): void
    {
        $resultat = app(GainFiscal2027::class)->comparer(self::SAISIE);
        $moteur = app(PayrollCalculatorService::class);

        $this->assertSame('gross_to_net', $resultat['mode']);
        $this->assertSame(['reference' => 2026, 'projete' => 2027], $resultat['exercices']);

        // Deux vrais résultats du moteur, un par exercice, pour la même saisie.
        $direct2026 = $moteur->calculer(self::SAISIE + ['exercice' => 2026]);
        $direct2027 = $moteur->calculer(self::SAISIE + ['exercice' => 2027]);

        $this->assertSame(2026, $resultat['reference']['exercice']);
        $this->assertSame(2027, $resultat['projete']['exercice']);

        foreach (['sbi', 'cotisation_cnss', 'cotisation_amo', 'cotisation_cimr', 'frais_pro', 'rni_annuel_net', 'ir_net', 'salaire_net'] as $cle) {
            $this->assertSame($direct2026[$cle], $resultat['reference'][$cle], "2026 : {$cle} issu du moteur");
            $this->assertSame($direct2027[$cle], $resultat['projete'][$cle], "2027 : {$cle} issu du moteur");
        }

        $sansExercice = fn (array $input): array => array_diff_key($input, ['exercice' => true]);
        $this->assertSame(self::SAISIE, $sansExercice($resultat['reference']['input']), 'Saisie 2026 non modifiée.');
        $this->assertSame(self::SAISIE, $sansExercice($resultat['projete']['input']), 'Saisie 2027 identique à celle de 2026.');

        // Brut et cotisations identiques : comparaison à brut constant.
        $this->assertSame($resultat['reference']['sbi'], $resultat['projete']['sbi']);
        $this->assertSame($resultat['reference']['cotisation_cnss'], $resultat['projete']['cotisation_cnss']);
        $this->assertSame($resultat['reference']['cotisation_amo'], $resultat['projete']['cotisation_amo']);

        // Cas d'acceptation : net 2026 documenté, frais professionnels plafonnés.
        $this->assertSame(15508.40, $resultat['reference']['salaire_net']);
        $this->assertTrue($resultat['reference']['fp_plafonne']);
        $this->assertTrue($resultat['projete']['fp_plafonne']);
        $this->assertSame($resultat['reference']['frais_pro'], $resultat['projete']['frais_pro']);

        // Écarts produits par le comparateur existant (#47).
        $comparateur = app(SimulationComparator::class);
        $this->assertSame($comparateur->metrics($resultat['reference'], $resultat['projete']), $resultat['metrics']);
        $net = $this->metric($resultat['metrics'], 'salaire_net');

        // gain_net = net_2027 - net_2026, lu dans le comparateur.
        $this->assertSame(round($resultat['projete']['salaire_net'] - $resultat['reference']['salaire_net'], 2), $net['ecart']);
        $this->assertSame($net['ecart'], $resultat['gain']['net_mensuel']);
        $this->assertSame(500.0, $resultat['gain']['net_mensuel']);
        $this->assertSame($net['ecart_pct'], $resultat['gain']['net_pct']);
        $this->assertSame(-500.0, $this->metric($resultat['metrics'], 'ir_net')['ecart'], 'Baisse d\'IR égale au gain net.');

        // Projection annuelle indicative : douze mois identiques.
        $this->assertSame(12, $resultat['gain']['nb_mois']);
        $this->assertSame(6000.0, $resultat['gain']['net_annuel_indicatif']);

        // Décomposition ordonnée, exacte au centime : barème puis frais professionnels.
        $decomposition = $resultat['decomposition'];
        $this->assertSame(['bareme', 'frais_pro'], $decomposition['ordre']);
        $this->assertSame(16008.40, $decomposition['net_intermediaire']);
        $this->assertSame(500.0, $decomposition['bareme']);
        $this->assertSame(0.0, $decomposition['frais_pro'], 'Plafond déjà atteint en 2026 : aucun gain lié aux frais professionnels.');
        $this->assertSame($resultat['gain']['net_mensuel'], round($decomposition['bareme'] + $decomposition['frais_pro'], 2));

        // Aucun basculement de statut légal du projet.
        $this->assertSame('projet', $resultat['projete']['reference_fiscale']['statut']);
        $this->assertNull($resultat['projete']['reference_fiscale']['validation_legale']);
    }
}
