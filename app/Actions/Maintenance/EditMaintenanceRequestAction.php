<?php

namespace App\Actions\Maintenance;

use App\Models\PreventiveMaintenance;
use App\Models\Request as RequestModel;
use App\Support\RequestAuthorization;
use Illuminate\Support\Facades\Auth;

class EditMaintenanceRequestAction
{
    /**
     * Show an existing PM request for editing.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\View\View
     */
    public function execute($id)
    {
        $trackingRequest = RequestModel::with('assignedTo')->findOrFail($id);

        (new CheckMaintenanceTicketAccessAction)->execute($trackingRequest);

        $user = Auth::user();
        if (!RequestAuthorization::canUpdateMaintenanceTicket($user, $trackingRequest)
            && !RequestAuthorization::canViewMaintenanceTicket($user, $trackingRequest)) {
            abort(403, 'You cannot edit this maintenance request.');
        }

        $maintenance = (new ResolveMaintenanceDetailAction)->execute($trackingRequest);

        return view('requests.maintenance.form', (new BuildMaintenanceFormViewDataAction)->execute(
            $trackingRequest,
            $maintenance,
            !RequestAuthorization::canUpdateMaintenanceTicket($user, $trackingRequest)
        ));
    }
}
