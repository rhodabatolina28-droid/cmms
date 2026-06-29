<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if (Auth::check()) {
            $user = Auth::user();
            return redirect($user->dashboardPath());
        }
        $redirect = $request->query('redirect');
        $redirect = $this->validateRedirect($redirect);
        return view('auth.login', compact('redirect'));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Anti-Brute Force Protection (Rate Limiting)
        $throttleKey = mb_strtolower($request->input('email')) . '|' . $request->ip();
        $ipKey = 'global_ip|' . $request->ip();

        if (RateLimiter::tooManyAttempts($ipKey, 20)) {
            $seconds = RateLimiter::availableIn($ipKey);
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => "Too many attempts. Try again in {$seconds} seconds."], 429);
            }
            return back()->withErrors(['email' => "Too many attempts. Try again in {$seconds} seconds."])->onlyInput('email');
        }

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Too many login attempts. Please try again in {$seconds} seconds."
                ], 429);
            }
            
            return back()->withErrors([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ])->onlyInput('email');
        }

        if (Auth::attempt($credentials)) {
            RateLimiter::clear($throttleKey);
            RateLimiter::clear($ipKey);

            // Block deactivated accounts from logging in
            if (!Auth::user()->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Your account has been deactivated. Please contact your administrator.'
                    ], 403);
                }

                return back()->withErrors([
                    'email' => 'Your account has been deactivated. Please contact your administrator.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();

            $user = Auth::user();
            $redirect = $this->validateRedirect($request->input('redirect'));
            $dashboardPath = $user->dashboardPath();

            // Check for pending QR redirect from session (guest scanned QR before login)
            $qrAssetId = $request->session()->pull('qr_redirect_asset_id');
            if ($qrAssetId && $user->role === 'user') {
                $finalRedirect = route('ict.create', ['asset_id' => $qrAssetId]);
            } else {
                $finalRedirect = $redirect ?: $dashboardPath;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'redirect' => $finalRedirect
                ]);
            }

            Log::info('Login successful', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'redirect' => $finalRedirect,
                'session_id' => $request->session()->getId()
            ]);

            return redirect($finalRedirect);
        }

        RateLimiter::hit($throttleKey, 60); // Lockout for 60 seconds
        RateLimiter::hit($ipKey, 60);

        Log::warning('Login failed', [
            'email' => $credentials['email'],
            'ip' => $request->ip(),
            'session_id' => $request->session()->getId()
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'The provided credentials do not match our records.'
            ], 422);
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('logged-out');
    }

    public function apiLogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $throttleKey = mb_strtolower($request->input('email')) . '|' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'success' => false,
                'message' => "Too many login attempts. Please try again in {$seconds} seconds."
            ], 429);
        }

        if (Auth::attempt($credentials)) {
            RateLimiter::clear($throttleKey);
            $user = Auth::user();

            if (!$user->is_active) {
                Auth::logout();
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated. Please contact your administrator.'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'role' => $user->role,
                'message' => 'Login successful',
                'user' => $user->only(['id', 'full_name', 'email', 'role', 'office', 'branch', 'department'])
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials'
        ], 401);
    }

    public function apiLogout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    private function validateRedirect($redirect)
    {
        if (!$redirect) return null;
        $base = url('/');
        if (str_starts_with($redirect, $base)) return $redirect;
        if (str_starts_with($redirect, '/')) return $redirect;
        return null;
    }
}