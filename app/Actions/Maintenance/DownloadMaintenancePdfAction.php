<?php

namespace App\Actions\Maintenance;

use App\Models\PreventiveMaintenance;
use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class DownloadMaintenancePdfAction
{
    /**
     * Generate and download a PM maintenance form PDF.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function execute($id)
    {
        $trackingRequest = RequestModel::findOrFail($id);

        // Inline checkTicketAccess — verify the user can view this ticket
        if (!Auth::user()->can('viewMaintenance', $trackingRequest)) {
            abort(403, 'Unauthorized access to this request.');
        }

        $maintenance = PreventiveMaintenance::findOrFail($trackingRequest->detail_id);
        $tasks = json_decode($maintenance->maintenance_tasks_json ?? '{}', true) ?: [];

        $pdf = Pdf::loadView('pdf.maintenance-form', [
            'request' => $trackingRequest,
            'pm' => $maintenance,
            'tasks' => $tasks,
        ])->setPaper('legal', 'portrait');

        if (ob_get_length()) {
            ob_end_clean();
        }
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="PM-' . $trackingRequest->request_number . '.pdf"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
