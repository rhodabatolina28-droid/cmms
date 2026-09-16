<?php

namespace App\Actions\ICT;

use App\Models\Request as RequestModel;
use App\Models\AuditLog;
use App\Http\Requests\ReviewIctRequest;
use App\Services\RequestNotificationService;
use Illuminate\Support\Facades\Auth;

class ReviewIctTicketAction
{
    /**
     * Division Admin reviews an ICT request (approve/reject).
     *
     * @param  \App\Http\Requests\ReviewIctRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(ReviewIctRequest $request, $id)
    {
        $trackingRequest = RequestModel::findOrFail($id);
        
        $admin = Auth::user();
        $isSuperAdmin = $admin->isSuperAdmin();

        if (!$admin->isDivisionAdmin() && !$isSuperAdmin) {
            return response()->json(['success' => false, 'message' => 'Only Division Admins and System Admins can review requests.'], 403);
        }

        // Verify the request belongs to the admin's scope (division or branch for supply admin)
        $ticketUser = $trackingRequest->user;
        if (!$ticketUser) {
            return response()->json(['success' => false, 'message' => 'Cannot verify request ownership.'], 403);
        }
        
        // Supply admin (Administrative) can review all tickets in branch
        // Regular division admin can only review tickets from their own division
        if ($isSuperAdmin) {
            // D9.20: the System Admin is the reviewer for every division in their
            // branch (ICT requests are routed straight to them) — branch scope.
            if (!\App\Support\RequestHelpers::ticketInSuperAdminBranch($admin, $trackingRequest)) {
                return response()->json(['success' => false, 'message' => 'This request is outside your branch scope.'], 403);
            }
        } elseif ($admin->canProcessSupply()) {
            if ($admin->branch && $ticketUser->branch !== $admin->branch) {
                return response()->json(['success' => false, 'message' => 'This request is outside your branch scope.'], 403);
            }
        } else {
            if ($ticketUser->office !== $admin->office) {
                return response()->json(['success' => false, 'message' => 'This request is outside your division scope.'], 403);
            }
        }

        // Prevent re-review (D9.20: the System Admin may override an already
        // auto-approved ticket — that is their reject path)
        if (!$isSuperAdmin && $trackingRequest->division_admin_review_status !== null) {
            return response()->json(['success' => false, 'message' => 'This request has already been reviewed.'], 422);
        }

        $validated = $request->validated();

        $trackingRequest->update([
            'division_admin_review_status' => $validated['status'],
            'division_admin_notes' => $validated['notes'] ?? null,
            'reviewed_by_admin_id' => $admin->id,
            'reviewed_at' => now(),
        ]);

        if ($validated['status'] === 'Approved') {
            // D9.20: the System Admin IS the reviewer — no self-notification.
            if (!$isSuperAdmin) {
                RequestNotificationService::notifySuperAdminOfForwardedRequest($trackingRequest, $admin);
            }
        } else {
            // If rejected, update the main status to Rejected as well
            $trackingRequest->update(['status' => RequestModel::STATUS_REJECTED]);

            $reviewerLabel = $isSuperAdmin ? 'the System Admin' : 'your Division Admin';
            
            \App\Models\Notification::send(
                $trackingRequest->user_id,
                $trackingRequest->id,
                'Request Rejected',
                "Your ICT Request {$trackingRequest->request_number} was rejected by {$reviewerLabel}. Reason: " . ($validated['notes'] ?: 'No reason provided.')
            );
        }

        $reviewerTitle = $isSuperAdmin ? 'System Admin' : 'Division Admin';

        AuditLog::log(
            "{$reviewerTitle} Review",
            'Requests',
            "{$reviewerTitle} reviewed {$trackingRequest->request_number} (Status: {$validated['status']})",
            $trackingRequest->office
        );

        return response()->json([
            'success' => true,
            'message' => "Request {$validated['status']} successfully."
        ]);
    }
}