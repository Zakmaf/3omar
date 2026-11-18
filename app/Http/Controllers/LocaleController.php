<?php

namespace App\Http\Controllers;

use App\Services\RetourNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request, string $locale, RetourNavigation $navigation): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('app.supported_locales')), 404);

        $request->session()->put('locale', $locale);

        // Lien rendu par le sélecteur (#295) : il porte la cible de sa page.
        // Elle seule compte, revalidée ; refusée, repli sur l'accueil. La
        // session, partagée entre onglets, n'est jamais consultée.
        if ($request->query->has(RetourNavigation::PARAMETRE)) {
            return redirect()->to(
                $navigation->cibleLangue($request->query(RetourNavigation::PARAMETRE)) ?? route('home'),
            );
        }

        // Lien historique sans cible (/lang/xx) : page précédente (référent,
        // sinon session), retenue seulement si elle est de même origine, puis
        // réduite à son chemin et revalidée comme une cible explicite. Sinon
        // l'accueil.
        $chemin = $this->cheminDeMemeOrigine(url()->previous(route('home')), $request);

        return redirect()->to(
            ($chemin !== null ? $navigation->cibleLangue($chemin) : null) ?? route('home'),
        );
    }

    /**
     * Chemin et requête d'une URL de même origine que la requête (schéma
     * http(s), hôte et port, ports par défaut 80 et 443), sans identifiants.
     * Null pour toute autre URL.
     */
    private function cheminDeMemeOrigine(string $url, Request $request): ?string
    {
        $parties = parse_url($url);

        if ($parties === false || isset($parties['user']) || isset($parties['pass'])) {
            return null;
        }

        $schema = strtolower($parties['scheme'] ?? '');

        if (! in_array($schema, ['http', 'https'], true)) {
            return null;
        }

        $port = $parties['port'] ?? ($schema === 'https' ? 443 : 80);

        if ($schema !== $request->getScheme()
            || strtolower($parties['host'] ?? '') !== strtolower($request->getHost())
            || $port !== (int) $request->getPort()) {
            return null;
        }

        return ($parties['path'] ?? '/').(isset($parties['query']) ? '?'.$parties['query'] : '');
    }
}
