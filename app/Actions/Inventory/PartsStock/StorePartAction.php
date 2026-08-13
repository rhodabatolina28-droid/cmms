<?php

namespace App\Actions\Inventory\PartsStock;

use App\Http\Requests\StorePartRequest;
use App\Models\AuditLog;
use App\Models\Part;
use Illuminate\Support\Facades\Auth;

class StorePartAction
{
    public function execute(StorePartRequest $request)
    {
        $user = Auth::user();
        if (! $user->canProcessSupply()) {
            return response()->json([
                'success' => false,
                'message' => 'Parts & consumables stock is managed by the Administrative supply admin.',
            ], 403);
        }

        $data = $request->validated();
        $data['region'] = $data['region'] ?? $user->region;
        $data['branch'] = $data['branch'] ?? $user->branch;
        $data['is_active'] = true;

        $part = Part::create($data);

        AuditLog::log(
            'Added Part',
            'Parts & Consumables',
            "Added {$part->item_name} ({$part->unit}) with {$part->on_hand_qty} on hand",
            $part->region
        );

        return response()->json(['success' => true, 'message' => 'Part added successfully', 'part' => $part]);
    }
}