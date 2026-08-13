<?php

namespace App\Actions\PurchaseRequest;

use App\Models\AuditLog;
use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\Auth;

class ApprovePurchaseRequestAction
{
    public function execute(PurchaseRequest $pr)
    {
        $user = Auth::user();
        if (! $user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Only supply can approve a Purchase Request.'], 403);
        }

        if (!$pr->isPending()) {
            return response()->json(['success' => false, 'message' => 'Only pending Purchase Requests can be approved.'], 422);
        }

        $pr->update([
            'status' => PurchaseRequest::STATUS_APPROVED,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        AuditLog::log('Approved PR', 'Purchase Request', "Approved {$pr->pr_number}", $user->region);

        return response()->json([
            'success' => true,
            'message' => $pr->pr_number . ' approved.',
            'redirect' => route('purchase_requests.show', $pr->id),
        ]);
    }
}