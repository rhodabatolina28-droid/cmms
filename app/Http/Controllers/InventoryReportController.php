<?php

namespace App\Http\Controllers;

use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryReportController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $assetsQuery = InventoryAsset::query();

        if ($user->canProcessSupply()) {
            app(InventoryController::class)->scopeAssetsToActor($assetsQuery, $user);
        } elseif ($user->role === 'super_admin') {
            $assetsQuery->where('region', $user->region);
            if ($user->branch) $assetsQuery->where('branch', $user->branch);
        } else {
            abort(403);
        }

        $statusDistribution = (clone $assetsQuery)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderBy('count', 'desc')
            ->get();

        $categoryDistribution = (clone $assetsQuery)
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->get();

        $totalValue = (clone $assetsQuery)->sum('acquisition_cost');

        $totalMaintenanceCost = (clone $assetsQuery)->sum('total_maintenance_cost');

        $warrantyExpiring = (clone $assetsQuery)
            ->whereNotNull('warranty_expiration')
            ->where('warranty_expiration', '>=', now())
            ->where('warranty_expiration', '<=', now()->addDays(30))
            ->with('assignedUser')
            ->get();

        $warrantyExpired = (clone $assetsQuery)
            ->whereNotNull('warranty_expiration')
            ->where('warranty_expiration', '<', now())
            ->with('assignedUser')
            ->get();

        $recentDisposals = InventoryAsset::onlyTrashed()
            ->whereIn('status', [\App\Enums\AssetStatus::SCRAPPED, \App\Enums\AssetStatus::FOR_DISPOSAL])
            ->where('region', $user->region)
            ->when($user->branch, fn ($q) => $q->where('branch', $user->branch))
            ->orderBy('updated_at', 'desc')
            ->limit(20)
            ->get();

        $totalAssets = $assetsQuery->count();

        return view('inventory.reports', compact(
            'statusDistribution',
            'categoryDistribution',
            'totalValue',
            'totalMaintenanceCost',
            'warrantyExpiring',
            'warrantyExpired',
            'recentDisposals',
            'totalAssets'
        ));
    }
}
