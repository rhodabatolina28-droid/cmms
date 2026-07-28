<?php

namespace App\Actions\Inventory;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GetInventoryUsersAction
{
    /**
     * AJAX endpoint — returns users for inventory assignment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(Request $request)
    {
        $user = Auth::user();

        if (! $user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Only the Administrative supply admin can list custodians for inventory assignment.'], 403);
        }

        $query = User::query();
        $query->where('region', $user->region);

        if ($request->filled('branch')) {
            $query->where('branch', $request->branch);
        } elseif ($user->branch) {
            $query->where('branch', $user->branch);
        }

        if ($request->filled('office')) {
            $query->where('office', $request->office);
        }

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        $users = $query->orderBy('full_name', 'asc')->limit(200)->get(['id', 'full_name as name', 'office', 'department']);

        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    }
}
