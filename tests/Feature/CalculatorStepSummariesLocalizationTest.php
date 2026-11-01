<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Calculateur (issue #214) : les résumés d'étapes de l'assistant, l'aperçu de
 * la prime d'ancienneté, les tranches d'ancienneté et l'aide des charges de
 * famille passent par lang/{fr,en,ar,es}/ui.php. Le seuil d'ancienneté du
 * script suit config('payroll.anciennete.tranches').
 */
class CalculatorStepSummariesLocalizationTest extends TestCase
{
    /**
     * Fragments français écrits en dur dans le gabarit ou dans son script.
     */
    private const FRAGMENTS_FRANCAIS = [
        "' ligne'",
        "' indemnite'",
        "'% anc.'",
        "' enf.'",
        "'conjoint'",
        "'Av. CNSS '",
        "'Mut. '",
        "'Ret. '",
        '(< 2 ans)',
        'MAD/mois x nombre de personnes',
    ];

    private const RESUMES = [
        'summary_seniority',
        'summary_overtime_one',
        'summary_overtime_other',
        'summary_allowances_one',
        'summary_allowances_other',
        'summary_cnss_benefits',
        'summary_mutual',
        'summary_children_one',
        'summary_children_other',
        'summary_spouse',
        'summary_deductions',
    ];

    private function calculateur(string $locale): string
    {
        return $this->withSession(['locale' => $locale])->get('/calculateur')->assertOk()->getContent();
    }

    /**
     * Une valeur telle que @json l'écrit dans le script.
     */
    private function json(mixed $valeur): string
    {
        return json_encode($valeur, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    }

    public function test_no_locale_renders_hard_coded_french_fragments(): void
    {
        foreach (array_keys(config('app.supported_locales')) as $locale) {
            $html = $this->calculateur($locale);

            foreach (self::FRAGMENTS_FRANCAIS as $fragment) {
                $this->assertStringNotContainsString($fragment, $html, "{$locale} : {$fragment}");
            }
        }
    }

    public function test_step_summaries_are_rendered_in_the_active_locale(): void
    {
        foreach (['en', 'ar'] as $locale) {
            $html = $this->calculateur($locale);

            $resumes = [];
            foreach (self::RESUMES as $cle) {
                $resumes[$cle] = __("ui.calculator.{$cle}", [], $locale);
                $this->assertNotSame(__("ui.calculator.{$cle}", [], 'fr'), $resumes[$cle], "{$locale} : {$cle}");
            }

            $this->assertStringContainsString('const TRANS_SUMMARY = '.$this->json([
                'seniority' => $resumes['summary_seniority'],
                'overtimeOne' => $resumes['summary_overtime_one'],
                'overtimeOther' => $resumes['summary_overtime_other'],
                'allowancesOne' => $resumes['summary_allowances_one'],
                'allowancesOther' => $resumes['summary_allowances_other'],
                'cnssBenefits' => $resumes['summary_cnss_benefits'],
                'mutual' => $resumes['summary_mutual'],
                'childrenOne' => $resumes['summary_children_one'],
                'childrenOther' => $resumes['summary_children_other'],
                'spouse' => $resumes['summary_spouse'],
                'deductions' => $resumes['summary_deductions'],
            ]).';', $html, $locale);
        }
    }

    public function test_seniority_threshold_follows_the_payroll_configuration(): void
    {
        $tranches = config('payroll.anciennete.tranches');
        $tranches[0]['min_annees'] = 3;
        config(['payroll.anciennete.tranches' => $tranches]);

        $html = $this->calculateur('fr');
        $this->assertStringContainsString('const SENIORITY_MIN_YEARS = 3;', $html);
        $this->assertStringContainsString('const TRANS_SENIORITY_BELOW = '.$this->json('0% (moins de 3 ans)').';', $html);
        $this->assertStringContainsString('if (annees < SENIORITY_MIN_YEARS) return 0;', $html);
        $this->assertStringContainsString(e('< 3 ans · 0%'), $html);

        $html = $this->calculateur('en');
        $this->assertStringContainsString('const TRANS_SENIORITY_BELOW = '.$this->json('0% (under 3 years)').';', $html);
        $this->assertStringContainsString(e('< 3 years · 0%'), $html);
        $this->assertStringContainsString('3 to 4 years · 5%', $html);
    }

    public function test_seniority_brackets_are_translated(): void
    {
        $this->assertStringContainsString('2 à 4 ans · 5%', $this->calculateur('fr'));
        $this->assertStringContainsString('25 ans et plus · 25%', $this->calculateur('fr'));

        $html = $this->calculateur('es');
        $this->assertStringContainsString('De 2 a 4 años · 5%', $html);
        $this->assertStringContainsString('25 años o más · 25%', $html);
        $this->assertStringNotContainsString('ans et plus', $html);
    }

    public function test_family_help_is_translated_with_configured_amounts(): void
    {
        $montants = [
            'amount' => number_format(config('payroll.charges_famille.par_personne'), 2, ',', ' '),
            'ceiling' => number_format(config('payroll.charges_famille.plafond'), 2, ',', ' '),
        ];

        foreach (array_keys(config('app.supported_locales')) as $locale) {
            $this->assertStringContainsString(
                e(__('ui.calculator.family_help', $montants, $locale)),
                $this->calculateur($locale),
                $locale,
            );
        }

        $this->assertStringContainsString(
            'MAD/month per dependent, capped at '.$montants['ceiling'].' MAD/month (Art. 74 CGI)',
            __('ui.calculator.family_help', $montants, 'en'),
        );
    }
}
