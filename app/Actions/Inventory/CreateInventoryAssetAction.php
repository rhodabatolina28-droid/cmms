<?php

namespace App\Actions\Inventory;

use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use App\Models\AuditLog;
use App\Http\Requests\StoreInventoryRequest;
use App\Services\ParNumberService;
use App\Models\Scopes\InventoryScope;
use Illuminate\Support\Facades\Auth;

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

        InventoryScope::applyInventoryOrgScope($validated, $user);

        // Specifications handling
        if (isset($validated['specifications']) && is_string($validated['specifications'])) {
            $decoded = json_decode($validated['specifications'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $validated['specifications'] = $decoded;
            }
        }

        // Auto-enforce status integrity is now handled by InventoryAsset model event (booted())
        // This applies to all saves across all locations/regions

        // Auto-generate PAR only when asset is assigned to a custodian
        if (!empty($validated['assigned_to_user'])) {
            $validated['par_number'] = ParNumberService::generateNextParNumber();
        }

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
            'new_status' => $validated['status'],
            'remarks' => 'Initial entry into inventory',
        ]);

        return response()->json(['success' => true, 'message' => 'Asset added successfully', 'par_number' => $asset->par_number]);
    }
}