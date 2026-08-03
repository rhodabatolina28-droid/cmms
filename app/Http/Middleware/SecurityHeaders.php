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

        // Allow Vite dev server assets in development mode
        $isLocal = config('app.env') === 'local';
        $viteDevServer = $isLocal ? ' http://localhost:5173 http://127.0.0.1:5173' : '';

        $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com{$viteDevServer}; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com{$viteDevServer}; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com{$viteDevServer}; img-src 'self' data:; connect-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com{$viteDevServer}; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'" . ($isLocal ? '' : '; upgrade-insecure-requests');

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
