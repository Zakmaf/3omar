<?php

namespace Tests\Feature;

use App\Services\SimulationCodec;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Indexation de la page « Mon gain fiscal en janvier 2027 » (issue #283,
 * suggestion retenue de la revue indépendante 2).
 *
 * Contrat : la page GET /calculateur/gain-2027?s=<payload> porte des montants
 * dans son URL et son contenu. Elle déclare exactement une balise
 * <meta name="robots"> dont le contenu comprend noindex et nofollow, pour
 * qu'un lien partagé publiquement ne soit ni indexé ni suivi. Les pages
 * éditoriales publiques restent indexables (aucun noindex global).
 *
 * Statut attendu : ROUGE sur l'arbre indexé actuel (aucune balise robots) ;
 * masqué (404) sur un HEAD sans la route. Garde-fou hors calculateur : VERT.
 */
class GainFiscal2027IndexationTest extends TestCase
{
    private function dom(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        $precedent = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($precedent);

        return new \DOMXPath($document);
    }

    /** @return list<string> directives robots, en minuscules. */
    private function directivesRobots(\DOMXPath $xpath): array
    {
        $metas = $xpath->query('//meta[translate(@name, "ROBTS", "robts")="robots"]');
        if ($metas->length === 0) {
            return [];
        }
        $this->assertSame(1, $metas->length, 'Une seule balise robots.');

        return array_values(array_filter(array_map(
            fn (string $d): string => strtolower(trim($d)),
            explode(',', $metas->item(0)->getAttribute('content')),
        )));
    }

    /**
     * @return array<string, array{array}>
     */
    public static function saisies(): array
    {
        return [
            'brut 2026' => [['mode' => 'gross_to_net', 'salaire_base' => 20000, 'type_frais_pro' => 'commun']],
            'brut 2027' => [['mode' => 'gross_to_net', 'salaire_base' => 20000, 'type_frais_pro' => 'commun', 'exercice' => 2027]],
            'net cible 2026' => [['mode' => 'net_to_gross', 'net_cible' => 12000, 'type_frais_pro' => 'commun']],
        ];
    }

    #[DataProvider('saisies')]
    public function test_la_page_de_gain_n_est_ni_indexee_ni_suivie(array $saisie): void
    {
        $codec = app(SimulationCodec::class);
        $payload = $codec->encode($saisie);
        $this->assertNotNull($codec->decode($payload), 'Prémisse : payload valide.');

        $html = $this->get('/calculateur/gain-2027?s='.$payload)->assertOk()->getContent();
        $directives = $this->directivesRobots($this->dom($html));

        $this->assertContains('noindex', $directives, 'Page porteuse de payload : noindex.');
        $this->assertContains('nofollow', $directives, 'Page porteuse de payload : nofollow.');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pagesPubliques(): array
    {
        return [
            'accueil' => ['/'],
            'documentation' => ['/documentation'],
            'guide salaire net' => ['/calcul-salaire-net-maroc'],
        ];
    }

    #[DataProvider('pagesPubliques')]
    public function test_les_pages_editoriales_restent_indexables(string $uri): void
    {
        $directives = $this->directivesRobots($this->dom($this->get($uri)->assertOk()->getContent()));

        $this->assertNotContains('noindex', $directives, "{$uri} reste indexable.");
        $this->assertNotContains('none', $directives, "{$uri} reste indexable.");
    }
}
