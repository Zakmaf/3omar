<?php

namespace Tests\Unit;

use App\Http\Requests\PayrollValidation;
use App\Services\PayrollCalculatorService;
use App\Services\SimulationProfileService;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SimulationProfileServiceTest extends TestCase
{
    private SimulationProfileService $profiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profiles = app(SimulationProfileService::class);
    }

    public function test_tous_les_profils_annonces_sont_disponibles(): void
    {
        $slugs = array_keys($this->profiles->all());

        $this->assertSame(['smig', 'standard', 'cadre', 'primes', 'cimr', 'journaliste'], $slugs);
    }

    public function test_un_profil_inconnu_est_ignore(): void
    {
        $this->assertNull($this->profiles->find('profil-inexistant'));
        $this->assertNull($this->profiles->find(null));
        $this->assertFalse($this->profiles->exists('profil-inexistant'));
        $this->assertTrue($this->profiles->exists('cadre'));
    }

    public function test_chaque_profil_expose_un_libelle_traduit_et_une_icone(): void
    {
        foreach ($this->profiles->all() as $slug => $profile) {
            $this->assertSame($slug, $profile['slug']);
            $this->assertStringStartsWith('bi-', $profile['icon'], $slug);

            foreach (array_keys(config('app.supported_locales')) as $locale) {
                foreach (['label', 'text'] as $key) {
                    $cle = "ui.calculator.profiles.{$slug}.{$key}";
                    $this->assertNotSame($cle, __($cle, [], $locale), "{$cle} manquant en {$locale}");
                }
            }
        }
    }

    /**
     * Un profil ne doit jamais produire une saisie que le formulaire refuserait.
     */
    public function test_chaque_profil_passe_la_validation_du_formulaire(): void
    {
        foreach ($this->profiles->all() as $slug => $profile) {
            $validator = Validator::make($profile['input'], PayrollValidation::webRules('gross_to_net'));

            $this->assertFalse($validator->fails(), "Profil {$slug} invalide : ".$validator->errors()->first());
        }
    }

    /**
     * Le sélecteur d'ancienneté du formulaire ne propose que les débuts de
     * tranche. Un profil qui porterait une autre valeur serait silencieusement
     * réaffiché à zéro : la simulation chargée ne correspondrait plus au profil.
     */
    public function test_l_anciennete_des_profils_est_selectionnable_dans_le_formulaire(): void
    {
        $anneesSelectionnables = array_merge(
            [0],
            array_column(config('payroll.anciennete.tranches'), 'min_annees'),
        );

        foreach ($this->profiles->all() as $slug => $profile) {
            $annees = (int) ($profile['input']['nb_annees_anciennete'] ?? 0);

            $this->assertContains($annees, $anneesSelectionnables, "Profil {$slug} : ancienneté {$annees} absente du sélecteur.");
        }
    }

    public function test_chaque_profil_produit_un_calcul_exploitable(): void
    {
        $calculator = app(PayrollCalculatorService::class);

        foreach ($this->profiles->all() as $slug => $profile) {
            $r = $calculator->calculer($profile['input']);

            $this->assertGreaterThan(0, $r['salaire_net'], "Profil {$slug} : net non positif.");
            $this->assertGreaterThan($r['salaire_brut_total'], $r['cout_total_employeur'], "Profil {$slug} : coût employeur incohérent.");
        }
    }

    /**
     * Le profil SMIG doit lire le salaire minimum dans config/payroll.php et non
     * le redéclarer : aucun avertissement « inférieur au SMIG » ne doit tomber.
     */
    public function test_le_profil_smig_suit_la_configuration_reglementaire(): void
    {
        $input = $this->profiles->find('smig')['input'];

        $this->assertSame((float) config('payroll.smig.mensuel'), $input['salaire_base']);

        $r = app(PayrollCalculatorService::class)->calculer($input);

        $this->assertEmpty($r['avertissements']);
    }

    /**
     * Même logique pour l'indemnité de transport du profil « avec primes » :
     * elle est calée sur le plafond configuré, donc entièrement exonérée.
     */
    public function test_le_profil_primes_reste_dans_le_plafond_d_indemnite_configure(): void
    {
        $input = $this->profiles->find('primes')['input'];

        $this->assertSame(
            (float) config('payroll.indemnites.transport.montant'),
            $input['indemnites'][0]['montant'],
        );

        $r = app(PayrollCalculatorService::class)->calculer($input);

        $this->assertSame(0.0, $r['excedent_indemnites']);
        $this->assertEmpty($r['avertissements']);
    }

    public function test_le_profil_journaliste_active_les_frais_pro_majores(): void
    {
        $input = $this->profiles->find('journaliste')['input'];

        $this->assertSame('journaliste', $input['type_frais_pro']);

        $r = app(PayrollCalculatorService::class)->calculer($input);

        $this->assertSame(config('payroll.frais_pro.journaliste.taux'), $r['taux_fp']);
    }

    public function test_le_profil_cimr_partage_la_cotisation(): void
    {
        $r = app(PayrollCalculatorService::class)->calculer($this->profiles->find('cimr')['input']);

        $this->assertGreaterThan(0, $r['cotisation_cimr']);
        $this->assertGreaterThan(0, $r['cotisation_cimr_patronale']);
    }
}
