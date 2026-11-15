<?php

namespace Tests\Unit;

use App\Services\GainFiscal2027;
use App\Services\PayrollCalculatorService;
use App\Services\SimulationCodec;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Cas complémentaires du gain fiscal 2026 / 2027 (issue #283), en complément du
 * cas d'acceptation 20 000 MAD de GainFiscal2027Test (inchangé).
 *
 * Données synthétiques. Aucun montant attendu n'est recopié d'un calcul
 * manuel : les tests comparent des résultats du moteur entre eux, et chaque
 * hypothèse sur un montant (IR nul, plafond atteint ou non) est d'abord
 * vérifiée comme prémisse sur le résultat réel du moteur.
 *
 * Contrat complémentaire de GainFiscal2027::comparer() :
 * - source : ['exercice' => exercice de la saisie reçue (absent : repli 2026),
 *   'mode' => mode de la saisie]. Une saisie 2027 est recalculée en 2026 et en
 *   2027, jamais assimilée en silence à un résultat 2026 : l'exercice source
 *   est exposé tel quel ;
 * - gain.aucun_gain_ir : vrai quand l'IR 2026 de la référence est déjà nul
 *   (« Aucun gain d'IR calculé », jamais un gain fictif) ;
 * - net cible (mode net_to_gross) : une seule résolution, en 2026, puis le
 *   salaire de base trouvé est figé pour les deux exercices. Sortie
 *   supplémentaire resolution : ['exercice' => 2026, 'net_cible',
 *   'salaire_base', 'converge'] lue dans resolution_net du moteur ;
 * - net cible d'une saisie 2027 : refus explicite (InvalidArgumentException,
 *   message ui.gain2027.net_source_2027_refused), avant toute résolution. Le
 *   net visé appartenait à 2027 ; le résoudre en 2026 changerait en silence
 *   le sens de la cible.
 *
 * Statut attendu au prochain run (pour le coordinateur) :
 * - ROUGES (fonctionnalité absente) : source exposée, aucun_gain_ir, tous les
 *   cas net cible ;
 * - VERTS dès maintenant, preuves de non-régression du moteur et du service
 *   livrés en tranche 1 : 10 000 MAD, plafond non atteint, écarts non
 *   négatifs, refus de catégorie, refus d'exercice non calculable, provenance
 *   de l'intermédiaire, absence de mutation de configuration, conservation
 *   des entrées, refus 2028 par le codec.
 */
class GainFiscal2027CasTest extends TestCase
{
    private const BASE = ['mode' => 'gross_to_net', 'type_frais_pro' => 'commun'];

    /** Saisie complète : famille, CIMR, AMO dérogatoire, mutuelle, retenues, parts patronales inconnues. */
    private const SAISIE_COMPLETE = [
        'mode' => 'gross_to_net',
        'salaire_base' => 12000,
        'nb_annees_anciennete' => 5,
        'prime_rendement' => 500,
        'type_frais_pro' => 'commun',
        'nb_enfants' => 2,
        'conjoint_charge' => 1,
        'cimr_taux' => 6,
        'cimr_taux_employeur_inconnu' => 1,
        'amo_taux_salarie_personnalise' => 1,
        'amo_taux_salarie' => 2.5,
        'retraite_complementaire_mensuel' => 300,
        'mutuelle_salarie' => 200,
        'mutuelle_patronale_inconnu' => 1,
        'retenues_exonerees_ir' => 50,
        'retenues_imposees_ir' => 100,
        'indemnites' => [['type' => 'transport', 'montant' => 700]],
    ];

    private function service(): GainFiscal2027
    {
        return app(GainFiscal2027::class);
    }

    private function saisie(float $salaire, array $extra = []): array
    {
        return array_merge(self::BASE, ['salaire_base' => $salaire], $extra);
    }

    private function assertDecompositionExacte(array $resultat, string $contexte): void
    {
        $d = $resultat['decomposition'];

        $this->assertSame(['bareme', 'frais_pro'], $d['ordre'], "{$contexte} : ordre explicite");
        $this->assertSame(
            $resultat['gain']['net_mensuel'],
            round($d['bareme'] + $d['frais_pro'], 2),
            "{$contexte} : barème + frais professionnels = gain, au centime après arrondi",
        );
        $this->assertSame(
            round($resultat['projete']['salaire_net'] - $resultat['reference']['salaire_net'], 2),
            $resultat['gain']['net_mensuel'],
            "{$contexte} : gain = net 2027 - net 2026",
        );
    }

    // ------------------------------------------------------------------
    // Brut vers net : cas de l'issue (non-régression de la tranche 1)
    // ------------------------------------------------------------------

    public function test_10000_les_deux_parts_sont_non_nulles_et_le_gain_differe_de_20000(): void
    {
        $r = $this->service()->comparer($this->saisie(10000));

        // Prémisses lues sur le moteur : FP 2026 non plafonnés, FP 2027 plafonnés.
        $this->assertFalse($r['reference']['fp_plafonne'], 'Prémisse : FP 2026 sous le plafond à 10 000.');
        $this->assertTrue($r['projete']['fp_plafonne'], 'Prémisse : FP 2027 plafonnés à 10 000.');
        $this->assertGreaterThan($r['reference']['frais_pro'], $r['projete']['frais_pro']);

        $this->assertGreaterThan(0, $r['decomposition']['bareme'], 'Part barème non nulle.');
        $this->assertGreaterThan(0, $r['decomposition']['frais_pro'], 'Part frais professionnels non nulle.');
        $this->assertDecompositionExacte($r, '10 000');

        // L'intermédiaire est celui du moteur, pas une reconstruction.
        $moteur = app(PayrollCalculatorService::class)->decomposerChangementExercice($this->saisie(10000), 2026, 2027);
        $this->assertSame($moteur['intermediaire']['salaire_net'], $r['decomposition']['net_intermediaire']);
        $this->assertSame($moteur['intermediaire']['frais_pro'], $r['reference']['frais_pro'], 'Intermédiaire : FP 2026.');

        $r20000 = $this->service()->comparer($this->saisie(20000));
        $this->assertNotSame($r20000['gain']['net_mensuel'], $r['gain']['net_mensuel'], 'Pas de gain identique pour tous.');
    }

    public function test_plafond_2027_non_atteint_avec_ir_2026_positif(): void
    {
        $r = $this->service()->comparer($this->saisie(6400));

        $this->assertFalse($r['projete']['fp_plafonne'], 'Prémisse : FP 2027 sous le plafond à 6 400.');
        $this->assertGreaterThan(0, $r['reference']['ir_net'], 'Prémisse : IR 2026 positif à 6 400.');

        $this->assertGreaterThan(0, $r['gain']['net_mensuel']);
        $this->assertGreaterThanOrEqual(0, $r['projete']['ir_net']);
        $this->assertDecompositionExacte($r, '6 400');
    }

    /**
     * @return array<string, array{float, string}>
     */
    public static function bornes(): array
    {
        return [
            'SMIG voisin 3 000' => [3000.0, 'commun'],
            'plafond FP 2027 6 481,48' => [6481.48, 'commun'],
            'seuil 2026 6 500' => [6500.0, 'commun'],
            'seuil 2026 + 1 centime' => [6500.01, 'commun'],
            '10 000 journaliste' => [10000.0, 'journaliste'],
            '10 000 artiste' => [10000.0, 'artiste'],
            '50 000' => [50000.0, 'commun'],
        ];
    }

    #[DataProvider('bornes')]
    public function test_les_ecarts_sont_coherents_et_non_negatifs(float $salaire, string $categorie): void
    {
        $r = $this->service()->comparer($this->saisie($salaire, ['type_frais_pro' => $categorie]));

        $this->assertGreaterThanOrEqual(0, $r['gain']['net_mensuel']);
        $this->assertGreaterThanOrEqual(0, $r['decomposition']['bareme']);
        $this->assertGreaterThanOrEqual(0, $r['decomposition']['frais_pro']);
        $this->assertSame(round($r['gain']['net_mensuel'] * $r['gain']['nb_mois'], 2), $r['gain']['net_annuel_indicatif']);
        $this->assertDecompositionExacte($r, "{$salaire} {$categorie}");
    }

    // ------------------------------------------------------------------
    // IR nul (ROUGE : aucun_gain_ir absent)
    // ------------------------------------------------------------------

    public function test_ir_deja_nul_aucun_gain_d_ir_calcule(): void
    {
        $r = $this->service()->comparer($this->saisie(4000));

        $this->assertSame(0.0, $r['reference']['ir_net'], 'Prémisse : IR 2026 nul à 4 000.');
        $this->assertSame(0.0, $r['projete']['ir_net'], 'Prémisse : IR 2027 nul à 4 000.');

        $this->assertTrue($r['gain']['aucun_gain_ir'], 'IR déjà nul : aucun gain fictif.');
        $this->assertSame(0.0, $r['gain']['net_mensuel']);
        $this->assertSame(0.0, $r['gain']['net_annuel_indicatif']);
        $this->assertSame(0.0, $r['decomposition']['bareme']);
        $this->assertSame(0.0, $r['decomposition']['frais_pro']);
    }

    public function test_ir_positif_aucun_gain_ir_est_faux(): void
    {
        $r = $this->service()->comparer($this->saisie(20000));

        $this->assertGreaterThan(0, $r['reference']['ir_net']);
        $this->assertFalse($r['gain']['aucun_gain_ir']);
    }

    // ------------------------------------------------------------------
    // Source de la saisie (ROUGE : source absente)
    // ------------------------------------------------------------------

    public function test_une_saisie_2027_est_recalculee_en_2026_et_signalee_comme_source_2027(): void
    {
        $sans = $this->service()->comparer($this->saisie(20000));
        $depuis2027 = $this->service()->comparer($this->saisie(20000, ['exercice' => 2027]));

        $this->assertSame(['exercice' => 2026, 'mode' => 'gross_to_net'], $sans['source'], 'Sans exercice : repli 2026.');
        $this->assertSame(['exercice' => 2027, 'mode' => 'gross_to_net'], $depuis2027['source'], 'Exercice source signalé, jamais écrasé en silence.');

        $this->assertSame(2026, $depuis2027['reference']['exercice'], 'Référence toujours recalculée en 2026.');
        $this->assertSame(2027, $depuis2027['projete']['exercice']);
        $this->assertSame($sans['gain'], $depuis2027['gain'], 'Même saisie, même gain, quel que soit l\'exercice source.');
        $this->assertSame($sans['decomposition'], $depuis2027['decomposition']);
    }

    // ------------------------------------------------------------------
    // Refus explicites (non-régression)
    // ------------------------------------------------------------------

    public function test_une_categorie_non_rattachee_en_2027_est_refusee_sans_repli(): void
    {
        config(['payroll.exercices.2027.frais_pro.categories' => ['commun', 'journaliste']]);
        $message = __('ui.validation.category_not_supported', ['exercice' => 2027]);

        try {
            $this->service()->comparer($this->saisie(10000, ['type_frais_pro' => 'artiste']));
            $this->fail('Une catégorie non calculable en 2027 doit être refusée.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame($message, $e->getMessage());
        }
    }

    public function test_la_decomposition_refuse_un_exercice_non_calculable(): void
    {
        $message = __('ui.validation.exercise_not_calculable', ['exercice' => 2028]);

        try {
            app(PayrollCalculatorService::class)->decomposerChangementExercice($this->saisie(10000), 2026, 2028);
            $this->fail('2028 n\'a pas de frais professionnels : refus attendu.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame($message, $e->getMessage());
        }
    }

    public function test_le_codec_refuse_une_saisie_d_un_exercice_futur_non_calculable(): void
    {
        $codec = app(SimulationCodec::class);

        $this->assertNull($codec->decode($codec->encode($this->saisie(10000, ['exercice' => 2028]))));
        $this->assertNotNull($codec->decode($codec->encode($this->saisie(10000, ['exercice' => 2027]))));
    }

    // ------------------------------------------------------------------
    // Provenance, configuration, entrées (non-régression)
    // ------------------------------------------------------------------

    public function test_l_intermediaire_n_appartient_a_aucun_exercice(): void
    {
        $calculs = app(PayrollCalculatorService::class)->decomposerChangementExercice(
            $this->saisie(10000, ['exercice' => 2027]),
            2026,
            2027,
        );
        $intermediaire = $calculs['intermediaire'];

        $this->assertNull($intermediaire['exercice']);
        $this->assertNull($intermediaire['reference_fiscale']);
        $this->assertSame(['ir_baremes' => 2027, 'frais_pro' => 2026], $intermediaire['composition']);
        $this->assertArrayNotHasKey('exercice', $intermediaire['input']);

        $this->assertArrayNotHasKey('composition', $calculs['reference'], 'Résultats d\'exercice inchangés.');
        $this->assertArrayNotHasKey('composition', $calculs['projete']);
        $this->assertSame('en_vigueur', $calculs['reference']['reference_fiscale']['statut']);
        $this->assertSame('projet', $calculs['projete']['reference_fiscale']['statut']);
    }

    public function test_aucune_mutation_de_configuration_ni_du_calcul_2026(): void
    {
        $moteur = app(PayrollCalculatorService::class);
        $configAvant = config('payroll');
        $calcul2026Avant = $moteur->calculer($this->saisie(10000, ['exercice' => 2026]));
        $calculSansExerciceAvant = $moteur->calculer($this->saisie(10000));

        $this->service()->comparer($this->saisie(10000));

        $this->assertSame($configAvant, config('payroll'), 'config(payroll) inchangée après la comparaison.');
        $this->assertSame($calcul2026Avant, $moteur->calculer($this->saisie(10000, ['exercice' => 2026])));
        $this->assertSame($calculSansExerciceAvant, $moteur->calculer($this->saisie(10000)));
    }

    public function test_les_entrees_completes_sont_transmises_sans_duplication_ni_suppression(): void
    {
        $r = $this->service()->comparer(self::SAISIE_COMPLETE);
        $sansExercice = fn (array $input): array => array_diff_key($input, ['exercice' => true]);

        $this->assertSame(self::SAISIE_COMPLETE, $sansExercice($r['reference']['input']));
        $this->assertSame(self::SAISIE_COMPLETE, $sansExercice($r['projete']['input']));

        $identiques = [
            'sbi', 'salaire_brut_total', 'prime_anciennete', 'total_indemnites', 'excedent_indemnites',
            'cotisation_cnss', 'cotisation_amo', 'amo_taux_salarie', 'amo_taux_salarie_personnalise',
            'cotisation_cimr', 'cimr_taux', 'mutuelle_salarie', 'retenues_exonerees_ir', 'retenues_imposees_ir',
            'total_retenues', 'rc_deduite', 'nb_personnes', 'charges_famille', 'total_patronal',
            'cout_total_employeur', 'cout_employeur_partiel', 'mutuelle_patronale_inconnue', 'cimr_taux_employeur_inconnu',
        ];
        foreach ($identiques as $cle) {
            $this->assertSame($r['reference'][$cle], $r['projete'][$cle], "{$cle} : identique en 2026 et 2027 (brut et paramètres constants)");
        }

        // Prémisses : chaque entrée a bien un effet, elle n'a pas été perdue.
        $this->assertGreaterThan(0, $r['reference']['charges_famille']);
        $this->assertGreaterThan(0, $r['reference']['cotisation_cimr']);
        $this->assertTrue($r['reference']['amo_taux_salarie_personnalise']);
        $this->assertTrue($r['reference']['cout_employeur_partiel']);
        $this->assertGreaterThan(0, $r['reference']['total_indemnites']);
        $this->assertDecompositionExacte($r, 'saisie complète');
    }

    // ------------------------------------------------------------------
    // Net cible (ROUGE : mode refusé par la tranche 1)
    // ------------------------------------------------------------------

    /**
     * Espion du moteur réel : compte les résolutions net vers brut et
     * l'exercice demandé, sans rien changer au calcul.
     */
    private function moteurEspion(): PayrollCalculatorService
    {
        $espion = new class extends PayrollCalculatorService
        {
            /** @var list<mixed> */
            public array $exercicesResolus = [];

            public function resoudreDepuisNet(array $input): array
            {
                $this->exercicesResolus[] = $input['exercice'] ?? null;

                return parent::resoudreDepuisNet($input);
            }
        };

        $this->app->instance(PayrollCalculatorService::class, $espion);

        return $espion;
    }

    public function test_net_cible_resolu_une_fois_en_2026_puis_brut_fige(): void
    {
        $espion = $this->moteurEspion();
        $saisie = ['mode' => 'net_to_gross', 'net_cible' => 12000, 'type_frais_pro' => 'commun', 'nb_enfants' => 1];

        $r = app(GainFiscal2027::class)->comparer($saisie);

        $this->assertSame([2026], $espion->exercicesResolus, 'Une seule résolution, en 2026, jamais une par exercice.');
        $this->assertSame('net_to_gross', $r['mode']);
        $this->assertSame(['exercice' => 2026, 'mode' => 'net_to_gross'], $r['source']);

        $resolution = $r['resolution'];
        $this->assertSame(2026, $resolution['exercice']);
        $this->assertTrue($resolution['converge']);
        $this->assertSame(12000.0, (float) $resolution['net_cible']);
        $this->assertGreaterThan(0, $resolution['salaire_base']);

        $this->assertSame($resolution['salaire_base'], $r['reference']['input']['salaire_base'], 'Brut 2026 = brut résolu.');
        $this->assertSame($resolution['salaire_base'], $r['projete']['input']['salaire_base'], 'Brut 2027 figé, non résolu à nouveau.');
        $this->assertSame($r['reference']['sbi'], $r['projete']['sbi']);
        $this->assertEqualsWithDelta(12000, $r['reference']['salaire_net'], 0.01, 'Net 2026 = net cible au centime.');
        $this->assertSame(1, $r['reference']['input']['nb_enfants'], 'Autres entrées conservées.');
        $this->assertSame($r['reference']['charges_famille'], $r['projete']['charges_famille']);

        $this->assertGreaterThan(0, $r['gain']['net_mensuel'], 'Le net 2027 dépasse la cible à brut constant.');
        $this->assertDecompositionExacte($r, 'net cible');
    }

    public function test_net_cible_le_brut_fige_rejoue_le_meme_resultat_en_brut_vers_net(): void
    {
        $this->moteurEspion();
        $r = app(GainFiscal2027::class)->comparer(['mode' => 'net_to_gross', 'net_cible' => 9000, 'type_frais_pro' => 'commun']);

        $enBrut = app(GainFiscal2027::class)->comparer($this->saisie($r['resolution']['salaire_base']));

        $this->assertSame($enBrut['reference']['salaire_net'], $r['reference']['salaire_net']);
        $this->assertSame($enBrut['projete']['salaire_net'], $r['projete']['salaire_net']);
        $this->assertSame($enBrut['gain'], $r['gain']);
        $this->assertSame($enBrut['decomposition'], $r['decomposition']);
    }

    public function test_net_cible_d_une_saisie_2027_est_refuse_avant_toute_resolution(): void
    {
        $espion = $this->moteurEspion();
        $message = __('ui.gain2027.net_source_2027_refused');
        $this->assertNotSame('ui.gain2027.net_source_2027_refused', $message, 'Message traduit attendu.');

        try {
            app(GainFiscal2027::class)->comparer(['mode' => 'net_to_gross', 'net_cible' => 12000, 'type_frais_pro' => 'commun', 'exercice' => 2027]);
            $this->fail('Un net cible 2027 ne doit pas être résolu en 2026 en silence.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame($message, $e->getMessage());
        }

        $this->assertSame([], $espion->exercicesResolus, 'Refus avant toute résolution.');
    }
}
