<?php

namespace App\Actions\Maintenance;

use App\Models\InventoryAsset;
use App\Models\PreventiveMaintenance;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class BuildMaintenanceFormViewDataAction
{
    /**
     * Build the view data array for the maintenance form (used by show/edit).
     *
     * @param  \App\Models\Request  $trackingRequest
     * @param  \App\Models\PreventiveMaintenance  $maintenance
     * @param  bool  $forceView
     * @return array
     */
    public function execute(RequestModel $trackingRequest, PreventiveMaintenance $maintenance, bool $forceView = false): array
    {
        $user = Auth::user();
        $flags = \App\Support\RequestHelpers::maintenanceFormFlags($user, $trackingRequest, $forceView);

        $requestorId = $trackingRequest->user_id;
        $myAssets = InventoryAsset::where('assigned_to_user', $requestorId)->get();
        $linkedPmAsset = null;
        if ($trackingRequest->linked_asset_id) {
            $linkedPmAsset = InventoryAsset::find($trackingRequest->linked_asset_id);
            if ($linkedPmAsset && !$myAssets->contains('asset_id', $linkedPmAsset->asset_id)) {
                $myAssets->push($linkedPmAsset);
            }
        }

        if ($maintenance->disposal_asset_id) {
            $disposalAsset = InventoryAsset::find($maintenance->disposal_asset_id);
            if ($disposalAsset && !$myAssets->contains('asset_id', $disposalAsset->asset_id)) {
                $myAssets->push($disposalAsset);
            }
        }

        $endUser = User::find($requestorId);

        $data = array_merge([
            'request'    => $trackingRequest,
            'maintenance' => $maintenance,
            'myAssets'   => $myAssets,
            'linkedPmAsset' => $linkedPmAsset,
            'endUser'    => $endUser,
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
