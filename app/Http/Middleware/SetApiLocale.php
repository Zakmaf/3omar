<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * L'API publique répond toujours en anglais, comme ses titres et détails RFC 7807,
 * quel que soit l'en-tête Accept-Language (#187).
 */
class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('en');

        return $next($request);
    }
}
