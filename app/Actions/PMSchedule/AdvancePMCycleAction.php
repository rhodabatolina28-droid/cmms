<?php

namespace App\Actions\PMSchedule;

use App\Models\PMSchedule;
use App\Services\GeneratePMScheduleService;
use Illuminate\Support\Facades\Auth;

class AdvancePMCycleAction
{
    /**
     * Check and auto-advance to next division if current division is complete.
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
            $schedule = PMSchedule::active()->first();
            if (!$schedule) {
                return response()->json(['success' => false, 'message' => 'No active schedule found.'], 404);
            }

            [$nextDivision, $cycleComplete] = $pmService->checkAndAdvance($schedule);

            if ($nextDivision) {
                return response()->json([
                    'success' => true,
                    'message' => "Advanced to next division: {$nextDivision}",
                    'next_division' => $nextDivision,
                ]);
            }

            if ($cycleComplete) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cycle complete! Next generation will start a new cycle.',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'No advance needed. Current division still in progress or all divisions complete.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Advance check failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
