<?php

namespace App\Actions\ICT;

use App\Models\Requisition;
use App\Models\Request as RequestModel;
use App\Support\RequisitionSupport;
use Illuminate\Support\Facades\Auth;

class ShowIctTicketAction
{
    /**
     * Job Order ticket hub — separate from ICT repair form.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\View\View
     */
    public function execute($id)
    {
        $trackingRequest = RequestModel::with(['assignedTo', 'user'])->findOrFail($id);

        if (!Auth::user()->can('viewIct', $trackingRequest)) {
            abort(403, 'Unauthorized access to this request.');
        }

        if ($trackingRequest->type !== 'ICT') {
            abort(404);
        }

        $user = Auth::user();
        $requisitions = Requisition::with(['requester', 'reviewer'])
            ->where('request_id', $trackingRequest->id)
            ->orderByDesc('created_at')
            ->get();

        $hasMyPendingParts = $requisitions->contains(
            fn ($r) => $r->status === Requisition::STATUS_PENDING
                && (int) $r->requested_by === (int) $user->id
        );

        return view('requests.ict.ticket', [
            'request' => $trackingRequest,
            'requisitions' => $requisitions,
            'canRequestPartsOnTicket' => in_array($user->role, ['it', 'super_admin'])
                && RequisitionSupport::canItSubmitForTicket($user, $trackingRequest)
                && !$hasMyPendingParts,
            'canOpenIctForm' => $user->can('viewIct', $trackingRequest),
            'canEditIctForm' => $user->can('updateIct', $trackingRequest),
        ]);
    }
}
