<?php

namespace App\Actions\Inventory;

use App\Models\InventoryAsset;
use App\Models\Scopes\InventoryScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchInventoryAssetsAction
{
    /**
     * AJAX endpoint — search inventory assets for assignment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(Request $request)
    {
        $user = Auth::user();
        $q = $request->input('q', '');

        $query = InventoryAsset::with('assignedUser');

        if ($user->role === 'user') {
            $query->where('assigned_to_user', $user->id)
                  ->whereNotIn('status', ['For Repair', 'For Disposal', 'Scrapped']);
        } elseif ($user->role === 'it') {
            $query->where('region', $user->region);
            if ($user->branch) $query->where('branch', $user->branch);
        } else {
            InventoryScope::scopeAssetsToActor($query, $user);
        }

        if (strlen($q) >= 2) {
            $q = strtolower($q);
            $query->where(function ($qry) use ($q) {
                $qry->whereRaw('LOWER(item_name) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(serial_number) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(property_number) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(par_number) LIKE ?', ["%{$q}%"]);
            });
        }

        $assets = $query->orderBy('item_name')->limit(50)->get(['asset_id', 'item_name', 'serial_number', 'property_number', 'category', 'status']);

        return response()->json(['success' => true, 'assets' => $assets]);
    }
}
