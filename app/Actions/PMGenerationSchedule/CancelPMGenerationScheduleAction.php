<?php

namespace App\Actions\PMGenerationSchedule;

use App\Models\PMGenerationSchedule;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelPMGenerationScheduleAction
{
    public function execute(PMGenerationSchedule $pmGenerationSchedule)
    {
        $queueRow = PMGenerationSchedule::lockForUpdate()->find($pmGenerationSchedule->id);

        if (!$queueRow || !$queueRow->isPending()) {
            return back()->with('error', 'Only pending generation schedules can be cancelled.');
        }

        DB::beginTransaction();
        try {
            $queueRow->update([
                'status' => PMGenerationSchedule::STATUS_CANCELLED,
            ]);

            $scheduleName = $queueRow->schedule?->schedule_name ?? "Schedule #{$queueRow->pm_schedule_id}";

            AuditLog::log(
                'Manual PM Generation Cancelled',
                'PM Schedule',
                "Cancelled PM generation for '{$scheduleName}' originally scheduled for {$queueRow->scheduled_date->toDateString()}.",
                auth()->user()->branch ?? 'N/A'
            );

            DB::commit();

            Log::info("PM generation #{$queueRow->id} cancelled by user #" . auth()->id());

            return back()->with('success', 'PM generation schedule cancelled successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to cancel PM generation: {$e->getMessage()}");

            return back()->with('error', 'Failed to cancel PM generation. Please try again.');
        }
    }
}