<?php

namespace App\Actions\Maintenance;

use App\Models\Request as RequestModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListScheduledPmsAction
{
    /**
     * List scheduled PM requests.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\View
     */
    public function execute(Request $request)
    {
        $user = Auth::user();
        $query = RequestModel::with(['user', 'maintenanceRequest', 'linkedAsset', 'assignedTo'])
            ->where('type', 'Preventive Maintenance')
            ->where('status', RequestModel::STATUS_SCHEDULED);

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
        } elseif ($user->role === 'super_admin') {
            if ($user->branch) {
                $query->where('branch', $user->branch);
            }
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('requests.maintenance.scheduled', compact('requests'));
    }
}
