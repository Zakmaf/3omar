<?php

namespace Tests\Feature;

use App\Services\PayrollCalculatorService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Détail des frais professionnels du bulletin (issue #241) : le moteur expose
 * le régime retenu (regime_fp), la vue construit le texte dans la langue active
 * à partir des clés ui.result.pro_fees_regime_*, avec le taux et le seuil lus
 * dans config/payroll.php.
 */
class ResultProFeesDetailLocalizationTest extends TestCase
{
    private const LOCALES = ['fr', 'en', 'es', 'ar'];

    /**
     * Une saisie par régime, sous le plafond mensuel des frais professionnels.
     */
    private const REGIMES = [
        'commun_bas' => [
            'saisie' => ['salaire_base' => 5000, 'type_frais_pro' => 'commun'],
            'cle' => 'ui.result.pro_fees_regime_common_low',
            'taux' => 'payroll.frais_pro.commun.bas.taux',
        ],
        'commun_haut' => [
            'saisie' => ['salaire_base' => 10000, 'type_frais_pro' => 'commun'],
            'cle' => 'ui.result.pro_fees_regime_common_high',
            'taux' => 'payroll.frais_pro.commun.haut.taux',
        ],
        'journaliste' => [
            'saisie' => ['salaire_base' => 5000, 'type_frais_pro' => 'journaliste'],
            'cle' => 'ui.result.pro_fees_regime_journalist',
            'taux' => 'payroll.frais_pro.journaliste.taux',
        ],
        'artiste' => [
            'saisie' => ['salaire_base' => 5000, 'type_frais_pro' => 'artiste'],
            'cle' => 'ui.result.pro_fees_regime_artist',
            'taux' => 'payroll.frais_pro.artiste.taux',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function localesEtRegimes(): array
    {
        $cas = [];
        foreach (self::LOCALES as $locale) {
            foreach (array_keys(self::REGIMES) as $regime) {
                $cas["{$locale} {$regime}"] = [$locale, $regime];
            }
        }

        return $cas;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function regimes(): array
    {
        $cas = [];
        foreach (array_keys(self::REGIMES) as $regime) {
            $cas[$regime] = [$regime];
        }

        return $cas;
    }

    /**
     * Page résultat rendue dans une langue.
     *
     * @param  array<string, mixed>  $saisie
     */
    private function page(string $locale, array $saisie): string
    {
        return $this->withSession(['locale' => $locale])
            ->post('/calculateur/calculer', $saisie)
            ->assertOk()
            ->getContent();
    }

    /**
     * HTML brut de la ligne de détail placée sous le libellé « Frais professionnels ».
     */
    private function celluleBrute(string $html, string $locale): string
    {
        $libelle = preg_quote(e(trans('ui.result.pro_fees', [], $locale)), '/');
        $trouve = preg_match('/'.$libelle.'\s*<br><small class="result-muted">(.*?)<\/small>/su', $html, $m);

        $this->assertSame(1, $trouve, "{$locale} : ligne de détail des frais professionnels introuvable.");

        return $m[1];
    }

    private function texte(string $cellule): string
    {
        return trim(html_entity_decode(strip_tags($cellule), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function detail(string $locale, string $regime): string
    {
        return $this->texte($this->celluleBrute($this->page($locale, self::REGIMES[$regime]['saisie']), $locale));
    }

    private function taux(float $taux): string
    {
        return rtrim(rtrim(number_format($taux * 100, 2, ',', ' '), '0'), ',');
    }

    private function attendu(string $locale, string $regime): string
    {
        return trans(self::REGIMES[$regime]['cle'], [
            'threshold' => number_format(config('payroll.frais_pro.seuil_mensuel'), 0, ',', ' '),
            'rate' => $this->taux(config(self::REGIMES[$regime]['taux'])),
        ], $locale);
    }

    #[DataProvider('localesEtRegimes')]
    public function test_le_detail_suit_la_langue_active_et_le_regime_retenu(string $locale, string $regime): void
    {
        $detail = $this->detail($locale, $regime);

        $this->assertSame($this->attendu($locale, $regime), $detail);
        $this->assertStringNotContainsString("\u{2014}", $detail);
        $this->assertStringNotContainsString('SBI', $detail);
        $this->assertStringNotContainsString("\u{2192}", $detail);
    }

    #[DataProvider('regimes')]
    public function test_les_langues_etrangeres_n_affichent_pas_le_texte_francais(string $regime): void
    {
        $francais = $this->detail('fr', $regime);

        foreach (['en', 'es', 'ar'] as $locale) {
            $this->assertNotSame($francais, $this->detail($locale, $regime), $locale);
            $this->assertStringNotContainsString('taux', $this->detail($locale, $regime), $locale);
        }
    }

    public function test_le_taux_et_le_seuil_viennent_de_la_configuration(): void
    {
        config([
            'payroll.frais_pro.seuil_mensuel' => 7000,
            'payroll.frais_pro.commun.bas.taux' => 0.3,
        ]);

        $detail = $this->detail('fr', 'commun_bas');

        $this->assertStringContainsString('7 000', $detail);
        $this->assertStringContainsString('30%', $detail);
        $this->assertSame($this->attendu('fr', 'commun_bas'), $detail);
    }

    public function test_la_mention_plafonne_suit_la_langue_active(): void
    {
        $saisie = ['salaire_base' => 20000, 'type_frais_pro' => 'commun'];

        foreach (self::LOCALES as $locale) {
            $detail = $this->texte($this->celluleBrute($this->page($locale, $saisie), $locale));

            $this->assertSame(
                $this->attendu($locale, 'commun_haut').' - '.trans('ui.result.pro_fees_capped', [], $locale),
                $detail,
                $locale,
            );
        }
    }

    public function test_le_rendu_arabe_est_rtl_et_isole_les_nombres(): void
    {
        $html = $this->page('ar', self::REGIMES['commun_bas']['saisie']);
        $cellule = $this->celluleBrute($html, 'ar');

        $this->assertMatchesRegularExpression('/<html[^>]*dir="rtl"/', $html);
        $this->assertStringContainsString('<span class="num">6 500</span>', $cellule);
        $this->assertStringContainsString('<span class="num">'.$this->taux(config('payroll.frais_pro.commun.bas.taux')).'</span>%', $cellule);
    }

    #[DataProvider('regimes')]
    public function test_le_moteur_expose_le_regime_et_plus_de_phrase(string $regime): void
    {
        $resultat = app(PayrollCalculatorService::class)->calculer(self::REGIMES[$regime]['saisie']);

        $this->assertSame($regime, $resultat['regime_fp']);
        $this->assertSame(config(self::REGIMES[$regime]['taux']), $resultat['taux_fp']);
        $this->assertArrayNotHasKey('desc_fp', $resultat);
    }

    public function test_l_api_publique_expose_le_regime_sans_texte_francais(): void
    {
        $reponse = $this->postJson(
            '/api/v1/simuler/brut-vers-net',
            self::REGIMES['journaliste']['saisie'],
            ['Accept-Language' => 'en'],
        )->assertOk();

        $this->assertSame('journaliste', $reponse->json('regime_fp'));
        $this->assertNull($reponse->json('desc_fp'));
    }
}
