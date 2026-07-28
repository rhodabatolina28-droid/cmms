<?php

namespace App\Actions\Requisition;

use App\Models\Request as RequestModel;
use App\Models\Requisition;
use App\Support\RequisitionSupport;
use Illuminate\Support\Facades\Auth;

class CreateRequisitionForTicketAction
{
    /**
     * Redirect IT/Super Admin to the requisition form for a ticket.
     *
     * @param  int  $requestId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function execute($requestId)
    {
        $user = Auth::user();

        // IT or Super Admin acting as IT (assigned to ticket) can request parts
        abort_unless(in_array($user->role, ['it', 'super_admin']), 403);

        $ticket = RequestModel::findOrFail($requestId);
        abort_unless(
            RequisitionSupport::canItSubmitForTicket($user, $ticket),
            403,
            'Parts requests are only for ICT tickets assigned to you.'
        );

        if ($ticket->status === RequestModel::STATUS_COMPLETED) {
            return redirect()
                ->route('requisitions.index')
                ->with('error', 'This ticket is already completed.');
        }

        $hasPending = Requisition::where('request_id', $ticket->id)
            ->where('requested_by', $user->id)
            ->where('status', Requisition::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            return redirect()
                ->route('requisitions.index', ['request_id' => $ticket->id])
                ->with('info', 'This ticket already has a pending parts request. You may continue through your request history.');
        }

        return redirect()->route('requisitions.index', ['request_id' => $ticket->id]);
    }
}
