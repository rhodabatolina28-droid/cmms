<?php

namespace App\Actions\Dashboard;

use App\Models\Request as RequestModel;
use App\Models\User;
use App\Models\InventoryAsset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SuperAdminDashboardAction
{
    /**
     * Build the Super Admin dashboard view.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function execute()
    {
        $user = Auth::user();

        // Super Admin is office-scoped (branch level only)
        // They should see ALL requests/users in their branch, not filtered by division
        $approvedRequests = RequestModel::query()
            ->where('division_admin_review_status', 'Approved')
            ->where('status', '!=', RequestModel::STATUS_SCHEDULED)
            ->whereHas('user', function ($query) use ($user) {
                if ($user->branch) {
                    $query->where('branch', $user->branch);
                }
                // DO NOT filter by office/division - Super Admin manages entire branch
            });

        $recentRequests = (clone $approvedRequests)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $statsRow = (clone $approvedRequests)
            ->where('status', '!=', RequestModel::STATUS_SCHEDULED)
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending", [RequestModel::STATUS_PENDING])
            ->selectRaw("SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status = 'Ongoing' THEN 1 ELSE 0 END) as ongoing")
            ->selectRaw("SUM(CASE WHEN type = 'ICT' THEN 1 ELSE 0 END) as ict")
            ->selectRaw("SUM(CASE WHEN type = 'Preventive Maintenance' THEN 1 ELSE 0 END) as maintenance")
            ->first();

        $departmentStats = (clone $approvedRequests)
            ->join('users', 'requests.user_id', '=', 'users.id')
            ->select('users.branch', DB::raw('count(requests.id) as total'))
            ->groupBy('users.branch')
            ->pluck('total', 'branch')
            ->toArray();

        $stats = [
            'total'       => $statsRow->total ?? 0,
            'pending'     => $statsRow->pending ?? 0,
            'completed'   => $statsRow->completed ?? 0,
            'ongoing'     => $statsRow->ongoing ?? 0,
            'ict'         => $statsRow->ict ?? 0,
            'maintenance' => $statsRow->maintenance ?? 0,
            'total_users' => User::query()
                ->when($user->region, fn ($query) => $query->where('region', $user->region))
                ->when($user->branch, fn ($query) => $query->where('branch', $user->branch))
                ->count(),
        ];

        // Warranty alerts — handle missing column gracefully
        try {
            $warrantyQuery = InventoryAsset::query()
                ->when($user->region, fn ($q) => $q->where('region', $user->region))
                ->when($user->branch, fn ($q) => $q->where('branch', $user->branch));
            $warrantyExpiring = (clone $warrantyQuery)->whereNotNull('warranty_expiration')
                ->where('warranty_expiration', '>=', now())
                ->where('warranty_expiration', '<=', now()->addDays(30))->limit(5)->get();
            $warrantyExpired = (clone $warrantyQuery)->whereNotNull('warranty_expiration')
                ->where('warranty_expiration', '<', now())->limit(5)->get();
        } catch (\Exception $e) {
            $warrantyExpiring = collect();
            $warrantyExpired = collect();
        }

        return view('dashboard.super-admin', compact('recentRequests', 'stats', 'departmentStats', 'warrantyExpiring', 'warrantyExpired'));
    }
}
