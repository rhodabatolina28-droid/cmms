<?php

namespace App\Actions\ICT;

use App\Models\RepairRequest;
use App\Models\Request as RequestModel;
use Barryvdh\DomPDF\Facade\Pdf;

class DownloadIctPdfAction
{
    /**
     * Download the ICT request form as PDF.
     *
     * @param  \App\Models\Request  $trackingRequest
     * @return \Illuminate\Http\Response
     */
    public function execute($trackingRequest)
    {
        $repairRequest = RepairRequest::findOrFail($trackingRequest->detail_id);
        
        $pdf = Pdf::loadView('pdf.ict-form', [
            'request' => $trackingRequest,
            'repairRequest' => $repairRequest
        ]);

        if (ob_get_length()) {
            ob_end_clean();
        }
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $trackingRequest->request_number . '.pdf"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}