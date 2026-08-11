<?php

namespace App\Actions\SuperAdmin;

use App\Models\Request as RequestModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GetRequestsDataAction
{
    /**
     * AJAX endpoint — returns paginated, filtered master list of requests with stats.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(Request $request)
    {
        $actor = Auth::user();

        // ── 1) Unfiltered stats ──
        $stats = [
            'total'     => RequestModel::where('type', 'ICT')->where('division_admin_review_status', 'Approved')->when($actor->region, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('region', $actor->region)))->when($actor->branch, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('branch', $actor->branch)))->count(),
            'pending'   => RequestModel::where('type', 'ICT')->where('division_admin_review_status', 'Approved')->where('status', 'Pending')->when($actor->region, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('region', $actor->region)))->when($actor->branch, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('branch', $actor->branch)))->count(),
            'ongoing'   => RequestModel::where('type', 'ICT')->where('division_admin_review_status', 'Approved')->where('status', 'Ongoing')->when($actor->region, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('region', $actor->region)))->when($actor->branch, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('branch', $actor->branch)))->count(),
            'completed' => RequestModel::where('type', 'ICT')->where('division_admin_review_status', 'Approved')->where('status', 'Completed')->when($actor->region, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('region', $actor->region)))->when($actor->branch, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('branch', $actor->branch)))->count(),
        ];

        // ── 2) Filtered query builder ──
        $query = RequestModel::where('type', 'ICT')
            ->where('division_admin_review_status', 'Approved')
            ->when($actor->region, fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('region', $actor->region)))
            ->when($actor->branch, fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('branch', $actor->branch)));

        // Search
        if ($search = $request->input('search')) {
            $s = strtolower($search);
            $query->where(function ($q) use ($s) {
                $q->whereRaw('LOWER(request_number) LIKE ?', ["%{$s}%"])
                  ->orWhereRaw('LOWER(description) LIKE ?', ["%{$s}%"])
                  ->orWhereRaw('LOWER(requestor_name) LIKE ?', ["%{$s}%"])
                  ->orWhereHas('user', fn ($uq) => $uq->whereRaw('LOWER(full_name) LIKE ?', ["%{$s}%"]));
            });
        }

        // Department filter
        if ($department = $request->input('department')) {
            $query->whereHas('user', fn ($q) => $q->where('department', $department));
        }

        // Division/Office filter
        if ($division = $request->input('division')) {
            $query->where('office', $division);
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // My Assigned filter
        $myAssigned = $request->boolean('my_assigned');
        if ($myAssigned) {
            $query->where('assigned_to', $actor->id);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $page    = max((int) $request->input('page', 1), 1);

        // Clone before paginate so we can still do count queries
        $countQuery = clone $query;

        $requests = $query->with(['assignedTo:id,full_name'])
            ->orderBy('created_at', 'desc')
            ->select(['id', 'request_number', 'description', 'requestor_name', 'office', 'assigned_to', 'status', 'created_at', 'completed_at'])
            ->paginate($perPage, ['*'], 'page', $page);

        $hasFilters = $request->filled('search') || $request->filled('department') ||
                      $request->filled('division') || $request->filled('status') ||
                      $myAssigned;

        $filteredStats = $hasFilters ? [
            'total'     => $requests->total(),
            'pending'   => (clone $countQuery)->where('status', 'Pending')->count(),
            'ongoing'   => (clone $countQuery)->where('status', 'Ongoing')->count(),
            'completed' => (clone $countQuery)->where('status', 'Completed')->count(),
        ] : $stats;

        return response()->json([
            'success'        => true,
            'requests'       => $requests->items(),
            'total'          => $requests->total(),
            'per_page'       => $requests->perPage(),
            'current_page'   => $requests->currentPage(),
            'last_page'      => $requests->lastPage(),
            'stats'          => $stats,
            'filtered_stats' => $filteredStats,
        ]);
    }
}
