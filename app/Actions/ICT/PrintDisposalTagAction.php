<?php

namespace App\Actions\ICT;

use App\Models\RepairRequest;
use App\Models\Request as RequestModel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class PrintDisposalTagAction
{
    /**
     * Print the disposal tag PDF for an ICT request.
     *
     * @param  \App\Models\Request  $trackingRequest
     * @return \Illuminate\Http\Response
     */
    public function execute($trackingRequest)
    {
        $repairRequest = RepairRequest::findOrFail($trackingRequest->detail_id);

        if (!$trackingRequest->linkedAsset) {
            abort(404, 'No asset linked to this request.');
        }

        if ($trackingRequest->linkedAsset->status !== \App\Enums\AssetStatus::FOR_DISPOSAL) {
            abort(403, 'This asset has not been marked For Disposal yet.');
        }

        $pdf = Pdf::loadView('pdf.disposal-tag', [
            'request' => $trackingRequest,
            'repairRequest' => $repairRequest,
            'asset' => $trackingRequest->linkedAsset,
            'itUser' => Auth::user()
        ]);

        // Small tag size or A4? Let's use A4 but layout as a card/tag.
        $pdf->setPaper('A4', 'portrait');

        if (ob_get_length()) {
            ob_end_clean();
        }
        
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="DISPOSAL-TAG-' . $trackingRequest->request_number . '.pdf"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}