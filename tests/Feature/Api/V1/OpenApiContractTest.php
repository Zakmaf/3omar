<?php

namespace Tests\Feature\Api\V1;

use App\Http\Requests\PayrollValidation;
use Tests\TestCase;

/**
 * Verrouille le contrat public de l'API (issue #168) : la specification
 * public/api/docs/openapi.json et la reference docs/API.md doivent rester
 * alignees sur la validation reelle et sur la sortie reelle du moteur.
 */
class OpenApiContractTest extends TestCase
{
    private const ENDPOINTS = [
        'gross_to_net' => [
            'path' => '/simuler/brut-vers-net',
            'schema' => 'BrutVersNetInput',
            'heading' => '### POST /api/v1/simuler/brut-vers-net',
        ],
        'net_to_gross' => [
            'path' => '/simuler/net-vers-brut',
            'schema' => 'NetVersBrutInput',
            'heading' => '### POST /api/v1/simuler/net-vers-brut',
        ],
    ];

    public function test_input_schemas_declare_exactly_the_fields_accepted_by_the_api(): void
    {
        $spec = $this->spec();

        foreach (self::ENDPOINTS as $mode => $endpoint) {
            $declared = array_keys($spec['components']['schemas'][$endpoint['schema']]['properties']);
            $accepted = PayrollValidation::apiFields($mode);

            $this->assertSame(
                [],
                array_values(array_diff($accepted, $declared)),
                "{$endpoint['schema']} : champs acceptes par l'API mais absents du schema OpenAPI."
            );
            $this->assertSame(
                [],
                array_values(array_diff($declared, $accepted)),
                "{$endpoint['schema']} : champs declares dans le schema OpenAPI mais ignores par l'API."
            );
        }
    }

    public function test_input_schemas_match_validation_types_bounds_and_required_fields(): void
    {
        $spec = $this->spec();
        $errors = [];

        foreach (self::ENDPOINTS as $mode => $endpoint) {
            $schema = $spec['components']['schemas'][$endpoint['schema']];
            $rules = $this->rulesFor($mode);

            $required = [];
            foreach ($rules as $field => $fieldRules) {
                if (str_contains($field, '.')) {
                    continue;
                }
                if (in_array('required', $fieldRules, true)) {
                    $required[] = $field;
                }
                $property = $schema['properties'][$field] ?? null;
                if ($property === null) {
                    continue; // couvert par le test de parite des champs
                }
                $this->compareProperty("{$endpoint['schema']}.{$field}", $fieldRules, $property, $errors);
            }

            foreach ($rules as $field => $fieldRules) {
                if (! preg_match('/^([a-z_]+)\.\*\.([a-z_]+)$/', $field, $m)) {
                    continue;
                }
                $property = $schema['properties'][$m[1]]['items']['properties'][$m[2]] ?? null;
                if ($property === null) {
                    $errors[] = "{$endpoint['schema']}.{$m[1]}[].{$m[2]} : absent du schema.";

                    continue;
                }
                $this->compareProperty("{$endpoint['schema']}.{$m[1]}[].{$m[2]}", $fieldRules, $property, $errors);
            }

            $indemnites = $schema['properties']['indemnites']['items']['properties']['type']['enum'] ?? [];
            $attendues = array_keys(config('payroll.indemnites'));
            sort($indemnites);
            sort($attendues);
            if ($indemnites !== $attendues) {
                $errors[] = "{$endpoint['schema']}.indemnites[].type : enum ".json_encode($indemnites).' au lieu de '.json_encode($attendues).'.';
            }

            $declaredRequired = $schema['required'] ?? [];
            sort($declaredRequired);
            sort($required);
            if ($declaredRequired !== $required) {
                $errors[] = "{$endpoint['schema']} : required ".json_encode($declaredRequired).' au lieu de '.json_encode($required).'.';
            }
        }

        $this->assertSame([], $errors, "Ecarts entre openapi.json et PayrollValidation :\n".implode("\n", $errors));
    }

    public function test_spec_version_equals_the_application_version(): void
    {
        $this->assertSame(
            config('app.version'),
            $this->spec()['info']['version'],
            "info.version d'openapi.json doit suivre config('app.version') : a mettre a jour a chaque release."
        );
    }

    public function test_openapi_request_examples_are_accepted_and_response_examples_match_the_engine(): void
    {
        $spec = $this->spec();
        $errors = [];

        foreach (self::ENDPOINTS as $mode => $endpoint) {
            $operation = $spec['paths'][$endpoint['path']]['post'];
            $request = $operation['requestBody']['content']['application/json']['example'];
            $documented = $operation['responses']['200']['content']['application/json']['example'] ?? null;

            $response = $this->postJson('/api/v1'.$endpoint['path'], $request);
            $response->assertOk();

            if ($documented !== null) {
                $this->compareDocumented("openapi.json {$endpoint['path']}", $documented, $response->json(), $errors);
            }
        }

        $this->assertSame([], $errors, "Exemples de reponse d'openapi.json differents du moteur :\n".implode("\n", $errors));
    }

    public function test_api_markdown_examples_reproduce_an_engine_run(): void
    {
        $markdown = file_get_contents(base_path('docs/API.md'));
        $errors = [];

        foreach (self::ENDPOINTS as $endpoint) {
            $blocks = $this->jsonBlocksUnder($markdown, $endpoint['heading']);
            $this->assertCount(2, $blocks, "docs/API.md {$endpoint['heading']} : un exemple de requete puis un exemple de reponse attendus.");

            [$request, $documented] = $blocks;
            $response = $this->postJson('/api/v1'.$endpoint['path'], $request);
            $response->assertOk();

            $this->compareDocumented("docs/API.md {$endpoint['path']}", $documented, $response->json(), $errors);
        }

        $this->assertSame([], $errors, "Exemples de docs/API.md differents du moteur :\n".implode("\n", $errors));
    }

    public function test_api_markdown_optional_fields_table_lists_exactly_the_accepted_fields(): void
    {
        $markdown = file_get_contents(base_path('docs/API.md'));
        $start = strpos($markdown, '## Parametres optionnels des simulations');
        $this->assertNotFalse($start, 'Section des parametres optionnels introuvable dans docs/API.md.');
        $section = substr($markdown, $start);
        $next = strpos($section, "\n## ", 3);
        if ($next !== false) {
            $section = substr($section, 0, $next);
        }

        preg_match_all('/^\| `([a-z_]+)` \|/m', $section, $m);
        $documented = $m[1];
        sort($documented);

        $accepted = array_values(array_diff(
            array_unique(array_merge(PayrollValidation::apiFields('gross_to_net'), PayrollValidation::apiFields('net_to_gross'))),
            ['salaire_base', 'net_cible', 'type_frais_pro'],
        ));
        sort($accepted);

        $this->assertSame($accepted, $documented, 'Le tableau des parametres optionnels de docs/API.md doit lister exactement les champs acceptes par l\'API.');
    }

    public function test_documentation_page_shows_the_spec_response_examples(): void
    {
        $html = $this->get('/api-documentation')->assertOk()->getContent();

        $this->assertStringContainsString('9053.64', $html);
        $this->assertStringContainsString('8873.1', $html);
        $this->assertStringNotContainsString('8234.56', $html);
        $this->assertStringNotContainsString('9723.45', $html);
    }

    private function spec(): array
    {
        return json_decode(file_get_contents(public_path('api/docs/openapi.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, list<string>>
     */
    private function rulesFor(string $mode): array
    {
        $raw = $mode === 'net_to_gross'
            ? PayrollValidation::netVersBrutRules()
            : PayrollValidation::brutVersNetRules();

        $rules = [];
        foreach ($raw as $field => $fieldRules) {
            $list = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $rules[$field] = array_values(array_filter($list, 'is_string'));
        }

        return $rules;
    }

    private function compareProperty(string $label, array $rules, array $property, array &$errors): void
    {
        $type = match (true) {
            in_array('integer', $rules, true) => 'integer',
            in_array('numeric', $rules, true) => 'number',
            in_array('boolean', $rules, true) => 'boolean',
            in_array('array', $rules, true) => 'array',
            default => 'string',
        };
        if (($property['type'] ?? null) !== $type) {
            $errors[] = "{$label} : type ".json_encode($property['type'] ?? null)." au lieu de \"{$type}\".";
        }

        $bounds = [];
        foreach ($rules as $rule) {
            if (preg_match('/^(min|max):(.+)$/', $rule, $m)) {
                $bounds[$m[1]] = (float) $m[2];
            }
            if (str_starts_with($rule, 'in:')) {
                $enum = explode(',', substr($rule, 3));
                $declared = $property['enum'] ?? [];
                sort($enum);
                sort($declared);
                if ($enum !== $declared) {
                    $errors[] = "{$label} : enum ".json_encode($declared).' au lieu de '.json_encode($enum).'.';
                }
            }
        }

        $keys = $type === 'array'
            ? ['min' => 'minItems', 'max' => 'maxItems']
            : ['min' => 'minimum', 'max' => 'maximum'];

        foreach ($keys as $bound => $key) {
            $expected = $bounds[$bound] ?? null;
            $declared = isset($property[$key]) ? (float) $property[$key] : null;
            if ($expected !== $declared) {
                $errors[] = "{$label} : {$key} ".json_encode($declared).' au lieu de '.json_encode($expected).'.';
            }
        }
    }

    /**
     * Compare recursivement les valeurs documentees a la reponse reelle.
     * Un objet de remplissage {"...": "..."} signale un contenu volontairement elide.
     */
    private function compareDocumented(string $label, mixed $documented, mixed $actual, array &$errors, string $path = ''): void
    {
        if ($documented === ['...' => '...']) {
            return;
        }

        if (is_array($documented) && $documented !== [] && ! array_is_list($documented)) {
            if (! is_array($actual)) {
                $errors[] = "{$label} {$path} : objet documente, recu ".json_encode($actual).'.';

                return;
            }
            foreach ($documented as $key => $value) {
                if (! array_key_exists($key, $actual)) {
                    $errors[] = "{$label} {$path}.{$key} : champ documente absent de la reponse.";

                    continue;
                }
                $this->compareDocumented($label, $value, $actual[$key], $errors, "{$path}.{$key}");
            }

            return;
        }

        if (is_int($documented) || is_float($documented)) {
            if (! (is_int($actual) || is_float($actual)) || abs($documented - $actual) > 0.005) {
                $errors[] = "{$label} {$path} : documente ".json_encode($documented).', moteur '.json_encode($actual).'.';
            }

            return;
        }

        if ($documented !== $actual) {
            $errors[] = "{$label} {$path} : documente ".json_encode($documented).', moteur '.json_encode($actual).'.';
        }
    }

    /**
     * @return list<array<mixed>>
     */
    private function jsonBlocksUnder(string $markdown, string $heading): array
    {
        $start = strpos($markdown, $heading);
        $this->assertNotFalse($start, "Section {$heading} introuvable dans docs/API.md.");

        $section = substr($markdown, $start + strlen($heading));
        $next = preg_match('/^#{2,3} /m', $section, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : strlen($section);
        $section = substr($section, 0, $next);

        preg_match_all('/```json\n(.*?)```/s', $section, $blocks);

        return array_map(
            fn (string $json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR),
            $blocks[1],
        );
    }
}
