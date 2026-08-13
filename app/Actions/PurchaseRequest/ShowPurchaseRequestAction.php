<?php

namespace App\Actions\PurchaseRequest;

use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\Auth;

class ShowPurchaseRequestAction
{
    public function execute($id)
    {
        $user = Auth::user();

        if (! $user->canProcessSupply() && $user->role !== 'super_admin') {
            abort(403);
        }

        $purchaseRequest = PurchaseRequest::with([
            'requisition.ticket',
            'requester',
            'approver',
            'receiver',
        ])->findOrFail($id);

        // Load latest on-hand per part line for the review panel.
        $stockHalves = collect();
        foreach ($purchaseRequest->items ?? [] as $index => $line) {
            $part = !empty($line['part_id']) ? \App\Models\Part::find($line['part_id']) : null;
            $stockHalves->put($index, [
                'part' => $part,
                'available' => $part?->on_hand_qty ?? null,
            ]);
        }

        return view('purchase-requests.show', compact('purchaseRequest', 'stockHalves'));
    }
}