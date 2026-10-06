<?php

namespace Tests\Feature;

use App\Services\GuideService;
use App\Services\PayrollCalculatorService;
use App\Services\SimulationProfileService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GuidePagesTest extends TestCase
{
    public static function provideGuides(): array
    {
        return array_map(fn (string $slug) => [$slug], array_combine(
            array_keys(GuideService::GUIDES),
            array_keys(GuideService::GUIDES),
        ));
    }

    #[DataProvider('provideGuides')]
    public function test_guide_page_has_dedicated_metadata_and_canonical_url(string $slug): void
    {
        $key = GuideService::GUIDES[$slug]['key'];
        $html = $this->get('/'.$slug)->assertOk()->getContent();

        $this->assertStringContainsString('<title>'.e(__("ui.guides.{$key}.meta_title")).'</title>', $html);
        $this->assertStringNotContainsString('content="'.e(__('ui.meta_description')).'"', $html);
        $this->assertMatchesRegularExpression('/<meta name="description" content="[^"]{50,170}">/u', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.route('guides.'.$slug).'">', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    #[DataProvider('provideGuides')]
    public function test_guide_page_links_to_prefilled_calculator_and_sources(string $slug): void
    {
        $guide = GuideService::GUIDES[$slug];
        $html = $this->get('/'.$slug)->assertOk()->getContent();

        $cta = e(route('calculator.index', ['profil' => $guide['profil']]));
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'href="'.$cta.'"'));

        foreach ($guide['docs'] as $anchor) {
            $this->assertStringContainsString(route('documentation').'#'.$anchor, $html);
        }
        foreach ($guide['sources'] as $source) {
            $this->assertStringContainsString(e($source), $html);
        }
        $this->assertStringContainsString(route('trust'), $html);
        $this->assertStringContainsString(e(__('ui.trust.official_payslip_notice')), $html);
    }

    #[DataProvider('provideGuides')]
    public function test_guide_example_is_computed_by_the_engine_from_the_cta_profile(string $slug): void
    {
        $guide = GuideService::GUIDES[$slug];
        $input = app(SimulationProfileService::class)->find($guide['profil'])['input'];
        $result = app(PayrollCalculatorService::class)->calculer($input);

        $expected = number_format($result[$guide['highlight']], 2, ',', ' ').' MAD/mois';

        $this->get('/'.$slug)->assertOk()->assertSee($expected);
    }

    public function test_guide_texts_quote_rates_from_payroll_config(): void
    {
        config()->set('payroll.cnss.plafond', 7000);

        $this->get('/cnss-amo-maroc')->assertOk()
            ->assertSee('7 000,00 MAD')
            ->assertDontSee('6 000,00 MAD');
    }

    public function test_seniority_guide_lists_configured_brackets(): void
    {
        $response = $this->get('/prime-anciennete-maroc')->assertOk();

        foreach (config('payroll.anciennete.tranches') as $tranche) {
            $response->assertSee(rtrim(rtrim(number_format($tranche['taux'] * 100, 2, ',', ' '), '0'), ',').' %');
        }
        $response->assertSee('De 2 à 4 ans')->assertSee('25 ans et plus');
    }

    #[DataProvider('provideGuides')]
    public function test_guide_page_renders_in_every_supported_locale(string $slug): void
    {
        $key = GuideService::GUIDES[$slug]['key'];

        foreach (array_keys(config('app.supported_locales')) as $locale) {
            app()->setLocale($locale);
            $title = __("ui.guides.{$key}.title");

            $this->withSession(['locale' => $locale])->get('/'.$slug)
                ->assertOk()
                ->assertSee($title);
        }
    }

    public function test_arabic_guide_page_is_rtl(): void
    {
        $this->withSession(['locale' => 'ar'])->get('/calcul-salaire-net-maroc')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('احسب أجرك الصافي بالمغرب سنة 2026');
    }

    public function test_footer_links_every_guide(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (array_keys(GuideService::GUIDES) as $slug) {
            $this->assertStringContainsString('href="'.route('guides.'.$slug).'"', $html);
        }
    }

    public function test_other_pages_keep_default_metadata(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<meta name="description" content="'.e(__('ui.meta_description')).'">', false)
            ->assertDontSee('rel="canonical"', false);
    }
}
