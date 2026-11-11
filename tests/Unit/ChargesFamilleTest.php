<?php

namespace Tests\Unit;

use App\Services\PayrollCalculatorService;
use Tests\TestCase;

/**
 * Déduction pour charges de famille (Art. 74 CGI, issue #273) : plafond de la
 * déduction, prise en compte du conjoint et borne zéro de l'IR net.
 *
 * Le moteur compte enfants et conjoint à charge, multiplie par le montant par
 * personne, plafonne la déduction, puis la retranche de l'IR mensuel brut sans
 * jamais descendre sous zéro. Au-delà du nombre de personnes couvertes par le
 * plafond, il ajoute un avertissement. Montant par personne et plafond sont lus
 * dans config('payroll.charges_famille'), jamais recopiés.
 *
 * Ces tests ne tranchent pas l'éligibilité légale des personnes à charge
 * (issues #15 et #165) : ils fixent le comportement actuel du moteur.
 */
class ChargesFamilleTest extends TestCase
{
    /**
     * Base dont l'IR mensuel brut dépasse la déduction maximale augmentée d'une
     * personne : la déduction et son plafond se lisent intégralement dans ir_net.
     */
    private const BASE_IR_OBSERVABLE = 20000.0;

    /**
     * Base dont l'IR mensuel brut est strictement positif mais inférieur à la
     * déduction d'une seule personne : seule la borne zéro protège ir_net.
     */
    private const BASE_IR_FAIBLE = 6000.0;

    private PayrollCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = app(PayrollCalculatorService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function calculer(float $base, int $nbEnfants, bool $conjoint): array
    {
        return $this->calculator->calculer([
            'salaire_base' => $base,
            'type_frais_pro' => 'commun',
            'nb_enfants' => $nbEnfants,
            'conjoint_charge' => $conjoint ? 1 : 0,
        ]);
    }

    private function parPersonne(): float
    {
        return (float) config('payroll.charges_famille.par_personne');
    }

    private function plafond(): float
    {
        return (float) config('payroll.charges_famille.plafond');
    }

    /** Nombre de personnes dont la déduction atteint exactement le plafond. */
    private function maxPersonnes(): int
    {
        $max = (int) ($this->plafond() / $this->parPersonne());

        // Les frontières testées supposent un plafond multiple du montant par
        // personne et au moins deux personnes couvertes (conjoint et enfant).
        $this->assertSame($this->plafond(), $max * $this->parPersonne(), 'plafond multiple du montant par personne');
        $this->assertGreaterThanOrEqual(2, $max, 'au moins deux personnes couvertes');

        return $max;
    }

    private function avertissementPlafond(int $nbPersonnes): string
    {
        return __('ui.result.warning_dependents_cap', [
            'nombre' => (string) $nbPersonnes,
            'max' => (string) $this->maxPersonnes(),
            'plafond' => number_format($this->plafond(), 2, ',', ' '),
        ]);
    }

    /**
     * Frontières du plafond, conjoint compris. Chaque cas : libellé, enfants,
     * conjoint, personnes attendues, déduction attendue, avertissement attendu.
     *
     * @return list<array{string, int, bool, int, float, bool}>
     */
    private function cas(): array
    {
        $max = $this->maxPersonnes();
        $parPersonne = $this->parPersonne();
        $plafond = $this->plafond();

        return [
            ['aucune personne', 0, false, 0, 0.0, false],
            ['conjoint seul', 0, true, 1, $parPersonne, false],
            ['enfant seul', 1, false, 1, $parPersonne, false],
            ['juste avant le plafond', $max - 1, false, $max - 1, ($max - 1) * $parPersonne, false],
            ['au plafond, enfants seuls', $max, false, $max, $plafond, false],
            ['au plafond, atteint par le conjoint', $max - 1, true, $max, $plafond, false],
            ['après le plafond, enfants seuls', $max + 1, false, $max + 1, $plafond, true],
            ['après le plafond, franchi par le conjoint', $max, true, $max + 1, $plafond, true],
        ];
    }

    public function test_la_deduction_suit_les_frontieres_du_plafond_et_le_conjoint(): void
    {
        $reference = $this->calculer(self::BASE_IR_OBSERVABLE, 0, false);
        $irBrut = $reference['ir_mensuel_brut'];

        // Sans plafond, le cas le plus chargé déduirait (max + 1) personnes :
        // l'IR brut doit rester au-dessus pour que l'écart se voie dans ir_net.
        $this->assertGreaterThan(($this->maxPersonnes() + 1) * $this->parPersonne(), $irBrut, 'IR brut assez élevé');

        foreach ($this->cas() as [$libelle, $enfants, $conjoint, $personnes, $deduction, $avertissement]) {
            $result = $this->calculer(self::BASE_IR_OBSERVABLE, $enfants, $conjoint);

            // La déduction intervient après le barème : l'IR brut ne bouge pas.
            $this->assertSame($irBrut, $result['ir_mensuel_brut'], $libelle);
            $this->assertSame($personnes, $result['nb_personnes'], $libelle);
            $this->assertSame($deduction, $result['charges_famille'], $libelle);
            $this->assertSame(round($irBrut - $deduction, 2), $result['ir_net'], $libelle);

            // Aucune autre règle ne se déclenche à cette base : l'avertissement
            // du plafond est seul présent au-delà, et absent jusqu'au plafond.
            $this->assertSame(
                $avertissement ? [$this->avertissementPlafond($personnes)] : [],
                $result['avertissements'],
                $libelle
            );
        }
    }

    public function test_au_dela_du_plafond_chaque_personne_supplementaire_ne_change_plus_l_ir(): void
    {
        $max = $this->maxPersonnes();

        $auPlafond = $this->calculer(self::BASE_IR_OBSERVABLE, $max, false);
        $auDela = $this->calculer(self::BASE_IR_OBSERVABLE, $max, true);

        $this->assertSame($auPlafond['charges_famille'], $auDela['charges_famille']);
        $this->assertSame($auPlafond['ir_net'], $auDela['ir_net']);
        $this->assertSame($auPlafond['salaire_net'], $auDela['salaire_net']);
    }

    public function test_une_deduction_superieure_a_l_ir_brut_laisse_un_ir_net_nul(): void
    {
        $result = $this->calculer(self::BASE_IR_FAIBLE, 1, true);
        $deduction = 2 * $this->parPersonne();

        // L'IR brut est réellement positif, et la déduction le dépasse : sans
        // borne zéro, ir_net deviendrait négatif.
        $this->assertGreaterThan(0.0, $result['ir_mensuel_brut'], 'IR brut positif');
        $this->assertSame(2, $result['nb_personnes']);
        $this->assertSame($deduction, $result['charges_famille']);
        $this->assertLessThan($deduction, $result['ir_mensuel_brut'], 'déduction supérieure à l\'IR brut');

        $this->assertSame(0.0, $result['ir_net']);
        $this->assertSame([], $result['avertissements']);

        // Un IR nul ne rend rien au salarié : le net ne contient que SBI moins
        // cotisations, sans surplus issu d'un IR négatif.
        $this->assertSame(
            round($result['sbi'] - $result['cotisation_cnss'] - $result['cotisation_amo'] - $result['cotisation_cimr'], 2),
            $result['salaire_net']
        );
    }
}
