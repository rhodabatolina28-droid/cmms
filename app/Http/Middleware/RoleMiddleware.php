<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $userRole = auth()->user()->role;

        // Treat supply_officer as admin for role-based access
        if ($userRole === 'supply_officer' && in_array('admin', $roles)) {
            return $next($request);
        }

        if (!in_array($userRole, $roles)) {
            // Return JSON for AJAX/API requests
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to this resource.'
                ], 403);
            }

            // Redirect browser users to their appropriate dashboard with an error message
            $dashboard = route(auth()->user()->dashboardRouteName());

            return redirect($dashboard)->with('error', 'Unauthorized: You do not have permission to access that page.');
        }

        return $next($request);
    }
}
