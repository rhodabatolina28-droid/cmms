<?php

namespace App\Actions\PMSchedule;

use App\Models\PMSchedule;
use App\Models\AuditLog;
use App\Services\GeneratePMScheduleService;
use Illuminate\Support\Facades\Auth;

class ForceRunPMAction
{
    /**
     * Generate PM for the next division in queue (batch generation).
     * Includes Anti-Spam check - will not run if a cycle is already active.
     *
     * @param  \App\Services\GeneratePMScheduleService  $pmService
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(GeneratePMScheduleService $pmService)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        try {
            $schedules = PMSchedule::active()->get();

            if ($schedules->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No active schedules found. Please create a schedule first.',
                    'total_generated' => 0,
                ]);
            }

            $totalGenerated = 0;
            $results = [];

            foreach ($schedules as $schedule) {
                if ($schedule->current_focus_division) {
                    $results[] = [
                        'schedule_name' => $schedule->schedule_name,
                        'message' => "Skipped - already processing {$schedule->current_focus_division}",
                        'generated' => 0,
                    ];
                    continue;
                }

                $created = $pmService->generate($schedule);

                if (isset($created['__not_due__'])) {
                    $nextDate = \Carbon\Carbon::parse($created['__not_due__'])->format('F d, Y');
                    $results[] = [
                        'schedule_name' => $schedule->schedule_name,
                        'message' => "Skipped - PM not yet due. Next scheduled: {$nextDate}",
                        'generated' => 0,
                    ];
                    AuditLog::log(
                        'Manual PM Generation Skipped',
                        'PM Schedule',
                        "Super admin attempted to generate PM for '{$schedule->schedule_name}' but it is not yet due until {$nextDate}",
                        $user->branch ?? 'System'
                    );
                    continue;
                }

                if (isset($created['__cooldown__'])) {
                    $nextDate = \Carbon\Carbon::parse($created['__cooldown__'])->format('F d, Y');
                    $results[] = [
                        'schedule_name' => $schedule->schedule_name,
                        'message' => "Skipped - on cooldown until {$nextDate}",
                        'generated' => 0,
                    ];
                    AuditLog::log(
                        'Manual PM Generation Skipped',
                        'PM Schedule',
                        "Super admin attempted to generate PM for '{$schedule->schedule_name}' but it is on cooldown until {$nextDate}",
                        $user->branch ?? 'System'
                    );
                    continue;
                }

                $count = count($created);
                $totalGenerated += $count;
                $results[] = [
                    'schedule_name' => $schedule->schedule_name,
                    'generated' => $count,
                ];

                $division = $schedule->fresh()->current_focus_division ?? 'N/A';
                AuditLog::log(
                    'Manual PM Generation',
                    'PM Schedule',
                    "Super admin manually generated {$count} PM work order(s) for '{$schedule->schedule_name}' — Division: {$division}",
                    $user->branch ?? 'System'
                );
            }

            return response()->json([
                'success' => true,
                'message' => $totalGenerated > 0
                    ? "Generated {$totalGenerated} PM request(s) for the next division."
                    : (isset($results[0]) && str_contains($results[0]['message'], 'cooldown')
                        ? $results[0]['message']
                        : "No eligible users found or cycle is already complete."),
                'total_generated' => $totalGenerated,
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
