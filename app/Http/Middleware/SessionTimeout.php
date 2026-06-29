<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeout
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $inactiveTime = config('session.lifetime');

        if (auth()->check()) {
            $lastActivity = session('last_activity', time());

            if (time() - $lastActivity > ($inactiveTime * 60)) {
                auth()->logout();
                session()->flush();
                return redirect('/login')->with('error', 'Your session has expired. Please login again.');
            }

            session(['last_activity' => time()]);
        }

        return $next($request);
    }
}
