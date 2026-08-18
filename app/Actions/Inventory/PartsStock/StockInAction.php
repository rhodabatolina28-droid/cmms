<?php

namespace App\Actions\Inventory\PartsStock;

use App\Http\Requests\StockInPartRequest;
use App\Models\AuditLog;
use App\Models\Part;
use App\Models\PartMovement;
use App\Models\PartUnit;
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
        $unitsData = $validated['units'] ?? [];

        if ($part->requires_unit_tracking) {
            if (count($unitsData) !== $qty) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tracked items require one unit entry for every quantity received.',
                ], 422);
            }

            foreach ($unitsData as $unit) {
                if (empty(trim((string) ($unit['serial_number'] ?? '')))
                    || empty(trim((string) ($unit['property_number'] ?? '')))
                    || !isset($unit['unit_value'])
                    || $unit['unit_value'] === '') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Each tracked unit needs a serial number, property number, and unit cost.',
                    ], 422);
                }
            }

            $serials = collect($unitsData)->pluck('serial_number')->map(fn ($serial) => trim((string) $serial));
            $properties = collect($unitsData)->pluck('property_number')->map(fn ($property) => trim((string) $property));

            if ($serials->unique()->count() !== $serials->count()
                || $properties->unique()->count() !== $properties->count()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tracked unit serial numbers and property numbers must be unique within this receipt.',
                ], 422);
            }

            if (PartUnit::where('part_id', $part->id)
                ->where(function ($query) use ($serials, $properties) {
                    $query->whereIn('serial_number', $serials)->orWhereIn('property_number', $properties);
                })
                ->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'A tracked unit with the same serial number or property number already exists for this item.',
                ], 422);
            }
        }

        DB::transaction(function () use ($part, $qty, $validated, $user, $unitsData) {
            // Row lock to avoid over-counting under concurrent updates.
            $locked = Part::whereKey($part->id)->lockForUpdate()->firstOrFail();
            $locked->increment('on_hand_qty', $qty);

            // Gumawa ng per-unit records para sa mga ibinigay na serial (max 1 kada piraso, ≤ qty).
            $count = 0;
            foreach ($unitsData as $u) {
                if ($count >= $qty) {
                    break;
                }
                PartUnit::create([
                    'part_id' => $locked->id,
                    'serial_number' => trim((string) ($u['serial_number'] ?? '')) ?: null,
                    'property_number' => trim((string) ($u['property_number'] ?? '')) ?: null,
                    'unit_value' => (isset($u['unit_value']) && $u['unit_value'] !== '') ? $u['unit_value'] : null,
                    'status' => 'in_stock',
                ]);
                $count++;
            }

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
