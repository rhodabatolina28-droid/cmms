<?php

namespace App\Providers;

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

        InventoryAsset::observe(InventoryAssetObserver::class);
        RequestModel::observe(RequestObserver::class);
    }
}
