<?php

use App\Models\Request as RequestModel;
use App\Models\InventoryAsset;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convert existing auto-generated PMs from PENDING to SCHEDULED
        RequestModel::where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true)
            ->where('status', 'Pending')
            ->update([
                'status' => RequestModel::STATUS_SCHEDULED,
                'assigned_to' => null,
            ]);

        // Backfill old non-auto-generated PMs still in PENDING status
        RequestModel::where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', false)
            ->where('status', 'Pending')
            ->whereNull('is_auto_generated')
            ->update([
                'status' => RequestModel::STATUS_SCHEDULED,
            ]);

        // Set initial next_pm_due_date for assets with completed PMs
        $completedPmAssetIds = RequestModel::where('type', 'Preventive Maintenance')
            ->where('status', 'Completed')
            ->whereNotNull('linked_asset_id')
            ->pluck('linked_asset_id')
            ->unique();

        foreach ($completedPmAssetIds as $assetId) {
            $asset = InventoryAsset::find($assetId);
            if ($asset && !$asset->next_pm_due_date) {
                $asset->next_pm_due_date = now()->addMonths(3);
                $asset->save();
            }
        }
    }

    public function down(): void
    {
        // Reverse SCHEDULED back to PENDING for auto-generated PMs
        RequestModel::where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true)
            ->where('status', RequestModel::STATUS_SCHEDULED)
            ->update([
                'status' => 'Pending',
            ]);
    }
};
