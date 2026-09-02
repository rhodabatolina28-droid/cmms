<?php

namespace App\Actions\PhysicalCount;

use App\Models\InventoryAsset;
use App\Models\Scopes\InventoryScope;
use App\Models\PhysicalCount;
use App\Models\PhysicalCountSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchPhysicalCountAssetAction
{
    /**
     * Search assets for physical counting (QR scan or text search).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $sessionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(Request $request, $sessionId)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $session = PhysicalCountSession::findOrFail($sessionId);
        if ($session->status !== 'Ongoing') {
            return response()->json(['success' => false, 'message' => 'Session is already completed.'], 422);
        }

        $q = trim($request->input('q', ''));
        $assetId = $request->input('asset_id');

        if (!$assetId && strlen($q) >= 1) {
            $idMatch = preg_match('/^ID[:\s]*(\d+)$/i', $q, $m);
            if ($idMatch) {
                $assetId = (int) $m[1];
            } else {
                $urlMatch = preg_match('/\/r\/(\d+)(?:\/|\?|$)/i', $q, $urlM);
                if ($urlMatch) {
                    $assetId = (int) $urlM[1];
                } else {
                    $decoded = json_decode($q, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($decoded['id'])) {
                        $assetId = (int) $decoded['id'];
                    }
                }
            }
        }

        $query = InventoryAsset::with('assignedUser');
        InventoryScope::scopeAssetsToActor($query, $user);

        if ($assetId) {
            $query->where('asset_id', $assetId);
        } elseif (strlen($q) >= 1) {
            $q = strtolower($q);
            $query->where(function ($qry) use ($q) {
                $qry->whereRaw('LOWER(item_name) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(serial_number) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(par_number) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(property_number) LIKE ?', ["%{$q}%"])
                    ->orWhereHas('assignedUser', function ($uq) use ($q) {
                        $uq->whereRaw('LOWER(full_name) LIKE ?', ["%{$q}%"]);
                    });
            });
        }

        $assets = $query->orderBy('item_name')->limit(20)->get();

        $countedIds = PhysicalCount::where('session_id', $sessionId)
            ->pluck('asset_id')
            ->toArray();

        $userAssets = collect();
        $scannedUserId = null;
        if ($assetId) {
            $scannedAsset = InventoryAsset::find($assetId);
            if ($scannedAsset && $scannedAsset->assigned_to_user) {
                $scannedUserId = $scannedAsset->assigned_to_user;
                $userAssetsQuery = InventoryAsset::with('assignedUser')
                    ->where('assigned_to_user', $scannedUserId)
                    ->where('asset_id', '!=', $assetId)
                    ->whereNotIn('status', ['For Disposal', 'Scrapped']);
                InventoryScope::scopeAssetsToActor($userAssetsQuery, $user);
                $userAssets = $userAssetsQuery->orderBy('category')
                    ->orderBy('item_name')
                    ->get();
            }
        }

        // Custodian group: text query matching exactly ONE user's full name
        // returns that user's whole assigned set (assigned-only, scope-checked,
        // no For Disposal/Scrapped). Multiple matches → null (flat list only,
        // prevents wrong bulk marking).
        $custodianGroup = null;
        if (!$assetId && strlen($q) >= 1) {
            $matchingUsers = User::whereRaw('LOWER(full_name) LIKE ?', ['%' . strtolower($q) . '%'])->get();

            if ($matchingUsers->count() === 1) {
                $groupUser = $matchingUsers->first();
                $groupQuery = InventoryAsset::with('assignedUser')
                    ->where('assigned_to_user', $groupUser->id)
                    ->whereNotIn('status', ['For Disposal', 'Scrapped']);
                InventoryScope::scopeAssetsToActor($groupQuery, $user);
                $groupAssets = $groupQuery->orderBy('category')
                    ->orderBy('item_name')
                    ->get();

                $custodianGroup = [
                    'user_id'   => $groupUser->id,
                    'full_name' => $groupUser->full_name,
                    'total'     => $groupAssets->count(),
                    'assets'    => $groupAssets,
                ];
            }
        }

        return response()->json([
            'success'    => true,
            'assets'     => $assets,
            'counted_ids' => $countedIds,
            'user_assets' => $userAssets,
            'scanned_user_id' => $scannedUserId,
            'custodian_group' => $custodianGroup,
        ]);
    }
}
