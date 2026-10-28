<?php

namespace Tests\Unit;

use App\Http\Requests\PayrollValidation;
use Illuminate\Support\Arr;
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

    public function test_all_supported_locales_have_the_same_validation_keys(): void
    {
        $reference = $this->flattenKeys(require lang_path('fr/validation.php'));

        foreach (array_keys(config('app.supported_locales')) as $locale) {
            $this->assertSame($reference, $this->flattenKeys(require lang_path("{$locale}/validation.php")), $locale);
        }
    }

    public function test_validation_catalogs_cover_every_rule_and_field_used_by_the_payroll_forms(): void
    {
        $rules = ['required', 'required_with', 'numeric', 'integer', 'boolean', 'array', 'in', 'distinct', 'min.numeric', 'max.numeric', 'max.array'];
        $fields = array_keys(array_merge(
            PayrollValidation::webRules('gross_to_net'),
            PayrollValidation::webRules('net_to_gross'),
            PayrollValidation::brutVersNetRules(),
            PayrollValidation::netVersBrutRules(),
        ));

        foreach (array_keys(config('app.supported_locales')) as $locale) {
            $catalog = require lang_path("{$locale}/validation.php");

            foreach ($rules as $rule) {
                $this->assertTrue(Arr::has($catalog, $rule), "{$locale} : message manquant pour la regle {$rule}.");
            }

            foreach ($fields as $field) {
                $this->assertArrayHasKey($field, $catalog['attributes'], "{$locale} : nom lisible manquant pour {$field}.");
            }
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
