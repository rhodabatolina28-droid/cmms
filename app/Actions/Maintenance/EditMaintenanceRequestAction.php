<?php

namespace App\Actions\Maintenance;

use App\Models\PreventiveMaintenance;
use App\Models\Request as RequestModel;
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
        if (!$user->can('updateMaintenance', $trackingRequest)
            && !$user->can('viewMaintenance', $trackingRequest)) {
            abort(403, 'You cannot edit this maintenance request.');
        }

        $maintenance = (new ResolveMaintenanceDetailAction)->execute($trackingRequest);

        return view('requests.maintenance.form', (new BuildMaintenanceFormViewDataAction)->execute(
            $trackingRequest,
            $maintenance,
            !$user->can('updateMaintenance', $trackingRequest)
        ));
    }
}
