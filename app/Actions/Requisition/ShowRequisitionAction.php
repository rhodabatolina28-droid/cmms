<?php

namespace App\Actions\Requisition;

use App\Models\Requisition;
use App\Models\InventoryAsset;
use App\Support\RequestAuthorization;
use Illuminate\Support\Facades\Auth;

class ShowRequisitionAction
{
    /**
     * Display a single requisition with inventory matches for supply officers.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\View\View
     */
    public function execute($id)
    {
        $user = Auth::user();
        $requisition = Requisition::with(['ticket.user', 'ticket.assignedTo', 'ticket.linkedAsset', 'requester', 'reviewer'])
            ->findOrFail($id);

        if (!RequestAuthorization::canViewRequisition($user, $requisition)) {
            abort(403);
        }

        $canReview = $user->canProcessSupply()
            && RequestAuthorization::canSupplyManageRequisition($user, $requisition);

        // For supply officers: check inventory availability per requested line item
        $inventoryMatches = collect();
        if ($user->canProcessSupply() || $user->role === 'super_admin') {
            $items = $requisition->items ?? [];
            foreach ($items as $index => $line) {
                $description = $line['description'] ?? '';
                if (empty($description)) continue;

                // Keyword-based search: split description into words, filter short ones
                $keywords = array_filter(
                    explode(' ', preg_replace('/[^a-zA-Z0-9 ]/', ' ', $description)),
                    fn($w) => strlen($w) >= 3
                );

                if (empty($keywords)) continue;

                $query = InventoryAsset::with('assignedUser')
                    ->whereIn('status', ['Spare', 'Active'])
                    ->where('region', $user->region);

                if ($user->branch) {
                    $query->where('branch', $user->branch);
                }

                // Match against item_name, brand, model, or specifications
                $query->where(function ($q) use ($keywords) {
                    foreach ($keywords as $word) {
                        $q->orWhere('item_name', 'LIKE', "%{$word}%")
                          ->orWhere('brand', 'LIKE', "%{$word}%")
                          ->orWhere('model', 'LIKE', "%{$word}%")
                          ->orWhere('category', 'LIKE', "%{$word}%");
                    }
                });

                $matches = $query->orderByRaw("FIELD(status, 'Spare', 'Active')")
                    ->limit(5)
                    ->get();

                if ($matches->isNotEmpty()) {
                    $inventoryMatches->put($index, [
                        'requested' => $line,
                        'assets' => $matches,
                    ]);
                }
            }
        }

        return view('requisitions.show', compact('requisition', 'canReview', 'inventoryMatches'));
    }
}
