<?php

namespace App\Actions\Inventory\PartsStock;

use App\Http\Requests\StockOutPartRequest;
use App\Models\AuditLog;
use App\Models\Part;
use App\Models\PartMovement;
use App\Models\PartUnit;
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
        $unitIds = $validated['unit_ids'] ?? [];
        $issuedTo = $validated['issued_to'] ?? null;
        $assetId = $validated['asset_id'] ?? null;
        $requestId = $validated['request_id'] ?? null;

        try {
            DB::transaction(function () use ($part, $qty, $validated, $user, $unitIds, $issuedTo, $assetId, $requestId) {
                $locked = Part::whereKey($part->id)->lockForUpdate()->firstOrFail();

                // Block negative stock.
                if ($locked->on_hand_qty < $qty) {
                    throw new RuntimeException('insufficient_stock');
                }

                // Tukuyin ang mga unit na mamarkahang issued.
                if (! empty($unitIds)) {
                    if (count($unitIds) !== $qty) {
                        throw new RuntimeException('unit_quantity_mismatch');
                    }

                    $units = PartUnit::where('part_id', $locked->id)
                        ->whereIn('id', $unitIds)
                        ->where('status', 'in_stock')
                        ->lockForUpdate()
                        ->get();

                    if ($units->count() !== count($unitIds)) {
                        throw new RuntimeException('invalid_units');
                    }
                } else {
                    // Auto-pick: pinakamatatandang in-stock units (kung may units).
                    $units = PartUnit::where('part_id', $locked->id)
                        ->where('status', 'in_stock')
                        ->orderBy('created_at')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->take($qty)
                        ->get();
                }

                if ($locked->requires_unit_tracking && $units->count() !== $qty) {
                    throw new RuntimeException('incomplete_unit_tracking');
                }

                if ($units->isNotEmpty()) {
                    foreach ($units as $unit) {
                        $unit->update([
                            'status' => 'issued',
                            'issued_to' => $issuedTo ?: $unit->issued_to,
                            'issued_at' => now(),
                            'asset_id' => $assetId ?: $unit->asset_id,
                            'request_id' => $requestId ?: $unit->request_id,
                        ]);
                    }
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

            if ($e->getMessage() === 'invalid_units') {
                $part->refresh();

                return response()->json([
                    'success' => false,
                    'message' => 'One or more selected units are no longer in stock.',
                    'on_hand_qty' => $part->on_hand_qty,
                ], 422);
            }

            if ($e->getMessage() === 'unit_quantity_mismatch') {
                $part->refresh();

                return response()->json([
                    'success' => false,
                    'message' => 'The selected unit count must match the quantity to issue.',
                    'on_hand_qty' => $part->on_hand_qty,
                ], 422);
            }

            if ($e->getMessage() === 'incomplete_unit_tracking') {
                $part->refresh();

                return response()->json([
                    'success' => false,
                    'message' => 'This tracked item cannot be issued until every quantity has an in-stock unit record.',
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
