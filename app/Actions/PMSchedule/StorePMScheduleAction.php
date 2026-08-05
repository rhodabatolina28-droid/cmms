<?php

namespace App\Actions\PMSchedule;

use App\Models\PMSchedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class StorePMScheduleAction
{
    /**
     * Store a newly created PM schedule.
     * Enforces one active PM schedule per branch.
     * Returns JSON for AJAX requests, redirect for regular form submits.
     *
     * @param  array  $validated
     * @param  Request|null  $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function execute(array $validated, ?Request $request = null)
    {
        $isAjax = $request && $request->expectsJson();
        $actorBranch = Auth::user()?->branch;
        $existingActive = PMSchedule::active()
            ->when($actorBranch, function ($q) use ($actorBranch) {
                $q->whereHas('creator', fn($u) => $u->where('branch', $actorBranch));
            })
            ->first();

        if ($existingActive) {
            $errorMsg = "An active PM Schedule already exists for your branch: \"{$existingActive->schedule_name}\". Please deactivate or delete it before creating a new one.";

            if ($isAjax) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMsg,
                ], 422);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', $errorMsg);
        }

        $schedule = PMSchedule::create([
            'schedule_name'      => $validated['schedule_name'],
            'asset_categories'   => [],
            'division_filter'    => $validated['division_filter'] ?? null,
            'frequency'          => $validated['frequency'],
            'next_scheduled_date' => $validated['next_scheduled_date'],
            'created_by'         => Auth::id(),
        ]);

        $successMsg = 'PM Schedule created successfully.';

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => $successMsg,
                'schedule_id' => $schedule->id,
            ]);
        }

        return redirect()->route('pm-schedules.show', $schedule->id)
            ->with('success', $successMsg);
    }
}