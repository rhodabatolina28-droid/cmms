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

        // Parts requisition context: assigned IT/Super Admin can request parts
        // for a PM ticket once a repair asset is linked (FOR REPAIR selection).
        $ticketRequisitions = collect();
        $canRequestPartsOnTicket = false;
        $hasMyPendingParts = false;
        if (in_array($user->role, ['it', 'super_admin'], true)) {
            $ticketRequisitions = \App\Models\Requisition::with(['requester', 'reviewer'])
                ->where('request_id', $trackingRequest->id)
                ->orderByDesc('created_at')
                ->get();

            $hasMyPendingParts = $ticketRequisitions->contains(
                fn ($r) => $r->status === \App\Models\Requisition::STATUS_PENDING
                    && (int) $r->requested_by === (int) $user->id
            );

            $canRequestPartsOnTicket = \App\Support\RequisitionSupport::canItSubmitForTicket($user, $trackingRequest)
                && !$hasMyPendingParts;
        }

        $data = array_merge([
            'request'    => $trackingRequest,
            'maintenance' => $maintenance,
            'myAssets'   => $myAssets,
            'linkedPmAsset' => $linkedPmAsset,
            'endUser'    => $endUser,
            'ticketRequisitions' => $ticketRequisitions,
            'canRequestPartsOnTicket' => $canRequestPartsOnTicket,
            'hasMyPendingParts' => $hasMyPendingParts,
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
