<?php

namespace Tests\Unit;

use Tests\TestCase;

class AdsConfigurationTest extends TestCase
{
    public function test_global_ad_placements_are_configured_without_hardcoded_ids(): void
    {
        $this->assertArrayHasKey('header', config('ads.placements'));
        $this->assertArrayHasKey('footer', config('ads.placements'));
        $this->assertNull(config('ads.client'));
        $this->assertNull(config('ads.placements.header.slot'));
        $this->assertNull(config('ads.placements.footer.slot'));
    }
}
