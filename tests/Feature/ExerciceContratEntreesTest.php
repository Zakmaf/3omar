<?php

namespace Tests\Feature;

use App\Services\PayrollCalculatorService;
use App\Services\SimulationCodec;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Contrat d'entrée du champ exercice (issue #281) aux quatre portes : API,
 * formulaire web, lien partagé (codec) et moteur direct. Caractérise le
 * comportement déjà validé : repli 2026 permanent, refus explicite des
 * exercices inconnus, invalides ou non calculables, provenance exacte.
 */
class ExerciceContratEntreesTest extends TestCase
{
    private const METADONNEES = ['statut', 'source', 'reference_ir', 'periode', 'date_effet_projetee', 'validation_legale'];

    private const BRUT = ['salaire_base' => 20000, 'type_frais_pro' => 'commun'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    /**
     * Corps JSON envoyé tel quel, pour les littéraux que postJson réécrirait.
     */
    private function posterJsonBrut(string $uri, string $corps)
    {
        return $this->call('POST', $uri, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $corps);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function exercicesAbsents(): array
    {
        return [
            'null' => [null],
            'vide' => [''],
            'blancs' => ['   '],
            'chaîne 2026' => ['2026'],
            'entier 2026' => [2026],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function exercicesInvalides(): array
    {
        return [
            'texte' => ['abc'],
            'avant le premier exercice' => [1999],
            'après le dernier exercice' => [2032],
            'projet sans frais professionnels' => [2027],
            'tableau' => [[2026]],
            'booléen' => [true],
        ];
    }

    public function test_api_sans_exercice_calcule_en_2026(): void
    {
        $this->postJson('/api/v1/simuler/brut-vers-net', self::BRUT)
            ->assertOk()
            ->assertJsonPath('exercice', 2026)
            ->assertJsonPath('reference_fiscale.statut', 'en_vigueur');
    }

    #[DataProvider('exercicesAbsents')]
    public function test_api_absence_ou_2026_explicite_donnent_le_meme_exercice(mixed $exercice): void
    {
        $this->postJson('/api/v1/simuler/brut-vers-net', self::BRUT + ['exercice' => $exercice])
            ->assertOk()
            ->assertJsonPath('exercice', 2026);
    }

    #[DataProvider('exercicesInvalides')]
    public function test_api_refuse_un_exercice_invalide_sur_le_seul_champ_exercice(mixed $exercice): void
    {
        $response = $this->postJson('/api/v1/simuler/brut-vers-net', self::BRUT + ['exercice' => $exercice]);

        $response->assertStatus(422);
        $this->assertSame(['exercice'], array_keys($response->json('errors') ?? []));
    }

    public function test_api_refuse_un_exercice_decimal_envoye_en_json_brut(): void
    {
        $response = $this->posterJsonBrut(
            '/api/v1/simuler/brut-vers-net',
            '{"salaire_base":20000,"type_frais_pro":"commun","exercice":2026.5}',
        );

        $response->assertStatus(422);
        $this->assertSame(['exercice'], array_keys($response->json('errors') ?? []));
    }

    public function test_web_refuse_un_projet_et_accepte_un_exercice_vide(): void
    {
        $this->from('/calculateur')
            ->post('/calculateur/calculer', self::BRUT + ['mode' => 'gross_to_net', 'exercice' => '2027'])
            ->assertRedirect('/calculateur')
            ->assertSessionHasErrors('exercice');

        $this->post('/calculateur/calculer', self::BRUT + ['mode' => 'gross_to_net', 'exercice' => ''])
            ->assertOk()
            ->assertSessionHasNoErrors();
    }

    public function test_le_lien_partage_conserve_2026_explicite_et_refuse_les_invalides(): void
    {
        $codec = app(SimulationCodec::class);
        $scenario = self::BRUT + ['mode' => 'gross_to_net'];

        $decode = $codec->decode($codec->encode($scenario + ['exercice' => 2026]));
        $this->assertNotNull($decode);
        $this->assertEquals(2026, $decode['exercice'] ?? null, 'L\'exercice explicite voyage dans le lien.');

        $this->assertNull($codec->decode($codec->encode($scenario + ['exercice' => 2027])), 'Projet non calculable refusé.');
        $this->assertNull($codec->decode($codec->encode($scenario + ['exercice' => 'abc'])), 'Exercice invalide refusé.');
    }

    public function test_le_moteur_direct_refuse_un_projet_ou_un_exercice_inconnu_avant_tout_calcul(): void
    {
        $moteur = app(PayrollCalculatorService::class);

        foreach ([
            2027 => __('ui.validation.exercise_not_calculable', ['exercice' => 2027]),
            1999 => __('ui.validation.exercise_unknown', ['exercice' => 1999]),
        ] as $exercice => $message) {
            try {
                $moteur->calculer(self::BRUT + ['exercice' => $exercice]);
                $this->fail("calculer() doit refuser l'exercice {$exercice}.");
            } catch (\InvalidArgumentException $e) {
                $this->assertSame($message, $e->getMessage());
            }

            try {
                $moteur->resoudreDepuisNet(['net_cible' => 15000, 'type_frais_pro' => 'commun', 'exercice' => $exercice]);
                $this->fail("resoudreDepuisNet() doit refuser l'exercice {$exercice}.");
            } catch (\InvalidArgumentException $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }
    }

    public function test_le_moteur_expose_exactement_les_six_metadonnees_du_profil(): void
    {
        $profil = config('payroll.exercices.2026');

        foreach ([[], ['exercice' => null], ['exercice' => 2026]] as $variante) {
            $resultat = app(PayrollCalculatorService::class)->calculer(self::BRUT + $variante);

            $this->assertSame(2026, $resultat['exercice']);
            $this->assertSame(self::METADONNEES, array_keys($resultat['reference_fiscale']));
            foreach (self::METADONNEES as $cle) {
                $this->assertSame($profil[$cle], $resultat['reference_fiscale'][$cle], $cle);
            }
        }
    }

    public function test_les_metadonnees_chaines_ne_sont_jamais_resolues_comme_references(): void
    {
        config(['payroll.exercices.2026.reference_ir' => 'frais_pro']);

        $resultat = app(PayrollCalculatorService::class)->calculer(self::BRUT);

        $this->assertSame('frais_pro', $resultat['reference_fiscale']['reference_ir']);
        $this->assertSame('en_vigueur', $resultat['reference_fiscale']['statut']);
    }

    public function test_une_surcharge_de_la_cle_historique_ir_baremes_suit_jusqu_au_moteur(): void
    {
        $baremes = config('payroll.ir.baremes');
        $derniere = array_key_last($baremes);
        $baremes[$derniere]['deduction'] = 30000;
        config(['payroll.ir.baremes' => $baremes]);

        $resultat = app(PayrollCalculatorService::class)->calculer(self::BRUT);

        $this->assertNull($resultat['tranche_ir']['max'], 'Le cas de test tombe dans la dernière tranche.');
        $this->assertSame(
            round($resultat['rni_annuel_net'] * $baremes[$derniere]['taux'] - 30000, 2),
            $resultat['ir_annuel_brut'],
        );
    }

    public function test_le_schema_openapi_declare_la_provenance_reelle(): void
    {
        $spec = json_decode(file_get_contents(public_path('api/docs/openapi.json')), true, flags: JSON_THROW_ON_ERROR);
        $resultat = $spec['components']['schemas']['SimulationResult']['properties'];

        $this->assertSame('integer', $resultat['exercice']['type']);

        $declarees = array_keys($resultat['reference_fiscale']['properties']);
        $reelles = array_keys($this->postJson('/api/v1/simuler/brut-vers-net', self::BRUT)->json('reference_fiscale'));
        sort($declarees);
        sort($reelles);

        $this->assertSame($reelles, $declarees);
    }
}
