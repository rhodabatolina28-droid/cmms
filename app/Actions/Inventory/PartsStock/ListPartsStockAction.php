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
    /**
     * Build the scoped base query (org region/branch) — consistent with inventory.
     */
    protected function baseQuery(User $user)
    {
        $query = Part::query();

        if ($user->region) {
            $query->where('region', $user->region);
        }
        if ($user->branch) {
            // Show branch-scoped parts AND region-wide parts whose branch is
            // still null (e.g. parts registered during receiving before the
            // branch value was persisted). They belong here too.
            $query->where(fn ($q) => $q->where('branch', $user->branch)->orWhereNull('branch'));
        }

        return $query;
    }

    /**
     * Apply the list filters (search / category / status) to a query.
     */
    protected function applyFilters($query, Request $request)
    {
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

        return $query;
    }

    /**
     * Summary stats for a query — respects any filters already applied, so the
     * stat cards reflect the currently filtered set (gaya ng inventory module).
     *
     * @return array{totalParts:int,totalOnHand:int,lowStockCount:int,criticalCount:int}
     */
    protected function statsFor($query): array
    {
        return [
            'totalParts' => (int) (clone $query)->count(),
            'totalOnHand' => (int) (clone $query)->sum('on_hand_qty'),
            'lowStockCount' => (int) (clone $query)
                ->where('reorder_level', '>', 0)
                ->whereColumn('on_hand_qty', '<', 'reorder_level')
                ->where('on_hand_qty', '>', 0)
                ->count(),
            'criticalCount' => (int) (clone $query)
                ->where('on_hand_qty', '<=', 0)
                ->count(),
        ];
    }

    /**
     * Shared scoped + filtered query (ginagamit sa data() at sa CSV export).
     */
    public function buildQuery(Request $request, User $user)
    {
        $query = $this->baseQuery($user);

        return $this->applyFilters($query, $request);
    }

    /**
     * Build the parts stock list data for the parts.blade.php view.
     *
     * @return array<string, mixed>
     */
    public function execute(Request $request, User $user): array
    {
        $query = $this->baseQuery($user);
        $this->applyFilters($query, $request);

        // Stats ay dapat laging kumatawan sa buong (na-filter) na set, HINDI sa
        // kasalukuyang pahina. Kaya kunin muna bago i-paginate ang $query.
        $stats = $this->statsFor($query);

        $parts = $query->orderBy('item_name')->paginate(15)->withQueryString();

        // Category dropdown stays scoped (ignores keyword/status) so it never empties.
        $categories = $this->baseQuery($user)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->sort()
            ->values();

        return array_merge([
            'parts' => $parts,
            'categories' => $categories,
            'filters' => [
                'search' => trim((string) $request->input('search')),
                'category' => $request->input('category'),
                'status' => (string) $request->input('status'),
            ],
        ], $stats);
    }

    /**
     * JSON payload for the live (Ajax) parts list — mirrors the inventory data endpoint.
     *
     * @return array<string, mixed>
     */
    public function data(Request $request, User $user): array
    {
        $query = $this->baseQuery($user);
        $this->applyFilters($query, $request);

        // Kunin ang stats bago i-paginate para laging buong-set value (hindi per page).
        $stats = $this->statsFor($query);

        $parts = $query->withCount('units')->withSum('units', 'unit_value')->orderBy('item_name')->paginate((int) $request->input('per_page', 15));

        return [
            'success' => true,
            'parts' => collect($parts->items())->map(fn (Part $part) => [
                'id' => $part->id,
                'item_name' => $part->item_name,
                'unit' => $part->unit,
                'category' => $part->category,
                'requires_unit_tracking' => $part->requires_unit_tracking,
                'on_hand_qty' => $part->on_hand_qty,
                'reorder_level' => $part->reorder_level,
                'level' => $part->statusLevel(),
                'unit_count' => (int) $part->units_count,
                'unit_value' => $part->units_count > 0 ? round((float) $part->units_sum_unit_value / $part->units_count, 2) : null,
                'total_cost' => $part->units_count > 0 ? round((float) $part->units_sum_unit_value, 2) : null,
            ])->all(),
            'total' => $parts->total(),
            'current_page' => $parts->currentPage(),
            'last_page' => $parts->lastPage(),
            'per_page' => $parts->perPage(),
            'stats' => $stats,
        ];
    }
}
