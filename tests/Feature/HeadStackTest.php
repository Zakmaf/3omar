<?php

namespace Tests\Feature;

use App\Services\GuideService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Le layout ne doit rendre la pile head qu'une seule fois : sinon tout ce que
 * les vues y poussent (lien canonique, Chart.js, CSS de page) est émis en
 * double dans le HTML (#199).
 */
class HeadStackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public static function provideGuides(): array
    {
        return array_map(fn (string $slug) => [$slug], array_combine(
            array_keys(GuideService::GUIDES),
            array_keys(GuideService::GUIDES),
        ));
    }

    #[DataProvider('provideGuides')]
    public function test_guide_page_renders_a_single_canonical_link(string $slug): void
    {
        $html = $this->get('/'.$slug)->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<link rel="canonical"'));
    }

    public function test_result_page_loads_chart_js_once(): void
    {
        $html = $this->post('/calculateur/calculer', [
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
        ])->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'npm/chart.js@'));
    }

    public function test_calculator_page_styles_are_rendered_once(): void
    {
        $html = $this->get('/calculateur')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '.simulator-flow {'));
    }
}
