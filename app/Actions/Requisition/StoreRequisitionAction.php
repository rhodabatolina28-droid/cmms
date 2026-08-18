<?php

namespace App\Actions\Requisition;

use App\Models\Requisition;
use App\Models\Request as RequestModel;
use App\Support\RequisitionSupport;
use App\Services\RequestNotificationService;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StoreRequisitionAction
{
    /**
     * Store a new requisition for a ticket.
     *
     * @param  \Illuminate\Http\Request  $httpRequest
     * @param  int  $requestId
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(Request $httpRequest, $requestId)
    {
        $user = Auth::user();

        // IT or Super Admin acting as IT can request parts
        if (!in_array($user->role, ['it', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'Only IT personnel or Super Admin (acting as IT) can request parts.'], 403);
        }

        $ticket = RequestModel::findOrFail($requestId);

        if (!RequisitionSupport::canItSubmitForTicket($user, $ticket)) {
            return response()->json(['success' => false, 'message' => 'You can only request parts for ICT or an eligible PM job order assigned to you.'], 403);
        }

        $issueContext = RequisitionSupport::ticketIssueContext($ticket);
        if (! $issueContext['valid']) {
            return response()->json(['success' => false, 'message' => $issueContext['message']], 422);
        }

        $validated = $httpRequest->validated();

        return DB::transaction(function () use ($validated, $ticket, $user) {
            Requisition::where('request_id', $ticket->id)
                ->where('requested_by', $user->id)
                ->where('status', Requisition::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            $existing = RequisitionSupport::findExistingSubmission(
                $ticket,
                $user,
                $validated['submission_id'] ?? null
            );

            if ($existing) {
                return response()->json($existing);
            }

            $requisition = Requisition::create(
                RequisitionSupport::buildCreatePayload($ticket, $user, $validated)
            );

            if (!empty($validated['set_awaiting_parts'])) {
                $ticket->update(['status' => RequestModel::STATUS_AWAITING_PARTS]);
                $ticket->refresh();
                RequestNotificationService::notifyRequestorTicketStatus(
                    $ticket,
                    RequestModel::STATUS_AWAITING_PARTS,
                    'IT requested parts from Supply Office. Your ticket is awaiting parts.'
                );
            }

            RequestNotificationService::notifySupplyOfficersOfRequisition($requisition);

            AuditLog::log(
                'Created Requisition',
                'Requisitions',
                "Parts request for {$ticket->request_number}",
                $ticket->region
            );

            return response()->json([
                'success' => true,
                'message' => 'Sent to Supply Office. You will be notified when they approve, issue, or reject.',
                'requisition_id' => $requisition->id,
            ]);
        });
    }
}
