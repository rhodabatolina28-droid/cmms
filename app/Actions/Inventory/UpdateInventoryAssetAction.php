<?php

namespace App\Actions\Inventory;

use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use App\Models\AuditLog;
use App\Models\User;
use App\Http\Requests\UpdateInventoryRequest;
use App\Services\ParNumberService;
use App\Services\AssetSetIntegrityService;
use App\Services\RequestNotificationService;
use App\Models\Scopes\InventoryScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateInventoryAssetAction
{
    /**
     * Update an existing inventory asset.
     *
     * @param  \App\Http\Requests\UpdateInventoryRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(UpdateInventoryRequest $request, $id)
    {
        $user = Auth::user();
        $asset = InventoryAsset::findOrFail($id);

        if (! $user->canProcessSupply()) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory updates are handled by the Administrative supply admin.',
            ], 403);
        }

        if (! InventoryScope::assetInInventoryScope($user, $asset)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to update this asset'], 403);
        }

        // FULL LOCK: Scrapped and For Disposal assets cannot be edited at all
        // Preserves disposal audit trail and DB integrity
        if (in_array($asset->status, \App\Enums\AssetStatus::LOCKED)) {
            return response()->json([
                'success' => false,
                'message' => "{$asset->status} assets are locked. All edits are disabled to preserve audit and disposal records.",
            ], 422);
        }

        // Block transfer of Defective or For Disposal assets
        $lockedStatuses = ['Defective', \App\Enums\AssetStatus::FOR_DISPOSAL, \App\Enums\AssetStatus::SCRAPPED];
        $newAssignee = $request->input('assigned_to_user');
        if (in_array($asset->status, $lockedStatuses) && $newAssignee != $asset->assigned_to_user) {
            return response()->json([
                'success' => false,
                'message' => "Cannot reassign a {$asset->status} asset. Resolve the asset status first."
            ], 422);
        }

        $validated = $request->validated();

        $assignmentError = InventoryScope::validateAssignedUserScope($user, $validated['assigned_to_user'] ?? null, $asset->region);
        if ($assignmentError) {
            return response()->json(['success' => false, 'message' => $assignmentError], 422);
        }

        InventoryScope::applyInventoryOrgScope($validated, $user, $asset);

        $data = $validated;
        // Specifications handling
        if (isset($data['specifications']) && is_string($data['specifications'])) {
            $decoded = json_decode($data['specifications'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['specifications'] = $decoded;
            }
        }

        // Set membership is established by import or initial encoding. Moving a
        // component between sets needs a dedicated audited transfer workflow.
        if ($asset->parent_asset_id && array_key_exists('parent_asset_id', $data)
            && (int) $data['parent_asset_id'] !== (int) $asset->parent_asset_id) {
            return response()->json(['success' => false, 'message' => 'A component cannot be detached from or moved to another PAR set through normal edit.'], 422);
        }

        $setIntegrity = app(AssetSetIntegrityService::class);
        $setCheck = $setIntegrity->validate($data, $asset);
        if ($setCheck['error']) {
            return response()->json(['success' => false, 'message' => $setCheck['error']], 422);
        }

        $parent = $setCheck['parent'];
        if ($parent) {
            if (! InventoryScope::assetInInventoryScope($user, $parent)) {
                return response()->json(['success' => false, 'message' => 'The parent asset is outside your inventory scope.'], 403);
            }

            if (array_key_exists('assigned_to_user', $data)
                && (int) ($data['assigned_to_user'] ?? 0) !== (int) ($parent->assigned_to_user ?? 0)) {
                return response()->json(['success' => false, 'message' => 'A set component inherits its custodian from the parent asset. Update the parent set instead.'], 422);
            }

            $setIntegrity->applyParentContext($data, $parent);
        }

        $previousStatus = $asset->status;
        $previousUser = $asset->assigned_to_user;
        $previousPar = $asset->par_number;

        // Auto-generate PAR when previously unassigned (Spare) asset gets assigned
        if (!$asset->par_number && !empty($data['assigned_to_user'])) {
            $data['par_number'] = ParNumberService::generateNextParNumber();
        }

        // PAR regeneration on reassignment: new custodian = new PAR (government compliance)
        if ($previousUser && !empty($data['assigned_to_user']) && (int) $data['assigned_to_user'] !== $previousUser) {
            $data['par_number'] = ParNumberService::generateNextParNumber();
        }

        if ($activationError = $setIntegrity->activationError($data, $asset)) {
            return response()->json(['success' => false, 'message' => $activationError], 422);
        }

        DB::transaction(function () use ($asset, $data, $previousUser, $previousStatus, $previousPar, $user, $request) {
            $asset->update($data);

        // When the parent set changes custodian/PAR, update every physical
        // component too. They remain separate assets but one accountable set.
        if (! $asset->parent_asset_id && $previousUser !== $asset->assigned_to_user) {
            $components = $asset->components()->lockForUpdate()->get();
            foreach ($components as $component) {
                $componentPreviousUser = $component->assigned_to_user;
                $component->update([
                    'assigned_to_user' => $asset->assigned_to_user,
                    'par_number' => $asset->par_number,
                    'region' => $asset->region,
                    'branch' => $asset->branch,
                    'office' => $asset->office,
                    'department' => $asset->department,
                ]);

                InventoryHistory::create([
                    'asset_id' => $component->asset_id,
                    'action' => 'Set Custodian Updated',
                    'performed_by' => $user->id,
                    'previous_user_id' => $componentPreviousUser,
                    'new_user_id' => $asset->assigned_to_user,
                    'previous_status' => $component->status,
                    'new_status' => $component->status,
                    'remarks' => "Inherited PAR/custodian update from parent asset #{$asset->asset_id}.",
                ]);
            }
        }

        AuditLog::log(
            "Updated Asset", 
            "Inventory", 
            "Updated details for {$asset->item_name} (SN: {$asset->serial_number})",
            $asset->office
        );

        if ($previousStatus !== $asset->status || $previousUser !== $asset->assigned_to_user) {
            $action = 'Asset Updated';
            if ($previousUser !== $asset->assigned_to_user) {
                $action = $asset->assigned_to_user ? 'Custodian Updated' : 'Asset Returned to Stock';
            }

            $remarks = $request->remarks ?? 'Asset details updated';
            if ($previousUser !== $asset->assigned_to_user && $previousPar && $asset->par_number !== $previousPar) {
                $remarks = "Reassigned from PAR {$previousPar} to PAR {$asset->par_number}. " . $remarks;
            }

            InventoryHistory::create([
                'asset_id' => $asset->asset_id,
                'action' => $action,
                'performed_by' => $user->id,
                'previous_user_id' => $previousUser,
                'new_user_id' => $asset->assigned_to_user,
                'previous_status' => $previousStatus,
                'new_status' => $asset->status,
                'remarks' => $remarks,
            ]);
        }

        // Auto-sync assigned user's department into asset (avoids manual "Update Department" action)
        if ($asset->assigned_to_user) {
            $assignedUser = User::find($asset->assigned_to_user);
            if ($assignedUser && $assignedUser->department !== $asset->department) {
                $asset->update(['department' => $assignedUser->department]);
            }
        }
        });

        if ($previousUser !== $asset->assigned_to_user) {
            RequestNotificationService::notifyAssetCustodianTransfer(
                $asset,
                $previousUser ? (int) $previousUser : null,
                $asset->assigned_to_user ? (int) $asset->assigned_to_user : null
            );
        }

        return response()->json(['success' => true, 'message' => 'Asset updated successfully']);
    }
}
