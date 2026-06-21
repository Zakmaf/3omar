<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

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
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net" . $this->adScriptSrc(),
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net",
            "img-src 'self' data:" . $this->adImgSrc(),
            "connect-src 'self'" . $this->adConnectSrc(),
            "frame-src" . ($this->adsEnabled() ? $this->adFrameSrc() : " 'none'"),
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
            ? " https://googleads.g.doubleclick.net https://tpc.googlesyndication.com"
            : '';
    }
}
