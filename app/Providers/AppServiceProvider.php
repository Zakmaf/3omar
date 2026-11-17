<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('calculer', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('api-simuler', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Condition unique du script, de l'initialisation et des emplacements
        // publicitaires (layout et composant ad-slot). Aucune publicité sur les
        // routes calculator.* (#283) : elles portent des montants de salaire,
        // dans le formulaire, dans l'URL (?s=, ?a=, ?b=, payload non chiffré)
        // ou dans le résultat, que la régie recevrait avec la page. Les pages
        // éditoriales gardent la publicité en production.
        Blade::if('publicite', function (): bool {
            return app()->environment('production')
                && (bool) config('ads.enabled')
                && (bool) config('ads.client')
                && ! request()->routeIs('calculator.*');
        });
    }
}
