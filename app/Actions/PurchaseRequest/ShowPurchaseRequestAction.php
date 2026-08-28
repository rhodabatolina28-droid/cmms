<?php

namespace App\Actions\PurchaseRequest;

use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Renders the PR document view (Appendix 60-adapted, printable).
 * Access: Supply Officer (all in scope), Super Admin, IT (own requests only).
 */
class ShowPurchaseRequestAction
{
    public function execute($id)
    {
        $user = Auth::user();

        $isSupply = $user->canProcessSupply();
        $isSuperAdmin = $user->role === 'super_admin';
        $isIt = $user->role === 'it';

        if (! $isSupply && ! $isSuperAdmin && ! $isIt) {
            abort(403);
        }

        $purchaseRequest = PurchaseRequest::with([
            'requisition.ticket',
            'requester',
            'creator',
            'finalizer',
            'deliverer',
            'attachments.uploader',
        ])->findOrFail($id);

        // IT may only view their own requests.
        if ($isIt && ! $isSupply && ! $isSuperAdmin) {
            $own = $purchaseRequest->requested_by === $user->id
                || $purchaseRequest->created_by === $user->id;
            if (! $own) {
                abort(403);
            }
        }

        // Phase C5 - Receive button context (form lives on its own page).
        $canReceive = false;
        if ($purchaseRequest->status === PurchaseRequest::STATUS_FINALIZED) {
            $canReceive = (new ReceivePurchaseRequestAction)->canReceive($purchaseRequest, $user);
        }

        return view('purchase-requests.show', compact('purchaseRequest', 'canReceive'));
    }
}
