<?php

namespace App\Actions\PMSchedule;

use App\Models\PMSchedule;
use App\Services\GeneratePMScheduleService;

class GeneratePMScheduleAction
{
    /**
     * Generate PM requests for a schedule.
     *
     * @param  \App\Models\PMSchedule  $pmSchedule
     * @param  \App\Services\GeneratePMScheduleService  $pmService
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(PMSchedule $pmSchedule, GeneratePMScheduleService $pmService)
    {
        if (!$pmSchedule->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot generate: schedule is inactive.',
            ], 422);
        }

        try {
            $created = $pmService->generate($pmSchedule);

            if (isset($created['__cooldown__'])) {
                $nextDate = \Carbon\Carbon::parse($created['__cooldown__'])->format('F d, Y');
                return response()->json([
                    'success' => false,
                    'message' => "PM cycle is on cooldown. Next generation is allowed on or after {$nextDate}.",
                    'cooldown_until' => $created['__cooldown__'],
                ], 422);
            }

            $count = count($created);

            return response()->json([
                'success' => true,
                'message' => "Generated {$count} PM request(s) successfully.",
                'request_numbers' => $created,
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
