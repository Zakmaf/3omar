<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocumentationPageTest extends TestCase
{
    public function test_page_is_available_and_lists_all_endpoints(): void
    {
        $this->get('/api-documentation')->assertOk()
            ->assertSee('Documentation API')
            ->assertSee('/simuler/brut-vers-net')
            ->assertSee('/simuler/net-vers-brut')
            ->assertSee('/parametres')
            ->assertSee('/health');
    }

    public function test_health_example_reflects_the_deployed_app_version_not_a_stale_literal(): void
    {
        config()->set('app.version', 'v9.9.9-test');

        $response = $this->get('/api-documentation')->assertOk();

        $response->assertSee('v9.9.9-test', false);
        $response->assertDontSee('V1.5.0', false);
    }

    public function test_page_shows_full_response_examples_for_both_simulation_endpoints(): void
    {
        $response = $this->get('/api-documentation')->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('"cotisation_cnss"', $html);
        $this->assertStringContainsString('"resolution_net"', $html);
        $this->assertStringContainsString('"converge"', $html);
    }

    public function test_page_shows_javascript_fetch_examples(): void
    {
        $this->get('/api-documentation')->assertOk()
            ->assertSee('fetch(', false)
            ->assertSee('/api/v1/simuler/brut-vers-net', false)
            ->assertSee('/api/v1/simuler/net-vers-brut', false);
    }

    public function test_page_has_interactive_try_it_forms_for_every_endpoint(): void
    {
        $response = $this->get('/api-documentation')->assertOk();
        $html = $response->getContent();

        preg_match_all('/class="api-try-form"/', $html, $matches);
        $this->assertCount(4, $matches[0], 'Each of the 4 documented endpoints should expose a try-it form');
    }

    public function test_page_has_copy_buttons_on_code_examples(): void
    {
        $response = $this->get('/api-documentation')->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('api-copy-btn', $html);
    }

    public function test_page_links_to_the_markdown_reference_and_openapi_spec(): void
    {
        $response = $this->get('/api-documentation')->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('docs/API.md', $html);
        $this->assertStringContainsString(asset('api/docs/openapi.json'), $html);
    }

    public function test_page_renders_translated_content_for_all_supported_locales(): void
    {
        $this->withSession(['locale' => 'en'])->get('/api-documentation')->assertOk()
            ->assertSee('API Documentation')
            ->assertSee('Try it live');

        $this->withSession(['locale' => 'es'])->get('/api-documentation')->assertOk()
            ->assertSee('Documentacion API')
            ->assertSee('Probar en vivo');

        $this->withSession(['locale' => 'ar'])->get('/api-documentation')->assertOk()
            ->assertSee('توثيق API')
            ->assertSee('جرّب مباشرة');
    }
}
