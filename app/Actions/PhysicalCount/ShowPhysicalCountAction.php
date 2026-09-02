<?php

namespace App\Actions\PhysicalCount;

use App\Models\InventoryAsset;
use App\Models\Scopes\InventoryScope;
use App\Models\PhysicalCountSession;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;

class ShowPhysicalCountAction
{
    use Concerns\BuildsCustodianGroups;
    /**
     * Show a physical count session with paginated assets.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Contracts\View\View
     */
    public function execute(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            abort(403);
        }

        $session = PhysicalCountSession::with(['startedBy', 'counts.asset.assignedUser', 'counts.countedBy'])
            ->findOrFail($id);

        if ($session->scope_region && $session->scope_region !== $user->region) {
            abort(403);
        }
        if ($user->branch && $session->scope_branch && $session->scope_branch !== $user->branch) {
            abort(403);
        }

        $totalAssetsQuery = InventoryAsset::query();
        InventoryScope::scopeAssetsToActor($totalAssetsQuery, $user);
        $totalCount = $totalAssetsQuery->count();

        $allAssets = InventoryAsset::with('assignedUser');
        InventoryScope::scopeAssetsToActor($allAssets, $user);
        $allAssets = $allAssets->orderBy('item_name')->get();

        $countedIds = $session->counts->pluck('asset_id')->toArray();

        $summary = [
            'total'   => $totalCount,
            'counted' => $session->counts->count(),
            'present' => $session->counts->where('status', 'Present')->count(),
            'missing' => $session->counts->where('status', 'Missing')->count(),
            'damaged' => $session->counts->where('status', 'Damaged')->count(),
        ];

        // Main table grouped by custodian (PAR-based accountability view).
        // One group per employee, unassigned/spare assets last, 10 groups per page.
        $groups = $this->buildCustodianGroups($allAssets, $session->counts);
        $perPage = 10;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $custodianGroups = new LengthAwarePaginator(
            $groups->forPage($page, $perPage)->values(),
            $groups->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );

        return view('inventory.physical-count-show', compact('session', 'allAssets', 'countedIds', 'summary', 'custodianGroups'));
    }
}
