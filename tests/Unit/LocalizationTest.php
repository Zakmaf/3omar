<?php

namespace Tests\Unit;

use Tests\TestCase;

class LocalizationTest extends TestCase
{
    public function test_all_supported_locales_have_the_same_interface_keys(): void
    {
        $reference = $this->flattenKeys(require lang_path('fr/ui.php'));

        foreach (array_keys(config('app.supported_locales')) as $locale) {
            $this->assertSame($reference, $this->flattenKeys(require lang_path("{$locale}/ui.php")), $locale);
        }
    }

    private function flattenKeys(array $messages, string $prefix = ''): array
    {
        $keys = [];

        foreach ($messages as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            $keys = is_array($value)
                ? [...$keys, ...$this->flattenKeys($value, $path)]
                : [...$keys, $path];
        }

        sort($keys);

        return $keys;
    }
}
