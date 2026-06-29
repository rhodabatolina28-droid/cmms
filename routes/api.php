<?php

/**
 * CMMS mobile/API routes are disabled until Laravel Sanctum is installed.
 *
 * Sanctum = Laravel package for API token auth (Bearer tokens), separate from web login session.
 * This project uses the web portal (session cookies) for all roles.
 *
 * To enable later:
 *   composer require laravel/sanctum
 *   php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
 *   php artisan migrate
 */

use Illuminate\Support\Facades\Route;

Route::any('/{any?}', function () {
    return response()->json([
        'success' => false,
        'message' => 'CMMS API is disabled. Use the web portal at /login, or install laravel/sanctum to enable API routes.',
    ], 501);
})->where('any', '.*');
