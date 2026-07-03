<?php

namespace App\Observers;

use App\Models\InventoryAsset;
use App\Models\User;
use App\Services\QrCodeService;

class InventoryAssetObserver
{
    public function created(InventoryAsset $asset): void
    {
        $asset->qr_code = QrCodeService::generateForAsset($asset);
        $asset->saveQuietly();
    }

    /**
     * Auto-sync office, department, and branch from the assigned user
     * whenever an asset is reassigned to a different user.
     *
     * This prevents PM generation from picking the wrong division
     * because the asset's office didn't match the user's actual office.
     *
     * Runs BEFORE the save so the correct values are persisted.
     */
    public function updating(InventoryAsset $asset): void
    {
        // Only sync when assigned_to_user actually changed
        if (!$asset->isDirty('assigned_to_user')) {
            return;
        }

        $newUserId = $asset->assigned_to_user;

        if ($newUserId) {
            // Asset is being assigned/transferred — sync org fields from new user
            $user = User::find($newUserId);
            if ($user) {
                $asset->office     = $user->office;
                $asset->department = $user->office; // department mirrors office
                $asset->branch     = $user->branch;
            }
        } else {
            // Asset is being unassigned — clear org fields (will become Spare via model booted)
            // Keep office/branch for historical reference but clear department
            // This allows the asset to still be found by location if needed
        }
    }
}
