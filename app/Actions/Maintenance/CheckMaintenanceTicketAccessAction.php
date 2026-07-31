<?php

namespace App\Actions\Maintenance;

use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\Auth;

class CheckMaintenanceTicketAccessAction
{
    /**
     * Check if the current user can view a maintenance ticket.
     *
     * @param  \App\Models\Request  $trackingRequest
     * @return void
     */
    public function execute(RequestModel $trackingRequest): void
    {
        if (!Auth::user()->can('viewMaintenance', $trackingRequest)) {
            abort(403, 'Unauthorized access to this request.');
        }
    }
}
