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

        // Lien historique sans cible (/lang/xx) : retour précédent du même
        // hôte, hors boucle de langue, sinon l'accueil.
        $target = url()->previous(route('home'));
        $targetHost = parse_url($target, PHP_URL_HOST);
        $currentHost = $request->getHost();
        $targetPath = parse_url($target, PHP_URL_PATH) ?: '/';

        if ($targetHost !== $currentHost || str_starts_with($targetPath, '/lang/')) {
            $target = route('home');
        }

        return redirect()->to($target);
    }
}
