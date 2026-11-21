<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /** Politique de référent des pages éditoriales. */
    public const REFERRER_POLICY = 'strict-origin-when-cross-origin';

    /**
     * Politique de référent des routes calculator.* (#283) et du changement de
     * langue (#295). Les premières portent des montants de salaire dans l'URL
     * (?s=, ?a=, ?b=, payload non chiffré) ; le lien de langue d'une page du
     * calculateur porte la même URL dans sa cible (?retour=). Une politique
     * plus large transmettrait l'URL complète à la page suivante de la même
     * origine. Les retours d'erreur
     * de validation et de langue ne dépendent pas du référent : la page
     * d'origine transmet sa cible (RetourNavigation).
     */
    public const REFERRER_POLICY_CALCULATEUR = 'no-referrer';

    /** Routes servies sous REFERRER_POLICY_CALCULATEUR. */
    public const ROUTES_SANS_REFERENT = ['calculator.*', 'locale.update'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $referent = $request->routeIs(...self::ROUTES_SANS_REFERENT) ? self::REFERRER_POLICY_CALCULATEUR : self::REFERRER_POLICY;
        $response->headers->set('Referrer-Policy', $referent);

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->headers->set('Content-Security-Policy', $this->buildCsp());

        return $response;
    }

    private function buildCsp(): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net",
            "img-src 'self' data:",
            "connect-src 'self'",
            "frame-src 'none'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        return implode('; ', $directives);
    }
}
