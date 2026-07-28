<?php

namespace App\Actions\ICT;

use App\Models\RepairRequest;
use App\Models\Request as RequestModel;
use App\Support\RequestAuthorization;
use Illuminate\Support\Facades\Auth;

class EditIctRequestAction
{
    /**
     * Show an existing ICT request for editing.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\View\View
     */
    public function execute($id)
    {
        $trackingRequest = RequestModel::with('assignedTo')->findOrFail($id);

        if (!RequestAuthorization::canViewIctTicket(Auth::user(), $trackingRequest)) {
            abort(403, 'Unauthorized access to this request.');
        }

        $user = Auth::user();
        if (!RequestAuthorization::canUpdateIctTicket($user, $trackingRequest) && !RequestAuthorization::canViewIctTicket($user, $trackingRequest)) {
            abort(403, 'You cannot edit this request.');
        }

        $repairRequest = RepairRequest::findOrFail($trackingRequest->detail_id);

        return view('requests.ict.form', (new BuildIctFormViewDataAction)->execute(
            $trackingRequest,
            $repairRequest,
            !RequestAuthorization::canUpdateIctTicket($user, $trackingRequest)
        ));
    }
}
