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
            $requests = $query->orderBy('created_at', 'desc')->paginate(20);
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
            }
            return view('requests.index', compact('requests'));
        } elseif ($user->role === 'it') {
            $query->where('type', 'ICT')->where('assigned_to', $user->id);
            $requests = $query->orderBy('created_at', 'desc')->paginate(20);
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
                $requests = $query->orderBy('created_at', 'desc')->paginate(20);
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
                    ->orderBy('created_at', 'desc')
                    ->paginate(20);
                if ($request->wantsJson() || $request->expectsJson()) {
                    return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
                }
                return view('super-admin.requests.index', compact('requests'));
            }
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);
        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
        }
        return view('requests.index', compact('requests'));
    }
}
