<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Cibles de retour propres à la page d'origine (issue #295).
 *
 * Sous Referrer-Policy: no-referrer (#283), la dernière URL de la session
 * (_previous.url) est partagée par tous les onglets : une page ouverte dans un
 * autre onglet détournerait le retour. La page d'origine transmet donc
 * elle-même sa cible : champ caché du formulaire du calculateur, paramètre
 * des liens de langue.
 *
 * Une cible fournie par le client n'est jamais renvoyée telle quelle : elle
 * doit être un chemin relatif interne, désigner une route GET rejouable de la
 * liste ci-dessous, et l'URL de retour est reconstruite par route() avec les
 * seuls paramètres utiles de cette route, sous forme de chaînes au format des
 * payloads. Tout le reste est écarté au profit d'un repli sûr.
 */
class RetourNavigation
{
    /** Nom du champ caché du formulaire et du paramètre des liens de langue. */
    public const PARAMETRE = 'retour';

    /** Longueur maximale d'une cible, large devant celle d'un payload réel. */
    private const LONGUEUR_MAX = 8192;

    /** Valeur admise pour un paramètre conservé : alphabet base64url des payloads et slugs de profil. */
    private const VALEUR_PARAMETRE = '/^[A-Za-z0-9_-]{1,4096}$/D';

    /**
     * Routes GET rejouables et paramètres de requête conservés pour chacune.
     * Les guides (guides.*) sont ajoutés sans paramètre. Sont exclus, entre
     * autres : le changement de langue (boucle), l'accès direct au résultat
     * (calculator.calculer.unavailable, page POST), l'API et la sonde /up.
     */
    private const ROUTES_REJOUABLES = [
        'home' => [],
        'calculator.index' => ['s', 'a', 'profil'],
        'calculator.comparer' => ['a', 'b'],
        'calculator.gain2027' => ['s'],
        'documentation' => [],
        'api.documentation' => [],
        'trust' => [],
    ];

    /** Cible du formulaire du calculateur affiché par cette requête. */
    public function formulaire(Request $request): string
    {
        return $this->reconstruire('calculator.index', $request->query());
    }

    /**
     * Retour après une erreur de validation : la cible postée par le
     * formulaire, à défaut le référent du même hôte (client sans le champ),
     * sinon le formulaire vide. Jamais la session.
     */
    public function retourFormulaire(mixed $retour, ?string $referent, string $hote): string
    {
        foreach ([$retour, $this->cheminDuReferent($referent, $hote)] as $candidat) {
            $cible = $this->resoudre($candidat);

            if ($cible !== null && $cible['route'] === 'calculator.index') {
                return $cible['url'];
            }
        }

        return route('calculator.index', [], false);
    }

    /** Cible d'un lien de langue, ou null si elle est refusée. */
    public function cibleLangue(mixed $retour): ?string
    {
        return $this->resoudre($retour)['url'] ?? null;
    }

    /** Cible de retour de la page GET courante, l'accueil à défaut. */
    public function pageCourante(Request $request): string
    {
        $cible = $request->isMethod('GET') ? $this->resoudre($request->getRequestUri()) : null;

        return $cible['url'] ?? route('home', [], false);
    }

    /**
     * @return array{route: string, url: string}|null
     */
    private function resoudre(mixed $valeur): ?array
    {
        if (! is_string($valeur) || $valeur === '' || strlen($valeur) > self::LONGUEUR_MAX) {
            return null;
        }

        // Chemin relatif interne strict, y compris une fois décodé (encodages
        // multiples) : un seul « / » initial, ni antislash, ni espace, ni
        // caractère de contrôle.
        foreach ([$valeur, $this->decoder($valeur)] as $forme) {
            if (! str_starts_with($forme, '/') || str_starts_with($forme, '//') || preg_match('/[\x00-\x20\x7F\\\\]/', $forme)) {
                return null;
            }
        }

        $parties = parse_url($valeur);

        if ($parties === false || array_intersect_key($parties, array_flip(['scheme', 'host', 'port', 'user', 'pass', 'fragment'])) !== []) {
            return null;
        }

        // matches() plutôt que match() : aucune liaison de paramètres sur les
        // objets route partagés, dont celui de la requête en cours.
        $requete = Request::create($parties['path'] ?? '/', 'GET');
        $route = collect(Route::getRoutes()->get('GET'))->first(fn ($candidate) => $candidate->matches($requete));

        if ($route === null) {
            return null;
        }

        $nom = (string) $route->getName();

        if ($this->parametresConserves($nom) === null) {
            return null;
        }

        parse_str($parties['query'] ?? '', $query);

        return ['route' => $nom, 'url' => $this->reconstruire($nom, $query)];
    }

    /** URL relative de la route avec ses seuls paramètres conservés et valides. */
    private function reconstruire(string $nom, array $query): string
    {
        $parametres = [];

        foreach ($this->parametresConserves($nom) ?? [] as $cle) {
            $valeur = $query[$cle] ?? null;

            if (is_string($valeur) && preg_match(self::VALEUR_PARAMETRE, $valeur)) {
                $parametres[$cle] = $valeur;
            }
        }

        return route($nom, $parametres, false);
    }

    /**
     * @return list<string>|null
     */
    private function parametresConserves(string $nom): ?array
    {
        if (array_key_exists($nom, self::ROUTES_REJOUABLES)) {
            return self::ROUTES_REJOUABLES[$nom];
        }

        return str_starts_with($nom, 'guides.') ? [] : null;
    }

    private function decoder(string $valeur): string
    {
        for ($i = 0; $i < 5; $i++) {
            $decodee = rawurldecode($valeur);

            if ($decodee === $valeur) {
                break;
            }

            $valeur = $decodee;
        }

        return $valeur;
    }

    /**
     * Chemin et requête d'un référent du même hôte, null sinon. Un référent
     * déjà relatif (chemin commençant par « / », admis par le framework) est
     * rendu tel quel : resoudre() le revalide entièrement (« // », antislash,
     * contrôles, encodages, schéma, identifiants, route permise).
     */
    private function cheminDuReferent(?string $referent, string $hote): ?string
    {
        if ($referent === null || $referent === '') {
            return null;
        }

        if (str_starts_with($referent, '/')) {
            return $referent;
        }

        $parties = parse_url($referent);

        if ($parties === false || ($parties['host'] ?? null) !== $hote || isset($parties['user']) || isset($parties['pass'])) {
            return null;
        }

        return ($parties['path'] ?? '/').(isset($parties['query']) ? '?'.$parties['query'] : '');
    }
}
