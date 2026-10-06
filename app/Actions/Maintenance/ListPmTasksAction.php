<?php

namespace App\Actions\Maintenance;

use App\Models\Request as RequestModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListPmTasksAction
{
    /**
     * Dedicated PM Tasks page for IT personnel.
     * Shows only PM work orders assigned to the current IT user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\View
     */
    public function execute(Request $request)
    {
        $user = Auth::user();

        $query = RequestModel::with(['user', 'maintenanceRequest', 'assignedTo', 'linkedAsset'])
            ->where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($user->role === 'it') {
            // PM Tasks IT-side fix A (Oct 2026): ONLY work orders assigned to
            // me. The old `orWhereNull('assigned_to') + branch` clause leaked
            // UNASSIGNED branch tickets into the IT's personal queue — per
            // user rule the page must show only "yung na-assign lang sa kanya".
            $query->where('assigned_to', $user->id);
        } elseif ($user->role === 'super_admin' && $user->branch) {
            $query->where('branch', $user->branch);
        }

        // PM Tasks IT-side fix B (Oct 2026): order like the System Admin's PM
        // Work Orders — active work first, Completed sinks to the bottom
        // (created_at desc tiebreak). Plain `created_at desc` kept a
        // JUST-completed task at the top of the queue.
        $pmTasks = $query
            ->orderByRaw("CASE status"
                . " WHEN 'Scheduled' THEN 0"
                . " WHEN 'Ongoing' THEN 1"
                . " WHEN 'Awaiting Signature' THEN 2"
                . " WHEN 'Completed' THEN 3"
                . " ELSE 4 END")
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Accurate stats — computed from the full filtered set, not just the current page
        $statsQuery = clone $query;
        $allTasks = $statsQuery->get(['id', 'status', 'created_at']);
        $stats = [
            'total'     => $allTasks->count(),
            'scheduled' => $allTasks->where('status', 'Scheduled')->count(),
            'ongoing'   => $allTasks->where('status', 'Ongoing')->count(),
            'completed' => $allTasks->where('status', 'Completed')->count(),
            'overdue'   => $allTasks->where('status', 'Scheduled')->filter(fn ($t) => $t->is_aging_overdue)->count(),
        ];

        return view('requests.maintenance.pm-tasks', compact('pmTasks', 'stats'));
    }
}
