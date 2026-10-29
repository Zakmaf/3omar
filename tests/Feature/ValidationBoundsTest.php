<?php

namespace Tests\Feature;

use App\Http\Requests\PayrollValidation;
use App\Services\PayrollCalculatorService;
use App\Services\SimulationCodec;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Bornes de saisie (#167) : aucun taux ni montant sans plafond, et un net à
 * payer négatif obtenu avec des valeurs valides est signalé.
 */
class ValidationBoundsTest extends TestCase
{
    /** Taux saisis en pourcentage. */
    private const TAUX = ['cimr_taux', 'cimr_taux_employeur'];

    /** Montants mensuels en MAD, plafonnés comme salaire_base. */
    private const MONTANTS = [
        'prime_bilan', 'prime_rendement', 'autres_primes',
        'retraite_complementaire_mensuel', 'rc_part_employeur',
        'mutuelle_salarie', 'mutuelle_patronale', 'assurance_rc_pro',
        'retenues_exonerees_ir', 'retenues_imposees_ir', 'avantages_cnss',
        'autres_retenues',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public static function provideBoundedFields(): array
    {
        $cases = [];
        foreach (self::TAUX as $field) {
            $cases[$field] = [$field, 100];
        }
        foreach (self::MONTANTS as $field) {
            $cases[$field] = [$field, 10000000];
        }

        return $cases;
    }

    public function test_every_numeric_field_has_a_maximum(): void
    {
        $missing = [];

        foreach ([
            'brut-vers-net' => PayrollValidation::brutVersNetRules(),
            'net-vers-brut' => PayrollValidation::netVersBrutRules(),
            'web brut' => PayrollValidation::webRules('gross_to_net'),
            'web net' => PayrollValidation::webRules('net_to_gross'),
        ] as $set => $rules) {
            foreach ($rules as $field => $fieldRules) {
                $list = array_filter(
                    is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules),
                    'is_string',
                );
                if (! array_intersect(['numeric', 'integer'], $list)) {
                    continue;
                }
                if (! preg_grep('/^max:/', $list)) {
                    $missing[] = "{$set} : {$field}";
                }
            }
        }

        $this->assertSame([], $missing, 'Champs numériques sans règle max.');
    }

    #[DataProvider('provideBoundedFields')]
    public function test_api_accepts_the_cap_and_rejects_above(string $field, int $max): void
    {
        $base = ['salaire_base' => 10000, 'type_frais_pro' => 'commun'];

        $this->postJson('/api/v1/simuler/brut-vers-net', $base + [$field => $max])
            ->assertOk();

        $this->postJson('/api/v1/simuler/brut-vers-net', $base + [$field => $max + 0.01])
            ->assertStatus(422)
            ->assertJsonPath("errors.{$field}.0", "The {$field} field must not be greater than {$max}.");

        $this->postJson('/api/v1/simuler/net-vers-brut', ['net_cible' => 8000, 'type_frais_pro' => 'commun', $field => $max + 0.01])
            ->assertStatus(422)
            ->assertJsonPath("errors.{$field}.0", "The {$field} field must not be greater than {$max}.");
    }

    public function test_api_rejects_the_cimr_rate_of_the_issue(): void
    {
        $this->postJson('/api/v1/simuler/brut-vers-net', [
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
            'cimr_taux' => 200,
        ])->assertStatus(422)
            ->assertJsonPath('detail', 'The cimr_taux field must not be greater than 100.');
    }

    /** autres_retenues n'existe que dans l'API. */
    public static function provideBoundedWebFields(): array
    {
        return array_diff_key(self::provideBoundedFields(), ['autres_retenues' => true]);
    }

    #[DataProvider('provideBoundedWebFields')]
    public function test_web_form_rejects_a_value_above_the_cap(string $field, int $max): void
    {
        $input = ['mode' => 'gross_to_net', 'salaire_base' => 10000, 'type_frais_pro' => 'commun'];

        $this->post('/calculateur/calculer', $input + [$field => $max])
            ->assertOk()
            ->assertSessionHasNoErrors();

        $attribute = trans("validation.attributes.{$field}", [], 'fr');

        $this->withSession(['locale' => 'fr'])
            ->from('/calculateur')
            ->post('/calculateur/calculer', $input + [$field => $max + 0.01])
            ->assertRedirect('/calculateur')
            ->assertSessionHasErrors([$field => "Le champ {$attribute} ne peut pas dépasser {$max}."]);
    }

    public function test_a_share_link_with_an_out_of_bounds_value_is_rejected(): void
    {
        $codec = app(SimulationCodec::class);
        $payload = $codec->encode([
            'mode' => 'gross_to_net',
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
            'cimr_taux' => 200,
        ]);

        $this->assertNull($codec->decode($payload));

        $this->get('/calculateur?s='.$payload)
            ->assertOk()
            ->assertSee('Le lien de simulation est illisible ou expiré')
            ->assertDontSee('value="200"', false);

        $valid = $codec->encode(['mode' => 'gross_to_net', 'salaire_base' => 10000, 'type_frais_pro' => 'commun']);

        $this->get('/calculateur/comparer?a='.$valid.'&b='.$payload)
            ->assertRedirect('/calculateur');
    }

    public function test_a_negative_net_with_valid_values_is_flagged_in_every_language(): void
    {
        $input = [
            'mode' => 'gross_to_net',
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
            'retenues_imposees_ir' => 20000,
        ];

        $net = app(PayrollCalculatorService::class)->calculer($input)['salaire_net'];
        $this->assertLessThan(0, $net);
        $montant = number_format($net, 2, ',', ' ');

        foreach (array_keys(config('app.supported_locales')) as $locale) {
            $message = trans('ui.result.warning_negative_net', ['montant' => $montant], $locale);
            $this->assertNotSame('ui.result.warning_negative_net', $message, $locale);
            $this->assertStringContainsString($montant, $message, $locale);

            $this->withSession(['locale' => $locale])
                ->post('/calculateur/calculer', $input)
                ->assertOk()
                ->assertSee($message);
        }

        $avertissements = $this->postJson('/api/v1/simuler/brut-vers-net', $input)
            ->assertOk()
            ->json('avertissements');

        $this->assertContains(trans('ui.result.warning_negative_net', ['montant' => $montant], 'en'), $avertissements);
    }

    public function test_a_positive_net_has_no_negative_net_warning(): void
    {
        $resultat = app(PayrollCalculatorService::class)->calculer([
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
        ]);

        $this->assertGreaterThan(0, $resultat['salaire_net']);
        $this->assertSame([], preg_grep('/Net à payer négatif/u', $resultat['avertissements']));
    }
}
