<?php

namespace Tests\Unit;

use App\Services\ExercicesFiscaux;
use App\Services\PayrollCalculatorService;
use App\Services\SimulationCodec;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Non-régression #281 : un exercice composé uniquement de blancs dans un lien
 * partagé. La validation Laravel n'exécute pas les règles non implicites sur
 * une chaîne vide après trim, donc le codec, qui ne passe pas par le
 * middleware TrimStrings, accepte ce payload. Le moteur doit alors traiter
 * l'exercice comme absent (repli sur l'exercice par défaut), selon la même
 * règle que la validation, et non le refuser comme exercice inconnu.
 */
class ExerciceLienVideTest extends TestCase
{
    #[DataProvider('exercicesBlancs')]
    public function test_un_exercice_blanc_dans_un_lien_retombe_sur_l_exercice_par_defaut(string $blanc): void
    {
        $simulation = [
            'mode' => 'gross_to_net',
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
        ];

        $decoded = app(SimulationCodec::class)->decode($this->forgePayload($simulation + ['exercice' => $blanc]));

        $this->assertNotNull($decoded, 'Le codec accepte un exercice blanc : la validation saute ses règles.');

        $this->assertNull(
            app(ExercicesFiscaux::class)->erreur($decoded['exercice'] ?? null),
            'Un exercice accepté par la validation du lien doit être calculable par le moteur.',
        );

        $calculateur = app(PayrollCalculatorService::class);

        // La clé 'input' reprend l'entrée telle quelle, exercice blanc compris :
        // seule la partie calculée est comparée.
        $sansEntree = fn (array $resultat): array => array_diff_key($resultat, ['input' => true]);

        $this->assertSame(
            $sansEntree($calculateur->calculer($simulation)),
            $sansEntree($calculateur->calculer($decoded)),
            'Un exercice blanc doit donner le même calcul qu\'un exercice absent.',
        );
    }

    public static function exercicesBlancs(): array
    {
        return [
            'espaces' => ['   '],
            'tabulation' => ["\t"],
            'espaces et tabulation' => [" \t "],
        ];
    }

    /**
     * Payload v1 au format du codec, construit à la main comme le ferait un
     * lien fabriqué hors de l'interface.
     */
    private function forgePayload(array $input): string
    {
        $json = json_encode(['v' => 1, 'i' => $input]);

        return rtrim(strtr(base64_encode(gzdeflate($json)), '+/', '-_'), '=');
    }
}
