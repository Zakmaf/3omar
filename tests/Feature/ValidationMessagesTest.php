<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

/**
 * Le bandeau d'erreurs du formulaire affiche $errors->all() : chaque message
 * doit etre traduit dans la langue active et nommer le champ (issue #187).
 */
class ValidationMessagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function invalidInput(): array
    {
        return [
            'mode' => 'gross_to_net',
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
            'nb_enfants' => 25,
            'cimr_taux' => -1,
            'autres_primes' => 'abc',
            'jours_travailles' => 2.5,
            'conjoint_charge' => 'peut-etre',
            'heures_sup' => [['nb_heures' => 5]],
            'indemnites' => [['type' => 'inconnue', 'montant' => 2000000]],
        ];
    }

    public function test_no_locale_shows_a_raw_translation_key(): void
    {
        foreach (array_keys(config('app.supported_locales')) as $locale) {
            $response = $this->withSession(['locale' => $locale])
                ->from('/calculateur')
                ->post('/calculateur/calculer', $this->invalidInput());

            $response->assertRedirect('/calculateur');
            $messages = session('errors')->all();

            $this->assertGreaterThanOrEqual(8, count($messages), $locale);
            foreach ($messages as $message) {
                $this->assertStringStartsNotWith('validation.', $message, "{$locale} : {$message}");
                $this->assertStringNotContainsString(':attribute', $message, "{$locale} : {$message}");
                $this->assertStringNotContainsString(':position', $message, "{$locale} : {$message}");
            }

            $catalog = require lang_path("{$locale}/validation.php");
            $this->assertStringContainsString(
                $catalog['attributes']['nb_enfants'],
                session('errors')->first('nb_enfants'),
                "{$locale} : le message doit nommer le champ."
            );
        }
    }

    public function test_french_messages_name_the_field_and_the_line(): void
    {
        $this->withSession(['locale' => 'fr'])
            ->from('/calculateur')
            ->post('/calculateur/calculer', $this->invalidInput())
            ->assertSessionHasErrors([
                'nb_enfants' => 'Le champ enfants à charge ne peut pas dépasser 20.',
                'cimr_taux' => 'Le champ taux CIMR salarié doit être supérieur ou égal à 0.',
                'autres_primes' => 'Le champ primes imposables doit être un nombre.',
                'jours_travailles' => 'Le champ jours travaillés dans le mois doit être un nombre entier.',
                'heures_sup.0.type' => 'Le champ type des heures supplémentaires (ligne 1) est obligatoire quand des heures sont saisies sur la même ligne.',
                'indemnites.0.type' => "La valeur du champ type d'indemnité (ligne 1) est invalide.",
                'indemnites.0.montant' => "Le champ montant de l'indemnité (ligne 1) ne peut pas dépasser 1000000.",
            ]);
    }

    public function test_english_messages_name_the_field(): void
    {
        $this->withSession(['locale' => 'en'])
            ->from('/calculateur')
            ->post('/calculateur/calculer', $this->invalidInput())
            ->assertSessionHasErrors([
                'nb_enfants' => 'The dependent children field must not be greater than 20.',
                'heures_sup.0.type' => 'The overtime type (line 1) field is required when hours are entered on the same line.',
            ]);
    }
}
