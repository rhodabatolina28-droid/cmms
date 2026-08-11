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
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere(function ($sub) use ($user) {
                      $sub->whereNull('assigned_to');
                      if ($user->branch) {
                          $sub->where('branch', $user->branch);
                      }
                  });
            });
        } elseif ($user->role === 'super_admin' && $user->branch) {
            $query->where('branch', $user->branch);
        }

        $pmTasks = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('requests.maintenance.pm-tasks', compact('pmTasks'));
    }
}
