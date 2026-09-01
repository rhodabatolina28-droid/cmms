<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\PurchaseRequest;
use App\Models\Requisition;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time repair for requisitions stuck at pending/approved even though a
 * linked PR was already DELIVERED (parts arrived). These predate the auto-issue
 * fix in ReceivePurchaseRequestAction, so their Supply Officer → Requisition
 * Review rows still ask for a manual "Issue" that is no longer needed.
 *
 * A requisition is marked issued when:
 *  - it is currently pending/approved, and
 *  - at least one PurchaseRequest linked to it (requisition_id) is delivered.
 *
 * Finalized-but-not-yet-delivered PRs do NOT count — the parts have not
 * arrived, so the requisition legitimately still waits.
 */
class FixStuckRequisitionIssues extends Command
{
    protected $signature = 'requisitions:fix-stuck-issued {--dry-run : Report what would change without saving}';

    protected $description = 'Mark requisitions as issued when a linked PR was already delivered (stuck-status cleanup)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Requisition ids that have a delivered PR.
        $fulfilledIds = PurchaseRequest::where('status', PurchaseRequest::STATUS_DELIVERED)
            ->whereNotNull('requisition_id')
            ->pluck('requisition_id')
            ->unique()
            ->values();

        $stuck = Requisition::whereIn('id', $fulfilledIds)
            ->whereIn('status', [Requisition::STATUS_PENDING, Requisition::STATUS_APPROVED])
            ->with('ticket')
            ->get();

        $this->info("Found {$stuck->count()} requisition(s) fulfilled by a delivered PR but still awaiting issue.");

        $fixed = 0;

        foreach ($stuck as $requisition) {
            /** @var PurchaseRequest|null $pr */
            $pr = PurchaseRequest::where('requisition_id', $requisition->id)
                ->where('status', PurchaseRequest::STATUS_DELIVERED)
                ->orderByDesc('delivered_at')
                ->first();

            $this->line("  [STUCK] Requisition #{$requisition->id} ("
                . ($requisition->ticket?->request_number ?? 'no ticket')
                . ") fulfilled by {$pr?->pr_number}.");

            if ($dryRun) {
                $fixed++;
                continue;
            }

            DB::transaction(function () use ($requisition, $pr, &$fixed) {
                $requisition->update([
                    'status' => Requisition::STATUS_ISSUED,
                    'reviewed_by' => $pr->delivered_by,
                    'reviewed_at' => $pr->delivered_at ?? now(),
                    'remarks' => trim((string) $requisition->remarks)
                        . (trim((string) $requisition->remarks) !== '' ? ' ' : '')
                        . "Auto-issued from delivered {$pr->pr_number} (stuck-status cleanup).",
                ]);

                AuditLog::log(
                    'Reviewed Requisition',
                    'Requisitions',
                    "Issue requisition #{$requisition->id} for {$pr->pr_number} (auto, stuck-status cleanup).",
                    $requisition->ticket?->region ?? null
                );

                if ($requisition->requested_by) {
                    Notification::send(
                        $requisition->requested_by,
                        $requisition->ticket?->id ?? $pr->id,
                        'Parts Request — Issued',
                        'Parts were issued for ' . ($requisition->ticket?->request_number ?? $pr->pr_number)
                            . '. You may continue repair work.'
                    );
                }

                $fixed++;
            });

            $this->line("    [FIXED] -> Issued");
        }

        if (!$dryRun && $fixed > 0) {
            AuditLog::log(
                'Requisition Stuck Status Cleanup',
                'Requisitions',
                "Marked {$fixed} requisition(s) as issued — fulfilled by already-delivered PRs.",
                null
            );
        }

        $this->info("Done. Fixed: {$fixed}.");

        return self::SUCCESS;
    }
}
