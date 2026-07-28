<?php

namespace App\Actions\Inventory;

use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use App\Models\Scopes\InventoryScope;
use Illuminate\Support\Facades\Auth;

class GetInventoryHistoryAction
{
    /**
     * AJAX endpoint — returns inventory history for an asset.
     *
     * @param  int  $assetId
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute($assetId)
    {
        $asset = InventoryAsset::findOrFail($assetId);
        $user = Auth::user();

        if (! $user->canProcessSupply() && $user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Inventory history is managed by the Administrative supply admin.'], 403);
        }

        if ($user->canProcessSupply() && !InventoryScope::assetInActorViewScope($user, $asset)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to view this history'], 403);
        }

        $history = InventoryHistory::where('asset_id', $assetId)
            ->with(['performedByUser', 'previousUser', 'newUser'])
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json(['success' => true, 'history' => $history]);
    }
}
