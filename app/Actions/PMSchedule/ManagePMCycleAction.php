<?php

namespace App\Actions\PMSchedule;

use App\Models\PMSchedule;
use App\Models\AuditLog;
use App\Models\PMCycle;
use Illuminate\Support\Facades\Auth;

class ManagePMCycleAction
{
    /**
     * Manage PM cycle state: pause, resume, or stop.
     *
     * @param  string  $action  'pause', 'resume', or 'stop'
     * @param  \App\Models\PMSchedule  $schedule
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(string $action, PMSchedule $schedule)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        switch ($action) {
            case 'pause':
                return $this->pause($schedule);
            case 'resume':
                return $this->resume($schedule);
            case 'stop':
                return $this->stop($schedule);
            default:
                return response()->json(['success' => false, 'message' => 'Invalid action.'], 422);
        }
    }

    /**
     * Pause the current PM cycle - halts auto-advance.
     * IT can still conduct PMs while paused.
     */
    private function pause(PMSchedule $schedule): \Illuminate\Http\JsonResponse
    {
        if (!$schedule->is_active) {
            return response()->json(['success' => false, 'message' => 'Schedule is inactive.'], 422);
        }

        $schedule->update([
            'is_paused' => true,
            'paused_at' => now(),
        ]);

        AuditLog::log("Paused PM Cycle", "PM Schedule",
            "Paused PM cycle for {$schedule->schedule_name}", "System");

        return response()->json(['success' => true, 'message' => 'PM cycle paused. IT can still conduct PMs.']);
    }

    /**
     * Resume the current PM cycle - continues auto-advance.
     */
    private function resume(PMSchedule $schedule): \Illuminate\Http\JsonResponse
    {
        $schedule->update([
            'is_paused' => false,
            'paused_at' => null,
        ]);

        AuditLog::log("Resumed PM Cycle", "PM Schedule",
            "Resumed PM cycle for {$schedule->schedule_name}", "System");

        return response()->json(['success' => true, 'message' => 'PM cycle resumed.']);
    }

    /**
     * Stop the current PM cycle entirely.
     */
    private function stop(PMSchedule $schedule): \Illuminate\Http\JsonResponse
    {
        $schedule->update([
            'is_active'              => true,
            'is_paused'              => false,
            'current_focus_division' => null,
            'current_cycle_id'       => null,
            'cycle_stopped_at'       => now(),
        ]);

        if ($schedule->current_cycle_id) {
            PMCycle::where('id', $schedule->current_cycle_id)
                ->update(['completed_at' => now()]);
        }

        AuditLog::log("Stopped PM Cycle", "PM Schedule",
            "Stopped PM cycle for {$schedule->schedule_name}. Schedule remains active for next generation.", "System");

        return response()->json(['success' => true, 'message' => 'PM cycle stopped. The schedule is still active — click "Generate PM" to start a new cycle.']);
    }
}
