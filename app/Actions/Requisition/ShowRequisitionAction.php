<?php

namespace App\Actions\Requisition;

use App\Models\Requisition;
use App\Models\InventoryAsset;
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

        if (!$user->can('view', $requisition)) {
            abort(403);
        }

        $canReview = $user->canProcessSupply()
            && $user->can('manage', $requisition);

        // For supply officers: check inventory availability per requested line item
        $inventoryMatches = collect();
        $partsStockMatches = collect();
        if ($user->canProcessSupply() || $user->role === 'super_admin') {
            $items = $requisition->items ?? [];
            foreach ($items as $index => $line) {
                $description = $line['description'] ?? '';
                if (empty($description)) continue;

                // ---- Parts & Consumables stock source ----
                // Use the explicit part_id when IT picked from Parts Stock; otherwise
                // fall back to a keyword match against part item names.
                $part = null;
                $source = $line['source'] ?? null;
                if ($source === 'parts-stock' && !empty($line['part_id'])) {
                    $part = \App\Models\Part::find($line['part_id']);
                }

                if (!$part) {
                    $keywords = array_filter(
                        explode(' ', preg_replace('/[^a-zA-Z0-9 ]/', ' ', $description)),
                        fn($w) => strlen($w) >= 3
                    );
                    if (!empty($keywords)) {
                        $part = \App\Models\Part::where('is_active', true)
                            ->when($user->region, fn($q) => $q->where('region', $user->region))
                            ->when($user->branch, fn($q) => $q->where('branch', $user->branch))
                            ->where(function ($q) use ($keywords) {
                                foreach ($keywords as $word) {
                                    $q->orWhere('item_name', 'LIKE', "%{$word}%");
                                }
                            })
                            ->orderByDesc('on_hand_qty')
                            ->first();
                    }
                }

                if ($part) {
                    $requested = (int) ($line['quantity'] ?? 1);
                    $partsStockMatches->put($index, [
                        'requested' => $line,
                        'part' => $part,
                        'on_hand' => $part->on_hand_qty,
                        'deficit' => $part->on_hand_qty < $requested,
                    ]);
                }

                // ---- Serialized inventory (spare / in-use asset) matches ----
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

        return view('requisitions.show', compact('requisition', 'canReview', 'inventoryMatches', 'partsStockMatches'));
    }
}
