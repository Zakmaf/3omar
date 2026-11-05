<?php

namespace Tests\Unit;

use App\Services\PayrollCalculatorService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Heures supplémentaires (Art. 201 du Code du travail, issue #237).
 *
 * Le moteur arrondit d'abord le taux horaire (salaire de base / heures légales)
 * au centime, puis calcule chaque ligne : heures x taux horaire x (1 + majoration),
 * arrondi au centime. Le total entre dans le SBI. Les majorations et la base
 * horaire sont lues dans config/payroll.php ; seul l'exemple contrôlé fige des
 * montants, comme les golden tests.
 */
class HeuresSupplementairesTest extends TestCase
{
    private PayrollCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = app(PayrollCalculatorService::class);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function types(): array
    {
        return [
            'semaine_diurne' => ['semaine_diurne'],
            'semaine_nocturne' => ['semaine_nocturne'],
            'repos_diurne' => ['repos_diurne'],
            'repos_nocturne' => ['repos_nocturne'],
        ];
    }

    #[DataProvider('types')]
    public function test_chaque_majoration_s_applique_au_taux_horaire_arrondi(string $type): void
    {
        $base = 10000.0;
        $heures = 10.0;
        $tauxHoraire = round($base / config('payroll.smig.heures_legales'), 2);
        $majoration = config("payroll.heures_sup.majorations.{$type}");

        $result = $this->calculator->calculer([
            'salaire_base' => $base,
            'heures_sup' => [['type' => $type, 'nb_heures' => $heures]],
        ]);

        $this->assertCount(1, $result['detail_hs']);
        $ligne = $result['detail_hs'][0];

        $this->assertSame($type, $ligne['type']);
        $this->assertSame($heures, $ligne['nb_heures']);
        $this->assertSame($tauxHoraire, $ligne['taux_horaire']);
        $this->assertSame($majoration, $ligne['majoration']);
        $this->assertSame(round($heures * $tauxHoraire * (1 + $majoration), 2), $ligne['montant']);
        $this->assertSame(round($base + $ligne['montant'], 2), $result['sbi']);
    }

    public function test_exemple_controle_avec_les_majorations_2026(): void
    {
        // 10 000 / 191 = 52,356... : le taux horaire est arrondi à 52,36 AVANT
        // multiplication. Sans cet arrondi, 10 h à +25 % donneraient 654,45.
        $diurne = $this->calculator->calculer([
            'salaire_base' => 10000,
            'heures_sup' => [['type' => 'semaine_diurne', 'nb_heures' => 10]],
        ]);
        $nocturneRepos = $this->calculator->calculer([
            'salaire_base' => 10000,
            'heures_sup' => [['type' => 'repos_nocturne', 'nb_heures' => 10]],
        ]);

        $this->assertSame(52.36, $diurne['detail_hs'][0]['taux_horaire']);
        $this->assertSame(654.5, $diurne['detail_hs'][0]['montant']);
        $this->assertSame(10654.5, $diurne['sbi']);
        $this->assertSame(1047.2, $nocturneRepos['detail_hs'][0]['montant']);
        $this->assertSame(11047.2, $nocturneRepos['sbi']);
    }

    public function test_plusieurs_lignes_s_additionnent_et_une_ligne_a_zero_heure_est_ignoree(): void
    {
        $result = $this->calculator->calculer([
            'salaire_base' => 10000,
            'heures_sup' => [
                ['type' => 'semaine_diurne', 'nb_heures' => 10],
                ['type' => 'repos_nocturne', 'nb_heures' => 4],
                ['type' => 'semaine_nocturne', 'nb_heures' => 0],
            ],
        ]);

        $this->assertSame(['semaine_diurne', 'repos_nocturne'], array_column($result['detail_hs'], 'type'));

        $total = round(array_sum(array_column($result['detail_hs'], 'montant')), 2);
        $this->assertSame(round(10000 + $total, 2), $result['sbi']);
    }
}
