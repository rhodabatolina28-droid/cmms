<?php

namespace App\Actions\Inventory;

use App\Models\InventoryAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GetSuperAdminInventoryAssetsAction
{
    /**
     * Super Admin — read-only asset data API.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $query = InventoryAsset::with('assignedUser')
            ->where('region', $user->region);

        if ($user->branch) {
            $query->where('branch', $user->branch);
        }

        $query->withCount('components');

        if ($search = $request->input('search')) {
            $search = strtolower($search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(item_name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(serial_number) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(par_number) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(property_number) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $cats = ['Desktop','Laptop','Monitor','Printer/Scanner','Peripherals','Network/Server','Others'];
        $fieldList = implode(',', array_map(fn($c) => "'$c'", $cats));
        $statusOrder = "'Active','Spare','For Repair','For Disposal','Scrapped','Disposed','Pending'";

        $perPage = min((int) $request->input('per_page', 50), 100);
        $page = max((int) $request->input('page', 1), 1);

        $assets = $query->orderByRaw("FIELD(status, $statusOrder)")
            ->orderByRaw("FIELD(category, $fieldList)")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $baseQuery = InventoryAsset::query();
        $baseQuery->where('region', $user->region);
        if ($user->branch) {
            $baseQuery->where('branch', $user->branch);
        }
        $totalActive = (clone $baseQuery)->where('status', 'Active')->count();
        $totalSpare = (clone $baseQuery)->where('status', 'Spare')->count();
        $totalRepair = (clone $baseQuery)->where('status', 'For Repair')->count();
        $totalDisposal = (clone $baseQuery)->whereIn('status', ['For Disposal', 'Scrapped', 'Disposed'])->count();
        $totalAll = (clone $baseQuery)->count();

        return response()->json([
            'success' => true,
            'assets' => $assets->items(),
            'total' => $assets->total(),
            'per_page' => $assets->perPage(),
            'current_page' => $assets->currentPage(),
            'last_page' => $assets->lastPage(),
            'stats' => [
                'total' => $totalAll,
                'active' => $totalActive,
                'spare' => $totalSpare,
                'repair' => $totalRepair,
                'disposal' => $totalDisposal,
            ],
        ]);
    }
}
