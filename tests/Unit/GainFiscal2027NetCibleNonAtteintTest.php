<?php

namespace Tests\Unit;

use App\Http\Requests\PayrollValidation;
use App\Services\ExercicesFiscaux;
use App\Services\GainFiscal2027;
use App\Services\PayrollCalculatorService;
use App\Services\SimulationCodec;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Net cible non atteint par le solveur (issue #283, revue indépendante 2).
 *
 * Contrat : GainFiscal2027::comparer() refuse explicitement une saisie net
 * cible dont la résolution unique en 2026 n'a pas atteint le net visé
 * (resolution_net.converge === false). Aucun gain n'est calculé : la
 * décomposition du moteur n'est jamais appelée, et l'exception
 * InvalidArgumentException porte le message traduit
 * ui.gain2027.net_target_not_reached (quatre catalogues). Le brut figé d'une
 * cible non atteinte ne doit jamais être présenté comme « le brut qui donne ce
 * net ».
 *
 * Trois situations, chacune vérifiée d'abord comme prémisse sur le vrai moteur
 * (aucun montant deviné) :
 * - cible hors d'atteinte : borne de recherche du solveur atteinte ;
 * - cible déjà dépassée à salaire_base = 0 par des primes ou une indemnité
 *   exonérée ;
 * - non-convergence forcée par un espion du moteur réel, sur une entrée
 *   valide que le vrai moteur sait résoudre.
 *
 * Statut attendu :
 * - sur l'arbre indexé actuel (net cible pris en charge, sans refus) : ROUGE,
 *   la comparaison est acceptée en silence ;
 * - sur un HEAD sans mode net cible dans le service, le rouge est masqué : le
 *   service refuse le mode avant toute résolution (assertion « une seule
 *   résolution » en échec), pas pour la bonne raison.
 */
class GainFiscal2027NetCibleNonAtteintTest extends TestCase
{
    private const CLE = 'ui.gain2027.net_target_not_reached';

    private const LOCALES = ['fr', 'en', 'ar', 'es'];

    /**
     * @return array<string, array{array, string}>
     */
    public static function ciblesNonAtteintes(): array
    {
        return [
            'hors borne du solveur' => [[
                'mode' => 'net_to_gross',
                'net_cible' => PayrollValidation::MONTANT_MAX,
                'type_frais_pro' => 'commun',
                'retenues_imposees_ir' => PayrollValidation::MONTANT_MAX,
            ], 'unreachable'],
            'cible deja depassee par des primes a salaire_base nul' => [[
                'mode' => 'net_to_gross',
                'net_cible' => 1000,
                'type_frais_pro' => 'commun',
                'prime_rendement' => 5000,
            ], 'already_reached'],
            'cible deja depassee par une indemnite exoneree a salaire_base nul' => [[
                'mode' => 'net_to_gross',
                'net_cible' => 300,
                'type_frais_pro' => 'commun',
                'indemnites' => [['type' => 'transport', 'montant' => 500]],
            ], 'already_reached'],
        ];
    }

    /**
     * Espion du moteur réel : compte les résolutions et les décompositions,
     * et peut forcer converge = false sans rien changer d'autre au calcul.
     */
    private function espion(bool $forcerNonConvergence = false): PayrollCalculatorService
    {
        $espion = new class($forcerNonConvergence) extends PayrollCalculatorService
        {
            public int $resolutions = 0;

            public int $decompositions = 0;

            public function __construct(public bool $forcerNonConvergence)
            {
                parent::__construct(app(ExercicesFiscaux::class));
            }

            public function resoudreDepuisNet(array $input): array
            {
                $this->resolutions++;
                $resultat = parent::resoudreDepuisNet($input);

                if ($this->forcerNonConvergence) {
                    $resultat['resolution_net']['converge'] = false;
                }

                return $resultat;
            }

            public function decomposerChangementExercice(array $input, int $exerciceReference, int $exerciceProjete): array
            {
                $this->decompositions++;

                return parent::decomposerChangementExercice($input, $exerciceReference, $exerciceProjete);
            }
        };

        $this->app->instance(PayrollCalculatorService::class, $espion);

        return $espion;
    }

    /** Résolution par le vrai moteur, telle que le service la demande (2026). */
    private function resolutionReelle(array $saisie): array
    {
        $moteur = new PayrollCalculatorService(app(ExercicesFiscaux::class));

        return $moteur->resoudreDepuisNet(array_merge($saisie, ['exercice' => GainFiscal2027::EXERCICE_REFERENCE]));
    }

    private function assertSaisieValide(array $saisie): void
    {
        $codec = app(SimulationCodec::class);
        $this->assertNotNull($codec->decode($codec->encode($saisie)), 'Prémisse : saisie valide pour le formulaire et le codec.');
    }

    private function assertRefus(PayrollCalculatorService $espion, array $saisie, string $contexte): void
    {
        try {
            app(GainFiscal2027::class)->comparer($saisie);
            $this->fail("{$contexte} : un net cible non atteint ne doit jamais produire de gain.");
        } catch (\InvalidArgumentException $e) {
            $this->assertSame(1, $espion->resolutions, "{$contexte} : une seule résolution, en 2026, avant le refus.");
            $this->assertSame(0, $espion->decompositions, "{$contexte} : aucun calcul de gain après une cible non atteinte.");
            $this->assertSame(__(self::CLE), $e->getMessage(), "{$contexte} : message traduit dédié.");
        }

        $this->assertNotSame(self::CLE, __(self::CLE), 'Clé de traduction présente.');
    }

    #[DataProvider('ciblesNonAtteintes')]
    public function test_une_cible_non_atteinte_par_le_vrai_moteur_est_refusee(array $saisie, string $situation): void
    {
        $this->assertSaisieValide($saisie);

        $resolu = $this->resolutionReelle($saisie);
        $netCible = (float) $saisie['net_cible'];

        $this->assertFalse($resolu['resolution_net']['converge'], 'Prémisse : le vrai moteur ne converge pas.');

        if ($situation === 'unreachable') {
            $this->assertLessThan($netCible, $resolu['salaire_net'], 'Prémisse : net obtenu sous la cible à la borne du solveur.');
            $this->assertContains(__('ui.result.warning_net_unreachable'), $resolu['avertissements']);
        } else {
            $this->assertSame(0.0, (float) $resolu['input']['salaire_base'], 'Prémisse : salaire de base nul.');
            $this->assertGreaterThan($netCible, $resolu['salaire_net'], 'Prémisse : cible déjà dépassée sans salaire de base.');
            $this->assertContains(__('ui.result.warning_net_already_reached'), $resolu['avertissements']);
        }

        $this->assertRefus($this->espion(), $saisie, $situation);
    }

    public function test_une_non_convergence_forcee_sur_une_entree_valide_est_refusee(): void
    {
        $saisie = ['mode' => 'net_to_gross', 'net_cible' => 12000, 'type_frais_pro' => 'commun'];
        $this->assertSaisieValide($saisie);

        // Prémisse : sans espion, le vrai moteur atteint cette cible. Seul le
        // drapeau forcé distingue ce cas d'un net cible accepté.
        $this->assertTrue($this->resolutionReelle($saisie)['resolution_net']['converge'], 'Prémisse : cible atteinte par le vrai moteur.');

        $espion = $this->espion(true);
        $this->assertRefus($espion, $saisie, 'non-convergence forcée');
    }

    public function test_une_cible_atteinte_reste_comparee(): void
    {
        // Garde-fou du correctif : le refus ne touche que converge === false.
        $espion = $this->espion();
        $resultat = app(GainFiscal2027::class)->comparer(['mode' => 'net_to_gross', 'net_cible' => 12000, 'type_frais_pro' => 'commun']);

        $this->assertTrue($resultat['resolution']['converge']);
        $this->assertSame(1, $espion->resolutions);
        $this->assertSame(1, $espion->decompositions);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function locales(): array
    {
        $cas = [];
        foreach (self::LOCALES as $locale) {
            $cas[$locale] = [$locale];
        }

        return $cas;
    }

    #[DataProvider('locales')]
    public function test_le_refus_est_traduit_dans_chaque_langue(string $locale): void
    {
        $texte = trans(self::CLE, [], $locale);
        $this->assertIsString($texte);
        $this->assertNotSame(self::CLE, $texte, "{$locale} : clé absente du catalogue");
        $this->assertNotSame('', trim($texte), "{$locale} : texte vide");
        $this->assertStringNotContainsString("\u{2014}", $texte, "{$locale} : sans tiret cadratin");

        app()->setLocale($locale);
        $espion = $this->espion(true);

        try {
            app(GainFiscal2027::class)->comparer(['mode' => 'net_to_gross', 'net_cible' => 12000, 'type_frais_pro' => 'commun']);
            $this->fail("{$locale} : refus attendu.");
        } catch (\InvalidArgumentException $e) {
            $this->assertSame($texte, $e->getMessage(), "{$locale} : message dans la langue active");
        }

        $this->assertSame(0, $espion->decompositions);
    }
}
