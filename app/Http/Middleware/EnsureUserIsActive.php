<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && (Auth::user()->is_active === null || Auth::user()->is_active === 0)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated. Please contact your administrator.'
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been deactivated. Please contact your administrator.',
            ]);
        }

        return $next($request);
    }
}
