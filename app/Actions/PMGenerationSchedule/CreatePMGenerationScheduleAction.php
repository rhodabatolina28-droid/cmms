<?php

namespace App\Actions\PMGenerationSchedule;

use App\Models\PMSchedule;
use App\Models\PMGenerationSchedule;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\GeneratePMScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreatePMGenerationScheduleAction
{
    public function execute(Request $request, User $user, GeneratePMScheduleService $pmService)
    {
        $validated = $request->validated();

        $schedule = PMSchedule::findOrFail($validated['pm_schedule_id']);

        if (!$schedule->is_active) {
            return back()->with('error', "Cannot schedule generation for inactive PM schedule '{$schedule->schedule_name}'.");
        }

        $preview = $pmService->preview($schedule);
        $estimatedCount = $preview['total_matching'] ?? 0;

        $existing = PMGenerationSchedule::where('pm_schedule_id', $schedule->id)
            ->where('scheduled_date', $validated['scheduled_date'])
            ->where('status', PMGenerationSchedule::STATUS_PENDING)
            ->exists();

        if ($existing) {
            return back()->with('error', 'A generation is already scheduled for this PM schedule on the selected date.');
        }

        DB::beginTransaction();
        try {
            $queueRow = PMGenerationSchedule::create([
                'pm_schedule_id'           => $schedule->id,
                'scheduled_date'           => $validated['scheduled_date'],
                'generated_by'             => $user->id,
                'status'                   => PMGenerationSchedule::STATUS_PENDING,
                'remarks'                  => $validated['remarks'] ?? null,
                'estimated_asset_count'    => $estimatedCount,
                'division_filter_snapshot' => $schedule->division_filter,
            ]);

            AuditLog::log(
                'Manual PM Generation Scheduled',
                'PM Schedule',
                "Queued PM generation for '{$schedule->schedule_name}' on {$validated['scheduled_date']}. Estimated assets: {$estimatedCount}.",
                $user->branch ?? 'N/A'
            );

            DB::commit();

            Log::info("PM generation scheduled by user #{$user->id} for schedule #{$schedule->id} on {$validated['scheduled_date']}");

            return back()->with('success', "PM generation scheduled for {$validated['scheduled_date']}. Estimated assets: {$estimatedCount}.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to schedule PM generation: {$e->getMessage()}");

            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'unique')) {
                return back()->with('error', 'A generation is already scheduled for this PM schedule on the selected date.');
            }

            return back()->with('error', 'Failed to schedule PM generation. Please try again.');
        }
    }
}