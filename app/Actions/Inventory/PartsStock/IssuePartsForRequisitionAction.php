<?php

namespace App\Actions\Inventory\PartsStock;

use App\Models\Part;
use App\Models\PartMovement;
use App\Models\PartUnit;
use App\Models\Requisition;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Deduct on-hand qty for every parts-stock line in a requisition when Supply issues.
 *
 * All-or-nothing: validates the entire order first, then deducts — so a partial
 * shortage never leaves the stock half-edited. Each deduction records a
 * PartMovement linked back to the requisition (audit trail).
 */
class IssuePartsForRequisitionAction
{
    /**
     * @return array{success: bool, message: string, issue_count?: int, deficits?: array<int, array<string, mixed>>}
     */
    public function execute(Requisition $requisition, ?int $performedBy = null): array
    {
        $items = $requisition->items ?? [];
        $ticket = $requisition->ticket;
        $assetId = $ticket?->linked_asset_id;
        // The IT requester is not the recipient. The ticket's linked asset
        // supplies the accountable custodian for ticket-based releases.
        $custodianId = $ticket?->linkedAsset?->assigned_to_user;
        $ticketId = $ticket?->id;

        try {
            DB::transaction(function () use ($items, $requisition, $performedBy, $assetId, $custodianId, $ticketId, &$issue_count, &$deficits) {
                $issue_count = 0;
                $deficits = [];
                $toDeduct = []; // validated lines ready for deduction

                foreach ($items as $line) {
                    $source = $line['source'] ?? null;
                    $partId = $line['part_id'] ?? null;

                    // Only parts-stock lines affect on_hand. Everything else is
                    // handled by the existing inventory/spare workflow.
                    if ($source !== 'parts-stock' || !$partId) {
                        continue;
                    }

                    $qty = (int) ($line['quantity'] ?? 1);
                    if ($qty < 1) {
                        continue;
                    }

                    $part = Part::whereKey($partId)->lockForUpdate()->first();
                    if (!$part) {
                        continue;
                    }

                    if ($part->on_hand_qty < $qty) {
                        $deficits[] = [
                            'description' => $part->item_name,
                            'available' => $part->on_hand_qty,
                            'requested' => $qty,
                        ];
                        continue;
                    }

                    $toDeduct[] = ['part' => $part, 'qty' => $qty];
                }

                if (!empty($deficits)) {
                    throw new RuntimeException('insufficient_stock');
                }

                foreach ($toDeduct as $entry) {
                    /** @var Part $part */
                    $part = $entry['part'];
                    $qty = $entry['qty'];

                    $part->decrement('on_hand_qty', $qty);

                    // Consistency: kapag may per-unit records ang part, markahan ang
                    // N na pinakamatatandang in_stock units bilang issued para hindi
                    // mag-iba ang on_hand sa bilang ng in_stock units.
                    $units = PartUnit::where('part_id', $part->id)
                        ->where('status', 'in_stock')
                        ->orderBy('created_at')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->take($qty)
                        ->get();

                    if ($part->requires_unit_tracking && $units->count() !== $qty) {
                        throw new RuntimeException('incomplete_unit_tracking');
                    }

                    if ($units->isNotEmpty()) {
                        foreach ($units as $unit) {
                            $unit->update([
                                'status' => 'issued',
                                'issued_to' => $custodianId,
                                'asset_id' => $assetId,
                                'request_id' => $ticketId,
                                'issued_at' => now(),
                            ]);

                            // Lifecycle parity with the PR receive flow: every issued
                            // unit must show up on the asset's Lifecycle History so
                            // administrators can trace when the part was installed.
                            if ($assetId) {
                                \App\Models\InventoryHistory::create([
                                    'asset_id' => $assetId,
                                    'action' => 'Part Installed',
                                    'performed_by' => $performedBy,
                                    'new_user_id' => $custodianId ?: null,
                                    'remarks' => 'Installed ' . $part->item_name
                                        . (trim((string) ($unit->serial_number ?? '')) !== ''
                                            ? ' (SN:' . $unit->serial_number . ')' : '')
                                        . ' via ' . ($ticket?->request_number ?? ('REQ #' . $requisition->id)),
                                ]);
                            }
                        }
                    }

                    PartMovement::create([
                        'part_id' => $part->id,
                        'qty_change' => -1 * $qty,
                        'reason' => 'Issue to requisition #' . $requisition->id,
                        'reference_type' => 'requisition',
                        'reference_id' => $requisition->id,
                        'performed_by' => $performedBy,
                    ]);

                    $issue_count++;
                }
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'insufficient_stock') {
                return [
                    'success' => false,
                    'message' => 'Insufficient parts stock to issue this requisition.',
                    'deficits' => $deficits ?? [],
                ];
            }

            if ($e->getMessage() === 'incomplete_unit_tracking') {
                return [
                    'success' => false,
                    'message' => 'A tracked item cannot be issued until every quantity has an in-stock unit record.',
                ];
            }

            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'Parts stock deducted.', 'issue_count' => $issue_count ?? 0];
    }
}
