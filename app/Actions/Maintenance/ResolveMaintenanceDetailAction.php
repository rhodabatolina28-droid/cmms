<?php

namespace App\Actions\Maintenance;

use App\Models\PreventiveMaintenance;
use App\Models\Request as RequestModel;

class ResolveMaintenanceDetailAction
{
    /**
     * Resolve (or repair) the PreventiveMaintenance record for a tracking request.
     * If detail_id is null (old/broken auto-generated records), create one on-the-fly
     * and save the link so future views don't crash.
     *
     * @param  \App\Models\Request  $trackingRequest
     * @return \App\Models\PreventiveMaintenance
     */
    public function execute(RequestModel $trackingRequest): PreventiveMaintenance
    {
        if ($trackingRequest->detail_id) {
            $pm = PreventiveMaintenance::find($trackingRequest->detail_id);
            if ($pm) {
                return $pm;
            }
        }

        $pm = PreventiveMaintenance::create([
            'form_no'           => $trackingRequest->request_number,
            'end_user_name'     => $trackingRequest->requestor_name ?? 'Auto-generated',
            'end_user_division' => $trackingRequest->office ?? '',
            'maintenance_date'  => $trackingRequest->created_at?->toDateString() ?? now()->toDateString(),
        ]);

        $trackingRequest->update(['detail_id' => $pm->id]);

        return $pm;
    }
}
