<?php

namespace App\Actions\Dashboard;

use App\Models\Request as RequestModel;
use App\Models\InventoryAsset;
use Illuminate\Support\Facades\Auth;

class UserDashboardAction
{
    /**
     * Build the user dashboard view.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function execute()
    {
        $user = Auth::user();

        // Users see ICT + PM requests in dashboard (PM needs CSM survey completion)
        $requests = RequestModel::where('user_id', $user->id)
            ->whereIn('type', ['ICT', 'Preventive Maintenance'])
            ->where('status', '!=', RequestModel::STATUS_SCHEDULED)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Optimized: Combined stats query
        $statsRow = RequestModel::where('user_id', $user->id)
            ->where('status', '!=', RequestModel::STATUS_SCHEDULED)
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending", [RequestModel::STATUS_PENDING])
            ->selectRaw("SUM(CASE WHEN status = 'Ongoing' THEN 1 ELSE 0 END) as ongoing")
            ->selectRaw("SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed")
            ->first();

        // Optimized: Single query for asset count
        $assetCount = InventoryAsset::where('assigned_to_user', $user->id)->count();

        $stats = [
            'total' => $statsRow->total ?? 0,
            'pending' => $statsRow->pending ?? 0,
            'ongoing' => $statsRow->ongoing ?? 0,
            'completed' => $statsRow->completed ?? 0,
            'assets' => $assetCount,
        ];

        $hasAssignedAssets = $assetCount > 0;

        // Optimized: Skip warranty alerts for regular users to speed up dashboard load
        // Warranty alerts are more relevant for admin/IT users
        $warrantyExpiring = collect();
        $warrantyExpired = collect();

        return view('dashboard.user', compact('requests', 'stats', 'hasAssignedAssets', 'warrantyExpiring', 'warrantyExpired'));
    }
}
