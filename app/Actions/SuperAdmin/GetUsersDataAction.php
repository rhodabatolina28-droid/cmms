<?php

namespace App\Actions\SuperAdmin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GetUsersDataAction
{
    /**
     * AJAX endpoint — returns paginated, filtered user list with stats.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(Request $request)
    {
        $actor = Auth::user();

        // ── 1) Unfiltered stats (single query with conditional counts) ──
        // Super Admin is scoped by region AND branch to prevent cross-region data leaks.
        $baseQuery = User::query()
            ->when($actor->region, fn ($q) => $q->where('region', $actor->region))
            ->when($actor->branch, fn ($q) => $q->where('branch', $actor->branch));

        $stats = [
            'total'    => (clone $baseQuery)->count(),
            'active'   => (clone $baseQuery)->where('is_active', 1)->count(),
            'inactive' => (clone $baseQuery)->where('is_active', 0)->count(),
        ];

        // ── 2) Filtered + paginated query ──
        $query = User::query()
            ->when($actor->region, fn ($q) => $q->where('region', $actor->region))
            ->when($actor->branch, fn ($q) => $q->where('branch', $actor->branch));

        // Search
        if ($search = $request->input('search')) {
            $search = strtolower($search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(full_name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
            });
        }

        // Department filter
        if ($department = $request->input('department')) {
            $query->where('department', $department);
        }

        // Division/Office filter
        if ($division = $request->input('division')) {
            $query->where('office', $division);
        }

        // Role filter
        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('is_active', $status === 'active' ? 1 : 0);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $page    = max((int) $request->input('page', 1), 1);

        $users = $query->orderBy('full_name', 'asc')
            ->select(['id', 'full_name', 'email', 'role', 'office', 'department', 'is_active'])
            ->paginate($perPage, ['*'], 'page', $page);

        // Check if any filter is active
        $hasFilters = $request->filled('search') || $request->filled('department') ||
                      $request->filled('division') || $request->filled('role') ||
                      $request->filled('status');

        // When filters are active, use paginator's total for filtered stats
        // (already counted by paginate)
        $filteredStats = $hasFilters ? [
            'total'    => $users->total(),
            'active'   => (clone $query)->where('is_active', 1)->count(),
            'inactive' => (clone $query)->where('is_active', 0)->count(),
        ] : $stats;

        return response()->json([
            'success'      => true,
            'users'        => $users->items(),
            'total'        => $users->total(),
            'per_page'     => $users->perPage(),
            'current_page' => $users->currentPage(),
            'last_page'    => $users->lastPage(),
            'stats'        => $stats,
            'filtered_stats' => $filteredStats,
        ]);
    }
}
