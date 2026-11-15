<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Affichage des frais professionnels proposés pour 2027 (issue #282), en FR,
 * EN, AR et ES.
 *
 * - Résultat 2027 : le détail des frais professionnels décrit le taux unique du
 *   projet (clé ui.result.pro_fees_regime_common_single), sans l'ancien seuil
 *   de 6 500 MAD, et la réserve de projet reste affichée.
 * - Fiabilité : l'exercice 2027 mentionne les paramètres proposés (taux,
 *   plafond annuel, référence) avec la clé ui.fiscal_year.pro_fees_project ;
 *   l'exercice 2026 et les exercices non calculables ne la portent pas.
 *
 * Taux et plafond sont lus dans config/payroll.php, jamais dans les
 * traductions.
 */
class FraisPro2027AffichageTest extends TestCase
{
    private const LOCALES = ['fr', 'en', 'ar', 'es'];

    private const CLES = [
        'ui.result.pro_fees_regime_common_single',
        'ui.fiscal_year.pro_fees_project',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function locales(): array
    {
        $cas = [];
        foreach (self::LOCALES as $locale) {
            $cas[$locale] = [$locale];
        }

        return $cas;
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        $precedent = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($precedent);

        return new \DOMXPath($document);
    }

    private function texte(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function texteNoeud(\DOMNode $noeud): string
    {
        return trim(preg_replace('/\s+/u', ' ', $noeud->textContent));
    }

    private function taux(): string
    {
        return rtrim(rtrim(number_format(config('payroll.exercices.2027.frais_pro.taux') * 100, 2, ',', ' '), '0'), ',');
    }

    private function plafondAnnuel(): string
    {
        return number_format(config('payroll.exercices.2027.frais_pro.plafond_annuel'), 0, ',', ' ');
    }

    private function detailFraisPro(string $html, string $locale): string
    {
        $libelle = preg_quote(e(trans('ui.result.pro_fees', [], $locale)), '/');
        $trouve = preg_match('/'.$libelle.'\s*<br><small class="result-muted">(.*?)<\/small>/su', $html, $m);
        $this->assertSame(1, $trouve, "{$locale} : ligne de détail des frais professionnels introuvable.");

        return $this->texte($m[1]);
    }

    #[DataProvider('locales')]
    public function test_les_cles_existent_et_sont_traduites(string $locale): void
    {
        foreach (self::CLES as $cle) {
            $valeur = trans($cle, [], $locale);
            $this->assertNotSame($cle, $valeur, "{$locale} : clé {$cle} absente");
            $this->assertStringNotContainsString("\u{2014}", $valeur, "{$locale} : {$cle}");

            if ($locale !== 'fr') {
                $this->assertNotSame(trans($cle, [], 'fr'), $valeur, "{$locale} : {$cle} non traduite");
            }
        }
    }

    #[DataProvider('locales')]
    public function test_le_resultat_2027_decrit_le_taux_unique_du_projet(string $locale): void
    {
        $html = $this->withSession(['locale' => $locale])
            ->post('/calculateur/calculer', ['mode' => 'gross_to_net', 'salaire_base' => 10000, 'type_frais_pro' => 'commun', 'exercice' => '2027'])
            ->assertOk()
            ->getContent();

        $attendu = trans('ui.result.pro_fees_regime_common_single', ['rate' => $this->taux()], $locale)
            .' - '.trans('ui.result.pro_fees_capped', [], $locale);

        $detail = $this->detailFraisPro($html, $locale);
        $this->assertSame($attendu, $detail, "{$locale} : détail des frais professionnels 2027");
        $this->assertStringNotContainsString('6 500', $detail, "{$locale} : aucun seuil 2026 dans le détail 2027");
        $this->assertStringContainsString('2 916,67', $this->texte($html), "{$locale} : montant plafonné affiché");

        $this->assertStringContainsString(
            trans('ui.fiscal_year.project_reserve', [], $locale),
            $this->texte($html),
            "{$locale} : réserve de projet",
        );

        if ($locale === 'ar') {
            $this->assertMatchesRegularExpression('/<html[^>]*dir="rtl"/', $html);
        }
    }

    #[DataProvider('locales')]
    public function test_le_resultat_2026_garde_son_detail(string $locale): void
    {
        $html = $this->withSession(['locale' => $locale])
            ->post('/calculateur/calculer', ['mode' => 'gross_to_net', 'salaire_base' => 10000, 'type_frais_pro' => 'commun', 'exercice' => '2026'])
            ->assertOk()
            ->getContent();

        $this->assertSame(
            trans('ui.result.pro_fees_regime_common_high', [
                'threshold' => number_format(config('payroll.frais_pro.seuil_mensuel'), 0, ',', ' '),
                'rate' => '25',
            ], $locale),
            $this->detailFraisPro($html, $locale),
        );
    }

    #[DataProvider('locales')]
    public function test_la_fiabilite_presente_les_frais_professionnels_proposes_pour_2027(string $locale): void
    {
        $html = $this->withSession(['locale' => $locale])->get('/fiabilite')->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $note = trans('ui.fiscal_year.pro_fees_project', ['rate' => $this->taux(), 'cap' => $this->plafondAnnuel()], $locale);

        foreach (config('payroll.exercices') as $annee => $profil) {
            $entrees = $xpath->query('//*[@id="trust-fiscal-years"]//li[.//*[@data-fiscal-year="'.$annee.'"]]');
            $this->assertSame(1, $entrees->length, "{$locale} : une entrée pour {$annee}");
            $texte = $this->texteNoeud($entrees->item(0));

            if ($annee === 2027) {
                $this->assertStringContainsString($note, $texte, "{$locale} : paramètres proposés 2027");
                $this->assertStringContainsString(config('payroll.exercices.2027.frais_pro.reference'), $texte, "{$locale} : référence 2027");
            } else {
                $this->assertStringNotContainsString($note, $texte, "{$locale} : aucune note frais professionnels projet pour {$annee}");
            }
        }
    }
}
