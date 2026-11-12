<?php

namespace Tests\Unit;

use App\Services\PayrollCalculatorService;
use Tests\TestCase;

/**
 * Barème IR annuel sélectionné par exercice fiscal (issue #281, epic #280).
 *
 * Première tranche verticale du versionnement : le calcul de l'IR annuel brut
 * reçoit l'exercice et applique le barème de cet exercice. Le barème 2026 en
 * vigueur reste le repli sans exercice et ne change pas.
 *
 * Le test vise l'IR annuel, pas calculer() : les frais professionnels de
 * l'exercice 2027 relèvent de #282 et ne doivent pas être remplacés, même
 * provisoirement, par ceux de 2026 dans un calcul complet. La routine privée
 * est appelée par réflexion, sans changer sa visibilité, comme dans
 * BaremeIrTranchesTest.
 *
 * Donnée synthétique : RNI annuel net de 196 350,36 MAD, celui du profil
 * 20 000 MAD de base mensuelle sans autre élément (#283, #209), dont les frais
 * professionnels sont plafonnés en 2026 comme dans le projet 2027.
 *
 * Montants attendus, calculés à la main depuis les tables de l'issue #281 :
 *  2026 en vigueur (Loi de Finances 50-25), tranche au-delà de 180 000 :
 *    196 350,36 x 37 % - 27 400                        = 45 249,63
 *  2027 projet (PLF n° 60-26, art. 247-XXXXVII-1 CGI), tranche au-delà de
 *  180 000, déduction dérivée par continuité aux seuils 60 000, 80 000,
 *  100 000 et 180 000 : 6 000 + 8 000 + 14 000 + 5 400 = 33 400
 *    196 350,36 x 37 % - 33 400                        = 39 249,63
 *  écart annuel 6 000,00, soit 500,00 par mois (#283).
 */
class BaremeIrExercicesTest extends TestCase
{
    public function test_exercice_2027_projet_applique_son_bareme_sans_modifier_2026(): void
    {
        $calculator = app(PayrollCalculatorService::class);
        $methode = new \ReflectionMethod(PayrollCalculatorService::class, 'calculerIRAnnuelBrut');
        $rniAnnuelNet = 196350.36;

        $sansExercice = $methode->invoke($calculator, $rniAnnuelNet);
        $exercice2026 = $methode->invoke($calculator, $rniAnnuelNet, 2026);
        $exercice2027 = $methode->invoke($calculator, $rniAnnuelNet, 2027);

        $this->assertSame(45249.63, $exercice2026['montant'], 'Le barème 2026 en vigueur reste inchangé.');
        $this->assertSame($exercice2026, $sansExercice, 'Sans exercice explicite, le repli déterministe est 2026.');

        $this->assertSame(39249.63, $exercice2027['montant'], 'Le projet 2027 applique son propre barème.');
        $this->assertSame(6000.0, round($exercice2026['montant'] - $exercice2027['montant'], 2));
    }
}
