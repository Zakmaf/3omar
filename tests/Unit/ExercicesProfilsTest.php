<?php

namespace Tests\Unit;

use App\Services\ExercicesFiscaux;
use Tests\TestCase;

/**
 * Structure, statuts et sources des profils d'exercice (issue #281).
 *
 * Caractérise la configuration validée : 2026 seul en vigueur et repli
 * permanent, 2027 à 2031 en projet daté du texte déposé, sans validation
 * légale ni frais professionnels. Aucune date du jour n'intervient.
 */
class ExercicesProfilsTest extends TestCase
{
    private const CLES_PROFIL = [
        'statut', 'source', 'reference_ir', 'periode',
        'date_effet_projetee', 'validation_legale', 'ir_baremes', 'frais_pro',
    ];

    public function test_les_exercices_declares_et_le_repli_permanent(): void
    {
        $this->assertSame([2026, 2027, 2028, 2029, 2030, 2031], app(ExercicesFiscaux::class)->declares());
        $this->assertSame(2026, config('payroll.exercice_defaut'));
        $this->assertSame(2026, app(ExercicesFiscaux::class)->resoudre(null));
        $this->assertSame(config('payroll.year'), config('payroll.exercice_defaut'), 'Les clés historiques restent les archives 2026.');
    }

    public function test_chaque_profil_declare_exactement_les_cles_attendues(): void
    {
        foreach (config('payroll.exercices') as $annee => $profil) {
            $this->assertSame(self::CLES_PROFIL, array_keys($profil), "{$annee} : clés du profil");
        }
    }

    public function test_le_profil_2026_est_en_vigueur_et_renvoie_aux_cles_historiques(): void
    {
        $profil = config('payroll.exercices.2026');

        $this->assertSame('en_vigueur', $profil['statut']);
        $this->assertSame(config('payroll.regulation'), $profil['source']['texte']);
        $this->assertSame('ir.baremes', $profil['ir_baremes']);
        $this->assertSame('frais_pro', $profil['frais_pro']);
        $this->assertSame(['debut' => '2026-01-01', 'fin' => '2026-12-31'], $profil['periode']);
        $this->assertNull($profil['date_effet_projetee']);

        $exercices = app(ExercicesFiscaux::class);
        $this->assertSame(config('payroll.ir.baremes'), $exercices->parametre(2026, 'ir_baremes'));
        $this->assertSame(config('payroll.frais_pro'), $exercices->parametre(2026, 'frais_pro'));
        $this->assertTrue($exercices->calculable(2026));
    }

    public function test_les_profils_futurs_sont_des_projets_dates_non_calculables(): void
    {
        $source = config('payroll.exercices.2027.source');

        $this->assertSame('texte déposé', $source['version']);
        $this->assertSame('2026-10-23', $source['date_publication']);
        $this->assertStringStartsWith('https://www.finances.gov.ma/', $source['url']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $source['sha256']);
        $this->assertStringContainsString('60-26', $source['texte']);

        foreach ([2027, 2028, 2029, 2030, 2031] as $annee) {
            $profil = config("payroll.exercices.{$annee}");

            $this->assertSame('projet', $profil['statut'], "{$annee} : statut");
            $this->assertNull($profil['validation_legale'], "{$annee} : aucune validation légale");
            $this->assertNull($profil['frais_pro'], "{$annee} : frais professionnels non définis (#282)");
            $this->assertSame($source, $profil['source'], "{$annee} : même source datée");
            $this->assertSame(['debut' => "{$annee}-01-01", 'fin' => "{$annee}-12-31"], $profil['periode']);
            $this->assertSame("{$annee}-01-01", $profil['date_effet_projetee'], "{$annee} : date d'effet projetée");
            $this->assertIsString($profil['reference_ir']);
            $this->assertIsArray($profil['ir_baremes'], "{$annee} : barème en valeurs");
            $this->assertFalse(app(ExercicesFiscaux::class)->calculable($annee), "{$annee} : non calculable");
        }
    }

    public function test_aucun_exercice_autre_que_le_repli_n_est_en_vigueur(): void
    {
        foreach (config('payroll.exercices') as $annee => $profil) {
            if ($annee === config('payroll.exercice_defaut')) {
                continue;
            }

            $this->assertNotSame('en_vigueur', $profil['statut'], "{$annee} ne peut pas être en vigueur sans validation légale");
        }
    }
}
