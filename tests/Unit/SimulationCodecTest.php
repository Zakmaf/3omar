<?php

namespace Tests\Unit;

use App\Services\PayrollCalculatorService;
use App\Services\SimulationCodec;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SimulationCodecTest extends TestCase
{
    private SimulationCodec $codec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->codec = app(SimulationCodec::class);
    }

    public function test_une_simulation_encodee_est_restituee_a_l_identique(): void
    {
        $input = [
            'mode' => 'gross_to_net',
            'salaire_base' => 12000,
            'type_frais_pro' => 'commun',
            'nb_annees_anciennete' => 8,
            'cimr_taux' => 6,
            'nb_enfants' => 2,
            'conjoint_charge' => true,
        ];

        $decoded = $this->codec->decode($this->codec->encode($input));

        $this->assertNotNull($decoded);
        $this->assertSame('gross_to_net', $decoded['mode']);
        $this->assertEquals(12000, $decoded['salaire_base']);
        $this->assertSame('commun', $decoded['type_frais_pro']);
        $this->assertEquals(8, $decoded['nb_annees_anciennete']);
        $this->assertEquals(6, $decoded['cimr_taux']);
        $this->assertEquals(2, $decoded['nb_enfants']);
    }

    public function test_les_lignes_repetables_survivent_a_l_encodage(): void
    {
        $input = [
            'mode' => 'gross_to_net',
            'salaire_base' => 9000,
            'type_frais_pro' => 'commun',
            'indemnites' => [
                ['type' => 'transport', 'montant' => 500],
                ['type' => 'panier', 'montant' => 900],
            ],
            'heures_sup' => [
                ['type' => 'semaine_diurne', 'nb_heures' => 10],
            ],
        ];

        $decoded = $this->codec->decode($this->codec->encode($input));

        $this->assertNotNull($decoded);
        $this->assertCount(2, $decoded['indemnites']);
        $this->assertSame('transport', $decoded['indemnites'][0]['type']);
        $this->assertSame('panier', $decoded['indemnites'][1]['type']);
        $this->assertCount(1, $decoded['heures_sup']);
        $this->assertSame('semaine_diurne', $decoded['heures_sup'][0]['type']);
    }

    public function test_le_mode_net_vers_brut_est_conserve(): void
    {
        $decoded = $this->codec->decode($this->codec->encode([
            'mode' => 'net_to_gross',
            'net_cible' => 8000,
            'type_frais_pro' => 'commun',
        ]));

        $this->assertNotNull($decoded);
        $this->assertSame('net_to_gross', $decoded['mode']);
        $this->assertEquals(8000, $decoded['net_cible']);
    }

    public function test_les_champs_inconnus_sont_ecartes(): void
    {
        $encoded = $this->codec->encode([
            'mode' => 'gross_to_net',
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
            'champ_pirate' => 'valeur',
        ]);

        $decoded = $this->codec->decode($encoded);

        $this->assertNotNull($decoded);
        $this->assertArrayNotHasKey('champ_pirate', $decoded);
    }

    /**
     * Un payload fabriqué à la main ne doit pas contourner la validation du
     * formulaire : ici un salaire négatif, refusé par les règles existantes.
     */
    public function test_un_payload_qui_viole_la_validation_est_refuse(): void
    {
        $payload = $this->forgePayload(['mode' => 'gross_to_net', 'salaire_base' => -500, 'type_frais_pro' => 'commun']);

        $this->assertNull($this->codec->decode($payload));
    }

    public function test_une_categorie_professionnelle_inconnue_est_refusee(): void
    {
        $payload = $this->forgePayload(['mode' => 'gross_to_net', 'salaire_base' => 10000, 'type_frais_pro' => 'pirate']);

        $this->assertNull($this->codec->decode($payload));
    }

    public function test_un_type_d_indemnite_inconnu_est_refuse(): void
    {
        $payload = $this->forgePayload([
            'mode' => 'gross_to_net',
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
            'indemnites' => [['type' => 'indemnite_inventee', 'montant' => 5000]],
        ]);

        $this->assertNull($this->codec->decode($payload));
    }

    public function test_une_version_de_format_inconnue_est_refusee(): void
    {
        $json = json_encode(['v' => 99, 'i' => ['salaire_base' => 10000, 'type_frais_pro' => 'commun']]);
        $payload = rtrim(strtr(base64_encode(gzdeflate($json)), '+/', '-_'), '=');

        $this->assertNull($this->codec->decode($payload));
    }

    #[DataProvider('payloadsIllisibles')]
    public function test_les_payloads_illisibles_sont_refuses(?string $payload): void
    {
        $this->assertNull($this->codec->decode($payload));
    }

    /**
     * Un payload qui se décompresse en un flux plus grand que
     * SimulationCodec::MAX_INFLATED_BYTES doit être refusé (protection contre
     * les bombes zlib). Le padding est placé dans un champ non transportable :
     * s'il survivait à la garde de taille, il serait de toute façon écarté par
     * validated(), et le reste du payload (mode/salaire_base/type_frais_pro)
     * est par ailleurs valide, pour isoler cette garde des autres refus
     * possibles (validation, version, format).
     *
     * gzinflate() traite son deuxième paramètre comme une taille de tampon
     * indicative et pas comme une coupure exacte : au voisinage immédiat de
     * la limite, PHP agrandit le tampon et laisse encore passer quelques
     * kilo-octets. Le padding vise donc un multiple large de la limite pour
     * dépasser franchement cette marge interne, tout en restant très
     * compressible (répétition d'un seul caractère) afin que le payload
     * encodé reste bien en-deçà de MAX_PAYLOAD_LENGTH.
     */
    public function test_un_payload_dont_la_taille_decompressee_depasse_la_limite_est_refuse(): void
    {
        $maxInflatedBytes = (new \ReflectionClass(SimulationCodec::class))->getConstant('MAX_INFLATED_BYTES');

        $payload = $this->forgePayload([
            'mode' => 'gross_to_net',
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
            'champ_pirate' => str_repeat('a', $maxInflatedBytes * 3),
        ]);

        $this->assertNull($this->codec->decode($payload));
    }

    /**
     * Un drapeau « inconnu » doit survivre au lien (issue #274) : sans lui, un
     * coût employeur partiel redeviendrait complet après rechargement, en
     * comptant un montant patronal que l'utilisateur a déclaré non connu.
     * Le montant ou taux patronal saisi est non nul pour que sa neutralisation
     * soit observable sur le coût.
     */
    #[DataProvider('postesPatronauxInconnus')]
    public function test_un_drapeau_inconnu_survit_au_lien_et_garde_le_cout_partiel(
        string $champ,
        int|float $valeur,
        string $posteSortie,
        string $drapeauSortie,
    ): void {
        $drapeau = $champ.'_inconnu';
        $saisie = $this->saisiePatronale([$champ => $valeur, $drapeau => '1']);

        $decoded = $this->codec->decode($this->codec->encode($saisie));

        $this->assertNotNull($decoded, "Le lien portant {$drapeau} doit se décoder.");
        $this->assertArrayHasKey($drapeau, $decoded, "{$drapeau} doit voyager dans le lien.");
        $this->assertTrue((bool) $decoded[$drapeau], "{$drapeau} doit rester actif après décodage.");
        $this->assertEquals($valeur, $decoded[$champ], "La valeur saisie de {$champ} doit voyager avec son drapeau.");

        $calculateur = app(PayrollCalculatorService::class);
        $avant = $calculateur->calculer($saisie);
        $apres = $calculateur->calculer($decoded);
        $sansPoste = $calculateur->calculer($this->saisiePatronale());

        $this->assertTrue($avant['cout_employeur_partiel'], "Avant transport, {$drapeau} doit rendre le coût partiel.");
        $this->assertTrue($apres['cout_employeur_partiel'], "Après transport, {$drapeau} doit garder le coût partiel.");
        $this->assertTrue($apres[$drapeauSortie], "Le moteur doit exposer {$drapeauSortie} après transport.");
        $this->assertSame(0.0, $apres[$posteSortie], "Le poste {$posteSortie} doit rester neutralisé après transport.");
        $this->assertSame($avant['total_patronal'], $apres['total_patronal'], "Le total patronal doit être identique avant et après transport ({$champ}).");
        $this->assertSame($avant['cout_total_employeur'], $apres['cout_total_employeur'], "Le coût employeur doit être identique avant et après transport ({$champ}).");
        $this->assertSame($sansPoste['cout_total_employeur'], $apres['cout_total_employeur'], "Le montant patronal inconnu de {$champ} ne doit pas entrer dans le coût.");
    }

    /**
     * Cas témoin : le même poste, connu et sans drapeau, contribue au coût
     * employeur après transport. Il garantit que le cas « inconnu » ci-dessus
     * teste bien une neutralisation et pas un poste sans effet sur le coût.
     */
    #[DataProvider('postesPatronauxInconnus')]
    public function test_un_poste_patronal_connu_transporte_contribue_au_cout(
        string $champ,
        int|float $valeur,
        string $posteSortie,
        string $drapeauSortie,
    ): void {
        $saisie = $this->saisiePatronale([$champ => $valeur]);

        $decoded = $this->codec->decode($this->codec->encode($saisie));

        $this->assertNotNull($decoded, "Le lien portant {$champ} doit se décoder.");
        $this->assertArrayNotHasKey($champ.'_inconnu', $decoded, "Aucun drapeau ne doit apparaître pour {$champ} connu.");
        $this->assertEquals($valeur, $decoded[$champ], "La valeur saisie de {$champ} doit voyager dans le lien.");

        $calculateur = app(PayrollCalculatorService::class);
        $avant = $calculateur->calculer($saisie);
        $apres = $calculateur->calculer($decoded);
        $sansPoste = $calculateur->calculer($this->saisiePatronale());

        $this->assertFalse($apres['cout_employeur_partiel'], "Un {$champ} connu ne doit pas rendre le coût partiel.");
        $this->assertFalse($apres[$drapeauSortie], "{$drapeauSortie} doit rester faux pour un poste connu.");
        $this->assertGreaterThan(0.0, $apres[$posteSortie], "Le poste {$posteSortie} connu doit être non nul.");
        $this->assertSame($avant['cout_total_employeur'], $apres['cout_total_employeur'], "Le coût employeur doit être identique avant et après transport ({$champ}).");
        $this->assertEqualsWithDelta(
            $sansPoste['cout_total_employeur'] + $apres[$posteSortie],
            $apres['cout_total_employeur'],
            0.005,
            "Le poste {$posteSortie} connu doit s'ajouter au coût employeur.",
        );
    }

    /**
     * Les cinq postes patronaux qui acceptent un drapeau « inconnu » : champ
     * d'entrée, valeur saisie non nulle (montant en MAD ou taux en %), puis les
     * noms de sortie du moteur, qui ne reprennent pas toujours le nom d'entrée.
     * Les valeurs sont des saisies utilisateur, pas des paramètres réglementaires.
     */
    public static function postesPatronauxInconnus(): array
    {
        return [
            'mutuelle patronale' => ['mutuelle_patronale', 300, 'mutuelle_patronale', 'mutuelle_patronale_inconnue'],
            'retraite complementaire employeur' => ['rc_part_employeur', 400, 'rc_part_employeur', 'rc_part_employeur_inconnu'],
            'taux CIMR employeur' => ['cimr_taux_employeur', 6, 'cotisation_cimr_patronale', 'cimr_taux_employeur_inconnu'],
            'taux assurance AT' => ['assurance_at_taux', 2, 'assurance_at', 'assurance_at_inconnue'],
            'assurance RC pro' => ['assurance_rc_pro', 150, 'assurance_rc_pro', 'assurance_rc_pro_inconnue'],
        ];
    }

    private function saisiePatronale(array $complements = []): array
    {
        return array_merge([
            'mode' => 'gross_to_net',
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
        ], $complements);
    }

    public static function payloadsIllisibles(): array
    {
        return [
            'null' => [null],
            'chaine vide' => [''],
            'caracteres interdits' => ['pas!du#base64'],
            'base64 valide mais non compresse' => [rtrim(strtr(base64_encode('bonjour'), '+/', '-_'), '=')],
            'trop long' => [str_repeat('a', SimulationCodec::MAX_PAYLOAD_LENGTH + 1)],
        ];
    }

    /**
     * Construit un payload valide au niveau du format mais dont les entrées
     * doivent être rejetées par la validation.
     */
    private function forgePayload(array $input): string
    {
        $json = json_encode(['v' => 1, 'i' => $input]);

        return rtrim(strtr(base64_encode(gzdeflate($json)), '+/', '-_'), '=');
    }
}
