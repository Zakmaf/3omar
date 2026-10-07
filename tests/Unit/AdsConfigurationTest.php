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

    public function test_client_is_derived_from_publisher_id(): void
    {
        $config = $this->loadAdsConfigWithPublisherId('pub-1234567890123456');

        $this->assertSame('pub-1234567890123456', $config['publisher_id']);
        $this->assertSame('ca-pub-1234567890123456', $config['client']);
    }

    public function test_empty_publisher_id_disables_client(): void
    {
        $config = $this->loadAdsConfigWithPublisherId('');

        $this->assertNull($config['publisher_id']);
        $this->assertNull($config['client']);
    }

    private function loadAdsConfigWithPublisherId(string $value): array
    {
        putenv("ADSENSE_PUBLISHER_ID={$value}");
        $_ENV['ADSENSE_PUBLISHER_ID'] = $_SERVER['ADSENSE_PUBLISHER_ID'] = $value;

        try {
            return require config_path('ads.php');
        } finally {
            putenv('ADSENSE_PUBLISHER_ID');
            unset($_ENV['ADSENSE_PUBLISHER_ID'], $_SERVER['ADSENSE_PUBLISHER_ID']);
        }
    }
}
