<?php

namespace App\Actions\ICT;

use App\Models\Request as RequestModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListIctRequestsAction
{
    /**
     * Show requests based on role (ICT only for users/admin, ICT+PM for IT/super_admin).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse
     */
    public function execute(Request $request)
    {
        $user = Auth::user();
        $query = RequestModel::with(['user', 'repairRequest', 'assignedTo']);

        if ($user->role === 'user') {
            $query->where('type', 'ICT')->where('user_id', $user->id);
            // Unfinished-first: Pending/Ongoing/waiting float, Completed sinks.
            $requests = $query->unfinishedFirst()->orderBy('created_at', 'desc')->paginate(20);
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
            }
            return view('requests.index', compact('requests'));
        } elseif ($user->role === 'it') {
            // D4b: officials-first queue — official tickets jump to the top.
            $query->where('type', 'ICT')->where('assigned_to', $user->id);
            // Unfinished-first FIRST (primary key), then officials within the active group.
            $requests = $query->unfinishedFirst()->officialsFirst()->orderBy('created_at', 'desc')->paginate(20);
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
            }
            return view('requests.index', compact('requests'));
        } elseif ($user->role === 'admin' || $user->role === 'supply_officer' || $user->role === 'super_admin') {
            if ($user->role === 'admin' || $user->role === 'supply_officer') {
                $query->where('type', 'ICT')->whereHas('user', function($q) use ($user) {
                    if ($user->branch) {
                        $q->where('branch', $user->branch);
                    }
                    if ($user->office) {
                        $q->where('office', $user->office);
                    }
                });
                // Unfinished-first FIRST (primary key), then officials within the group.
                $requests = $query->unfinishedFirst()->officialsFirst()->orderBy('created_at', 'desc')->paginate(20);
                if ($request->wantsJson() || $request->expectsJson()) {
                    return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
                }
                return view('admin.requests.index', compact('requests'));
            } else {
                $requests = $query->where('type', 'ICT')
                    ->where('division_admin_review_status', 'Approved')
                    ->whereHas('user', function ($q) use ($user) {
                        if ($user->branch) {
                            $q->where('branch', $user->branch);
                        }
                    })
                    ->unfinishedFirst()
                    ->officialsFirst()
                    ->orderBy('created_at', 'desc')
                    ->paginate(20);
                if ($request->wantsJson() || $request->expectsJson()) {
                    return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
                }
                return view('super-admin.requests.index', compact('requests'));
            }
        }

        // Unfinished-first (primary key), then officials, then newest.
        $requests = $query->unfinishedFirst()->officialsFirst()->orderBy('created_at', 'desc')->paginate(20);
        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
        }
        return view('requests.index', compact('requests'));
    }
}
