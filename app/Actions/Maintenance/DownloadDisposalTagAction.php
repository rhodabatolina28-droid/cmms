<?php

namespace App\Actions\Maintenance;

use App\Models\PreventiveMaintenance;
use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class DownloadDisposalTagAction
{
    /**
     * Generate and download a disposal tag PDF for a PM request.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function execute($id)
    {
        $user = Auth::user();

        // Only IT and Super Admin can access the disposal tag
        if (!in_array($user->role, ['it', 'super_admin'])) {
            abort(403, 'Only IT personnel and Super Admin can access the disposal tag.');
        }

        $trackingRequest = RequestModel::with(['linkedAsset', 'user'])->findOrFail($id);

        // Inline checkTicketAccess — verify the user can view this ticket
        if (!Auth::user()->can('viewMaintenance', $trackingRequest)) {
            abort(403, 'Unauthorized access to this request.');
        }

        $maintenance = PreventiveMaintenance::findOrFail($trackingRequest->detail_id);

        if (!$maintenance->disposal_asset_id) {
            abort(404, 'No disposal asset linked to this request.');
        }

        $asset = \App\Models\InventoryAsset::find($maintenance->disposal_asset_id);
        if (!$asset) {
            abort(404, 'Disposal asset not found.');
        }

        if ($asset->status !== \App\Enums\AssetStatus::FOR_DISPOSAL && $asset->status !== 'For Disposal') {
            abort(403, 'This asset has not been marked For Disposal yet.');
        }

        $pdf = Pdf::loadView('pdf.disposal-tag', [
            'request' => $trackingRequest,
            'asset'   => $asset,
            'reason'  => $maintenance->disposal_reason ?? 'Not specified',
            'itUser'  => Auth::user()
        ])->setPaper('a4', 'portrait');

        if (ob_get_length()) {
            ob_end_clean();
        }

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="DisposalTag-' . $trackingRequest->request_number . '.pdf"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
