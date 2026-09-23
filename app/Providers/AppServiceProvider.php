<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use App\Models\InventoryAsset;
use App\Models\Request as RequestModel;
use App\Observers\InventoryAssetObserver;
use App\Observers\RequestObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        View::composer('*', function ($view) {
            $nonce = request()->attributes->get('csp_nonce', '');
            $view->with('cspNonce', $nonce);

            // Enable Vite to automatically add the CSP nonce to script/style tags
            if ($nonce) {
                Vite::useCspNonce($nonce);
            }
        });

        // D9.40 - pin the shared, styled pagination bar so every ->links() call
        // (including list pages added later) renders the CMMS paginator instead of
        // Laravel's raw Tailwind one.
        Paginator::defaultView('vendor.pagination.cmms');

        InventoryAsset::observe(InventoryAssetObserver::class);
        RequestModel::observe(RequestObserver::class);
    }
}
