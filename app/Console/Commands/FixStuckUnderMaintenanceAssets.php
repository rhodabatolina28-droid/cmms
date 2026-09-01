<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use App\Models\Request as Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time repair for assets stuck at "Under Maintenance" even though their
 * maintenance ticket(s) are already Completed/Cancelled.
 *
 * Root cause (see docs/PM_REPAIR_PARTS_REQUISITION.md, Phase PM-FIX): once a
 * bundled (auto-generated) PM ticket got a repair-linked asset, the completion
 * status sync only restored that single asset and left the user's other
 * assets at "Under Maintenance" forever.
 *
 * An asset is restored to Active when:
 *  - it is currently "Under Maintenance", and
 *  - no active (non-completed/cancelled) ICT or PM ticket references it,
 *    either directly (linked_asset_id) or via an active bundled PM
 *    (auto-generated PM with no linked asset covering the custodian).
 */
class FixStuckUnderMaintenanceAssets extends Command
{
    protected $signature = 'maintenance:fix-stuck-assets {--dry-run : Report what would change without saving}';

    protected $description = 'Restore assets stuck at "Under Maintenance" whose maintenance tickets are already finished';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $assets = InventoryAsset::where('status', 'Under Maintenance')->get();

        $this->info("Found {$assets->count()} asset(s) currently \"Under Maintenance\".");

        $restored = 0;
        $skipped = 0;

        foreach ($assets as $asset) {
            $hasActiveTicket = Ticket::where(function ($q) use ($asset) {
                    $q->where('linked_asset_id', $asset->asset_id)
                        ->orWhere(function ($sub) use ($asset) {
                            // Active bundled (auto-generated) PM covering this custodian
                            $sub->whereNull('linked_asset_id')
                                ->where('type', 'Preventive Maintenance')
                                ->where('is_auto_generated', true)
                                ->where('user_id', $asset->assigned_to_user);
                        });
                })
                ->whereNotIn('status', [Ticket::STATUS_COMPLETED, Ticket::STATUS_CANCELLED])
                ->exists();

            if ($hasActiveTicket) {
                $skipped++;
                $this->line("  [SKIP] Asset #{$asset->asset_id} ({$asset->item_name}) still has an active ticket.");
                continue;
            }

            if ($dryRun) {
                $restored++;
                $this->line("  [WOULD FIX] Asset #{$asset->asset_id} ({$asset->item_name}) -> Active");
                continue;
            }

            DB::transaction(function () use ($asset, &$restored) {
                $previousStatus = $asset->status;
                $asset->status = 'Active';
                $asset->save();

                InventoryHistory::create([
                    'asset_id' => $asset->asset_id,
                    'action' => 'PM Stuck Status Restored',
                    'performed_by' => null,
                    'previous_user_id' => $asset->assigned_to_user,
                    'new_user_id' => $asset->assigned_to_user,
                    'previous_status' => $previousStatus,
                    'new_status' => $asset->status,
                    'remarks' => 'Asset restored from "Under Maintenance" to Active: no active maintenance ticket references it (stuck-status cleanup).',
                ]);

                $restored++;
                $this->line("  [FIXED] Asset #{$asset->asset_id} ({$asset->item_name}) -> Active");
            });
        }

        if (!$dryRun && $restored > 0) {
            AuditLog::log(
                'PM Stuck Status Cleanup',
                'Inventory',
                "Restored {$restored} asset(s) stuck at \"Under Maintenance\" with no active maintenance ticket.",
                null
            );
        }

        $this->info("Done. Restored: {$restored}. Skipped (active ticket): {$skipped}.");

        return self::SUCCESS;
    }
}
