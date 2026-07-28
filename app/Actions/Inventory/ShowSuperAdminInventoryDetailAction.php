<?php

namespace App\Actions\Inventory;

use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\Auth;

class ShowSuperAdminInventoryDetailAction
{
    /**
     * Super Admin — read-only asset detail page.
     *
     * @param  int  $assetId
     * @return \Illuminate\Contracts\View\View
     */
    public function execute($assetId)
    {
        $user = Auth::user();
        if ($user->role !== 'super_admin') abort(403);

        $asset = InventoryAsset::with(['assignedUser', 'attachments.uploader', 'components', 'parentAsset.components'])
            ->findOrFail($assetId);

        $assetUserId = $asset->assigned_to_user;
        $repairHistory = RequestModel::with(['user', 'repairRequest', 'maintenanceRequest', 'assignedTo'])
            ->where(function ($q) use ($assetId, $assetUserId) {
                $q->where('linked_asset_id', $assetId)
                  ->orWhere(function ($sub) use ($assetUserId) {
                      $sub->where('type', 'Preventive Maintenance')
                          ->where('is_auto_generated', true)
                          ->where('user_id', $assetUserId);
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

        return view('inventory.detail', compact('asset', 'repairHistory', 'transferHistory') + [
            'isSuperAdminView' => true,
        ]);
    }
}
