<?php

namespace App\Actions\Inventory\PartsStock;

use App\Http\Requests\StockOutPartRequest;
use App\Models\AuditLog;
use App\Models\Part;
use App\Models\PartMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockOutAction
{
    public function execute(StockOutPartRequest $request, Part $part)
    {
        $user = Auth::user();
        if (! $user->canProcessSupply()) {
            return response()->json([
                'success' => false,
                'message' => 'Parts & consumables stock is managed by the Administrative supply admin.',
            ], 403);
        }

        $validated = $request->validated();
        $qty = (int) $validated['qty'];

        try {
            DB::transaction(function () use ($part, $qty, $validated, $user) {
                $locked = Part::whereKey($part->id)->lockForUpdate()->firstOrFail();

                // Block negative stock.
                if ($locked->on_hand_qty < $qty) {
                    throw new RuntimeException('insufficient_stock');
                }

                $locked->decrement('on_hand_qty', $qty);

                PartMovement::create([
                    'part_id' => $locked->id,
                    'qty_change' => -1 * $qty,
                    'reason' => $validated['reason'],
                    'reference_type' => $validated['reference_type'] ?? 'adjustment',
                    'reference_id' => $validated['reference_id'] ?? null,
                    'performed_by' => $user->id,
                ]);
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'insufficient_stock') {
                $part->refresh();

                return response()->json([
                    'success' => false,
                    'message' => 'Not enough stock on hand to issue this quantity.',
                    'on_hand_qty' => $part->on_hand_qty,
                ], 422);
            }

            throw $e;
        }

        $part->refresh();

        AuditLog::log(
            'Stock Out',
            'Parts & Consumables',
            "Issued {$qty} {$part->unit} of {$part->item_name} (new on-hand: {$part->on_hand_qty})",
            $part->region
        );

        return response()->json([
            'success' => true,
            'message' => 'Stock issued successfully',
            'on_hand_qty' => $part->on_hand_qty,
        ]);
    }
}