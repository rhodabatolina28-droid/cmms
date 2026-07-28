<?php

namespace App\Actions\ICT;

use App\Models\RepairRequest;
use App\Models\Request as RequestModel;
use App\Support\RequestAuthorization;
use Illuminate\Support\Facades\Auth;

class ShowIctRequestAction
{
    /**
     * Show an existing ICT request for viewing.
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

        $repairRequest = RepairRequest::findOrFail($trackingRequest->detail_id);
        $user = Auth::user();
        $forceView = !RequestAuthorization::canUpdateIctTicket($user, $trackingRequest);

        return view('requests.ict.form', (new BuildIctFormViewDataAction)->execute($trackingRequest, $repairRequest, $forceView));
    }
}
