<?php

namespace App\Actions\PMGenerationSchedule;

use App\Models\PMGenerationSchedule;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReschedulePMGenerationScheduleAction
{
    public function execute(Request $request, PMGenerationSchedule $pmGenerationSchedule)
    {
        $queueRow = PMGenerationSchedule::lockForUpdate()->find($pmGenerationSchedule->id);

        if (!$queueRow || !$queueRow->isPending()) {
            return back()->with('error', 'Only pending generation schedules can be rescheduled.');
        }

        $validated = $request->validated();
        $oldDate = $queueRow->scheduled_date->toDateString();
        $newDate = $validated['scheduled_date'];

        DB::beginTransaction();
        try {
            $queueRow->update([
                'scheduled_date' => $newDate,
                'remarks'        => $validated['remarks'] ?? $queueRow->remarks,
            ]);

            $scheduleName = $queueRow->schedule?->schedule_name ?? "Schedule #{$queueRow->pm_schedule_id}";

            AuditLog::log(
                'Manual PM Generation Rescheduled',
                'PM Schedule',
                "Rescheduled PM generation for '{$scheduleName}' from {$oldDate} to {$newDate}.",
                auth()->user()->branch ?? 'N/A'
            );

            DB::commit();

            Log::info("PM generation #{$queueRow->id} rescheduled from {$oldDate} to {$newDate}");

            return back()->with('success', "PM generation rescheduled from {$oldDate} to {$newDate}.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to reschedule PM generation: {$e->getMessage()}");

            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'unique')) {
                return back()->with('error', 'A generation is already scheduled for this PM schedule on the selected date.');
            }

            return back()->with('error', 'Failed to reschedule PM generation. Please try again.');
        }
    }
}