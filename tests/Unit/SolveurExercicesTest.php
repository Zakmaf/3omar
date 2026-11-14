<?php

namespace Tests\Unit;

use App\Services\PayrollCalculatorService;
use Tests\TestCase;

/**
 * Convergence du solveur net vers brut sur deux barèmes (issue #281).
 *
 * Le second barème est celui du profil 2027, rendu calculable uniquement dans
 * ce test par une CONFIGURATION SYNTHETIQUE DE TEST : ses frais
 * professionnels reçoivent une copie technique des valeurs 2026. Ce n'est pas
 * un profil légal, la configuration de production n'est jamais modifiée et le
 * statut reste projet.
 */
class SolveurExercicesTest extends TestCase
{
    private function fixtureTechnique2027(): void
    {
        config(['payroll.exercices.2027.frais_pro' => config('payroll.frais_pro')]);
    }

    public function test_le_solveur_converge_sur_le_bareme_de_l_exercice_demande(): void
    {
        $this->fixtureTechnique2027();
        $moteur = app(PayrollCalculatorService::class);
        $netCible = 15000.0;

        $resultats = [];
        foreach ([2026, 2027] as $exercice) {
            $r = $moteur->resoudreDepuisNet(['net_cible' => $netCible, 'type_frais_pro' => 'commun', 'exercice' => $exercice]);

            $this->assertTrue($r['resolution_net']['converge'], "{$exercice} : convergence");
            $this->assertSame($exercice, $r['exercice'], "{$exercice} : exercice exposé par le solveur");
            $this->assertEqualsWithDelta($netCible, $r['salaire_net'], 0.01, "{$exercice} : net atteint");

            $recalcul = $moteur->calculer([
                'salaire_base' => $r['input']['salaire_base'],
                'type_frais_pro' => 'commun',
                'exercice' => $exercice,
            ]);
            $this->assertSame($r['salaire_net'], $recalcul['salaire_net'], "{$exercice} : même barème pour le recalcul");

            $resultats[$exercice] = $r;
        }

        $this->assertLessThan(
            $resultats[2026]['input']['salaire_base'],
            $resultats[2027]['input']['salaire_base'],
            'Le barème projet 2027 impose moins à ce niveau : le brut nécessaire est plus bas.',
        );
        $this->assertSame('projet', $resultats[2027]['reference_fiscale']['statut'], 'La fixture ne change pas le statut.');
        $this->assertNull($resultats[2027]['reference_fiscale']['validation_legale']);
    }
}
