<?php

namespace App\Actions\Inventory\PartsStock;

use App\Models\AuditLog;
use App\Models\Part;
use App\Models\PartUnit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StorePartUnitAction
{
    public function execute(Request $request, Part $part)
    {
        $user = Auth::user();
        if (! $user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $serial = trim((string) $request->input('serial_number'));
        $property = trim((string) $request->input('property_number'));
        $value = $request->input('unit_value');

        if ($serial !== '') {
            $dupe = PartUnit::where('part_id', $part->id)->where('serial_number', $serial)->first();
            if ($dupe) {
                return response()->json(['success' => false, 'message' => 'Duplicate serial number for this part.'], 422);
            }
        }

        $unit = null;
        DB::transaction(function () use ($part, $serial, $property, $value, &$unit) {
            $locked = Part::whereKey($part->id)->lockForUpdate()->firstOrFail();

            $unit = PartUnit::create([
                'part_id' => $locked->id,
                'serial_number' => $serial ?: null,
                'property_number' => $property ?: null,
                'unit_value' => ($value !== null && $value !== '') ? $value : null,
                'status' => 'in_stock',
            ]);

            $locked->increment('on_hand_qty', 1);
        });

        $part->refresh();

        AuditLog::log(
            'Added Part Unit',
            'Parts & Consumables',
            "Added unit {$unit->serial_number} of {$part->item_name} (new on-hand: {$part->on_hand_qty})",
            $part->region
        );

        return response()->json([
            'success' => true,
            'message' => 'Unit added successfully',
            'unit' => $unit,
            'on_hand_qty' => $part->on_hand_qty,
        ]);
    }
}