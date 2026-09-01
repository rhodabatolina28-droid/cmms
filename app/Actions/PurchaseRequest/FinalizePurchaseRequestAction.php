<?php

namespace App\Actions\PurchaseRequest;

use App\Models\AuditLog;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Finalizes a submitted PR (Supply Officer only) — marks it ready to print.
 * The printed document is then physically submitted to the Procurement
 * Office (outside this system).
 */
class FinalizePurchaseRequestAction
{
    /**
     * @return array{success: bool, message: string}
     */
    public function execute(PurchaseRequest $purchaseRequest, User $user): array
    {
        if (! $user->canProcessSupply()) {
            return ['success' => false, 'message' => 'Only the Supply Officer can finalize a Purchase Request.'];
        }

        if ($purchaseRequest->status !== PurchaseRequest::STATUS_SUBMITTED) {
            return [
                'success' => false,
                'message' => 'Only submitted purchase requests can be finalized.',
            ];
        }

        DB::transaction(function () use ($purchaseRequest, $user) {
            $purchaseRequest->update([
                'status' => PurchaseRequest::STATUS_FINALIZED,
                'finalized_by' => $user->id,
                'finalized_at' => now(),
            ]);

            AuditLog::log(
                'Finalized Purchase Request',
                'Purchase Request',
                "Finalized {$purchaseRequest->pr_number} — ready for printing and physical submission to Procurement."
            );
        });

        \App\Services\PurchaseRequestNotificationService::notifyFinalized($purchaseRequest->fresh());

        return [
            'success' => true,
            'message' => "{$purchaseRequest->pr_number} finalized — ready to print.",
        ];
    }
}
