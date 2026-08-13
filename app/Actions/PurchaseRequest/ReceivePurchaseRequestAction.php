<?php

namespace App\Actions\PurchaseRequest;

use App\Models\AuditLog;
use App\Models\Part;
use App\Models\PartMovement;
use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReceivePurchaseRequestAction
{
    /**
     * Mark a PR as received and stock-in every part line (on_hand increases).
     */
    public function execute(PurchaseRequest $pr)
    {
        $user = Auth::user();
        if (! $user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Only supply can receive a Purchase Request.'], 403);
        }

        if (!$pr->isApproved()) {
            return response()->json(['success' => false, 'message' => 'Approve the Purchase Request first before receiving.'], 422);
        }

        DB::transaction(function () use ($pr, $user) {
            foreach ($pr->items ?? [] as $line) {
                if (empty($line['part_id'])) {
                    continue;
                }

                $part = Part::whereKey($line['part_id'])->lockForUpdate()->first();
                if (!$part) {
                    continue;
                }

                $qty = (int) ($line['quantity'] ?? 1);
                if ($qty < 1) {
                    continue;
                }

                $part->increment('on_hand_qty', $qty);

                PartMovement::create([
                    'part_id' => $part->id,
                    'qty_change' => $qty,
                    'reason' => 'PR received — ' . $pr->pr_number,
                    'reference_type' => 'purchase',
                    'reference_id' => $pr->id,
                    'performed_by' => $user->id,
                ]);
            }

            $pr->update([
                'status' => PurchaseRequest::STATUS_RECEIVED,
                'received_by' => $user->id,
                'received_at' => now(),
            ]);
        });

        AuditLog::log('Received PR', 'Purchase Request', "Received {$pr->pr_number}", $user->region);

        return response()->json([
            'success' => true,
            'message' => $pr->pr_number . ' received — stock has been updated.',
            'redirect' => route('purchase_requests.show', $pr->id),
        ]);
    }
}