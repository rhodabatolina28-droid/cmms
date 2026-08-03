<?php

namespace App\Actions\Inventory;

use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use App\Models\Request as RequestModel;
use App\Models\Scopes\InventoryScope;
use Illuminate\Support\Facades\Auth;

class ShowInventoryDetailAction
{
    /**
     * Asset detail page — full profile with repair history and attachments.
     *
     * @param  int  $assetId
     * @return \Illuminate\Contracts\View\View
     */
    public function execute($assetId)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            abort(403);
        }

        $asset = InventoryAsset::with(['assignedUser', 'attachments.uploader', 'components', 'parentAsset.components'])
            ->findOrFail($assetId);

        if (!InventoryScope::assetInActorViewScope($user, $asset)) {
            abort(403, 'Asset is outside your scope.');
        }

        $assetUserId = $asset->assigned_to_user;

        // If asset is unassigned (e.g., For Disposal), find the previous user from history
        // so PM records don't disappear from the repair history
        if (!$assetUserId) {
            $lastAssignment = InventoryHistory::where('asset_id', $assetId)
                ->whereNotNull('previous_user_id')
                ->orderByDesc('created_at')
                ->first();
            $assetUserId = $lastAssignment?->previous_user_id;
        }

        $repairHistory = RequestModel::with(['user', 'repairRequest', 'maintenanceRequest', 'assignedTo'])
            ->where(function ($q) use ($assetId, $assetUserId) {
                $q->where('linked_asset_id', $assetId);
                if ($assetUserId) {
                    $q->orWhere(function ($sub) use ($assetUserId) {
                        $sub->where('type', 'Preventive Maintenance')
                            ->where('is_auto_generated', true)
                            ->where('user_id', $assetUserId);
                    });
                }
                $q->orWhere(function ($sub) use ($assetId) {
                    $sub->where('type', 'Preventive Maintenance')
                        ->whereHas('maintenanceRequest', function ($pm) use ($assetId) {
                            $pm->where('disposal_asset_id', $assetId);
                        });
                });
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $transferHistory = InventoryHistory::with(['performedByUser', 'previousUser', 'newUser'])
            ->where('asset_id', $assetId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('inventory.detail', compact('asset', 'repairHistory', 'transferHistory'));
    }
}
