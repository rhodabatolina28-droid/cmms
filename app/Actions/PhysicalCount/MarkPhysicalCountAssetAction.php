<?php

namespace App\Actions\PhysicalCount;

use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\PhysicalCount;
use App\Models\PhysicalCountSession;
use App\Http\Requests\MarkAssetPhysicalCountRequest;
use Illuminate\Support\Facades\Auth;

class MarkPhysicalCountAssetAction
{
    /**
     * Mark an asset as counted in a physical count session.
     *
     * @param  \App\Http\Requests\MarkAssetPhysicalCountRequest  $request
     * @param  int  $sessionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(MarkAssetPhysicalCountRequest $request, $sessionId)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $session = PhysicalCountSession::findOrFail($sessionId);
        if ($session->status !== 'Ongoing') {
            return response()->json(['success' => false, 'message' => 'Session is already completed.'], 422);
        }

        $validated = $request->validated();
        $asset = InventoryAsset::findOrFail($validated['asset_id']);

        $existing = PhysicalCount::where('session_id', $sessionId)
            ->where('asset_id', $asset->asset_id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => "Asset already counted as {$existing->status}. Cannot change status.",
            ], 422);
        }

        $count = PhysicalCount::create([
            'session_id' => $sessionId,
            'asset_id'   => $asset->asset_id,
            'counted_by' => $user->id,
            'status'     => $validated['status'],
            'remarks'    => $validated['remarks'] ?? null,
            'counted_at' => now(),
        ]);

        AuditLog::log(
            'Physical Count',
            'Inventory',
            "Asset #{$asset->asset_id} ({$asset->item_name}) marked as {$validated['status']}",
            $asset->office
        );

        return response()->json(['success' => true, 'message' => "Asset marked as {$validated['status']}."]);
    }
}
