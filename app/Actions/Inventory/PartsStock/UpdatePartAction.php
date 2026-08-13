<?php

namespace App\Actions\Inventory\PartsStock;

use App\Http\Requests\UpdatePartRequest;
use App\Models\AuditLog;
use App\Models\Part;
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