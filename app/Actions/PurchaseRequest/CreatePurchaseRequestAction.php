<?php

namespace App\Actions\PurchaseRequest;

use App\Models\Part;
use App\Models\PurchaseRequest;
use App\Models\Requisition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreatePurchaseRequestAction
{
    /**
     * Create a PR from the short (deficit) parts-stock lines of a requisition.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(Requisition $requisition)
    {
        $user = Auth::user();
        if (! $user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Only supply can create a Purchase Request.'], 403);
        }

        return DB::transaction(function () use ($requisition, $user) {
            // One PR per requisition — avoid duplicates while one is still open.
            $existing = PurchaseRequest::where('requisition_id', $requisition->id)
                ->whereIn('status', [PurchaseRequest::STATUS_PENDING, PurchaseRequest::STATUS_APPROVED])
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'A Purchase Request already exists for this requisition.',
                    'purchase_request_id' => $existing->id,
                ]);
            }

            // Collect the short lines: parts-stock lines where on_hand < requested.
            $items = $requisition->items ?? [];
            $prItems = [];

            foreach ($items as $line) {
                if (($line['source'] ?? null) !== 'parts-stock' || empty($line['part_id'])) {
                    continue;
                }

                $part = Part::whereKey($line['part_id'])->lockForUpdate()->first();
                if (!$part) {
                    continue;
                }

                $requested = (int) ($line['quantity'] ?? 1);
                $deficit = $requested - $part->on_hand_qty;

                if ($deficit > 0) {
                    $prItems[] = [
                        'description' => $part->item_name,
                        'quantity' => $deficit,
                        'part_id' => $part->id,
                    ];
                }
            }

            if (empty($prItems)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Walang kulang na parts-stock line — lahat ay may stock.',
                ], 422);
            }

            $year = now()->year;
            $seq = PurchaseRequest::whereYear('created_at', $year)->count() + 1;

            $pr = PurchaseRequest::create([
                'pr_number' => 'PR-' . $year . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'requisition_id' => $requisition->id,
                'status' => PurchaseRequest::STATUS_PENDING,
                'items' => $prItems,
                'requested_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Purchase Request ' . $pr->pr_number . ' created.',
                'purchase_request_id' => $pr->id,
            ]);
        });
    }
}