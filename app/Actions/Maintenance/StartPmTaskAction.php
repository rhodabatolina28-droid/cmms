<?php

namespace App\Actions\Maintenance;

use App\Models\Request as RequestModel;
use App\Support\RequestAuthorization;
use Illuminate\Support\Facades\Auth;

class StartPmTaskAction
{
    /**
     * Start a PM task (change status from Scheduled to Ongoing).
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function execute($id)
    {
        $trackingRequest = RequestModel::findOrFail($id);
        $user = Auth::user();

        if (!RequestAuthorization::canUpdateMaintenanceTicket($user, $trackingRequest)) {
            abort(403, 'You cannot start this PM task.');
        }

        if ($trackingRequest->status === RequestModel::STATUS_SCHEDULED
            && (int) $trackingRequest->assigned_to === (int) $user->id) {
            $trackingRequest->update(['status' => RequestModel::STATUS_ONGOING]);
        }

        return redirect()->route('maintenance.edit', $id);
    }
}
