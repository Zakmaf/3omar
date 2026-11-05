<?php

namespace Tests\Unit;

use App\Services\PayrollCalculatorService;
use Tests\TestCase;

/**
 * Frais professionnels (Art. 59 I-A CGI, issue #238) : statut artiste et
 * frontière du seuil mensuel du régime commun.
 *
 * Le moteur applique au SBI le taux du régime retenu, puis plafonne au plafond
 * mensuel. Régime commun : taux bas si SBI <= seuil_mensuel, taux haut au-delà.
 * Taux, seuil et plafonds sont lus dans config('payroll.frais_pro'), jamais
 * recopiés (docs/CALCUL.md, « Contrôles de cohérence »).
 */
class FraisProfessionnelsTest extends TestCase
{
    private PayrollCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = app(PayrollCalculatorService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function calculer(float $base, string $type): array
    {
        $result = $this->calculator->calculer([
            'salaire_base' => $base,
            'type_frais_pro' => $type,
        ]);

        // Sans prime ni heure supplémentaire, le SBI est le salaire de base.
        $this->assertSame($base, $result['sbi']);

        return $result;
    }

    public function test_artiste_sous_le_plafond_applique_son_taux(): void
    {
        $config = config('payroll.frais_pro.artiste');
        $base = 5000.0;
        $this->assertLessThan($config['plafond'], $base * $config['taux'], 'saisie sous le plafond');

        $result = $this->calculer($base, 'artiste');

        $this->assertSame($config['taux'], $result['taux_fp']);
        $this->assertSame($config['plafond'], $result['plafond_fp']);
        $this->assertSame(round($base * $config['taux'], 2), $result['frais_pro']);
        $this->assertFalse($result['fp_plafonne']);
    }

    public function test_artiste_au_dessus_du_plafond_est_plafonne(): void
    {
        $config = config('payroll.frais_pro.artiste');
        $base = 10000.0;
        $this->assertGreaterThan($config['plafond'], $base * $config['taux'], 'saisie au-dessus du plafond');

        $result = $this->calculer($base, 'artiste');

        $this->assertSame($config['taux'], $result['taux_fp']);
        $this->assertSame((float) $config['plafond'], $result['frais_pro']);
        $this->assertTrue($result['fp_plafonne']);
    }

    public function test_regime_commun_au_seuil_applique_le_taux_bas(): void
    {
        $seuil = (float) config('payroll.frais_pro.seuil_mensuel');
        $bas = config('payroll.frais_pro.commun.bas');
        $this->assertLessThan($bas['plafond'], $seuil * $bas['taux'], 'seuil sous le plafond du taux bas');

        $result = $this->calculer($seuil, 'commun');

        $this->assertSame($bas['taux'], $result['taux_fp']);
        $this->assertSame(round($seuil * $bas['taux'], 2), $result['frais_pro']);
        $this->assertFalse($result['fp_plafonne']);
    }

    public function test_regime_commun_un_centime_au_dessus_du_seuil_applique_le_taux_haut(): void
    {
        $base = round(config('payroll.frais_pro.seuil_mensuel') + 0.01, 2);
        $haut = config('payroll.frais_pro.commun.haut');
        $this->assertLessThan($haut['plafond'], $base * $haut['taux'], 'saisie sous le plafond du taux haut');

        $result = $this->calculer($base, 'commun');

        $this->assertSame($haut['taux'], $result['taux_fp']);
        $this->assertSame(round($base * $haut['taux'], 2), $result['frais_pro']);
        $this->assertFalse($result['fp_plafonne']);
    }

    public function test_la_frontiere_du_seuil_fait_baisser_les_frais(): void
    {
        $seuil = (float) config('payroll.frais_pro.seuil_mensuel');

        $auSeuil = $this->calculer($seuil, 'commun');
        $auDela = $this->calculer(round($seuil + 0.01, 2), 'commun');

        // Effet de seuil assumé par le barème : un centime de plus de SBI fait
        // passer au taux haut, donc baisser les frais déduits.
        $this->assertNotSame($auSeuil['taux_fp'], $auDela['taux_fp']);
        $this->assertGreaterThan($auDela['frais_pro'], $auSeuil['frais_pro']);
    }
}
