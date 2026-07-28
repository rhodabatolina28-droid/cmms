<?php

namespace App\Actions\Dashboard;

use App\Models\Request as RequestModel;
use App\Models\User;
use App\Models\InventoryAsset;
use App\Models\Requisition;
use App\Support\RequestAuthorization;
use Illuminate\Support\Facades\Auth;

class AdminDashboardAction
{
    /**
     * Build the admin dashboard view.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function execute()
    {
        $user = Auth::user();

        // Role-based request visibility
        if ($user->role === 'user' || $user->role === 'admin' || $user->role === 'supply_officer') {
            // Regular users and Admin/Supply Officer: ICT only
            $requestsQuery = RequestModel::where('type', 'ICT')->whereHas('user', function($q) use ($user) {
                if ($user->branch) {
                    $q->where('branch', $user->branch);
                }
                if ($user->office) {
                    $q->where('office', $user->office);
                }
                // Department filter removed - office (division) is sufficient
            });
        } elseif ($user->role === 'it') {
            // IT: ICT + PM assigned to them
            $requestsQuery = RequestModel::where(function ($q) use ($user) {
                $q->where('type', 'ICT')
                  ->orWhere(function ($sub) use ($user) {
                      $sub->where('type', 'Preventive Maintenance')
                          ->where('assigned_to', $user->id);
                  });
            })->whereHas('user', function($q) use ($user) {
                if ($user->branch) {
                    $q->where('branch', $user->branch);
                }
                if ($user->office) {
                    $q->where('office', $user->office);
                }
            });
        } else {
            // Super Admin: All requests (ICT + PM)
            $requestsQuery = RequestModel::whereHas('user', function($q) use ($user) {
                if ($user->branch) {
                    $q->where('branch', $user->branch);
                }
                if ($user->office) {
                    $q->where('office', $user->office);
                }
            });
        }

        $requests = $requestsQuery->with('user')->orderBy('created_at', 'desc')->limit(10)->get();

        // Fetch scoped users - division level
        $usersQuery = User::query();
        if ($user->branch) {
            $usersQuery->where('branch', $user->branch);
        }
        if ($user->office) {
            $usersQuery->where('office', $user->office);
        }
        $users = $usersQuery->limit(10)->get();

        // Fetch scoped assets - division level
        $assetsQuery = InventoryAsset::whereHas('assignedUser', function($q) use ($user) {
            if ($user->branch) {
                $q->where('branch', $user->branch);
            }
            if ($user->office) {
                $q->where('office', $user->office);
            }
        });
        $assets = $assetsQuery->with('assignedUser')->limit(10)->get();

        // Stats calculation — consolidated into single query
        $statsRow = (clone $requestsQuery)
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending", [RequestModel::STATUS_PENDING])
            ->selectRaw("SUM(CASE WHEN status = 'Ongoing' THEN 1 ELSE 0 END) as ongoing")
            ->selectRaw("SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN type = 'ICT' AND assigned_to IS NULL AND status IN (?, ?) THEN 1 ELSE 0 END) as unassigned_jobs", [RequestModel::STATUS_PENDING, RequestModel::STATUS_ONGOING])
            ->first();

        $stats = [
            'total' => $statsRow->total ?? 0,
            'pending' => $statsRow->pending ?? 0,
            'ongoing' => $statsRow->ongoing ?? 0,
            'completed' => $statsRow->completed ?? 0,
            'unassigned_jobs' => $statsRow->unassigned_jobs ?? 0,
        ];

        $unassignedRequests = (clone $requestsQuery)
            ->with(['user', 'assignedTo'])
            ->where('type', 'ICT')
            ->whereNull('assigned_to')
            ->whereIn('status', [RequestModel::STATUS_PENDING, RequestModel::STATUS_ONGOING])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Supply logic for authorized supply admins
        $supplyStats = [];
        $pendingRequisitions = collect();
        if ($user->canProcessSupply()) {
            // Supply admin sees assets office-wide (entire branch), not just their division
            $assetsQuerySupply = InventoryAsset::query();
            if ($user->region) {
                $assetsQuerySupply->where('region', $user->region);
            }
            if ($user->branch) {
                $assetsQuerySupply->where('branch', $user->branch);
            }
            // Supply admin manages entire branch - no division filter

            $reqQuery = Requisition::query();
            RequestAuthorization::scopeRequisitionsForSupplyOfficer($user, $reqQuery);

            $assetStatsRow = (clone $assetsQuerySupply)
                ->selectRaw("COUNT(*) as total_assets")
                ->selectRaw("SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active")
                ->selectRaw("SUM(CASE WHEN status = 'For Repair' THEN 1 ELSE 0 END) as under_repair")
                ->first();

            $reqStatusRow = (clone $reqQuery)
                ->selectRaw("SUM(CASE WHEN status = '" . Requisition::STATUS_PENDING . "' THEN 1 ELSE 0 END) as pending_reqs")
                ->selectRaw("SUM(CASE WHEN status = '" . Requisition::STATUS_APPROVED . "' THEN 1 ELSE 0 END) as approved_reqs")
                ->first();

            $supplyStats = [
                'total_assets' => $assetStatsRow->total_assets ?? 0,
                'active' => $assetStatsRow->active ?? 0,
                'under_repair' => $assetStatsRow->under_repair ?? 0,
                'pending_reqs' => $reqStatusRow->pending_reqs ?? 0,
                'approved_reqs' => $reqStatusRow->approved_reqs ?? 0,
            ];

            $pendingRequisitions = (clone $reqQuery)
                ->where('status', Requisition::STATUS_PENDING)
                ->with(['ticket', 'requester'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
        }

        // Warranty alerts — handle missing column gracefully
        try {
            $warrantyQuery = InventoryAsset::whereHas('assignedUser', function($q) use ($user) {
                if ($user->branch) $q->where('branch', $user->branch);
                if ($user->office) $q->where('office', $user->office);
            });
            $warrantyExpiring = (clone $warrantyQuery)->whereNotNull('warranty_expiration')
                ->where('warranty_expiration', '>=', now())
                ->where('warranty_expiration', '<=', now()->addDays(30))->limit(5)->get();
            $warrantyExpired = (clone $warrantyQuery)->whereNotNull('warranty_expiration')
                ->where('warranty_expiration', '<', now())->limit(5)->get();
        } catch (\Exception $e) {
            $warrantyExpiring = collect();
            $warrantyExpired = collect();
        }

        return view('dashboard.admin', compact('requests', 'stats', 'users', 'assets', 'unassignedRequests', 'supplyStats', 'pendingRequisitions', 'warrantyExpiring', 'warrantyExpired'));
    }
}
