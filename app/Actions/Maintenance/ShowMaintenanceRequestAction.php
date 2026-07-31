<?php

namespace App\Actions\Maintenance;

use App\Models\PreventiveMaintenance;
use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\Auth;

class ShowMaintenanceRequestAction
{
    /**
     * Show an existing PM request for viewing.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\View\View
     */
    public function execute($id)
    {
        $trackingRequest = RequestModel::with('assignedTo')->findOrFail($id);

        (new CheckMaintenanceTicketAccessAction)->execute($trackingRequest);

        $maintenance = (new ResolveMaintenanceDetailAction)->execute($trackingRequest);

        $user = Auth::user();
        $forceView = !$user->can('updateMaintenance', $trackingRequest);

        return view('requests.maintenance.form', (new BuildMaintenanceFormViewDataAction)->execute($trackingRequest, $maintenance, $forceView));
    }
}
