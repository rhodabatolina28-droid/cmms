<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\InventoryAsset;

class AssetController extends Controller
{
    public function myAssets()
    {
        $assets = InventoryAsset::where('assigned_to_user', Auth::id())
            ->whereNotIn('status', ['For Repair', 'For Disposal', 'Scrapped'])
            ->get();

        return view('profile.assets', [
            'assets' => $assets,
        ]);
    }
}
