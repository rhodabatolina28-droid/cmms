<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use App\Models\InventoryAsset;
use App\Observers\InventoryAssetObserver;

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
        });

        InventoryAsset::observe(InventoryAssetObserver::class);
    }
}
