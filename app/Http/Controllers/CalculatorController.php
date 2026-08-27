<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayrollValidation;
use App\Services\PayrollCalculatorService;
use App\Services\SimulationCodec;
use App\Services\SimulationComparator;
use App\Services\SimulationProfileService;
use Illuminate\Http\Request;

class CalculatorController extends Controller
{
    public function __construct(
        private PayrollCalculatorService $calculator,
        private SimulationCodec $codec,
        private SimulationComparator $comparator,
        private SimulationProfileService $profiles,
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
        ]);
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

        $request->validate(
            PayrollValidation::webRules($mode),
            PayrollValidation::webMessages(),
        );

        $input = $request->only(PayrollValidation::webFields());

        // Un scénario A mémorisé transforme ce calcul en comparaison directe.
        $scenarioA = $this->codec->decode($this->stringValue($request->input('comparer_a')));

        try {
            if ($scenarioA !== null) {
                return $this->rendreComparaison($scenarioA, $input);
            }

            $result = $this->executer($input, $mode);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('calculator.index')
                ->withInput()
                ->withErrors(['calculator' => $e->getMessage()]);
        }

        return view('calculator.result', [
            'r' => $result,
            'indemnites_config' => config('payroll.indemnites'),
            'hs_labels' => config('payroll.heures_sup.labels'),
            'share_payload' => $this->codec->encode($input),
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
