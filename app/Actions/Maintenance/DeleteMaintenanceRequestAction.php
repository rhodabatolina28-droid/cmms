<?php

namespace App\Actions\Maintenance;

use App\Models\AuditLog;
use App\Models\PreventiveMaintenance;
use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeleteMaintenanceRequestAction
{
    /**
     * Delete a PM request (Super Admin only).
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
            if (!\App\Support\RequestHelpers::ticketInSuperAdminBranch($user, $trackingRequest)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Request is outside your branch scope.'], 403);
            }
            $maintenance = $trackingRequest->detail_id
                ? PreventiveMaintenance::find($trackingRequest->detail_id)
                : null;

            if ($maintenance) {
                $maintenance->delete();
            }
            $trackingRequest->delete();

            AuditLog::log("Deleted PM Request", "Requests", "Deleted PM request {$trackingRequest->request_number}", $trackingRequest->office);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Maintenance request deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
