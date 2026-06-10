<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('app.supported_locales'));
        $locale = $request->session()->get('locale', config('app.locale'));

        app()->setLocale(in_array($locale, $supported, true) ? $locale : config('app.fallback_locale'));

        return $next($request);
    }
}
