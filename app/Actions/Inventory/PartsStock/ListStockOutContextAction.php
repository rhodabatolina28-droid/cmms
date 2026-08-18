<?php

namespace App\Actions\Inventory\PartsStock;

use App\Models\InventoryAsset;
use App\Models\Request as RequestModel;
use App\Models\Scopes\InventoryScope;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ListStockOutContextAction
{
    /**
     * AJAX endpoint — candidate assets, tickets, and custodians for the manual
     * Stock Out auto-fill (Phase 5). Selecting an asset or a ticket in the modal
     * auto-fills the target asset and the linked asset custodian.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute()
    {
        $user = Auth::user();

        if (! $user->canProcessSupply()) {
            return response()->json([
                'success' => false,
                'message' => 'Parts & consumables stock is managed by the Administrative supply admin.',
            ], 403);
        }

        // Assets that have a custodian, within the supply admin's scope.
        $assetQuery = InventoryAsset::with('assignedUser')->whereNotNull('assigned_to_user');
        InventoryScope::scopeAssetsToActor($assetQuery, $user);

        $assets = $assetQuery
            ->orderBy('item_name')
            ->limit(300)
            ->get(['asset_id', 'item_name', 'serial_number', 'property_number', 'assigned_to_user'])
            ->map(fn ($a) => [
                'asset_id'       => $a->asset_id,
                'item_name'      => $a->item_name,
                'serial_number'  => $a->serial_number,
                'property_number'=> $a->property_number,
                'custodian_id'   => $a->assigned_to_user,
                'custodian_name' => $a->assignedUser?->full_name ?: ($a->assignedUser?->name ?? null),
            ]);

        // Active ICT / manual-PM tickets with a linked asset that has a custodian.
        $tickets = RequestModel::with('linkedAsset.assignedUser')
            ->whereIn('type', ['ICT', 'Preventive Maintenance'])
            ->whereNotNull('linked_asset_id')
            ->whereNotIn('status', [RequestModel::STATUS_COMPLETED, RequestModel::STATUS_CANCELLED])
            ->orderByDesc('updated_at')
            ->limit(300)
            ->get(['id', 'request_number', 'type', 'linked_asset_id'])
            ->map(fn ($t) => [
                'id'             => $t->id,
                'request_number' => $t->request_number,
                'type'           => $t->type,
                'asset_id'       => $t->linked_asset_id,
                'asset_name'     => $t->linkedAsset?->item_name,
                'custodian_id'   => $t->linkedAsset?->assigned_to_user,
                'custodian_name' => $t->linkedAsset?->assignedUser?->full_name,
            ]);

        // Custodians the supply admin can pick from when no asset/ticket is used.
        $custodians = User::query()
            ->where('is_active', true)
            ->where('region', $user->region)
            ->when($user->branch, fn ($q) => $q->where('branch', $user->branch))
            ->orderBy('full_name')
            ->limit(300)
            ->get(['id', 'full_name']);

        return response()->json([
            'success' => true,
            'assets' => $assets,
            'tickets' => $tickets,
            'custodians' => $custodians,
        ]);
    }
}