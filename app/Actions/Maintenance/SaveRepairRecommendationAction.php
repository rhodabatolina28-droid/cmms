<?php

namespace App\Actions\Maintenance;

use App\Models\AuditLog;
use App\Models\PreventiveMaintenance;
use App\Models\Request as RequestModel;
use App\Support\RequisitionSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Lightweight endpoint so assigned IT/Super Admin can save the FOR REPAIR
 * recommendation (checkbox + selected repair asset + parts notes) WITHOUT
 * completing the whole PM form (signatures, checklist, etc.).
 *
 * Persisting the repair selection links the asset to the PM ticket
 * (requests.linked_asset_id), which makes the ticket parts-requestable in the
 * Material Requisition flow.
 */
class SaveRepairRecommendationAction
{
    public function execute(Request $request, $id)
    {
        $user = Auth::user();

        $ticket = RequestModel::findOrFail($id);

        if (!$user->can('updateMaintenance', $ticket)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to update this request.',
            ], 403);
        }

        if ($ticket->status === RequestModel::STATUS_COMPLETED) {
            return response()->json([
                'success' => false,
                'message' => 'This request is already completed and cannot be modified.',
            ], 403);
        }

        $data = $request->validate([
            'for_repair' => 'required|in:YES,NO',
            'repair_asset_id' => 'nullable|integer|exists:inventory_assets,asset_id',
            'repair_parts' => 'nullable|string|max:2000',
        ]);

        $maintenance = (new ResolveMaintenanceDetailAction)->execute($ticket);

        $yes = $data['for_repair'] === 'YES';

        if ($yes && empty($data['repair_asset_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Please select the specific asset to tag for repair.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            if ($yes) {
                $assetId = (int) $data['repair_asset_id'];

                $maintenance->for_repair = 'YES';
                $maintenance->repair_asset_id = $assetId;
                if (array_key_exists('repair_parts', $data)) {
                    $maintenance->repair_parts = $data['repair_parts'];
                }
                $maintenance->save();

                if ((int) $ticket->linked_asset_id !== $assetId) {
                    $ticket->linked_asset_id = $assetId;
                    $ticket->save();

                    AuditLog::log(
                        'Linked Repair Asset (PM)',
                        'Requests',
                        'Linked asset for repair via PM request ' . $ticket->request_number
                            . ' (previous linked asset: ' . ($ticket->getOriginal('linked_asset_id') ?? 'none') . ')',
                        $ticket->office
                    );
                }
            } else {
                $oldRepairAssetId = $maintenance->repair_asset_id;

                $maintenance->for_repair = 'NO';
                $maintenance->repair_asset_id = null;
                if (array_key_exists('repair_parts', $data)) {
                    $maintenance->repair_parts = $data['repair_parts'];
                }
                $maintenance->save();

                // Unlink only when the current linkage came from the repair selection.
                if ($oldRepairAssetId && (int) $ticket->linked_asset_id === (int) $oldRepairAssetId) {
                    $ticket->linked_asset_id = null;
                    $ticket->save();
                }

                AuditLog::log(
                    'Cleared Repair Recommendation (PM)',
                    'Requests',
                    'Cleared FOR REPAIR recommendation via PM request ' . $ticket->request_number,
                    $ticket->office
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        $ticket->refresh();

        return response()->json([
            'success' => true,
            'message' => $yes
                ? 'Repair recommendation saved. This PM ticket can now be used for a parts request.'
                : 'Repair recommendation cleared.',
            'for_repair' => $maintenance->for_repair,
            'repair_asset_id' => $maintenance->repair_asset_id,
            'linked_asset_id' => $ticket->linked_asset_id,
            'can_request_parts' => RequisitionSupport::canItSubmitForTicket($user, $ticket),
        ]);
    }
}
