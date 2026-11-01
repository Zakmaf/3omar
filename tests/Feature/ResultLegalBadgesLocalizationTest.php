<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

/**
 * Badges de référence légale du bulletin détaillé (issue #215) : chaque badge
 * passe par une clé ui.result.legal_* et suit la langue active, comme la page
 * documentation qui traduit déjà ces références.
 */
class ResultLegalBadgesLocalizationTest extends TestCase
{
    /**
     * Une simulation qui affiche toutes les lignes portant un badge légal :
     * ancienneté, indemnité au-delà du plafond, CNSS, AMO, CIMR, frais
     * professionnels, retraite complémentaire, IR, charges de famille et
     * détail employeur.
     */
    private const SAISIE = [
        'salaire_base' => 10000,
        'type_frais_pro' => 'commun',
        'nb_annees_anciennete' => 5,
        'cimr_taux' => 6,
        'cimr_taux_employeur' => 6,
        'retraite_complementaire_mensuel' => 500,
        'rc_part_employeur' => 200,
        'nb_enfants' => 2,
        'conjoint_charge' => 1,
        'indemnites' => [['type' => 'transport', 'montant' => 800]],
    ];

    private const BADGES_FR = [
        'Art. 350 CT',
        'Arrêté 1314-25',
        'Dahir 1-72-184',
        'Loi 65-00',
        'Art. 28-III CGI',
        'Art. 59 CGI',
        'Art. 28-IV CGI',
        'Art. 73 CGI',
        'Art. 74 CGI',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    /**
     * Textes des badges légaux rendus, sans doublon, dans l'ordre de la page.
     *
     * @return list<string>
     */
    private function badges(string $locale): array
    {
        $html = $this->withSession(['locale' => $locale])
            ->post('/calculateur/calculer', self::SAISIE)
            ->assertOk()
            ->getContent();

        preg_match_all(
            '/<span class="badge text-bg-light badge-legal ms-1">((?:<span class="num">[^<]*<\/span>|[^<])*)<\/span>/u',
            $html,
            $matches,
        );

        $textes = array_map(
            fn (string $badge) => trim(html_entity_decode(strip_tags($badge), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            $matches[1],
        );

        return array_values(array_unique($textes));
    }

    public function test_template_has_no_literal_legal_badge(): void
    {
        $gabarit = file_get_contents(resource_path('views/calculator/result.blade.php'));

        $this->assertSame(0, preg_match_all('/badge-legal ms-1">[^{]/', $gabarit));
    }

    public function test_french_badges_are_unchanged(): void
    {
        $this->assertSame(self::BADGES_FR, $this->badges('fr'));
    }

    public function test_foreign_locales_render_translated_badges(): void
    {
        $attendus = [
            'en' => ['Art. 350 Labor Code', 'Order No. 1314-25', 'Dahir No. 1-72-184', 'Law No. 65-00'],
            'ar' => ['المادة 350 من مدونة الشغل', 'القرار رقم 1314-25', 'ظهير رقم 1-72-184', 'القانون رقم 65-00'],
            'es' => ['Art. 350 Código del Trabajo', 'Orden n.º 1314-25', 'Dahir n.º 1-72-184', 'Ley n.º 65-00'],
        ];

        foreach ($attendus as $locale => $traduits) {
            $badges = $this->badges($locale);

            $this->assertCount(count(self::BADGES_FR), $badges, $locale);
            $this->assertNotContains('Art. 350 CT', $badges, $locale);
            $this->assertNotContains('Arrêté 1314-25', $badges, $locale);
            $this->assertNotContains('Loi 65-00', $badges, $locale);

            foreach ($traduits as $traduit) {
                $this->assertContains($traduit, $badges, $locale);
            }
        }
    }

    public function test_reference_numbers_are_isolated_from_bidi_reordering(): void
    {
        $html = $this->withSession(['locale' => 'ar'])
            ->post('/calculateur/calculer', self::SAISIE)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('المادة <span class="num">28-III</span> من CGI', $html);
        $this->assertStringContainsString('القرار رقم <span class="num">1314-25</span>', $html);
    }
}
