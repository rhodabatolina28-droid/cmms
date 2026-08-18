<?php

namespace App\Actions\Inventory;

use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use App\Models\AuditLog;
use App\Http\Requests\StoreInventoryRequest;
use App\Services\ParNumberService;
use App\Services\AssetSetIntegrityService;
use App\Services\RequestNotificationService;
use App\Models\Scopes\InventoryScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateInventoryAssetAction
{
    /**
     * Create a new inventory asset.
     *
     * @param  \App\Http\Requests\StoreInventoryRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(StoreInventoryRequest $request)
    {
        $user = Auth::user();
        if (! $user->canProcessSupply()) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory encoding is handled by the Administrative supply admin.',
            ], 403);
        }

        $validated = $request->validated();

        $validated['region'] = $user->region;

        $assignmentError = InventoryScope::validateAssignedUserScope($user, $validated['assigned_to_user'] ?? null, $validated['region'] ?? null);
        if ($assignmentError) {
            return response()->json(['success' => false, 'message' => $assignmentError], 422);
        }

        $setIntegrity = app(AssetSetIntegrityService::class);
        $setCheck = $setIntegrity->validate($validated);
        if ($setCheck['error']) {
            return response()->json(['success' => false, 'message' => $setCheck['error']], 422);
        }

        $parent = $setCheck['parent'];
        if ($parent && ! InventoryScope::assetInInventoryScope($user, $parent)) {
            return response()->json(['success' => false, 'message' => 'The selected parent asset is outside your inventory scope.'], 403);
        }

        InventoryScope::applyInventoryOrgScope($validated, $user);
        if ($parent) {
            $setIntegrity->applyParentContext($validated, $parent);
        }

        // Specifications handling
        if (isset($validated['specifications']) && is_string($validated['specifications'])) {
            $decoded = json_decode($validated['specifications'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $validated['specifications'] = $decoded;
            }
        }

        // Auto-enforce status integrity is now handled by InventoryAsset model event (booted())
        // This applies to all saves across all locations/regions

        // A component inherits its parent's PAR; a standalone assigned asset gets its own PAR.
        if (! $parent && !empty($validated['assigned_to_user'])) {
            $validated['par_number'] = ParNumberService::generateNextParNumber();
        }

        if ($activationError = $setIntegrity->activationError($validated)) {
            return response()->json(['success' => false, 'message' => $activationError], 422);
        }

        $asset = DB::transaction(function () use ($validated, $user) {
            $asset = InventoryAsset::create($validated);

            AuditLog::log(
            "Added New Asset", 
            "Inventory", 
            "Added {$asset->item_name} (SN: {$asset->serial_number}) to inventory",
            $asset->office
            );

        // Log history — no receipt generated, physical PTR handled outside system
            InventoryHistory::create([
            'asset_id' => $asset->asset_id,
            'action' => !empty($validated['assigned_to_user']) ? 'Asset Registered & Assigned' : 'Asset Added',
            'performed_by' => $user->id,
            'new_user_id' => $validated['assigned_to_user'] ?? null,
                'new_status' => $asset->status,
                'remarks' => $asset->parent_asset_id ? "Set component of asset #{$asset->parent_asset_id}." : 'Initial entry into inventory',
            ]);

            return $asset;
        });

        if ($asset->assigned_to_user) {
            RequestNotificationService::notifyNewAssetCustodian($asset, (int) $asset->assigned_to_user);
        }

        return response()->json(['success' => true, 'message' => 'Asset added successfully', 'par_number' => $asset->par_number]);
    }
}
