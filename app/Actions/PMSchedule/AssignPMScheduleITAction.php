<?php

namespace App\Actions\PMSchedule;

use App\Models\PMSchedule;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AssignPMScheduleITAction
{
    /**
     * Assign an IT personnel to a PM Schedule for the current division.
     * All existing PM work orders in the current division are updated
     * with the assigned IT. Future work orders will also use this IT.
     *
     * Both Super Admin and IT can perform this assignment.
     *
     * @param  PMSchedule  $schedule
     * @param  int  $itUserId
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(PMSchedule $schedule, int $itUserId)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['super_admin', 'it'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $itUser = User::find($itUserId);
        if (!$itUser || !in_array($itUser->role, ['it', 'admin', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'Selected user is not an IT personnel.'], 422);
        }

        // Update the schedule's assigned IT
        $schedule->update(['assigned_it_id' => $itUserId]);

        // Update all existing PM work orders in the current division
        $focusDivision = $schedule->current_focus_division;
        $updatedCount = 0;

        if ($focusDivision) {
            $updatedCount = RequestModel::where('pm_schedule_id', $schedule->id)
                ->where('is_auto_generated', true)
                ->where('office', $focusDivision)
                ->whereIn('status', ['Scheduled', 'Ongoing', 'Awaiting Signature'])
                ->update(['assigned_to' => $itUserId]);
        }

        AuditLog::log(
            'PM IT Assigned',
            'PM Schedule',
            "{$user->full_name} assigned {$itUser->full_name} to schedule '{$schedule->schedule_name}'"
                . ($focusDivision ? " for division {$focusDivision}" : '')
                . " ({$updatedCount} work order(s) updated)",
            $user->branch ?? 'System'
        );

        Log::info("PM Schedule #{$schedule->id} assigned to IT #{$itUserId}. Updated {$updatedCount} work orders.");

        return response()->json([
            'success' => true,
            'message' => "Assigned to {$itUser->full_name}."
                . ($updatedCount > 0 ? " {$updatedCount} work order(s) updated." : ''),
            'updated_count' => $updatedCount,
        ]);
    }
}