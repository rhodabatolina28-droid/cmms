<?php

namespace App\Actions\Inventory\PartsStock;

use App\Models\Part;
use App\Models\User;
use Illuminate\Http\Request;

class ListPartsStockAction
{
    /**
     * Build the parts stock list data for the parts.blade.php view.
     *
     * @return array<string, mixed>
     */
    public function execute(Request $request, User $user): array
    {
        $query = Part::query();

        // Multi-location scoping — consistent with the inventory module.
        if ($user->region) {
            $query->where('region', $user->region);
        }
        if ($user->branch) {
            $query->where('branch', $user->branch);
        }

        $search = trim((string) $request->input('search'));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $category = $request->input('category');
        if (! empty($category)) {
            $query->where('category', $category);
        }

        $status = (string) $request->input('status');
        if ($status === 'ok') {
            $query->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('reorder_level', '>', 0)->whereColumn('on_hand_qty', '>=', 'reorder_level');
                })->orWhere(function ($q2) {
                    $q2->where('reorder_level', 0)->where('on_hand_qty', '>', 0);
                });
            });
        } elseif ($status === 'low') {
            $query->where('reorder_level', '>', 0)
                ->whereColumn('on_hand_qty', '<', 'reorder_level')
                ->where('on_hand_qty', '>', 0);
        } elseif ($status === 'critical') {
            $query->where('on_hand_qty', '<=', 0);
        }

        $parts = $query->orderBy('item_name')->paginate(15)->withQueryString();

        // Banner counts (respect org scoping, ignore keyword so the banner is global).
        $scope = fn ($q) => $q
            ->when($user->region, fn ($sub) => $sub->where('region', $user->region))
            ->when($user->branch, fn ($sub) => $sub->where('branch', $user->branch));

        $lowStockCount = $scope(Part::query())
            ->where('reorder_level', '>', 0)
            ->whereColumn('on_hand_qty', '<', 'reorder_level')
            ->where('on_hand_qty', '>', 0)
            ->count();

        $criticalCount = $scope(Part::query())
            ->where('on_hand_qty', '<=', 0)
            ->count();

        $categories = $scope(Part::query())
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->sort()
            ->values();

        $totalParts = $scope(Part::query())->count();
        $totalOnHand = $scope(Part::query())->sum('on_hand_qty');

        return [
            'parts' => $parts,
            'lowStockCount' => $lowStockCount,
            'criticalCount' => $criticalCount,
            'totalParts' => $totalParts,
            'totalOnHand' => $totalOnHand,
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'status' => $status,
            ],
        ];
    }
}