<?php

namespace Tests\Feature;

use App\Services\SimulationCodec;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Propagation de l'exercice fiscal dans les parcours utilisateur (issue #281).
 *
 * Contrat attendu, en FR, EN, AR et ES :
 * - formulaire : un <select name="exercice"> liste les exercices déclarés ;
 *   seuls les exercices calculables sont activables, chaque option affiche le
 *   statut de son profil ; l'exercice d'un lien ou d'une saisie renvoyée est
 *   conservé ;
 * - résultat et comparaison : chaque scénario porte un bloc de provenance
 *   marqué data-fiscal-year (exercice appliqué) et data-fiscal-status (statut
 *   du profil), qui affiche le libellé traduit du statut et le texte source lu
 *   dans config/payroll.php ; un profil projet affiche en plus la réserve
 *   ui.fiscal_year.project_reserve, jamais un profil en vigueur ;
 * - Fiabilité : un bloc par exercice déclaré avec les mêmes marqueurs ; pour un
 *   projet, le texte source, la date de publication dans <time datetime> et la
 *   réserve.
 *
 * Clés de traduction attendues (sans valeur réglementaire) :
 * ui.fiscal_year.label, ui.fiscal_year.status_en_vigueur,
 * ui.fiscal_year.status_projet, ui.fiscal_year.project_reserve.
 *
 * Les scénarios 2027 utilisent une CONFIGURATION SYNTHETIQUE DE TEST : les
 * frais professionnels du profil 2027 reçoivent une copie technique des
 * valeurs 2026 dont le seuil mensuel est volontairement modifié (7 777), pour
 * prouver que la vue lit le profil appliqué. Ce n'est pas un profil légal, la
 * configuration de production n'est pas modifiée et le statut reste projet.
 */
class ExerciceParcoursTest extends TestCase
{
    private const LOCALES = ['fr', 'en', 'ar', 'es'];

    private const SCENARIO = ['mode' => 'gross_to_net', 'salaire_base' => 10000, 'type_frais_pro' => 'commun'];

    private const CLES = [
        'ui.fiscal_year.label',
        'ui.fiscal_year.status_en_vigueur',
        'ui.fiscal_year.status_projet',
        'ui.fiscal_year.project_reserve',
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

    private function fixtureTechnique2027(): void
    {
        $fraisPro = config('payroll.frais_pro');
        $fraisPro['seuil_mensuel'] = 7777;
        config(['payroll.exercices.2027.frais_pro' => $fraisPro]);
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

    private function texte(\DOMNode $noeud): string
    {
        return trim(preg_replace('/\s+/u', ' ', $noeud->textContent));
    }

    private function libelleStatut(string $statut, string $locale): string
    {
        return trans("ui.fiscal_year.status_{$statut}", [], $locale);
    }

    private function reserve(string $locale): string
    {
        return trans('ui.fiscal_year.project_reserve', [], $locale);
    }

    /**
     * @return list<\DOMElement>
     */
    private function blocsProvenance(string $html): array
    {
        return iterator_to_array($this->xpath($html)->query('//*[@data-fiscal-year]'));
    }

    private function assertBlocProvenance(\DOMElement $bloc, int $exercice, string $locale): void
    {
        $profil = config("payroll.exercices.{$exercice}");
        $texte = $this->texte($bloc);

        $this->assertSame((string) $exercice, $bloc->getAttribute('data-fiscal-year'), "{$locale} : exercice appliqué");
        $this->assertSame($profil['statut'], $bloc->getAttribute('data-fiscal-status'), "{$locale} : statut du profil");
        $this->assertStringContainsString($this->libelleStatut($profil['statut'], $locale), $texte, "{$locale} : libellé du statut");
        $this->assertStringContainsString($profil['source']['texte'], $texte, "{$locale} : texte source");

        if ($profil['statut'] === 'projet') {
            $this->assertStringContainsString($this->reserve($locale), $texte, "{$locale} : réserve d'un projet");
        } else {
            $this->assertStringNotContainsString($this->reserve($locale), $texte, "{$locale} : pas de réserve pour un exercice en vigueur");
        }

        // Le lien vers le texte source s'ouvre dans un nouvel onglet sans
        // transmettre l'URL de la page, qui peut porter des payloads de
        // simulation non chiffrés (comparaison).
        if (! empty($profil['source']['url'])) {
            $liens = (new \DOMXPath($bloc->ownerDocument))->query('.//a[@href="'.$profil['source']['url'].'"]', $bloc);
            $this->assertSame(1, $liens->length, "{$locale} : lien vers le texte source ({$exercice})");

            $lien = $liens->item(0);
            $rel = preg_split('/\s+/', trim($lien->getAttribute('rel')));
            $this->assertSame('_blank', $lien->getAttribute('target'), "{$locale} : source ouverte dans un nouvel onglet");
            $this->assertContains('noopener', $rel, "{$locale} : rel noopener sur la source ({$exercice})");
            $this->assertContains('noreferrer', $rel, "{$locale} : rel noreferrer sur la source ({$exercice})");
        }
    }

    /**
     * Une balise de composant mal formée n'est pas compilée par Blade et reste
     * telle quelle dans le HTML : aucune page de provenance ne doit en contenir.
     */
    private function assertComposantsCompiles(string $html, string $contexte): void
    {
        $this->assertStringNotContainsString('<x-', $html, "{$contexte} : balise de composant Blade non compilée");
    }

    /**
     * Retour de validation avec un exercice que le moteur ne peut pas calculer :
     * année, locale et message attendu (première erreur du champ, traduite).
     *
     * @return array<string, array{string, string}>
     */
    public static function exercicesRefuses(): array
    {
        $cas = [];
        foreach (self::LOCALES as $locale) {
            $cas["2027 sans fixture {$locale}"] = ['2027', $locale];
            $cas["1999 inconnu {$locale}"] = ['1999', $locale];
        }

        return $cas;
    }

    private function messageRefusAttendu(string $exercice, string $locale): string
    {
        if ($exercice === '1999') {
            // Première règle en échec : min, avec le nom lisible du champ.
            return trans('validation.min.numeric', [
                'attribute' => trans('validation.attributes.exercice', [], $locale),
                'min' => min(array_keys(config('payroll.exercices'))),
            ], $locale);
        }

        // integer, min et max passent : la règle partagée avec le moteur refuse.
        return trans('ui.validation.exercise_not_calculable', ['exercice' => (int) $exercice], $locale);
    }

    /**
     * @return array<string, \DOMElement> options indexées par valeur
     */
    private function optionsExercice(string $html, string $contexte): array
    {
        $select = $this->xpath($html)->query('//select[@name="exercice"]');
        $this->assertSame(1, $select->length, "{$contexte} : le formulaire doit proposer un champ exercice.");

        $options = [];
        foreach ($select->item(0)->getElementsByTagName('option') as $option) {
            $options[$option->getAttribute('value')] = $option;
        }

        return $options;
    }

    private function valeurSelectionnee(array $options): ?string
    {
        foreach ($options as $valeur => $option) {
            if ($option->hasAttribute('selected')) {
                return (string) $valeur;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Catalogues
    // ------------------------------------------------------------------

    #[DataProvider('locales')]
    public function test_les_libelles_de_provenance_existent_dans_chaque_langue(string $locale): void
    {
        foreach (self::CLES as $cle) {
            $this->assertNotSame($cle, trans($cle, [], $locale), "{$locale} : clé {$cle} absente du catalogue");
        }
    }

    // ------------------------------------------------------------------
    // Formulaire
    // ------------------------------------------------------------------

    #[DataProvider('locales')]
    public function test_le_formulaire_propose_les_exercices_avec_leur_statut(string $locale): void
    {
        $html = $this->withSession(['locale' => $locale])->get('/calculateur')->assertOk()->getContent();
        $options = $this->optionsExercice($html, $locale);

        $this->assertSame(
            array_map('strval', array_keys(config('payroll.exercices'))),
            array_map('strval', array_keys($options)),
            "{$locale} : un choix par exercice déclaré",
        );
        $this->assertSame('2026', $this->valeurSelectionnee($options), "{$locale} : repli 2026 sélectionné par défaut");

        foreach (config('payroll.exercices') as $annee => $profil) {
            $option = $options[(string) $annee];
            $this->assertStringContainsString($this->libelleStatut($profil['statut'], $locale), $this->texte($option), "{$locale} : statut de {$annee}");
            $this->assertSame($annee !== 2026, $option->hasAttribute('disabled'), "{$locale} : seul un exercice calculable est activable ({$annee})");
        }
    }

    public function test_un_projet_rendu_calculable_par_la_fixture_reste_affiche_comme_projet(): void
    {
        $this->fixtureTechnique2027();

        $options = $this->optionsExercice($this->get('/calculateur')->assertOk()->getContent(), 'fixture');

        $this->assertFalse($options['2027']->hasAttribute('disabled'), '2027 devient activable avec la fixture technique.');
        $this->assertStringContainsString($this->libelleStatut('projet', 'fr'), $this->texte($options['2027']));
        $this->assertTrue($options['2028']->hasAttribute('disabled'));
    }

    public function test_un_lien_partage_conserve_l_exercice_explicite_dans_le_formulaire(): void
    {
        $lien2026 = $this->codec->encode(self::SCENARIO + ['exercice' => 2026]);
        $options = $this->optionsExercice($this->get('/calculateur?s='.$lien2026)->assertOk()->getContent(), 'lien 2026');
        $this->assertSame('2026', $this->valeurSelectionnee($options));

        $this->fixtureTechnique2027();
        $lien2027 = $this->codec->encode(self::SCENARIO + ['exercice' => 2027]);
        $options = $this->optionsExercice($this->get('/calculateur?s='.$lien2027)->assertOk()->getContent(), 'lien 2027');
        $this->assertSame('2027', $this->valeurSelectionnee($options), 'Le formulaire reprend l\'exercice du lien.');
    }

    public function test_une_saisie_renvoyee_par_la_validation_conserve_l_exercice(): void
    {
        $this->fixtureTechnique2027();

        $html = $this->followingRedirects()
            ->from('/calculateur')
            ->post('/calculateur/calculer', ['mode' => 'gross_to_net', 'salaire_base' => '', 'type_frais_pro' => 'commun', 'exercice' => '2027'])
            ->assertOk()
            ->getContent();

        $this->assertSame('2027', $this->valeurSelectionnee($this->optionsExercice($html, 'retour de validation')));
    }

    /**
     * Caractérisation du retour de validation pour un exercice non calculable
     * (2027 sans fixture) ou inconnu (1999) : le formulaire ne retombe jamais en
     * silence sur 2026, il présélectionne le choix vide désactivé, garde le
     * champ obligatoire et affiche le message traduit de l'erreur.
     */
    #[DataProvider('exercicesRefuses')]
    public function test_un_exercice_refuse_renvoye_par_la_validation_n_est_pas_remplace_par_2026(string $exercice, string $locale): void
    {
        $html = $this->withSession(['locale' => $locale])
            ->followingRedirects()
            ->from('/calculateur')
            ->post('/calculateur/calculer', self::SCENARIO + ['exercice' => $exercice])
            ->assertOk()
            ->getContent();

        $contexte = "{$locale} / {$exercice}";
        $xpath = $this->xpath($html);
        $options = $this->optionsExercice($html, $contexte);

        $select = $xpath->query('//select[@name="exercice"]')->item(0);
        $this->assertTrue($select->hasAttribute('required'), "{$contexte} : champ exercice obligatoire");

        $this->assertArrayHasKey('', $options, "{$contexte} : choix vide proposé");
        $this->assertSame('', $this->valeurSelectionnee($options), "{$contexte} : choix vide sélectionné, aucune présélection");
        $this->assertTrue($options['']->hasAttribute('disabled'), "{$contexte} : choix vide désactivé");
        $this->assertFalse($options['2026']->hasAttribute('selected'), "{$contexte} : aucune sélection silencieuse de 2026");

        $retour = $xpath->query('//*[@id="exerciceUnsupported"]');
        $this->assertSame(1, $retour->length, "{$contexte} : message d'erreur du champ exercice");
        $this->assertStringContainsString('exerciceUnsupported', $select->getAttribute('aria-describedby'), "{$contexte} : message relié au champ");
        $this->assertSame($this->messageRefusAttendu($exercice, $locale), $this->texte($retour->item(0)), "{$contexte} : message explicite traduit");
    }

    // ------------------------------------------------------------------
    // Résultat
    // ------------------------------------------------------------------

    #[DataProvider('locales')]
    public function test_le_resultat_affiche_l_exercice_le_statut_et_la_source(string $locale): void
    {
        foreach ([self::SCENARIO, self::SCENARIO + ['exercice' => '2026']] as $saisie) {
            $html = $this->withSession(['locale' => $locale])
                ->post('/calculateur/calculer', $saisie)
                ->assertOk()
                ->getContent();

            $this->assertComposantsCompiles($html, "{$locale} : résultat");
            $blocs = $this->blocsProvenance($html);
            $this->assertCount(1, $blocs, "{$locale} : un bloc de provenance sur la page résultat");
            $this->assertBlocProvenance($blocs[0], 2026, $locale);
        }
    }

    public function test_le_resultat_conserve_l_exercice_dans_le_lien_de_partage(): void
    {
        $this->fixtureTechnique2027();

        $response = $this->post('/calculateur/calculer', self::SCENARIO + ['exercice' => '2027'])->assertOk();
        $decode = $this->codec->decode($response->viewData('share_payload'));

        $this->assertNotNull($decode);
        $this->assertEquals(2027, $decode['exercice'] ?? null);
    }

    #[DataProvider('locales')]
    public function test_un_resultat_projet_porte_la_reserve_et_les_parametres_de_son_profil(string $locale): void
    {
        $this->fixtureTechnique2027();

        $html = $this->withSession(['locale' => $locale])
            ->post('/calculateur/calculer', ['mode' => 'gross_to_net', 'salaire_base' => 5000, 'type_frais_pro' => 'commun', 'exercice' => '2027'])
            ->assertOk()
            ->getContent();

        $this->assertComposantsCompiles($html, "{$locale} : résultat projet");
        $blocs = $this->blocsProvenance($html);
        $this->assertCount(1, $blocs, "{$locale} : un bloc de provenance");
        $this->assertBlocProvenance($blocs[0], 2027, $locale);

        if ($locale === 'fr') {
            // Le détail des frais professionnels lit le seuil du profil appliqué,
            // pas celui de 2026.
            $this->assertStringContainsString('7 777', $html);
        }
    }

    // ------------------------------------------------------------------
    // Comparaison
    // ------------------------------------------------------------------

    #[DataProvider('locales')]
    public function test_absence_et_2026_explicite_se_comparent_sans_ecart_fictif(string $locale): void
    {
        $a = $this->codec->encode(self::SCENARIO);
        $b = $this->codec->encode(self::SCENARIO + ['exercice' => 2026]);

        $html = $this->withSession(['locale' => $locale])
            ->get('/calculateur/comparer?a='.$a.'&b='.$b)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(e(trans('ui.comparison.inputs_identical', [], $locale)), $html, "{$locale} : entrées identiques");
        $this->assertStringNotContainsString(e(trans('ui.comparison.inputs_advanced_diff', [], $locale)), $html, "{$locale} : aucun écart caché");

        $this->assertComposantsCompiles($html, "{$locale} : comparaison");
        $blocs = $this->blocsProvenance($html);
        $this->assertCount(2, $blocs, "{$locale} : un bloc de provenance par scénario");
        foreach ($blocs as $bloc) {
            $this->assertBlocProvenance($bloc, 2026, $locale);
        }
    }

    #[DataProvider('locales')]
    public function test_une_comparaison_2026_et_projet_montre_les_deux_provenances(string $locale): void
    {
        $this->fixtureTechnique2027();
        $a = $this->codec->encode(self::SCENARIO + ['exercice' => 2026]);
        $b = $this->codec->encode(self::SCENARIO + ['exercice' => 2027]);

        $html = $this->withSession(['locale' => $locale])
            ->get('/calculateur/comparer?a='.$a.'&b='.$b)
            ->assertOk()
            ->getContent();

        $this->assertComposantsCompiles($html, "{$locale} : comparaison avec projet");
        $blocs = $this->blocsProvenance($html);
        $this->assertCount(2, $blocs, "{$locale} : un bloc de provenance par scénario");
        $this->assertBlocProvenance($blocs[0], 2026, $locale);
        $this->assertBlocProvenance($blocs[1], 2027, $locale);
    }

    // ------------------------------------------------------------------
    // Fiabilité
    // ------------------------------------------------------------------

    #[DataProvider('locales')]
    public function test_la_fiabilite_distingue_le_profil_en_vigueur_des_projets_dates(string $locale): void
    {
        $html = $this->withSession(['locale' => $locale])->get('/fiabilite')->assertOk()->getContent();
        $this->assertComposantsCompiles($html, "{$locale} : Fiabilité");
        $xpath = $this->xpath($html);

        foreach (config('payroll.exercices') as $annee => $profil) {
            $blocs = $xpath->query('//*[@data-fiscal-year="'.$annee.'"]');
            $this->assertSame(1, $blocs->length, "{$locale} : un bloc pour l'exercice {$annee}");

            $bloc = $blocs->item(0);
            $this->assertBlocProvenance($bloc, $annee, $locale);

            if ($profil['statut'] === 'projet') {
                $dates = $xpath->query('.//time[@datetime="'.$profil['source']['date_publication'].'"]', $bloc);
                $this->assertGreaterThan(0, $dates->length, "{$locale} : date de publication du texte source ({$annee})");
            }
        }
    }
}
