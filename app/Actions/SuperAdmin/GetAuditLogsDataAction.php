<?php

namespace App\Actions\SuperAdmin;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class GetAuditLogsDataAction
{
    /**
     * AJAX endpoint — returns paginated, filtered audit logs with stats.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(Request $request)
    {
        // ── 1) Unfiltered stats ──
        $baseQuery = AuditLog::query();

        $stats = [
            'total'    => (clone $baseQuery)->count(),
            'auth'     => (clone $baseQuery)->where('module', 'Auth')->count(),
            'inventory' => (clone $baseQuery)->where('module', 'Inventory')->count(),
            'requests' => (clone $baseQuery)->where('module', 'Requests')->count(),
            'users'    => (clone $baseQuery)->where('module', 'User Management')->count(),
        ];

        // ── 2) Filtered + paginated query ──
        // Build base query WITHOUT with() to avoid N+1 on cloned stats queries
        $baseFiltered = AuditLog::query();

        // Search
        if ($search = $request->input('search')) {
            $search = strtolower($search);
            $baseFiltered->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(action) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(module) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(details) LIKE ?', ["%{$search}%"])
                  ->orWhereHas('user', fn ($uq) => $uq->whereRaw('LOWER(full_name) LIKE ?', ["%{$search}%"]));
            });
        }

        // Module filter
        if ($module = $request->input('module')) {
            $baseFiltered->where('module', $module);
        }

        $perPage = min((int) $request->input('per_page', 50), 100);
        $page    = max((int) $request->input('page', 1), 1);

        // Apply with() ONLY to the paginated data query
        $logs = (clone $baseFiltered)->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Check if filters are active
        $hasFilters = $request->filled('search') || $request->filled('module');

        // Use baseFiltered (NO with()) for stats queries to avoid N+1
        $filteredStats = $hasFilters ? [
            'total'    => $logs->total(),
            'auth'     => (clone $baseFiltered)->where('module', 'Auth')->count(),
            'inventory' => (clone $baseFiltered)->where('module', 'Inventory')->count(),
            'requests' => (clone $baseFiltered)->where('module', 'Requests')->count(),
            'users'    => (clone $baseFiltered)->where('module', 'User Management')->count(),
        ] : $stats;

        return response()->json([
            'success'      => true,
            'logs'         => $logs->items(),
            'total'        => $logs->total(),
            'per_page'     => $logs->perPage(),
            'current_page' => $logs->currentPage(),
            'last_page'    => $logs->lastPage(),
            'stats'        => $stats,
            'filtered_stats' => $filteredStats,
        ]);
    }
}
