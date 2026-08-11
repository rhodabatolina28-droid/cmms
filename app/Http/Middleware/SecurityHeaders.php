<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(32);
        $request->attributes->set('csp_nonce', $nonce);
        View::share('cspNonce', $nonce);

        // Use permissive CSP in development to allow Vite dev server
        // (HTTP assets, WebSocket HMR, blob URIs, data URIs, inline scripts/styles)
        // Use strict nonce-based CSP in production for real XSS protection
        if (config('app.env') === 'local') {
            $csp = "default-src * 'unsafe-inline' 'unsafe-eval' 'unsafe-hashes' data: blob:; img-src * data: blob:; font-src * data:; connect-src * ws: wss:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'";
        } else {
            // Nonce is now actually injected — 'unsafe-inline' is intentionally removed so the nonce works
            $csp = "default-src 'self'; script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'; upgrade-insecure-requests";
        }

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $response->headers->set('Content-Security-Policy', $csp);

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
