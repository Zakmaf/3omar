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
     * Politique de référent des routes calculator.* (#283). Elles portent des
     * montants de salaire dans l'URL (?s=, ?a=, ?b=, payload non chiffré) :
     * strict-origin-when-cross-origin transmettrait l'URL complète à la page
     * suivante de la même origine, qui peut charger une régie publicitaire.
     * strict-origin n'est pas retenu : sans référent, url()->previous() (retour
     * de langue, erreur de validation) se replie sur _previous.url de la
     * session, qui garde l'URL complète ; avec l'origine seule, il renverrait
     * à l'accueil.
     */
    public const REFERRER_POLICY_CALCULATEUR = 'no-referrer';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $referent = $request->routeIs('calculator.*') ? self::REFERRER_POLICY_CALCULATEUR : self::REFERRER_POLICY;
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
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net".$this->adScriptSrc(),
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net",
            "img-src 'self' data:".$this->adImgSrc(),
            "connect-src 'self'".$this->adConnectSrc(),
            'frame-src'.($this->adsEnabled() ? $this->adFrameSrc() : " 'none'"),
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        return implode('; ', $directives);
    }

    private function adsEnabled(): bool
    {
        return app()->environment('production')
            && config('ads.enabled')
            && config('ads.client');
    }

    private function adScriptSrc(): string
    {
        return $this->adsEnabled()
            ? ' https://pagead2.googlesyndication.com https://www.googletagservices.com'
            : '';
    }

    private function adImgSrc(): string
    {
        return $this->adsEnabled()
            ? ' https://pagead2.googlesyndication.com'
            : '';
    }

    private function adConnectSrc(): string
    {
        return $this->adsEnabled()
            ? ' https://pagead2.googlesyndication.com'
            : '';
    }

    private function adFrameSrc(): string
    {
        return $this->adsEnabled()
            ? ' https://googleads.g.doubleclick.net https://tpc.googlesyndication.com'
            : '';
    }
}
