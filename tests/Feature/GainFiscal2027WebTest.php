<?php

namespace Tests\Feature;

use App\Services\GainFiscal2027;
use App\Services\SimulationCodec;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Page « Mon gain fiscal en janvier 2027 » (issue #283), par le vrai chemin HTTP.
 *
 * Contrat de l'interface (aucune API publique nouvelle) :
 * - route GET /calculateur/gain-2027?s=<payload SimulationCodec>, nommée
 *   calculator.gain2027, limitée comme le calcul (throttle:calculer) ;
 * - le payload est celui du lien de partage d'un résultat (2026 ou 2027, brut
 *   vers net ou net cible). Il est décodé et revalidé par SimulationCodec : un
 *   payload absent, illisible ou d'un exercice non calculable (2028) renvoie
 *   au calculateur avec la notice ui.calculator.gain2027_invalid_notice ;
 * - la page reçoit en donnée de vue `gain` la sortie exacte de
 *   GainFiscal2027::comparer() appliquée à la saisie décodée : aucun montant
 *   n'est recalculé par le contrôleur ni par la vue ;
 * - un net cible d'une saisie 2027 est refusé explicitement (retour au
 *   calculateur, notice ui.gain2027.net_source_2027_refused) : le net visé
 *   appartenait à 2027, le résoudre en 2026 changerait en silence le sens de
 *   la cible. Un net cible 2026 est résolu une fois en 2026 et la page dit
 *   « brut déterminé pour atteindre le net cible en 2026 »
 *   (ui.gain2027.net_mode_notice) ;
 * - une erreur du moteur (catégorie non calculable en 2027) renvoie au
 *   calculateur avec l'erreur traduite sous la clé calculator, comme la
 *   comparaison existante.
 *
 * Marqueurs de la page (indépendants de la langue) :
 * - [data-gain2027-page] porte data-source-exercice (exercice de la saisie
 *   reçue) et data-mode ;
 * - chaque montant affiché porte data-gain2027="<clé>" et
 *   data-montant="<valeur moteur, 2 décimales, point>" ; le texte visible est
 *   formaté comme les autres vues (number_format(..., 2, ',', ' ')). Clés :
 *   gain-mensuel (premier montant de la page), gain-pct, gain-annuel,
 *   net-2026, net-2027, ir-2026, ir-2027, fp-2026, fp-2027, brut-2026,
 *   brut-2027, net-intermediaire, part-bareme, part-frais-pro, et brut-fige en
 *   mode net cible ;
 * - un bloc x-fiscal-provenance par exercice (2026 en vigueur, 2027 projet,
 *   source officielle datée), aucun bloc 2028 à 2031 ;
 * - lien de partage #gain2027ShareUrl (même payload, rejoue la même
 *   comparaison), décrit par l'avertissement ui.result.share_privacy_warning ;
 * - bouton d'impression window.print() ; les montants ne sont jamais dans un
 *   bloc .no-print ;
 * - la page résultat propose le lien a[data-gain2027-cta] vers la page de gain
 *   pour sa propre saisie.
 *
 * Confidentialité : aucun script ni attribut d'analytics, aucun montant dans
 * le titre du document. Statut attendu au prochain run : ROUGE (route, vue,
 * contrôleur, clés de traduction et mode net cible absents).
 */
class GainFiscal2027WebTest extends TestCase
{
    private const LOCALES = ['fr', 'en', 'ar', 'es'];

    private const SAISIE_20000 = ['mode' => 'gross_to_net', 'salaire_base' => 20000, 'type_frais_pro' => 'commun'];

    private const SAISIE_COMPLETE = [
        'mode' => 'gross_to_net',
        'salaire_base' => 12000,
        'nb_annees_anciennete' => 5,
        'prime_rendement' => 500,
        'type_frais_pro' => 'commun',
        'nb_enfants' => 2,
        'conjoint_charge' => 1,
        'cimr_taux' => 6,
        'cimr_taux_employeur_inconnu' => 1,
        'amo_taux_salarie_personnalise' => 1,
        'amo_taux_salarie' => 2.5,
        'retraite_complementaire_mensuel' => 300,
        'mutuelle_salarie' => 200,
        'mutuelle_patronale_inconnu' => 1,
        'rc_part_employeur_inconnu' => 1,
        'retenues_exonerees_ir' => 50,
        'retenues_imposees_ir' => 100,
        'indemnites' => [['type' => 'transport', 'montant' => 700]],
    ];

    private const CLES = [
        'ui.gain2027.title',
        'ui.gain2027.cta',
        'ui.gain2027.headline_label',
        'ui.gain2027.same_input_notice',
        'ui.gain2027.source_2027_notice',
        'ui.gain2027.net_mode_notice',
        'ui.gain2027.prospective_notice',
        'ui.gain2027.badge_projet',
        'ui.gain2027.badge_applicable',
        'ui.gain2027.net_guaranteed_notice',
        'ui.gain2027.annual_indicative',
        'ui.gain2027.no_ir_gain',
        'ui.gain2027.details_title',
        'ui.gain2027.decomposition_title',
        'ui.gain2027.share_label',
        'ui.gain2027.net_source_2027_refused',
        'ui.calculator.gain2027_invalid_notice',
    ];

    /** Montant affiché => chemin dans la sortie du service. */
    private const MONTANTS = [
        'gain-mensuel' => 'gain.net_mensuel',
        'gain-annuel' => 'gain.net_annuel_indicatif',
        'net-2026' => 'reference.salaire_net',
        'net-2027' => 'projete.salaire_net',
        'ir-2026' => 'reference.ir_net',
        'ir-2027' => 'projete.ir_net',
        'fp-2026' => 'reference.frais_pro',
        'fp-2027' => 'projete.frais_pro',
        'brut-2026' => 'reference.salaire_brut_total',
        'brut-2027' => 'projete.salaire_brut_total',
        'net-intermediaire' => 'decomposition.net_intermediaire',
        'part-bareme' => 'decomposition.bareme',
        'part-frais-pro' => 'decomposition.frais_pro',
    ];

    private SimulationCodec $codec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->codec = app(SimulationCodec::class);
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

    // ------------------------------------------------------------------
    // Outils (noms propres à ce test, sans collision avec TestCase)
    // ------------------------------------------------------------------

    private function urlGain2027(string $payload): string
    {
        return '/calculateur/gain-2027?s='.$payload;
    }

    private function ouvrirGain2027(array $saisie, string $locale = 'fr'): \Illuminate\Testing\TestResponse
    {
        return $this->withSession(['locale' => $locale])
            ->get($this->urlGain2027($this->codec->encode($saisie)));
    }

    private function domGain2027(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        $precedent = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($precedent);

        return new \DOMXPath($document);
    }

    private function texteNoeud(\DOMNode $noeud): string
    {
        return trim(preg_replace('/\s+/u', ' ', $noeud->textContent));
    }

    private function montantMachine(float $valeur): string
    {
        return number_format($valeur, 2, '.', '');
    }

    private function montantAffiche(float $valeur): string
    {
        return number_format($valeur, 2, ',', ' ');
    }

    private function elementMontant(\DOMXPath $xpath, string $cle): \DOMElement
    {
        $noeuds = $xpath->query('//*[@data-gain2027="'.$cle.'"]');
        $this->assertSame(1, $noeuds->length, "Un seul montant {$cle} sur la page.");

        return $noeuds->item(0);
    }

    /** Chemin et payload s d'une URL absolue ou relative. */
    private function cheminEtPayload(string $url): array
    {
        $parties = parse_url($url);
        parse_str($parties['query'] ?? '', $query);

        return [$parties['path'] ?? '', $query['s'] ?? null];
    }

    private function sortieService(array $saisieDecodee): array
    {
        app()->setLocale('fr');

        return app(GainFiscal2027::class)->comparer($saisieDecodee);
    }

    private function assertMontantsDuService(\DOMXPath $xpath, array $gain, bool $texteFr): void
    {
        foreach (self::MONTANTS as $cle => $chemin) {
            $valeur = (float) data_get($gain, $chemin);
            $element = $this->elementMontant($xpath, $cle);

            $this->assertSame($this->montantMachine($valeur), $element->getAttribute('data-montant'), "{$cle} : valeur du moteur");
            if ($texteFr) {
                $this->assertStringContainsString($this->montantAffiche($valeur), $this->texteNoeud($element), "{$cle} : montant lisible");
            }
        }

        if ($gain['gain']['net_pct'] !== null) {
            $this->assertSame(
                $this->montantMachine($gain['gain']['net_pct']),
                $this->elementMontant($xpath, 'gain-pct')->getAttribute('data-montant'),
                'gain-pct : écart relatif du comparateur',
            );
        }
    }

    private function assertProvenance(\DOMXPath $xpath, string $locale): void
    {
        $blocs = $xpath->query('//*[@data-fiscal-year]');
        $this->assertSame(2, $blocs->length, "{$locale} : un bloc de provenance par exercice comparé");

        $annees = [];
        foreach ($blocs as $bloc) {
            $annees[] = $bloc->getAttribute('data-fiscal-year');
        }
        $this->assertSame(['2026', '2027'], $annees, "{$locale} : 2026 puis 2027, aucun exercice 2028 à 2031");

        $this->assertSame('en_vigueur', $blocs->item(0)->getAttribute('data-fiscal-status'));
        $bloc2027 = $blocs->item(1);
        $this->assertSame('projet', $bloc2027->getAttribute('data-fiscal-status'), "{$locale} : 2027 reste un projet");

        $source = config('payroll.exercices.2027.source');
        $this->assertStringContainsString($source['texte'], $this->texteNoeud($bloc2027), "{$locale} : source officielle");
        $this->assertSame(1, $xpath->query('.//time[@datetime="'.$source['date_publication'].'"]', $bloc2027)->length, "{$locale} : date du texte");

        $lien = $xpath->query('.//a[@href="'.$source['url'].'"]', $bloc2027);
        $this->assertSame(1, $lien->length, "{$locale} : lien vers le texte officiel");
        $rel = preg_split('/\s+/', trim($lien->item(0)->getAttribute('rel')));
        $this->assertContains('noopener', $rel);
        $this->assertContains('noreferrer', $rel, 'La page porte un payload : aucun référent transmis.');
        $this->assertStringContainsString(trans('ui.fiscal_year.project_reserve', [], $locale), $this->texteNoeud($bloc2027));
    }

    private function assertContientTraduction(string $html, string $cle, string $locale): void
    {
        $this->assertStringContainsString(e(trans($cle, [], $locale)), $html, "{$locale} : {$cle} affiché");
    }

    private function assertSansTraduction(string $html, string $cle, string $locale): void
    {
        $this->assertStringNotContainsString(e(trans($cle, [], $locale)), $html, "{$locale} : {$cle} absent");
    }

    // ------------------------------------------------------------------
    // Catalogues et route
    // ------------------------------------------------------------------

    #[DataProvider('locales')]
    public function test_les_libelles_existent_dans_chaque_langue(string $locale): void
    {
        foreach (self::CLES as $cle) {
            $texte = trans($cle, [], $locale);
            $this->assertIsString($texte, "{$locale} : {$cle}");
            $this->assertNotSame($cle, $texte, "{$locale} : clé {$cle} absente du catalogue");
            $this->assertNotSame('', trim($texte), "{$locale} : {$cle} vide");
            $this->assertStringNotContainsString("\u{2014}", $texte, "{$locale} : {$cle} sans tiret cadratin");
        }
    }

    public function test_la_route_est_nommee_en_get_et_limitee_comme_le_calcul(): void
    {
        $route = Route::getRoutes()->getByName('calculator.gain2027');

        $this->assertNotNull($route, 'Route calculator.gain2027 déclarée.');
        $this->assertSame('calculateur/gain-2027', $route->uri());
        $this->assertContains('GET', $route->methods());
        $this->assertContains('throttle:calculer', $route->gatherMiddleware());
    }

    // ------------------------------------------------------------------
    // Lien depuis la page résultat
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{array}>
     */
    public static function resultatsSources(): array
    {
        return [
            'brut 2026 sans exercice' => [self::SAISIE_20000],
            'brut 2027' => [self::SAISIE_20000 + ['exercice' => '2027']],
            'net cible 2026' => [['mode' => 'net_to_gross', 'net_cible' => 12000, 'type_frais_pro' => 'commun']],
        ];
    }

    #[DataProvider('resultatsSources')]
    public function test_la_page_resultat_propose_le_gain_2027_pour_sa_propre_saisie(array $saisie): void
    {
        $response = $this->post('/calculateur/calculer', $saisie)->assertOk();
        $xpath = $this->domGain2027($response->getContent());

        $liens = $xpath->query('//a[@data-gain2027-cta]');
        $this->assertSame(1, $liens->length, 'Un lien « Mon gain fiscal en janvier 2027 » sur la page résultat.');
        $lien = $liens->item(0);
        $this->assertStringContainsString(trans('ui.gain2027.cta'), $this->texteNoeud($lien));

        [$chemin, $payload] = $this->cheminEtPayload($lien->getAttribute('href'));
        $this->assertSame('/calculateur/gain-2027', $chemin);
        $this->assertSame(
            $this->codec->decode($response->viewData('share_payload')),
            $this->codec->decode($payload),
            'Le lien porte la saisie du résultat, sans la modifier.',
        );
        $this->assertStringNotContainsString('salaire_base=', $lien->getAttribute('href'), 'Aucun montant en clair dans l\'URL.');
    }

    // ------------------------------------------------------------------
    // Page de gain : cas d'acceptation 20 000
    // ------------------------------------------------------------------

    public function test_page_gain_20000_affiche_les_valeurs_exactes_du_service(): void
    {
        $response = $this->ouvrirGain2027(self::SAISIE_20000)->assertOk();
        $html = $response->getContent();
        $xpath = $this->domGain2027($html);

        $decode = $this->codec->decode($this->codec->encode(self::SAISIE_20000));
        $attendu = $this->sortieService($decode);
        $this->assertSame($attendu, $response->viewData('gain'), 'Sortie exacte de GainFiscal2027::comparer().');

        $racine = $xpath->query('//*[@data-gain2027-page]');
        $this->assertSame(1, $racine->length);
        $this->assertSame('2026', $racine->item(0)->getAttribute('data-source-exercice'));
        $this->assertSame('gross_to_net', $racine->item(0)->getAttribute('data-mode'));

        $this->assertMontantsDuService($xpath, $attendu, true);

        $premier = $xpath->query('//*[@data-gain2027]')->item(0);
        $this->assertSame('gain-mensuel', $premier->getAttribute('data-gain2027'), 'Le gain mensuel est montré en premier.');

        $h1 = $xpath->query('//h1');
        $this->assertSame(1, $h1->length);
        $this->assertSame(trans('ui.gain2027.title'), $this->texteNoeud($h1->item(0)));

        foreach (['same_input_notice', 'prospective_notice', 'badge_projet', 'net_guaranteed_notice', 'annual_indicative', 'headline_label', 'details_title', 'decomposition_title'] as $cle) {
            $this->assertContientTraduction($html, "ui.gain2027.{$cle}", 'fr');
        }
        foreach (['source_2027_notice', 'net_mode_notice', 'no_ir_gain', 'badge_applicable'] as $cle) {
            $this->assertSansTraduction($html, "ui.gain2027.{$cle}", 'fr');
        }

        $this->assertProvenance($xpath, 'fr');
    }

    public function test_la_decomposition_et_les_tableaux_sont_titres_et_ordonnes(): void
    {
        $xpath = $this->domGain2027($this->ouvrirGain2027(self::SAISIE_20000)->assertOk()->getContent());

        foreach (['gain2027-details-title' => 'details_title', 'gain2027-decomposition-title' => 'decomposition_title'] as $id => $cle) {
            $titre = $xpath->query('//*[@id="'.$id.'"]');
            $this->assertSame(1, $titre->length, "Titre {$id}.");
            $this->assertSame(trans("ui.gain2027.{$cle}"), $this->texteNoeud($titre->item(0)));

            $section = $xpath->query('//section[@aria-labelledby="'.$id.'"]');
            $this->assertSame(1, $section->length, "Section nommée par {$id}.");

            $zone = $xpath->query('.//*[@role="region"][@data-table-scroll][@tabindex="0"][@aria-labelledby="'.$id.'"]', $section->item(0));
            $this->assertSame(1, $zone->length, "{$id} : zone défilante atteignable au clavier.");
            $this->assertSame(1, $xpath->query('.//table', $zone->item(0))->length);
            $this->assertGreaterThan(0, $xpath->query('.//th[@scope]', $zone->item(0))->length, "{$id} : en-têtes de tableau portés par scope.");
        }

        // Ordre explicite : la part barème précède la part frais professionnels.
        $parts = [];
        foreach ($xpath->query('//*[@data-gain2027="part-bareme" or @data-gain2027="part-frais-pro"]') as $noeud) {
            $parts[] = $noeud->getAttribute('data-gain2027');
        }
        $this->assertSame(['part-bareme', 'part-frais-pro'], $parts);
    }

    // ------------------------------------------------------------------
    // Quatre langues, RTL
    // ------------------------------------------------------------------

    #[DataProvider('locales')]
    public function test_la_page_est_rendue_dans_chaque_langue(string $locale): void
    {
        $html = $this->ouvrirGain2027(self::SAISIE_20000, $locale)->assertOk()->getContent();
        $xpath = $this->domGain2027($html);

        $racine = $xpath->query('//html')->item(0);
        $this->assertSame($locale, $racine->getAttribute('lang'));
        $this->assertSame($locale === 'ar' ? 'rtl' : 'ltr', $racine->getAttribute('dir'));
        $this->assertSame(trans('ui.gain2027.title', [], $locale), $this->texteNoeud($xpath->query('//h1')->item(0)));

        foreach (['same_input_notice', 'prospective_notice', 'badge_projet', 'net_guaranteed_notice', 'annual_indicative'] as $cle) {
            $this->assertContientTraduction($html, "ui.gain2027.{$cle}", $locale);
        }
        $this->assertStringNotContainsString('ui.gain2027.', $html, "{$locale} : aucune clé brute affichée");

        $this->assertMontantsDuService($xpath, $this->sortieService($this->codec->decode($this->codec->encode(self::SAISIE_20000))), false);
        $this->assertProvenance($xpath, $locale);
    }

    // ------------------------------------------------------------------
    // Source 2027, net cible, IR nul
    // ------------------------------------------------------------------

    public function test_une_source_2027_est_signalee_et_recalculee_en_2026(): void
    {
        $saisie = self::SAISIE_20000 + ['exercice' => 2027];
        $response = $this->ouvrirGain2027($saisie)->assertOk();
        $html = $response->getContent();
        $xpath = $this->domGain2027($html);

        $this->assertSame('2027', $xpath->query('//*[@data-gain2027-page]')->item(0)->getAttribute('data-source-exercice'));
        $this->assertContientTraduction($html, 'ui.gain2027.source_2027_notice', 'fr');
        $this->assertContientTraduction($html, 'ui.gain2027.same_input_notice', 'fr');

        $attendu = $this->sortieService($this->codec->decode($this->codec->encode($saisie)));
        $this->assertSame($attendu, $response->viewData('gain'));
        $this->assertSame(2026, $attendu['reference']['exercice']);
        $this->assertMontantsDuService($xpath, $attendu, true);
        $this->assertProvenance($xpath, 'fr');
    }

    public function test_un_net_cible_2026_affiche_le_brut_determine_en_2026(): void
    {
        $saisie = ['mode' => 'net_to_gross', 'net_cible' => 12000, 'type_frais_pro' => 'commun'];
        $response = $this->ouvrirGain2027($saisie)->assertOk();
        $html = $response->getContent();
        $xpath = $this->domGain2027($html);

        $attendu = $this->sortieService($this->codec->decode($this->codec->encode($saisie)));
        $this->assertSame($attendu, $response->viewData('gain'));
        $this->assertSame('net_to_gross', $xpath->query('//*[@data-gain2027-page]')->item(0)->getAttribute('data-mode'));
        $this->assertContientTraduction($html, 'ui.gain2027.net_mode_notice', 'fr');

        $brut = $this->elementMontant($xpath, 'brut-fige');
        $this->assertSame($this->montantMachine($attendu['resolution']['salaire_base']), $brut->getAttribute('data-montant'));
        $this->assertMontantsDuService($xpath, $attendu, true);
    }

    public function test_un_net_cible_2027_est_refuse_explicitement(): void
    {
        $saisie = ['mode' => 'net_to_gross', 'net_cible' => 12000, 'type_frais_pro' => 'commun', 'exercice' => 2027];
        $this->assertNotNull($this->codec->decode($this->codec->encode($saisie)), 'Prémisse : payload valide.');

        $this->ouvrirGain2027($saisie)
            ->assertRedirect(route('calculator.index'))
            ->assertSessionHas('calculator_notice', 'ui.gain2027.net_source_2027_refused');
    }

    public function test_ir_deja_nul_affiche_aucun_gain_d_ir_calcule(): void
    {
        $html = $this->ouvrirGain2027(['mode' => 'gross_to_net', 'salaire_base' => 4000, 'type_frais_pro' => 'commun'])
            ->assertOk()
            ->getContent();
        $xpath = $this->domGain2027($html);

        $this->assertContientTraduction($html, 'ui.gain2027.no_ir_gain', 'fr');
        $this->assertSame('0.00', $this->elementMontant($xpath, 'gain-mensuel')->getAttribute('data-montant'));
    }

    // ------------------------------------------------------------------
    // Entrées complètes : ni duplication ni suppression
    // ------------------------------------------------------------------

    public function test_les_entrees_completes_traversent_la_page_sans_perte(): void
    {
        $response = $this->ouvrirGain2027(self::SAISIE_COMPLETE)->assertOk();
        $gain = $response->viewData('gain');
        $decode = $this->codec->decode($this->codec->encode(self::SAISIE_COMPLETE));
        $sansExercice = fn (array $input): array => array_diff_key($input, ['exercice' => true]);

        $this->assertSame($this->sortieService($decode), $gain);
        $this->assertSame($sansExercice($decode), $sansExercice($gain['reference']['input']));
        $this->assertSame($sansExercice($decode), $sansExercice($gain['projete']['input']));

        foreach (['charges_famille', 'nb_personnes', 'cotisation_cimr', 'amo_taux_salarie', 'mutuelle_salarie', 'total_retenues', 'rc_deduite', 'total_indemnites', 'cout_employeur_partiel', 'mutuelle_patronale_inconnue', 'rc_part_employeur_inconnu', 'cimr_taux_employeur_inconnu'] as $cle) {
            $this->assertSame($gain['reference'][$cle], $gain['projete'][$cle], "{$cle} identique dans les deux exercices");
        }
        $this->assertGreaterThan(0, $gain['reference']['charges_famille']);
        $this->assertTrue($gain['reference']['cout_employeur_partiel']);
    }

    // ------------------------------------------------------------------
    // Partage, impression, confidentialité
    // ------------------------------------------------------------------

    public function test_le_lien_de_partage_rejoue_la_meme_comparaison(): void
    {
        $saisie = self::SAISIE_20000 + ['exercice' => 2027];
        $response = $this->ouvrirGain2027($saisie)->assertOk();
        $xpath = $this->domGain2027($response->getContent());

        $champ = $xpath->query('//input[@id="gain2027ShareUrl"]');
        $this->assertSame(1, $champ->length, 'Lien de partage de la comparaison.');
        $this->assertTrue($champ->item(0)->hasAttribute('readonly'));

        $aide = $champ->item(0)->getAttribute('aria-describedby');
        $this->assertNotSame('', $aide);
        $this->assertStringContainsString(
            trans('ui.result.share_privacy_warning'),
            $this->texteNoeud($xpath->query('//*[@id="'.$aide.'"]')->item(0)),
            'Le partage rappelle que l\'URL expose les montants.',
        );

        [$chemin, $payload] = $this->cheminEtPayload($champ->item(0)->getAttribute('value'));
        $this->assertSame('/calculateur/gain-2027', $chemin);
        $this->assertSame($this->codec->decode($this->codec->encode($saisie)), $this->codec->decode($payload), 'Même saisie, exercice source compris.');

        $rejoue = $this->withSession(['locale' => 'fr'])->get($this->urlGain2027($payload))->assertOk();
        $this->assertSame($response->viewData('gain'), $rejoue->viewData('gain'), 'Le lien rejoue la même comparaison.');
    }

    public function test_la_page_s_imprime_avec_ses_montants(): void
    {
        $xpath = $this->domGain2027($this->ouvrirGain2027(self::SAISIE_20000)->assertOk()->getContent());

        $this->assertGreaterThan(0, $xpath->query('//button[@onclick="window.print()"]')->length, 'Action d\'impression.');

        foreach (array_keys(self::MONTANTS) as $cle) {
            $masque = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " no-print ")]//*[@data-gain2027="'.$cle.'"]');
            $this->assertSame(0, $masque->length, "{$cle} reste visible à l'impression.");
        }
        $this->assertSame(
            1,
            $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " no-print ")]//input[@id="gain2027ShareUrl"]')->length,
            'Le partage n\'est pas imprimé.',
        );
    }

    public function test_aucun_montant_n_est_expose_aux_analytics_ni_au_titre(): void
    {
        $response = $this->ouvrirGain2027(self::SAISIE_20000)->assertOk();
        $html = $response->getContent();
        $gain = $response->viewData('gain');

        foreach (['gtag(', 'dataLayer', 'data-analytics', 'data-gtm', 'plausible', 'umami'] as $motif) {
            $this->assertStringNotContainsString($motif, $html, "Aucun traceur : {$motif}");
        }

        $titre = $this->texteNoeud($this->domGain2027($html)->query('//title')->item(0));
        foreach (['gain.net_mensuel', 'gain.net_annuel_indicatif', 'reference.salaire_net', 'projete.salaire_net'] as $chemin) {
            $this->assertStringNotContainsString($this->montantAffiche((float) data_get($gain, $chemin)), $titre, "Titre sans {$chemin}");
        }
        $this->assertTrue($response->headers->has('Referrer-Policy'), 'Politique de référent active.');
    }

    // ------------------------------------------------------------------
    // Badge prospectif
    // ------------------------------------------------------------------

    /**
     * Garde-fou : #283 n'autorise aucune bascule légale. Le profil 2027 livré
     * reste au statut projet, et la page affiche toujours le badge
     * « Projet / estimation », jamais « Loi de finances 2027 applicable ».
     *
     * Une valeur validation_legale non nulle ne suffit pas à annoncer une loi
     * applicable. CONFIGURATION SYNTHETIQUE DE TEST : la valeur injectée est
     * volontairement incomplète et non officielle ; elle ne définit aucun
     * schéma d'approbation et n'en suggère aucun pour la production.
     *
     * Hors périmètre : la future bascule vers le badge applicable, ses
     * conditions (preuve au Bulletin officiel, validation réglementaire, statut
     * du profil) et ses tests relèvent de #280. Elles ne sont ni implémentées
     * ni validées ici.
     */
    public function test_un_profil_projet_reste_badge_projet_meme_avec_une_validation_legale_synthetique(): void
    {
        $this->assertSame('projet', config('payroll.exercices.2027.statut'), 'Prémisse : profil 2027 livré au statut projet.');
        $this->assertNull(config('payroll.exercices.2027.validation_legale'), 'Prémisse : aucune validation en production.');

        $html = $this->ouvrirGain2027(self::SAISIE_20000)->assertOk()->getContent();
        $this->assertContientTraduction($html, 'ui.gain2027.badge_projet', 'fr');
        $this->assertSansTraduction($html, 'ui.gain2027.badge_applicable', 'fr');

        config(['payroll.exercices.2027.validation_legale' => ['note' => 'valeur synthétique incomplète, non officielle']]);
        $this->assertSame('projet', config('payroll.exercices.2027.statut'), 'Le statut projet reste inchangé.');

        $html = $this->ouvrirGain2027(self::SAISIE_20000)->assertOk()->getContent();
        $this->assertContientTraduction($html, 'ui.gain2027.badge_projet', 'fr');
        $this->assertSansTraduction($html, 'ui.gain2027.badge_applicable', 'fr');

        $xpath = $this->domGain2027($html);
        $this->assertSame('projet', $xpath->query('//*[@data-fiscal-year="2027"]')->item(0)->getAttribute('data-fiscal-status'), 'Provenance 2027 toujours projet.');
    }

    // ------------------------------------------------------------------
    // Refus
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function requetesInvalides(): array
    {
        return [
            'sans payload' => ['/calculateur/gain-2027'],
            'payload illisible' => ['/calculateur/gain-2027?s=pas-un-payload!'],
            'payload tableau' => ['/calculateur/gain-2027?s[]=x'],
        ];
    }

    #[DataProvider('requetesInvalides')]
    public function test_une_requete_invalide_revient_au_calculateur(string $url): void
    {
        $this->get($url)
            ->assertRedirect(route('calculator.index'))
            ->assertSessionHas('calculator_notice', 'ui.calculator.gain2027_invalid_notice');
    }

    public function test_un_payload_d_un_exercice_non_calculable_est_refuse_par_le_codec(): void
    {
        $payload = $this->codec->encode(self::SAISIE_20000 + ['exercice' => 2028]);
        $this->assertNull($this->codec->decode($payload), 'Prémisse : le codec refuse 2028.');

        $this->get($this->urlGain2027($payload))
            ->assertRedirect(route('calculator.index'))
            ->assertSessionHas('calculator_notice', 'ui.calculator.gain2027_invalid_notice');
    }

    public function test_une_categorie_non_calculable_en_2027_revient_avec_l_erreur_traduite(): void
    {
        config(['payroll.exercices.2027.frais_pro.categories' => ['commun', 'journaliste']]);
        $saisie = ['mode' => 'gross_to_net', 'salaire_base' => 10000, 'type_frais_pro' => 'artiste'];
        $this->assertNotNull($this->codec->decode($this->codec->encode($saisie)), 'Prémisse : valide en 2026.');

        $this->ouvrirGain2027($saisie)
            ->assertRedirect(route('calculator.index'))
            ->assertSessionHasErrors(['calculator' => __('ui.validation.category_not_supported', ['exercice' => 2027])]);
    }
}
