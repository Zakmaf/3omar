<?php

namespace Tests\Feature;

use App\Services\SimulationCodec;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Libellés des catégories de frais professionnels neutres vis-à-vis du taux
 * (issue #282, constat LE-1 de la revue indépendante).
 *
 * Les clés ui.calculator.category_common, category_journalist et
 * category_artist nomment une catégorie, jamais un taux : le taux dépend de
 * l'exercice appliqué (profil de config/payroll.php), alors que ces libellés
 * sont affichés à côté d'un choix 2026 comme d'un choix 2027, dans le
 * formulaire et dans la ligne « catégorie professionnelle » de la comparaison.
 * Un libellé qui porte un taux 2026 contredit le résultat 2027 du moteur, et
 * un taux recopié dans une traduction enfreint la source unique (AGENTS.md).
 *
 * Contrat vérifié en FR, EN, AR et ES : un libellé de catégorie ne contient
 * aucun chiffre (quel que soit le système de chiffres) ni aucun signe de
 * pourcentage (ASCII, arabe, petit ou pleine chasse).
 *
 * Les scénarios 2027 utilisent une CONFIGURATION SYNTHETIQUE DE TEST, comme
 * ExerciceParcoursTest : le profil 2027 reçoit, le temps du test, des frais
 * professionnels complets de forme historique (par seuil), copiés de 2026 puis
 * volontairement modifiés (seuil 7 777, taux distincts de ceux de 2026). Ce
 * n'est pas un profil légal : il rend 2027 calculable indépendamment du moteur
 * du projet de loi de finances, pour que le test ne juge que la neutralité des
 * libellés. Le profil 2026 et la configuration de production ne sont pas
 * modifiés.
 */
class FraisProCategorieLibellesTest extends TestCase
{
    private const LOCALES = ['fr', 'en', 'ar', 'es'];

    /** Catégorie saisie (type_frais_pro) => clé de son libellé. */
    private const LIBELLES = [
        'commun' => 'ui.calculator.category_common',
        'journaliste' => 'ui.calculator.category_journalist',
        'artiste' => 'ui.calculator.category_artist',
    ];

    /**
     * Tout chiffre décimal (latin, arabe-indien, persan...) et tout signe de
     * pourcentage : % (U+0025), ٪ (U+066A), ﹪ (U+FE6A), ％ (U+FF05).
     */
    private const MOTIF_TAUX = '/[\p{Nd}%\x{066A}\x{FE6A}\x{FF05}]/u';

    private const SAISIE = ['mode' => 'gross_to_net', 'salaire_base' => 10000];

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

    /**
     * Frais professionnels 2027 synthétiques, complets, de forme historique.
     * Les taux sont déplacés de 2026 pour qu'un taux 2026 affiché à côté d'un
     * scénario 2027 soit visiblement faux. Seul le profil 2027 est surchargé.
     */
    private function fixtureHistorique2027(): void
    {
        $fraisPro = config('payroll.frais_pro');

        $this->assertIsArray($fraisPro, 'Prémisse : frais professionnels historiques 2026 paramétrés.');
        foreach (['commun.bas', 'commun.haut', 'journaliste', 'artiste'] as $cle) {
            $this->assertIsNumeric(data_get($fraisPro, "{$cle}.taux"), "Prémisse : taux {$cle} 2026.");
            $this->assertIsNumeric(data_get($fraisPro, "{$cle}.plafond"), "Prémisse : plafond {$cle} 2026.");
        }

        $fraisPro['seuil_mensuel'] = 7777;
        $fraisPro['commun']['bas']['taux'] = 0.31;
        $fraisPro['commun']['haut']['taux'] = 0.21;
        $fraisPro['journaliste']['taux'] = 0.41;
        $fraisPro['artiste']['taux'] = 0.33;

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

    private function normaliser(string $texte): string
    {
        return trim(preg_replace('/\s+/u', ' ', $texte));
    }

    private function libelle(string $categorie, string $locale): string
    {
        $cle = self::LIBELLES[$categorie];
        $libelle = trans($cle, [], $locale);

        $this->assertNotSame($cle, $libelle, "{$locale} : clé {$cle} absente du catalogue");

        return $this->normaliser($libelle);
    }

    private function assertSansTaux(string $texte, string $contexte): void
    {
        $this->assertDoesNotMatchRegularExpression(
            self::MOTIF_TAUX,
            $texte,
            "{$contexte} : un libellé de catégorie ne porte ni chiffre ni pourcentage, le taux dépend de l'exercice appliqué (« {$texte} »).",
        );
    }

    /**
     * Taux 2026 du profil historique (fractions), lus dans config/payroll.php,
     * jamais recopiés dans le test.
     *
     * @return list<float>
     */
    private function taux2026(): array
    {
        $fraisPro = config('payroll.frais_pro');

        return array_map(
            fn (string $cle) => (float) data_get($fraisPro, "{$cle}.taux"),
            ['commun.bas', 'commun.haut', 'journaliste', 'artiste'],
        );
    }

    /**
     * Libellés affichés par les options du champ type_frais_pro, par valeur.
     *
     * @return array<string, string>
     */
    private function optionsCategorie(string $html, string $contexte): array
    {
        $select = $this->xpath($html)->query('//select[@name="type_frais_pro"]');
        $this->assertSame(1, $select->length, "{$contexte} : le formulaire propose le champ type_frais_pro.");

        $options = [];
        foreach ($select->item(0)->getElementsByTagName('option') as $option) {
            $options[$option->getAttribute('value')] = $this->normaliser($option->textContent);
        }

        return $options;
    }

    /**
     * Cellules A et B de la ligne « catégorie professionnelle » du tableau des
     * entrées de la comparaison.
     *
     * @return array{string, string}
     */
    private function ligneCategorieComparee(string $html, string $locale): array
    {
        $xpath = $this->xpath($html);
        $intitule = $this->normaliser(trans('ui.comparison.inputs.pro_expenses', [], $locale));
        $this->assertNotSame('ui.comparison.inputs.pro_expenses', $intitule, "{$locale} : intitulé de ligne traduit");

        $trouvees = [];
        foreach ($xpath->query('//section[@aria-labelledby="cmp-inputs-title"]//tbody/tr') as $ligne) {
            $entete = $xpath->query('./th[@scope="row"]', $ligne)->item(0);
            if ($entete !== null && str_starts_with($this->normaliser($entete->textContent), $intitule)) {
                $trouvees[] = array_map(
                    fn (\DOMNode $cellule) => $this->normaliser($cellule->textContent),
                    iterator_to_array($xpath->query('./td', $ligne)),
                );
            }
        }

        $this->assertCount(1, $trouvees, "{$locale} : une ligne catégorie professionnelle dans les entrées comparées");
        $this->assertCount(2, $trouvees[0], "{$locale} : une cellule par scénario");

        return [$trouvees[0][0], $trouvees[0][1]];
    }

    // ------------------------------------------------------------------
    // Catalogues
    // ------------------------------------------------------------------

    #[DataProvider('locales')]
    public function test_les_libelles_de_categorie_du_catalogue_ne_portent_aucun_taux(string $locale): void
    {
        foreach (array_keys(self::LIBELLES) as $categorie) {
            $this->assertSansTaux($this->libelle($categorie, $locale), "{$locale} / catalogue / {$categorie}");
        }
    }

    // ------------------------------------------------------------------
    // Formulaire
    // ------------------------------------------------------------------

    #[DataProvider('locales')]
    public function test_le_formulaire_affiche_des_categories_neutres_en_2026_et_en_2027(string $locale): void
    {
        $this->fixtureHistorique2027();

        $pages = [
            '2026 par défaut' => '/calculateur',
            '2027 restauré' => '/calculateur?s='.$this->codec->encode(self::SAISIE + ['type_frais_pro' => 'artiste', 'exercice' => 2027]),
        ];

        foreach ($pages as $cas => $url) {
            $reponse = $this->withSession(['locale' => $locale])->get($url)->assertOk();
            $contexte = "{$locale} / formulaire {$cas}";
            $options = $this->optionsCategorie($reponse->getContent(), $contexte);

            $this->assertSame(array_keys(self::LIBELLES), array_keys($options), "{$contexte} : une option par catégorie");

            foreach (array_keys(self::LIBELLES) as $categorie) {
                $this->assertSansTaux($options[$categorie], "{$contexte} / {$categorie}");
                $this->assertSame($this->libelle($categorie, $locale), $options[$categorie], "{$contexte} : option {$categorie} lue dans le catalogue");
            }
        }
    }

    // ------------------------------------------------------------------
    // Comparaison 2026 / 2027
    // ------------------------------------------------------------------

    #[DataProvider('locales')]
    public function test_la_ligne_categorie_d_une_comparaison_2026_2027_ne_porte_aucun_taux(string $locale): void
    {
        $this->fixtureHistorique2027();

        foreach (array_keys(self::LIBELLES) as $categorie) {
            $contexte = "{$locale} / comparaison {$categorie}";
            $a = $this->codec->encode(self::SAISIE + ['type_frais_pro' => $categorie, 'exercice' => 2026]);
            $b = $this->codec->encode(self::SAISIE + ['type_frais_pro' => $categorie, 'exercice' => 2027]);

            $reponse = $this->withSession(['locale' => $locale])
                ->get('/calculateur/comparer?a='.$a.'&b='.$b)
                ->assertOk();

            // Prémisse : chaque scénario est calculé dans son exercice, pour la
            // catégorie demandée.
            $resultatA = $reponse->viewData('a');
            $resultatB = $reponse->viewData('b');
            $this->assertSame(2026, $resultatA['exercice'], "{$contexte} : scénario A en 2026");
            $this->assertSame(2027, $resultatB['exercice'], "{$contexte} : scénario B en 2027");
            $this->assertSame($categorie, $resultatA['input']['type_frais_pro'] ?? null, "{$contexte} : catégorie du scénario A");
            $this->assertSame($categorie, $resultatB['input']['type_frais_pro'] ?? null, "{$contexte} : catégorie du scénario B");

            [$celluleA, $celluleB] = $this->ligneCategorieComparee($reponse->getContent(), $locale);

            $this->assertSansTaux($celluleA, "{$contexte} / scénario A (2026)");
            $this->assertSansTaux($celluleB, "{$contexte} / scénario B (2027)");

            // Aucun taux 2026 différent du taux réellement appliqué en 2027.
            foreach ($this->taux2026() as $taux) {
                if (abs($taux - (float) $resultatB['taux_fp']) < 0.00001) {
                    continue;
                }
                $pourcentage = (string) round($taux * 100);
                $this->assertDoesNotMatchRegularExpression(
                    '/(?<!\d)'.preg_quote($pourcentage, '/').'\s*[%\x{066A}]/u',
                    $celluleB,
                    "{$contexte} : le scénario 2027 n'affiche pas le taux 2026 {$pourcentage} %",
                );
            }

            $libelle = $this->libelle($categorie, $locale);
            $this->assertSame($libelle, $celluleA, "{$contexte} : libellé du scénario A lu dans le catalogue");
            $this->assertSame($libelle, $celluleB, "{$contexte} : libellé du scénario B lu dans le catalogue");
        }
    }
}
