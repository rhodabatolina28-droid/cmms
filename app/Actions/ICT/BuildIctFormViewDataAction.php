<?php

namespace App\Actions\ICT;

use App\Models\InventoryAsset;
use App\Models\RepairRequest;
use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\Auth;

class BuildIctFormViewDataAction
{
    /**
     * Build the view data array for the ICT form (used by show/edit).
     *
     * @param  \App\Models\Request  $trackingRequest
     * @param  \App\Models\RepairRequest  $repairRequest
     * @param  bool  $forceView
     * @return array
     */
    public function execute(RequestModel $trackingRequest, RepairRequest $repairRequest, bool $forceView = false): array
    {
        $user = Auth::user();
        $flags = \App\Support\RequestHelpers::ictFormFlags($user, $trackingRequest, $forceView, $repairRequest);

        $requestorId = $trackingRequest->user_id;
        $myAssets = InventoryAsset::where('assigned_to_user', $requestorId)
            ->whereNotIn('status', ['For Repair', 'For Disposal', 'Scrapped'])
            ->get();
        if ($trackingRequest->linked_asset_id) {
            $linkedAsset = InventoryAsset::find($trackingRequest->linked_asset_id);
            if ($linkedAsset && !$myAssets->contains('asset_id', $linkedAsset->asset_id)) {
                $myAssets->push($linkedAsset);
            }
        }

        $linkedAssetData = null;
        if ($trackingRequest->linked_asset_id) {
            $linkedAsset = InventoryAsset::find($trackingRequest->linked_asset_id);
            if ($linkedAsset) {
                $linkedAssetData = [
                    'serial_number'   => $linkedAsset->serial_number,
                    'property_number' => $linkedAsset->property_number,
                    'par_number'      => $linkedAsset->par_number,
                    'item_name'       => $linkedAsset->item_name,
                    'category'        => $linkedAsset->category,
                    'specifications'  => $linkedAsset->specifications ?? [],
                    'date_acquired'   => $linkedAsset->date_acquired
                        ? \Carbon\Carbon::parse($linkedAsset->date_acquired)->format('Y-m-d')
                        : null,
                ];
            }
        }

        $ictAssetsMap = [];
        foreach ($myAssets as $asset) {
            $ictAssetsMap[$asset->asset_id] = [
                'serial_number'   => $asset->serial_number,
                'property_number' => $asset->property_number,
                'par_number'      => $asset->par_number,
                'date_acquired'   => $asset->date_acquired
                    ? \Carbon\Carbon::parse($asset->date_acquired)->format('Y-m-d')
                    : null,
                'item_name'       => $asset->item_name,
                'category'        => $asset->category,
            ];
        }

        $data = array_merge([
            'request' => $trackingRequest,
            'repairRequest' => $repairRequest,
            'myAssets' => $myAssets,
            'linkedAssetData' => $linkedAssetData,
            'ictAssetsMap' => $ictAssetsMap,
        ], $flags);

        if (!empty($flags['canAssignIt'])) {
            $data['itPersonnel'] = \App\Support\RequestHelpers::itPersonnelInAdminScope($user);
            if ($user->role === 'super_admin') {
                $data['canSelfAssign'] = true;
            }
        }

        return $data;
    }
}
