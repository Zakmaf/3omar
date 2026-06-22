<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocaleSwitcherTest extends TestCase
{
    public function test_switching_locale_persists_in_session_and_redirects_back(): void
    {
        $response = $this->get('/lang/en');

        $response->assertRedirect('/');
        $response->assertSessionHas('locale', 'en');
    }

    public function test_each_supported_locale_can_be_selected(): void
    {
        foreach (array_keys(config('app.supported_locales')) as $locale) {
            $response = $this->get("/lang/{$locale}");

            $response->assertRedirect();
            $response->assertSessionHas('locale', $locale);
        }
    }

    public function test_unsupported_locale_returns_404(): void
    {
        $this->get('/lang/xx')->assertNotFound();
    }

    public function test_locale_applies_on_subsequent_request(): void
    {
        $this->get('/lang/en');

        $this->get('/')
            ->assertOk()
            ->assertSee('lang="en"', false);
    }

    public function test_arabic_locale_sets_rtl_direction(): void
    {
        $this->get('/lang/ar');

        $this->get('/')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('lang="ar"', false);
    }

    public function test_locale_persists_across_navigation(): void
    {
        $this->get('/lang/es');

        $this->get('/')->assertOk()->assertSee('lang="es"', false);
        $this->get('/calculateur')->assertOk()->assertSee('lang="es"', false);
        $this->get('/documentation')->assertOk()->assertSee('lang="es"', false);
    }
}
