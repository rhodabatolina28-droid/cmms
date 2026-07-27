<?php

namespace App\Actions\Inventory;

use App\Models\InventoryAsset;
use App\Models\Scopes\InventoryScope;
use Illuminate\Support\Facades\Auth;

class ApiAssetProfileAction
{
    /**
     * Return the asset API profile.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute($id)
    {
        $user = Auth::user();

        $asset = InventoryAsset::with(['assignedUser'])
            ->findOrFail($id);

        if (!InventoryScope::assetInActorViewScope($user, $asset)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $assetUserId = $asset->assigned_to_user;
        $repairHistory = \App\Models\Request::with(['repairRequest', 'maintenanceRequest'])
            ->where(function ($q) use ($id, $assetUserId) {
                $q->where('linked_asset_id', $id)
                  ->orWhere(function ($sub) use ($assetUserId) {
                      $sub->where('type', 'Preventive Maintenance')
                          ->where('is_auto_generated', true)
                          ->where('user_id', $assetUserId);
                  });
            })
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $lastRepair = $repairHistory->firstWhere('type', 'repair');
        $lastPm = $repairHistory->firstWhere('type', 'Preventive Maintenance');

        $inventoryRoute = $user->canProcessSupply()
            ? route('inventory.detail', $id)
            : ($user->role === 'super_admin' ? route('super_admin.inventory.detail', $id) : null);

        $actions = [];
        if ($user->canProcessSupply() || $user->role === 'super_admin') {
            $actions[] = [
                'label' => 'View Detail',
                'url'   => $inventoryRoute,
                'icon'  => 'fa-eye',
            ];
            if (session('physical_count_session_id')) {
                $actions[] = [
                    'label' => 'Mark in Physical Count',
                    'url'   => route('physical-count.show', session('physical_count_session_id')),
                    'icon'  => 'fa-check',
                ];
            }
        }
        if (in_array($user->role, ['it', 'super_admin'], true)) {
            $actions[] = [
                'label' => 'Create Repair Ticket',
                'url'   => route('ict.create', ['asset_id' => $id]),
                'icon'  => 'fa-screwdriver-wrench',
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'asset' => [
                    'asset_id'          => $asset->asset_id,
                    'item_name'         => $asset->item_name,
                    'serial_number'     => $asset->serial_number,
                    'par_number'        => $asset->par_number,
                    'property_number'   => $asset->property_number,
                    'brand'             => $asset->brand,
                    'model'             => $asset->model,
                    'category'          => $asset->category,
                    'status'            => $asset->status,
                    'specifications'    => $asset->specifications,
                    'assigned_user'     => $asset->assignedUser ? [
                        'id'        => $asset->assignedUser->id,
                        'full_name' => $asset->assignedUser->full_name,
                        'office'    => $asset->assignedUser->office,
                    ] : null,
                    'date_acquired'      => $asset->date_acquired?->format('Y-m-d'),
                    'acquisition_cost'   => $asset->acquisition_cost,
                    'warranty_expiration' => $asset->warranty_expiration?->format('Y-m-d'),
                    'end_of_useful_life' => $asset->end_of_useful_life?->format('Y-m-d'),
                ],
                'history' => [
                    'last_repair' => $lastRepair?->created_at->format('M d, Y'),
                    'last_pm'     => $lastPm?->created_at->format('M d, Y'),
                    'total_repairs' => $repairHistory->count(),
                ],
                'actions' => $actions,
            ],
        ]);
    }
}