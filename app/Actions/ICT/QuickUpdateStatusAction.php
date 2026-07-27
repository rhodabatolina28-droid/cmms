<?php

namespace App\Actions\ICT;

use App\Models\Request as RequestModel;
use App\Models\AuditLog;
use App\Http\Requests\UpdateIctStatusRequest;
use App\Support\RequestAuthorization;
use Illuminate\Support\Facades\Auth;

class QuickUpdateStatusAction
{
    /**
     * Quick-update the status of an ICT/maintenance request (Admin only).
     *
     * @param  \App\Http\Requests\UpdateIctStatusRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(UpdateIctStatusRequest $request)
    {
        $admin = Auth::user();
        if ($admin->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validated();

        $trackingRequest = RequestModel::with('assignedTo')->findOrFail($validated['id']);

        if (!RequestAuthorization::canAdminQuickUpdateStatus($admin, $trackingRequest, $validated['status'])) {
            $hint = empty($trackingRequest->assigned_to) && $validated['status'] === RequestModel::STATUS_ONGOING
                ? ' Assign IT personnel first (View ticket → Assign IT).'
                : ($validated['status'] === RequestModel::STATUS_COMPLETED
                    ? ' Completed status is set by the end-user (ICT acceptance) or IT technician signature (PM), not via quick update.'
                    : '');

            return response()->json([
                'success' => false,
                'message' => 'This status change is not allowed.' . $hint,
            ], 422);
        }

        $trackingRequest->update([
            'status' => $validated['status'],
            'remarks' => $validated['remarks'],
        ]);

        $typeLabel = $trackingRequest->type === 'ICT' ? 'ICT request' : 'maintenance request';

        \App\Models\Notification::send(
            $trackingRequest->user_id,
            $trackingRequest->id,
            "Request {$validated['status']}",
            "Your {$typeLabel} {$trackingRequest->request_number} has been updated to {$validated['status']}."
        );

        AuditLog::log(
            'Updated Request Status',
            'Requests',
            "Quick-updated {$trackingRequest->request_number} to {$validated['status']}",
            $trackingRequest->office
        );

        return response()->json(['success' => true, 'message' => 'Request status updated successfully']);
    }
}