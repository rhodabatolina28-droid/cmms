<?php

namespace App\Actions\Inventory;

use App\Enums\AssetStatus;
use App\Models\InventoryAsset;
use App\Models\Scopes\InventoryScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListParentAssetsAction
{
    /**
     * AJAX endpoint — lists valid candidate parent assets (standalone PAR sets)
     * so a supply admin can attach a manual component without typing a raw ID.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(Request $request)
    {
        $user = Auth::user();

        if (! $user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Only the Administrative supply admin can select parent set assets.'], 403);
        }

        $q = $request->input('q', '');

        $query = InventoryAsset::with('assignedUser')
            ->whereNull('parent_asset_id')
            ->whereNotNull('par_number')
            // Locked/disposed assets cannot be set parents
            ->whereNotIn('status', AssetStatus::LOCKED);

        InventoryScope::scopeAssetsToActor($query, $user);

        if (mb_strlen($q) >= 2) {
            $lower = mb_strtolower($q);
            $query->where(function ($qry) use ($lower) {
                $qry->whereRaw('LOWER(item_name) LIKE ?', ["%{$lower}%"])
                    ->orWhereRaw('LOWER(serial_number) LIKE ?', ["%{$lower}%"])
                    ->orWhereRaw('LOWER(par_number) LIKE ?', ["%{$lower}%"])
                    ->orWhereRaw('LOWER(property_number) LIKE ?', ["%{$lower}%"]);
            });
        }

        $assets = $query
            ->orderBy('item_name')
            ->limit(200)
            ->get(['asset_id', 'item_name', 'par_number', 'serial_number', 'property_number', 'category', 'assigned_to_user'])
            ->map(fn ($a) => [
                'asset_id'       => $a->asset_id,
                'item_name'      => $a->item_name,
                'par_number'     => $a->par_number,
                'serial_number'  => $a->serial_number,
                'property_number'=> $a->property_number,
                'category'       => $a->category,
                'custodian_name' => $a->assignedUser?->full_name ?: ($a->assignedUser?->name ?? null),
            ]);

        return response()->json(['success' => true, 'assets' => $assets]);
    }
}