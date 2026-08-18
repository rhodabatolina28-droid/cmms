<?php

namespace App\Actions\Inventory\PartsStock;

use App\Http\Requests\UpdatePartRequest;
use App\Models\AuditLog;
use App\Models\Part;
use App\Models\PartUnit;
use Illuminate\Support\Facades\Auth;

class UpdatePartAction
{
    public function execute(UpdatePartRequest $request, Part $part)
    {
        $user = Auth::user();
        if (! $user->canProcessSupply()) {
            return response()->json([
                'success' => false,
                'message' => 'Parts & consumables stock is managed by the Administrative supply admin.',
            ], 403);
        }

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['requires_unit_tracking'] = $request->boolean('requires_unit_tracking');

        if ($data['requires_unit_tracking']) {
            $completeUnits = PartUnit::where('part_id', $part->id)
                ->where('status', 'in_stock')
                ->whereNotNull('serial_number')
                ->whereNotNull('property_number')
                ->whereNotNull('unit_value')
                ->count();

            if ($completeUnits < $part->on_hand_qty) {
                return response()->json([
                    'success' => false,
                    'message' => 'This item cannot require unit tracking until every on-hand unit has a serial number, property number, and unit cost.',
                ], 422);
            }
        }

        $part->update($data);

        AuditLog::log(
            'Updated Part',
            'Parts & Consumables',
            "Updated {$part->item_name}",
            $part->region
        );

        $part->refresh();

        return response()->json(['success' => true, 'message' => 'Part updated successfully', 'part' => $part]);
    }
}
