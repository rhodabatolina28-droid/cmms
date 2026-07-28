<?php

namespace App\Actions\PhysicalCount;

use App\Http\Controllers\InventoryController;
use App\Models\InventoryAsset;
use App\Models\PhysicalCountSession;
use Illuminate\Support\Facades\Auth;

class PrintPhysicalCountReportAction
{
    /**
     * Print a physical count report view.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\View\View
     */
    public function execute($id)
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

        $allAssets = InventoryAsset::with('assignedUser');
        app(InventoryController::class)->scopeAssetsToActor($allAssets, $user);
        $allAssets = $allAssets->orderBy('category')->orderBy('item_name')->get();

        $summary = [
            'total'   => $allAssets->count(),
            'counted' => $session->counts->count(),
            'present' => $session->counts->where('status', 'Present')->count(),
            'missing' => $session->counts->where('status', 'Missing')->count(),
            'damaged' => $session->counts->where('status', 'Damaged')->count(),
        ];

        $grouped = $allAssets->groupBy('category')->sortKeys();

        return view('inventory.physical-count-print', compact('session', 'allAssets', 'summary', 'grouped'));
    }
}
