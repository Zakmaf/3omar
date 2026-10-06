<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class ResultPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_result_page_follows_the_reading_order_of_issue_122(): void
    {
        $this->post('/calculateur/calculer', [
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
            'nb_enfants' => 2,
        ])->assertOk()
            ->assertSeeInOrder([
                'data-result-section="synthese"',
                'data-result-section="diagnostic"',
                'data-result-section="explication"',
                'data-result-section="repartition"',
                'data-result-section="details"',
                'data-result-section="actions"',
            ], false)
            ->assertSeeTextInOrder([
                'Les montants à retenir',
                'Salaire brut',
                'Net à payer',
                'Coût total employeur',
                'Diagnostic',
                'Du brut au net, étape par étape',
                'Répartition du salaire brut',
                'Toutes les lignes du calcul',
                'Bulletin du salarié',
                'Coût employeur détaillé',
                'Barème IR',
                'Que faire ensuite ?',
            ]);
    }

    public function test_detail_tables_are_visible_and_keyboard_scrollable(): void
    {
        $html = $this->post('/calculateur/calculer', [
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
        ])->assertOk()
            ->assertDontSee('<details', false)
            ->assertSee('Base / Assiette (MAD/mois)')
            ->getContent();

        // Chaque zone défilante est atteignable au clavier et nommée par son titre.
        $this->assertSame(2, preg_match_all('/data-table-scroll tabindex="0" role="region" aria-labelledby="result-(employee|employer)-table-title"/', $html));
    }

    public function test_net_flow_shows_allowances_and_deductions_so_the_steps_add_up(): void
    {
        // 10 000 de base, 500 d'indemnité de transport exonérée, 200 de mutuelle salariale.
        $this->post('/calculateur/calculer', [
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
            'mutuelle_salarie' => 200,
            'indemnites' => [['type' => 'transport', 'montant' => 500]],
        ])->assertOk()
            ->assertSeeTextInOrder([
                'Du brut au net, étape par étape',
                'Salaire brut imposable',
                'Cotisations salariales',
                'Impôt sur le revenu',
                'Indemnités exonérées',
                '500,00',
                'Retenues sur la paie',
                '200,00',
                'Net à payer',
            ]);
    }

    public function test_flow_omits_allowance_and_deduction_steps_when_they_are_zero(): void
    {
        $html = $this->post('/calculateur/calculer', [
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
        ])->assertOk()->getContent();

        $flow = substr($html, strpos($html, 'data-result-block="flow"'));
        $flow = substr($flow, 0, strpos($flow, '</ol>'));

        $this->assertStringNotContainsString('Indemnités exonérées', $flow);
        $this->assertStringNotContainsString('Retenues sur la paie', $flow);
    }

    public function test_employer_detail_is_translated_in_every_locale(): void
    {
        foreach (['en', 'es', 'ar'] as $locale) {
            $this->withSession(['locale' => $locale])
                ->post('/calculateur/calculer', ['salaire_base' => 10000, 'type_frais_pro' => 'commun'])
                ->assertOk()
                ->assertSee(__('ui.result.employer_detail_title', [], $locale))
                ->assertSee(__('ui.result.gross_salary_paid', [], $locale))
                // Ancien récapitulatif employeur : libellés français codés en dur.
                ->assertDontSee('All. familiales')
                ->assertDontSee('Retraite compl. employeur');
        }
    }

    public function test_amounts_inside_translations_are_isolated_from_bidi_reordering(): void
    {
        $this->post('/calculateur/calculer', [
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
            'nb_enfants' => 2,
        ])->assertOk()
            ->assertSee('(× 12 = <span class="num">', false)
            ->assertSee('× <span class="num">', false);
    }

    public function test_payslip_detail_withholds_retenues_exonerees_ir_from_the_displayed_net(): void
    {
        $this->post('/calculateur/calculer', [
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
            'retenues_exonerees_ir' => 1000,
        ])->assertOk()
            ->assertSeeTextInOrder([
                "Retenues exonérées d'IR (déd. avant IR)",
                '− 1 000,00',
                'IR net retenu',
                '− 367,71',
                "Retenues exonérées d'IR (prélevées sur la paie)",
                '− 1 000,00',
                'NET À PAYER',
                '8 137,49',
            ]);
    }
}
