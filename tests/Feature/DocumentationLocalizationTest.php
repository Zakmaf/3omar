<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Page /documentation (issue #216) : l'exemple de plafond de l'épargne
 * retraite et l'unité du SMIG mensuel passent par les catalogues
 * lang/{fr,en,ar,es}/ui.php, avec des nombres toujours lus dans
 * config/payroll.php.
 */
class DocumentationLocalizationTest extends TestCase
{
    /**
     * Texte de la page, espaces regroupés, pour comparer des phrases que le
     * gabarit peut répartir sur plusieurs lignes.
     */
    private function documentation(string $locale): string
    {
        $html = $this->withSession(['locale' => $locale])->get('/documentation')->assertOk()->getContent();

        return preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function test_foreign_locales_have_no_french_ceiling_example_or_units(): void
    {
        foreach (['en', 'ar', 'es'] as $locale) {
            $texte = $this->documentation($locale);

            $this->assertStringNotContainsString('Plafond =', $texte, $locale);
            $this->assertStringNotContainsString('MAD/an', $texte, $locale);
            $this->assertStringNotContainsString('MAD/mois', $texte, $locale);
        }
    }

    public function test_foreign_locales_render_the_translated_example_and_smig_unit(): void
    {
        $this->assertStringContainsString('Ceiling = 8 000 × 12 × 50% = 48 000 MAD/year', $this->documentation('en'));
        $this->assertStringContainsString('3 422,72 MAD/month', $this->documentation('en'));

        $this->assertStringContainsString('السقف = 8 000 × 12 × 50% = 48 000 درهم/سنة', $this->documentation('ar'));
        $this->assertStringContainsString('3 422,72 درهم/شهر', $this->documentation('ar'));

        $this->assertStringContainsString('Tope = 8 000 × 12 × 50% = 48 000 MAD/año', $this->documentation('es'));
        $this->assertStringContainsString('3 422,72 MAD/mes', $this->documentation('es'));
    }

    public function test_french_rendering_is_unchanged(): void
    {
        $texte = $this->documentation('fr');

        $this->assertStringContainsString('Plafond = 8 000 × 12 × 50% = 48 000 MAD/an', $texte);
        $this->assertStringContainsString('3 422,72 MAD/mois', $texte);
    }

    public function test_ceiling_example_follows_the_payroll_configuration(): void
    {
        config([
            'payroll.ir.nb_mois' => 13,
            'payroll.retraite_complementaire.deduction_ir_max_pct' => 0.25,
        ]);

        $this->assertStringContainsString('Plafond = 8 000 × 13 × 25% = 26 000 MAD/an', $this->documentation('fr'));
        $this->assertStringContainsString('Ceiling = 8 000 × 13 × 25% = 26 000 MAD/year', $this->documentation('en'));
    }
}
