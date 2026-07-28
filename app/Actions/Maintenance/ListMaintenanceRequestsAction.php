<?php

namespace App\Actions\Maintenance;

use App\Models\Request as RequestModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListMaintenanceRequestsAction
{
    /**
     * List PM requests (non-scheduled) for IT/Super Admin.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse
     */
    public function execute(Request $request)
    {
        $user = Auth::user();

        if ($user->role === 'user' || $user->role === 'admin' || $user->role === 'supply_officer') {
            abort(403, 'Preventive Maintenance requests are managed by IT personnel and Super Admin only.');
        }

        $query = RequestModel::with(['user', 'maintenanceRequest', 'assignedTo'])
            ->where('type', 'Preventive Maintenance')
            ->where('status', '!=', RequestModel::STATUS_SCHEDULED);

        if ($user->role === 'it') {
            $query->where('assigned_to', $user->id);
        } elseif ($user->role === 'super_admin') {
            if ($user->branch) {
                $query->where('branch', $user->branch);
            }
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
        }

        return view('requests.maintenance.index', compact('requests'));
    }
}
