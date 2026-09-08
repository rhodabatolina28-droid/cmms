<?php

namespace App\Console\Commands;

use App\Models\InventoryAsset;
use App\Models\Request as Ticket;
use Illuminate\Console\Command;

/**
 * X2 — One-time repair of downtime data corrupted by the Carbon 3 signed
 * diffInMinutes bug (see docs/asset-downtime-tracking.md, bugs B1/G1).
 *
 *  1. abs() every negative requests.downtime_duration
 *  2. Close stale open windows on terminal-status tickets (left open before
 *     the X3 fix) — downtime_end approximated with the ticket's updated_at.
 *  3. Recompute BOTH asset buckets from scratch:
 *       total_downtime    = SUM(|duration|) of ALL closed windows (any type)
 *       total_pm_downtime = SUM(|duration|) of closed PM windows
 *     Bundled (auto-generated) PM tickets credit every asset of the
 *     custodian, matching the X1 model-event behaviour.
 *
 * Run the backup FIRST: storage/ux_backup/cmms_pre_downtime_fix_YYYYMMDD.sql
 */
class RepairDowntimeData extends Command
{
    protected $signature = 'downtime:repair {--dry-run : Report what would change without saving}';

    protected $description = 'Repair negative downtime durations and recompute asset downtime buckets (Carbon 3 sign bug cleanup)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // --- Step 1: abs() negative durations --------------------------------
        $negative = Ticket::where('downtime_duration', '<', 0)->get();
        $this->info("Step 1: {$negative->count()} ticket(s) with negative downtime_duration.");

        foreach ($negative as $ticket) {
            $fixed = (int) abs((int) $ticket->downtime_duration);
            $this->line("  [ABS] Ticket {$ticket->request_number}: {$ticket->downtime_duration} -> {$fixed}");
            if (!$dryRun) {
                Ticket::whereKey($ticket->getKey())->update(['downtime_duration' => $fixed]);
            }
        }

        // --- Step 2: close stale open windows on terminal tickets ------------
        $stale = Ticket::whereNotNull('downtime_start')
            ->whereNull('downtime_end')
            ->whereIn('status', [
                Ticket::STATUS_COMPLETED,
                Ticket::STATUS_CANCELLED,
                Ticket::STATUS_REJECTED,
                Ticket::STATUS_REFERRED_EXTERNAL,
            ])
            ->get();
        $this->info("Step 2: {$stale->count()} stale open window(s) on terminal-status tickets.");

        foreach ($stale as $ticket) {
            $end = $ticket->updated_at ?? now();
            $duration = (int) abs($ticket->downtime_start->diffInMinutes($end));
            $this->line("  [CLOSE] Ticket {$ticket->request_number} ({$ticket->status}): end={$end} duration={$duration}m");
            if (!$dryRun) {
                Ticket::whereKey($ticket->getKey())->update([
                    'downtime_end' => $end,
                    'downtime_duration' => $duration,
                ]);
            }
        }

        return $this->recomputeBuckets($dryRun);
    }

    /**
     * Step 3 — recompute both asset buckets from the closed-window ledger.
     */
    private function recomputeBuckets(bool $dryRun): int
    {
        $assets = InventoryAsset::all();
        $this->info("Step 3: recomputing downtime buckets for {$assets->count()} asset(s).");

        // Closed windows: downtime_start AND downtime_end present.
        $closedTickets = Ticket::whereNotNull('downtime_start')
            ->whereNotNull('downtime_end')
            ->whereNotNull('downtime_duration')
            ->get(['id', 'type', 'is_auto_generated', 'user_id', 'linked_asset_id', 'downtime_duration']);

        // Bundled-PM custodians -> asset ids (one query per distinct custodian).
        $custodianAssetIds = [];
        foreach ($closedTickets as $ticket) {
            if ($ticket->type === 'Preventive Maintenance' && $ticket->is_auto_generated && $ticket->user_id) {
                $custodian = (int) $ticket->user_id;
                if (!array_key_exists($custodian, $custodianAssetIds)) {
                    $custodianAssetIds[$custodian] = InventoryAsset::where('assigned_to_user', $custodian)->pluck('asset_id')->all();
                }
            }
        }

        // Accumulate per-asset targets from scratch.
        $targets = []; // asset_id => ['total' => int, 'pm' => int]
        foreach ($closedTickets as $ticket) {
            $minutes = (int) abs((int) $ticket->downtime_duration);
            $isPm = $ticket->type === 'Preventive Maintenance';

            $assetIds = [];
            if ($ticket->linked_asset_id) {
                $assetIds[] = (int) $ticket->linked_asset_id;
            }
            if ($isPm && $ticket->is_auto_generated && $ticket->user_id) {
                foreach ($custodianAssetIds[(int) $ticket->user_id] ?? [] as $id) {
                    $assetIds[] = (int) $id;
                }
            }

            foreach (array_unique($assetIds) as $assetId) {
                $targets[$assetId] ??= ['total' => 0, 'pm' => 0];
                $targets[$assetId]['total'] += $minutes;
                if ($isPm) {
                    $targets[$assetId]['pm'] += $minutes;
                }
            }
        }

        $touched = 0;
        foreach ($assets as $asset) {
            $target = $targets[$asset->asset_id] ?? ['total' => 0, 'pm' => 0];
            $currentTotal = (int) $asset->total_downtime;
            $currentPm = (int) $asset->total_pm_downtime;

            if ($target['total'] === $currentTotal && $target['pm'] === $currentPm) {
                continue;
            }

            $this->line(sprintf(
                '  [RECALC] Asset #%d (%s | %s): total %d -> %d, pm %d -> %d',
                $asset->asset_id,
                $asset->item_name,
                $asset->serial_number ?? '-',
                $currentTotal,
                $target['total'],
                $currentPm,
                $target['pm']
            ));

            if (!$dryRun) {
                InventoryAsset::whereKey($asset->asset_id)->update([
                    'total_downtime' => $target['total'],
                    'total_pm_downtime' => $target['pm'],
                ]);
            }
            $touched++;
        }

        $this->info($dryRun
            ? "DRY RUN done. Assets to change: {$touched}."
            : "Done. Assets updated: {$touched}.");

        return self::SUCCESS;
    }
}
