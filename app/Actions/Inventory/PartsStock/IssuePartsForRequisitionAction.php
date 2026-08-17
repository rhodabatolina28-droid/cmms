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

        try {
            DB::transaction(function () use ($items, $requisition, $performedBy, &$issue_count, &$deficits) {
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
                        ->take($qty)
                        ->get();

                    if ($units->isNotEmpty()) {
                        foreach ($units as $unit) {
                            $unit->update([
                                'status' => 'issued',
                                'issued_at' => now(),
                            ]);
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

            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'Parts stock deducted.', 'issue_count' => $issue_count ?? 0];
    }
}