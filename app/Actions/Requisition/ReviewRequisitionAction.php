<?php

namespace App\Actions\Requisition;

use App\Models\Requisition;
use App\Models\Request as RequestModel;
use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use App\Support\RequestAuthorization;
use App\Support\RequisitionSupport;
use App\Services\RequestNotificationService;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReviewRequisitionAction
{
    /**
     * Review (approve/reject/issue) a requisition.
     *
     * @param  \Illuminate\Http\Request  $httpRequest
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(Request $httpRequest, $id)
    {
        $supply = Auth::user();
        if (! $supply->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $httpRequest->validated();

        return DB::transaction(function () use ($validated, $supply, $id) {
            $requisition = Requisition::with('ticket', 'requester')->lockForUpdate()->findOrFail($id);

            if (!RequestAuthorization::canSupplyManageRequisition($supply, $requisition)) {
                return response()->json(['success' => false, 'message' => 'This requisition is outside your scope.'], 403);
            }

            $action = $validated['action'];
            $error = RequisitionSupport::validateSupplyAction($action, $requisition->status);
            if ($error) {
                return response()->json(['success' => false, 'message' => $error], 422);
            }

            $newStatus = match ($action) {
                'approve' => Requisition::STATUS_APPROVED,
                'reject' => Requisition::STATUS_REJECTED,
                'issue' => Requisition::STATUS_ISSUED,
            };

            $requisition->update([
                'status' => $newStatus,
                'reviewed_by' => $supply->id,
                'reviewed_at' => now(),
                'remarks' => $validated['remarks'] ?? $requisition->remarks,
            ]);

            $ticket = $requisition->ticket;

            // Auto-revert ticket status when requisition is rejected
            if ($ticket && $action === 'reject' && $ticket->status === RequestModel::STATUS_AWAITING_PARTS) {
                $ticket->update(['status' => RequestModel::STATUS_ONGOING]);
                $ticket->refresh();
                RequestNotificationService::notifyRequestorTicketStatus(
                    $ticket,
                    RequestModel::STATUS_ONGOING,
                    'Parts request was rejected by Supply. Repair is now ongoing without parts.'
                );
            }

            if ($ticket && $action === 'issue' && $ticket->status === RequestModel::STATUS_AWAITING_PARTS) {
                $ticket->update(['status' => RequestModel::STATUS_ONGOING]);
                $ticket->refresh();
                RequestNotificationService::notifyRequestorTicketStatus(
                    $ticket,
                    RequestModel::STATUS_ONGOING,
                    'Parts were issued. Your repair request is ongoing again.'
                );
            }

            if ($requisition->status === Requisition::STATUS_ISSUED && $ticket && $ticket->linked_asset_id) {
                $asset = InventoryAsset::find($ticket->linked_asset_id);
                if ($asset) {
                    InventoryHistory::create([
                        'asset_id' => $asset->asset_id,
                        'action' => 'Parts Issued',
                        'performed_by' => $supply->id,
                        'previous_user_id' => $asset->assigned_to_user,
                        'new_user_id' => $asset->assigned_to_user,
                        'previous_status' => $asset->status,
                        'new_status' => $asset->status,
                        'remarks' => "Administrative supply admin issued requisition #{$requisition->id} for ICT request {$ticket->request_number}.",
                    ]);
                }
            }

            if ($requisition->requested_by && $ticket) {
                RequestNotificationService::notifyItOfRequisitionAction($requisition, $action);
            }

            AuditLog::log(
                'Reviewed Requisition',
                'Requisitions',
                ucfirst($action) . " requisition #{$requisition->id} for {$ticket->request_number}",
                $ticket->region ?? $supply->region
            );

            $labels = [
                'approve' => 'Approved',
                'reject' => 'Rejected',
                'issue' => 'Issued',
            ];

            return response()->json([
                'success' => true,
                'message' => 'Requisition marked as ' . $labels[$action] . '.',
                'redirect' => route('requisitions.show', $requisition->id),
            ]);
        });
    }
}
