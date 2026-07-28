<?php

namespace App\Actions\PhysicalCount;

use App\Http\Controllers\InventoryController;
use App\Models\InventoryAsset;
use App\Models\PhysicalCountSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class ShowPhysicalCountAction
{
    /**
     * Show a physical count session with paginated assets.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Contracts\View\View
     */
    public function execute(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            abort(403);
        }

        $session = PhysicalCountSession::with(['startedBy', 'counts.asset.assignedUser', 'counts.countedBy'])
            ->findOrFail($id);

        if ($session->scope_region && $session->scope_region !== $user->region) {
            abort(403);
        }
        if ($user->branch && $session->scope_branch && $session->scope_branch !== $user->branch) {
            abort(403);
        }

        $totalAssetsQuery = InventoryAsset::query();
        app(InventoryController::class)->scopeAssetsToActor($totalAssetsQuery, $user);
        $totalCount = $totalAssetsQuery->count();

        $allAssets = InventoryAsset::with('assignedUser');
        app(InventoryController::class)->scopeAssetsToActor($allAssets, $user);
        $allAssets = $allAssets->orderBy('category')->orderBy('item_name')
            ->paginate(50);

        $countedIds = $session->counts->pluck('asset_id')->toArray();

        $summary = [
            'total'   => $totalCount,
            'counted' => $session->counts->count(),
            'present' => $session->counts->where('status', 'Present')->count(),
            'missing' => $session->counts->where('status', 'Missing')->count(),
            'damaged' => $session->counts->where('status', 'Damaged')->count(),
        ];

        return view('inventory.physical-count-show', compact('session', 'allAssets', 'countedIds', 'summary'));
    }
}
