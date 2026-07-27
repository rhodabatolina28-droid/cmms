<?php

namespace App\Actions\ICT;

use App\Models\RepairRequest;
use App\Models\Request as RequestModel;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeleteIctTicketAction
{
    /**
     * Soft-delete an ICT request (Super Admin only).
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute($id)
    {
        $user = Auth::user();
        if ($user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Only Super Admins can delete requests.'], 403);
        }

        try {
            DB::beginTransaction();
            $trackingRequest = RequestModel::findOrFail($id);
            if (!\App\Support\RequestAuthorization::ticketInSuperAdminBranch($user, $trackingRequest)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Request is outside your branch scope.'], 403);
            }
            $repairRequest = RepairRequest::findOrFail($trackingRequest->detail_id);
            
            // Due to Soft Deletes implementation, we do NOT delete signature files from storage anymore.
            // This ensures signatures remain intact if the request is restored.

            $repairRequest->delete();
            $trackingRequest->delete();

            AuditLog::log("Deleted ICT Request", "Requests", "Deleted ICT request {$trackingRequest->request_number}", $trackingRequest->office);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'ICT request deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}