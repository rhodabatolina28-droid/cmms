<?php

namespace App\Actions\PurchaseRequest;

use App\Models\AuditLog;
use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\Auth;

class CancelPurchaseRequestAction
{
    public function execute(PurchaseRequest $pr)
    {
        $user = Auth::user();
        if (! $user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Only supply can cancel a Purchase Request.'], 403);
        }

        if (!in_array($pr->status, [PurchaseRequest::STATUS_PENDING, PurchaseRequest::STATUS_APPROVED], true)) {
            return response()->json(['success' => false, 'message' => 'This Purchase Request can no longer be cancelled.'], 422);
        }

        $pr->update([
            'status' => PurchaseRequest::STATUS_CANCELLED,
            'cancelled_by' => $user->id,
            'cancelled_at' => now(),
        ]);

        AuditLog::log('Cancelled PR', 'Purchase Request', "Cancelled {$pr->pr_number}", $user->region);

        return response()->json([
            'success' => true,
            'message' => $pr->pr_number . ' cancelled.',
            'redirect' => route('purchase_requests.show', $pr->id),
        ]);
    }
}