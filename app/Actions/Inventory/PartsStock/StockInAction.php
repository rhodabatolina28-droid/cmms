<?php

namespace App\Actions\Inventory\PartsStock;

use App\Http\Requests\StockInPartRequest;
use App\Models\AuditLog;
use App\Models\Part;
use App\Models\PartMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockInAction
{
    public function execute(StockInPartRequest $request, Part $part)
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

        DB::transaction(function () use ($part, $qty, $validated, $user) {
            // Row lock to avoid over-counting under concurrent updates.
            $locked = Part::whereKey($part->id)->lockForUpdate()->firstOrFail();
            $locked->increment('on_hand_qty', $qty);

            PartMovement::create([
                'part_id' => $locked->id,
                'qty_change' => $qty,
                'reason' => $validated['reason'],
                'reference_type' => $validated['reference_type'] ?? 'adjustment',
                'reference_id' => $validated['reference_id'] ?? null,
                'performed_by' => $user->id,
            ]);
        });

        $part->refresh();

        AuditLog::log(
            'Stock In',
            'Parts & Consumables',
            "Stocked in {$qty} {$part->unit} of {$part->item_name} (new on-hand: {$part->on_hand_qty})",
            $part->region
        );

        return response()->json([
            'success' => true,
            'message' => 'Stock in recorded successfully',
            'on_hand_qty' => $part->on_hand_qty,
        ]);
    }
}