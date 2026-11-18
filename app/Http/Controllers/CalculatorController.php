<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayrollValidation;
use App\Services\ExercicesFiscaux;
use App\Services\GainFiscal2027;
use App\Services\PayrollCalculatorService;
use App\Services\RetourNavigation;
use App\Services\SimulationCodec;
use App\Services\SimulationComparator;
use App\Services\SimulationProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CalculatorController extends Controller
{
    public function __construct(
        private PayrollCalculatorService $calculator,
        private SimulationCodec $codec,
        private SimulationComparator $comparator,
        private SimulationProfileService $profiles,
        private ExercicesFiscaux $exercices,
        private GainFiscal2027 $gain2027,
        private RetourNavigation $navigation,
    ) {}

    public function index(Request $request)
    {
        $prefill = null;
        $profileLoaded = null;
        $restored = false;
        $sBranchTaken = false;

        // Profil prêt à l'emploi (issue #51) ou simulation reprise depuis une URL
        // partagée (issue #50). Un profil explicite est prioritaire sur un payload.
        $profilQuery = $this->stringQuery($request, 'profil');
        $sQuery = $this->stringQuery($request, 's');
        $aQuery = $this->stringQuery($request, 'a');

        if ($this->profiles->exists($profilQuery)) {
            $profileLoaded = $profilQuery;
            $prefill = $this->profiles->find($profileLoaded)['input'];
        } elseif ($request->filled('s')) {
            $sBranchTaken = true;
            $prefill = $this->codec->decode($sQuery);
            $restored = $prefill !== null;
        }

        // Scénario mémorisé pour une comparaison (issue #47) : il reste dans un
        // champ caché du formulaire tant que l'utilisateur construit le scénario B.
        $comparisonA = $request->filled('a') && $this->codec->decode($aQuery) !== null
            ? $aQuery
            : null;

        // Le formulaire lit ses valeurs via old() : on injecte le préremplissage
        // dans l'ancien input, uniquement pour cette requête (session()->now).
        // Un retour d'erreur de validation reste prioritaire sur le préremplissage.
        $injected = $prefill !== null && ! $request->session()->hasOldInput();

        if ($injected) {
            $request->session()->now('_old_input', $prefill);
        }

        // $restored reste un signal pur de succès de décodage (utilisé par
        // share_payload_invalid, indépendant du fait que l'injection ait eu
        // lieu). Les bannières de préremplissage, elles, ne s'affichent que
        // si l'injection a effectivement eu lieu (sinon l'ancien input a
        // gagné et rien n'a réellement été chargé dans le formulaire).
        return view('calculator.index', [
            'indemnites_config' => config('payroll.indemnites'),
            'hs_labels' => config('payroll.heures_sup.labels'),
            'profiles' => $this->profiles->all(),
            'profile_loaded' => $injected ? $profileLoaded : null,
            'simulation_restored' => $injected && $restored,
            'share_payload_invalid' => $sBranchTaken && ! $restored,
            'comparison_a' => $comparisonA,
            // Cible de retour d'une erreur de validation, propre à ce formulaire (#295).
            'retour_formulaire' => $this->navigation->formulaire($request),
            'exercices' => $this->choixExercices(),
            'exercice_defaut' => $this->exercices->defaut(),
        ]);
    }

    /**
     * Exercices proposés par le formulaire (#281), dans l'ordre de la
     * configuration. Seul un profil calculable est activable ; le statut est
     * celui du profil, même s'il devient calculable.
     *
     * @return list<array{exercice: int, statut: string, calculable: bool}>
     */
    private function choixExercices(): array
    {
        return array_map(fn (int $annee): array => [
            'exercice' => $annee,
            'statut' => (string) $this->exercices->referenceFiscale($annee)['statut'],
            'calculable' => $this->exercices->calculable($annee),
        ], $this->exercices->declares());
    }

    public function resultatIndisponible()
    {
        return redirect()
            ->route('calculator.index')
            ->with('calculator_notice', 'ui.calculator.result_direct_access_notice');
    }

    public function calculer(Request $request)
    {
        $mode = $request->input('mode', 'gross_to_net');

        // Retour d'erreur vers le formulaire d'origine (#295) : sa cible postée,
        // revalidée, jamais la dernière URL de la session partagée entre onglets.
        $retour = $this->navigation->retourFormulaire(
            $request->input(RetourNavigation::PARAMETRE),
            $request->headers->get('referer'),
            $request->getHost(),
        );

        $validator = Validator::make(
            $request->all(),
            PayrollValidation::webRules($mode),
            PayrollValidation::webMessages(),
        );

        if ($validator->fails()) {
            return redirect()->to($retour)->withInput()->withErrors($validator);
        }

        $input = $request->only(PayrollValidation::webFields());

        // Un scénario A mémorisé transforme ce calcul en comparaison directe.
        $scenarioA = $this->codec->decode($this->stringValue($request->input('comparer_a')));

        try {
            if ($scenarioA !== null) {
                // Page obtenue par POST : la langue revient à son URL rejouable.
                return $this->rendreComparaison($scenarioA, $input)->with('retour_langue', route('calculator.comparer', [
                    'a' => $this->codec->encode($scenarioA),
                    'b' => $this->codec->encode($input),
                ], false));
            }

            $result = $this->executer($input, $mode);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->to($retour)
                ->withInput()
                ->withErrors(['calculator' => $e->getMessage()]);
        }

        $sharePayload = $this->codec->encode($input);

        return view('calculator.result', [
            'r' => $result,
            'indemnites_config' => config('payroll.indemnites'),
            'hs_labels' => config('payroll.heures_sup.labels'),
            'share_payload' => $sharePayload,
            // Refus certain de la page de gain 2027 : notice au lieu du lien.
            'gain2027_refus' => $this->gain2027->motifRefus($input, $result),
            // Page obtenue par POST, non rejouable : la langue recharge le
            // calculateur avec la saisie, par son lien partagé (#295).
            'retour_langue' => route('calculator.index', ['s' => $sharePayload], false),
        ]);
    }

    /**
     * Comparaison de deux scénarios portés par l'URL (issue #47). L'URL est donc
     * partageable et rejouable, sans stockage serveur.
     */
    public function comparer(Request $request)
    {
        $scenarioA = $this->codec->decode($this->stringQuery($request, 'a'));
        $scenarioB = $this->codec->decode($this->stringQuery($request, 'b'));

        if ($scenarioA === null || $scenarioB === null) {
            return redirect()
                ->route('calculator.index')
                ->with('calculator_notice', 'ui.calculator.comparison_invalid_notice');
        }

        try {
            return $this->rendreComparaison($scenarioA, $scenarioB);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('calculator.index')
                ->withErrors(['calculator' => $e->getMessage()]);
        }
    }

    /**
     * Gain de salaire net en janvier 2027 face à 2026 (issue #283) pour la
     * saisie portée par l'URL : rejouable et partageable, sans stockage serveur.
     * Le payload est revalidé par le codec ; tout refus revient au calculateur
     * avec un message explicite, jamais un calcul de repli.
     */
    public function gain2027(Request $request)
    {
        $saisie = $this->codec->decode($this->stringQuery($request, 's'));

        if ($saisie === null) {
            return redirect()
                ->route('calculator.index')
                ->with('calculator_notice', 'ui.calculator.gain2027_invalid_notice');
        }

        // Refus d'une saisie valide : retour au formulaire prérempli avec cette
        // même saisie (?s=), pour la corriger sans la ressaisir.
        $retour = route('calculator.index', ['s' => $this->codec->encode($saisie)]);

        if ($this->gain2027->sourceNetRefusee($saisie)) {
            return redirect($retour)
                ->with('calculator_notice', GainFiscal2027::REFUS_NET_SOURCE_2027);
        }

        try {
            $gain = $this->gain2027->comparer($saisie);
        } catch (\InvalidArgumentException $e) {
            return redirect($retour)
                ->withErrors(['calculator' => $e->getMessage()]);
        }

        return view('calculator.gain2027', [
            'gain' => $gain,
            'share_payload' => $this->codec->encode($saisie),
        ]);
    }

    private function rendreComparaison(array $scenarioA, array $scenarioB)
    {
        $resultatA = $this->executer($scenarioA, $scenarioA['mode'] ?? 'gross_to_net');
        $resultatB = $this->executer($scenarioB, $scenarioB['mode'] ?? 'gross_to_net');

        return view('calculator.comparison', [
            'a' => $resultatA,
            'b' => $resultatB,
            'metrics' => $this->comparator->metrics($resultatA, $resultatB),
            'highlights' => $this->comparator->highlights($resultatA, $resultatB),
            'differences' => $this->comparator->inputDifferences($scenarioA, $scenarioB),
            'payload_a' => $this->codec->encode($scenarioA),
            'payload_b' => $this->codec->encode($scenarioB),
        ]);
    }

    private function executer(array $input, string $mode): array
    {
        if ($mode === 'net_to_gross') {
            return $this->calculator->resoudreDepuisNet($input);
        }

        $result = $this->calculator->calculer($input);
        $result['mode'] = 'gross_to_net';

        return $result;
    }

    /**
     * Lit un paramètre de requête (query string) en le traitant comme absent
     * s'il n'est pas une chaîne scalaire (issue #152 : ?profil[]=x, ?s[]=x...).
     */
    private function stringQuery(Request $request, string $key): ?string
    {
        return $this->stringValue($request->query($key));
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
