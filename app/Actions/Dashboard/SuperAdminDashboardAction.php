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
        // 1. ALL requests (for service distribution)
        $allRequests = RequestModel::query()
            ->where('division_admin_review_status', 'Approved')
            ->whereHas('user', function ($query) use ($user) {
                if ($user->branch) {
                    $query->where('branch', $user->branch);
                }
            });

        // 2. ICT & Repair ONLY (User-submitted tickets)
        $userRequests = (clone $allRequests)
            ->where('type', '!=', 'Preventive Maintenance')
            ->where('status', '!=', RequestModel::STATUS_SCHEDULED);

        // Recent System Activity (Only user-submitted)
        $recentRequests = (clone $userRequests)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Stats for colored cards (Only user-submitted)
        $statsRow = (clone $userRequests)
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending", [RequestModel::STATUS_PENDING])
            ->selectRaw("SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status = 'Ongoing' THEN 1 ELSE 0 END) as ongoing")
            ->first();
            
        // Department stats (Only user-submitted, to see which office requests the most)
        $departmentStats = (clone $userRequests)
            ->join('users', 'requests.user_id', '=', 'users.id')
            ->select('users.office', DB::raw('count(requests.id) as total'))
            ->groupBy('users.office')
            ->pluck('total', 'office')
            ->toArray();
            
        // Overdue PMs
        $overduePMsCount = RequestModel::query()
            ->where('type', 'Preventive Maintenance')
            ->where('status', RequestModel::STATUS_SCHEDULED)
            ->where('is_auto_generated', true)
            ->where('created_at', '<', now()->subDays(7))
            ->whereHas('user', function ($query) use ($user) {
                if ($user->branch) {
                    $query->where('branch', $user->branch);
                }
            })
            ->count();
            
        // Total Assets for health metric
        $totalAssets = InventoryAsset::query()
            ->when($user->region, fn ($q) => $q->where('region', $user->region))
            ->when($user->branch, fn ($q) => $q->where('branch', $user->branch))
            ->where('status', 'Active')
            ->count();

        $stats = [
            'total'       => $statsRow->total ?? 0,
            'pending'     => $statsRow->pending ?? 0,
            'completed'   => $statsRow->completed ?? 0,
            'ongoing'     => $statsRow->ongoing ?? 0,
            'total_users' => User::query()
                ->when($user->region, fn ($query) => $query->where('region', $user->region))
                ->when($user->branch, fn ($query) => $query->where('branch', $user->branch))
                ->count(),
            'total_assets' => $totalAssets,
            'overdue_pms'  => $overduePMsCount,
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

        // Client Satisfaction (CSM) Snapshot
        try {
            $surveys = DB::table('csm_surveys')->get(['sqd1','sqd2','sqd3','sqd4','sqd5','sqd6','sqd7','sqd8','sqd9']);
            $totalScore = 0;
            $totalQuestions = 0;
            
            $scoreMap = [
                'Strongly Agree' => 5,
                'Agree' => 4,
                'Neither Agree nor Disagree' => 3,
                'Disagree' => 2,
                'Strongly Disagree' => 1,
            ];

            foreach ($surveys as $survey) {
                foreach (['sqd1','sqd2','sqd3','sqd4','sqd5','sqd6','sqd7','sqd8','sqd9'] as $sqd) {
                    if (isset($scoreMap[$survey->$sqd])) {
                        $totalScore += $scoreMap[$survey->$sqd];
                        $totalQuestions++;
                    }
                }
            }

            $csmAverage = $totalQuestions > 0 ? round($totalScore / $totalQuestions, 1) : 0;
            $csmResponses = $surveys->count();
        } catch (\Exception $e) {
            $csmAverage = 0;
            $csmResponses = 0;
        }

        return view('dashboard.super-admin', compact('recentRequests', 'stats', 'departmentStats', 'warrantyExpiring', 'warrantyExpired', 'csmAverage', 'csmResponses'));
    }
}
