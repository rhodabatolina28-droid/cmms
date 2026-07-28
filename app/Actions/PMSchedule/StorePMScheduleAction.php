<?php

namespace App\Actions\PMSchedule;

use App\Models\PMSchedule;
use Illuminate\Support\Facades\Auth;

class StorePMScheduleAction
{
    /**
     * Store a newly created PM schedule.
     * Enforces one active PM schedule per branch.
     *
     * @param  array  $validated
     * @return \Illuminate\Http\RedirectResponse
     */
    public function execute(array $validated)
    {
        $actorBranch = Auth::user()?->branch;
        $existingActive = PMSchedule::active()
            ->when($actorBranch, function ($q) use ($actorBranch) {
                $q->whereHas('creator', fn($u) => $u->where('branch', $actorBranch));
            })
            ->first();

        if ($existingActive) {
            return redirect()->back()
                ->withInput()
                ->with('error', "An active PM Schedule already exists for your branch: \"{$existingActive->schedule_name}\". Please deactivate or delete it before creating a new one.");
        }

        $schedule = PMSchedule::create([
            'schedule_name'      => $validated['schedule_name'],
            'asset_categories'   => [],
            'division_filter'    => $validated['division_filter'] ?? null,
            'frequency'          => $validated['frequency'],
            'next_scheduled_date' => now()->toDateString(),
            'created_by'         => Auth::id(),
        ]);

        return redirect()->route('pm-schedules.show', $schedule->id)
            ->with('success', 'PM Schedule created successfully.');
    }
}
