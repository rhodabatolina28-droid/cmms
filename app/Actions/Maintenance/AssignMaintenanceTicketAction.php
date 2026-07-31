<?php

namespace App\Actions\Maintenance;

use App\Models\User;
use App\Models\AuditLog;
use App\Models\Request as RequestModel;
use App\Http\Requests\AssignItRequest;
use App\Services\RequestNotificationService;
use Illuminate\Support\Facades\Auth;

class AssignMaintenanceTicketAction
{
    /**
     * Assign (or unassign) an IT personnel to a PM request.
     *
     * @param  \App\Http\Requests\AssignItRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(AssignItRequest $request, $id)
    {
        $trackingRequest = RequestModel::with('assignedTo')->findOrFail($id);

        // Inline checkTicketAccess — verify the user can view this ticket
        if (!Auth::user()->can('viewMaintenance', $trackingRequest)) {
            abort(403, 'Unauthorized access to this request.');
        }

        $admin = Auth::user();
        if (!$admin->can('assignTicket', $trackingRequest)) {
            return response()->json(['success' => false, 'message' => 'You cannot assign this request.'], 403);
        }

        $validated = $request->validated();

        $itId = $validated['assigned_to'] ?? null;

        // Super Admin can assign himself if no IT available or IT not present
        if ($itId) {
            $itUser = User::findOrFail($itId);

            // Allow Super Admin to assign himself
            if ((int) $itId === (int) $admin->id) {
                // Super Admin assigning himself - allowed
                if ($admin->role !== 'super_admin') {
                    return response()->json(['success' => false, 'message' => 'Only Super Admin can assign themselves.'], 422);
                }
            } else {
                // Assigning someone else - check if IT role and in scope
                if ($itUser->role !== 'it') {
                    return response()->json(['success' => false, 'message' => 'Selected user must have IT role.'], 422);
                }

                if ($admin->role !== 'super_admin' && !\App\Support\RequestHelpers::itUserInAdminScope($admin, $itUser)) {
                    return response()->json(['success' => false, 'message' => 'Selected IT personnel is not in your scope.'], 422);
                }
            }
        }

        $previousId = $trackingRequest->assigned_to;
        $updates = ['assigned_to' => $itId];
        
        $trackingRequest->update($updates);
        $trackingRequest->refresh();

        if ($itId && (int) $previousId !== (int) $itId) {
            $itUser = User::findOrFail($itId);
            RequestNotificationService::notifyItAssigned($trackingRequest, $itUser);
            RequestNotificationService::notifyRequestorItAssigned($trackingRequest, $itUser);

            AuditLog::log(
                'Assigned PM Request',
                'Requests',
                "Assigned {$trackingRequest->request_number} to " . ($itUser->role === 'super_admin' ? 'Super Admin' : 'IT') . " user #{$itId}",
                $trackingRequest->office
            );
        } elseif (!$itId && $previousId) {
            AuditLog::log(
                'Unassigned PM Request',
                'Requests',
                "Removed IT assignment from {$trackingRequest->request_number}",
                $trackingRequest->office
            );
        }

        return response()->json([
            'success' => true,
            'message' => $itId ? 'Personnel assigned successfully.' : 'Assignment cleared.',
            'assigned_name' => $itId ? User::find($itId)?->full_name : null,
        ]);
    }
}
