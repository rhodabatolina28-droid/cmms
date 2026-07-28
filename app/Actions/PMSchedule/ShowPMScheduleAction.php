<?php

namespace App\Actions\PMSchedule;

use App\Models\PMSchedule;
use App\Models\PMCycle;
use App\Models\PMDivisionSchedule;
use App\Models\InventoryAsset;
use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\Auth;

class ShowPMScheduleAction
{
    /**
     * Display a single PM schedule with division progress.
     *
     * @param  \App\Models\PMSchedule  $pmSchedule
     * @return \Illuminate\Contracts\View\View
     */
    public function execute(PMSchedule $pmSchedule)
    {
        $pmSchedule->load(['creator', 'history']);

        $pmDivisions = [];
        $pmTotalPending = 0;
        $pmTotalCompleted = 0;

        $focusDivision = $pmSchedule->current_focus_division;

        $assets = InventoryAsset::where('status', 'Active')
            ->whereNotNull('assigned_to_user')
            ->get();

        $usersByDivision = [];
        foreach ($assets as $asset) {
            $div = $asset->office ?? $asset->department ?? 'Unassigned';
            $usersByDivision[$div][$asset->assigned_to_user] = true;
        }

        $completedDivisions = collect();

        $displayCycle = $pmSchedule->current_cycle_id
            ? PMCycle::find($pmSchedule->current_cycle_id)
            : PMCycle::where('pm_schedule_id', $pmSchedule->id)
                ->whereNotNull('completed_at')
                ->latest('completed_at')
                ->first();

        if ($displayCycle) {
            $completedDivisions = PMDivisionSchedule::where('pm_cycle_id', $displayCycle->id)
                ->whereNotNull('last_completed_at')
                ->get()
                ->keyBy('division_name');
        }

        $completedUsersQuery = RequestModel::where('pm_schedule_id', $pmSchedule->id)
            ->where('is_auto_generated', true)
            ->where('status', 'Completed');

        if ($displayCycle) {
            $completedUsersQuery->where('created_at', '>=', $displayCycle->started_at);
        }

        $completedUsersThisWave = $completedUsersQuery->get()
            ->groupBy('office')
            ->map(fn($reqs) => $reqs->unique('user_id')->count());

        foreach ($usersByDivision as $div => $users) {
            $total = count($users);
            $isCompleted = $completedDivisions->has($div);

            if ($isCompleted) {
                $done = $total;
                $nextScheduleDate = $completedDivisions[$div]->next_scheduled_at ? \Carbon\Carbon::parse($completedDivisions[$div]->next_scheduled_at)->format('M d, Y') : null;
            } else {
                $done = $completedUsersThisWave[$div] ?? 0;
                $nextScheduleDate = null;
            }

            $done = min($done, $total);

            $pmDivisions[$div] = [
                'total' => $total,
                'done'  => $done,
                'next_date' => $nextScheduleDate,
                'status' => $isCompleted ? 'Completed' : ($focusDivision === $div ? 'In Progress' : 'Pending')
            ];

            if ($isCompleted) {
                $pmTotalCompleted++;
            } else {
                $pmTotalPending++;
            }
        }

        foreach ($completedDivisions as $divName => $divRecord) {
            if (!isset($pmDivisions[$divName])) {
                $pmDivisions[$divName] = [
                    'total' => 0,
                    'done'  => 0,
                    'next_date' => $divRecord->next_scheduled_at ? \Carbon\Carbon::parse($divRecord->next_scheduled_at)->format('M d, Y') : null,
                    'status' => 'Completed'
                ];
                $pmTotalCompleted++;
            }
        }

        ksort($pmDivisions);

        return view('pm-schedules.show', compact(
            'pmSchedule', 'pmDivisions', 'pmTotalPending', 'pmTotalCompleted', 'displayCycle'
        ));
    }
}
