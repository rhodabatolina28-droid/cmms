<?php

namespace App\Actions\Inventory;

use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use App\Models\AuditLog;
use App\Http\Requests\ConfirmDisposalRequest;
use Illuminate\Support\Facades\Auth;

class ConfirmAssetDisposalAction
{
    /**
     * Supply Officer confirms physical disposal — sets asset to Scrapped (permanent lock).
     *
     * @param  \App\Http\Requests\ConfirmDisposalRequest  $request
     * @param  int  $assetId
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(ConfirmDisposalRequest $request, $assetId)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Only the supply officer can confirm disposal.'], 403);
        }

        $asset = InventoryAsset::findOrFail($assetId);

        if ($user->region && $asset->region !== $user->region) {
            return response()->json(['success' => false, 'message' => 'Asset is outside your region scope.'], 403);
        }
        if ($user->branch && $asset->branch !== $user->branch) {
            return response()->json(['success' => false, 'message' => 'Asset is outside your branch scope.'], 403);
        }

        if ($asset->status !== 'For Disposal') {
            return response()->json(['success' => false, 'message' => 'Asset must be tagged "' . \App\Enums\AssetStatus::FOR_DISPOSAL . '" before confirming scrapped.'], 422);
        }

        $validated = $request->validated();

        $remarks = $validated['remarks'] ?? 'Physical disposal confirmed by Supply Officer.';

        $previousUser = $asset->assigned_to_user;

        $asset->update([
            'status'           => 'Scrapped',
            'assigned_to_user' => null,
        ]);

        InventoryHistory::create([
            'asset_id'        => $asset->asset_id,
            'action'          => 'Disposal Confirmed — Scrapped',
            'performed_by'    => $user->id,
            'previous_user_id'=> $previousUser,
            'new_user_id'     => null,
            'previous_status' => 'For Disposal',
            'new_status'      => 'Scrapped',
            'remarks'         => $remarks,
        ]);

        AuditLog::log(
            'Asset Scrapped',
            'Inventory',
            "Supply Officer confirmed disposal of {$asset->item_name} (SN: {$asset->serial_number}). Asset is now Scrapped.",
            $asset->office
        );

        return response()->json(['success' => true, 'message' => 'Asset confirmed as Scrapped. Record is now permanently locked.']);
    }
}