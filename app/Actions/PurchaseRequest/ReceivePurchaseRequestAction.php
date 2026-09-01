<?php

namespace App\Actions\PurchaseRequest;

use App\Models\AuditLog;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Receives a finalized PR - records that the purchased goods physically arrived.
 *
 * Authorization (user-decided ₱10,000 rule):
 *  - total < 10,000 : the owning IT requester / SuperAdmin buys outside the
 *    system and may receive their own PR (Supply Officer remains a backup).
 *  - total >= 10,000 : Procurement track - only the Supply Officer receives.
 *
 * Every receive below the threshold additionally requires at least one
 * uploaded receipt attachment (checked in execute(); see Phase C6 gate).
 */
class ReceivePurchaseRequestAction
{
    /** Purchases strictly below this amount use the IT fast-track. */
    public const SMALL_PURCHASE_THRESHOLD = 10000;

    /**
     * Record delivery of all goods on this PR.
     *
     * @return array{success: bool, message: string}
     */
    public function execute(PurchaseRequest $purchaseRequest, User $user, array $lines = []): array
    {
        if (! $this->canReceive($purchaseRequest, $user)) {
            return [
                'success' => false,
                'message' => $this->denialReason($purchaseRequest, $user),
            ];
        }

        // C6 - proof-of-purchase gate: a sub-threshold purchase was made by
        // the requester outside the system; the receipt file is the only
        // check-and-balance. No receipt -> no receive.
        if ($purchaseRequest->isSmallPurchase()
            && ! $purchaseRequest->attachments()->exists()) {
            return [
                'success' => false,
                'message' => 'Upload at least one receipt (PDF/JPG/PNG) before receiving this purchase.',
            ];
        }

        $ticket = $purchaseRequest->request ?: $purchaseRequest->requisition?->ticket;

        try {
            DB::transaction(function () use ($purchaseRequest, $user, $lines, $ticket) {
                if (! empty($lines)) {
                    $this->processLines($purchaseRequest, $lines, $ticket);
                }

                $purchaseRequest->update([
                    'status' => PurchaseRequest::STATUS_DELIVERED,
                    'delivered_by' => $user->id,
                    'delivered_at' => now(),
                ]);

                // When the parts on a PR tied to a requisition arrive, the
                // requisition is fulfilled — reflect that in the Supply Officer
                // → Requisition Review by marking it issued (so it no longer
                // waits in the queue and the IT requester is notified).
                $source = $purchaseRequest->requisition;
                if ($source && in_array($source->status, [
                    \App\Models\Requisition::STATUS_PENDING,
                    \App\Models\Requisition::STATUS_APPROVED,
                ], true)) {
                    $source->update([
                        'status' => \App\Models\Requisition::STATUS_ISSUED,
                        'reviewed_by' => $user->id,
                        'reviewed_at' => now(),
                        'remarks' => trim((string) $source->remarks)
                            . (trim((string) $source->remarks) !== '' ? ' ' : '')
                            . "Auto-issued from delivered {$purchaseRequest->pr_number}.",
                    ]);

                    \App\Models\AuditLog::log(
                        'Reviewed Requisition',
                        'Requisitions',
                        "Issue requisition #{$source->id} for {$purchaseRequest->pr_number} (auto, on delivery).",
                        $purchaseRequest->request?->region ?? $source->ticket?->region ?? $user->region
                    );

                    // Notify the IT requester that the parts are now issued.
                    if ($source->requested_by) {
                        \App\Services\RequestNotificationService::notifyItOfRequisitionAction($source, 'issued');
                    }
                }

                AuditLog::log(
                    'Received Purchase Request',
                    'Purchase Request',
                    "Marked {$purchaseRequest->pr_number} as delivered by {$user->full_name}."
                );
            });
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        \App\Services\PurchaseRequestNotificationService::notifyDelivered($purchaseRequest->fresh());

        return [
            'success' => true,
            'message' => "{$purchaseRequest->pr_number} marked as delivered.",
        ];
    }

    /**
     * C5 - per-line goods bookkeeping. Validates everything BEFORE touching
     * stock so a bad line never leaves half-processed numbers behind.
     */
    private function processLines(PurchaseRequest $purchaseRequest, array $lines, $ticket): void
    {
        $items = $purchaseRequest->items ?? [];
        $assetId = $ticket?->linked_asset_id;
        $custodianId = $assetId
            ? (\App\Models\InventoryAsset::find($assetId)->assigned_to_user ?? null)
            : null;

        if (count($lines) !== count($items)) {
            throw new \InvalidArgumentException('Every item on the purchase request must be received.');
        }

        $prepared = $this->validateLines($purchaseRequest, $lines, $assetId);
        $this->applyLines($purchaseRequest, $prepared, $assetId, $custodianId, $ticket?->id);
    }

    /**
     * Pure validation pass - returns normalized line data or throws.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function validateLines(PurchaseRequest $purchaseRequest, array $lines, ?int $assetId): array
    {
        $items = $purchaseRequest->items ?? [];
        $prepared = [];

        foreach ($lines as $i => $line) {
            if ($i >= count($items)) {
                break;
            }
            $qty = max(1, (int) ($items[$i]['quantity'] ?? 1));
            // Carry the PR unit cost into the created unit records so the
            // parts table Unit Value / Total and the Units modal show the price.
            $unitCost = (isset($items[$i]['unit_cost']) && $items[$i]['unit_cost'] !== '')
                ? (float) $items[$i]['unit_cost'] : null;
            $destination = $line['destination'] ?? '';
            if (! in_array($destination, ['stock-in', 'direct-asset'], true)) {
                throw new \InvalidArgumentException('Each line needs a destination: stock-in or direct-to-asset.');
            }
            if ($destination === 'direct-asset' && ! $assetId) {
                throw new \InvalidArgumentException('Direct-to-asset receiving requires a linked asset on the job order. Use stock-in instead.');
            }

            // "Create new part on the fly" — the purchased item is not yet in
            // Parts Stock, so register it here instead of blocking the receive.
            $part = null;
            if (($line['part_id'] ?? '') === 'new') {
                $part = $this->createPartOnTheFly($purchaseRequest, $line);
            } else {
                $part = \App\Models\Part::whereKey((int) ($line['part_id'] ?? 0))->first();
            }
            if (! $part) {
                throw new \InvalidArgumentException('Match every purchased item to an existing part from Parts Stock (or choose "Create new...").');
            }

            $units = collect($line['units'] ?? [])
                ->map(fn ($u) => [
                    'serial_number' => trim((string) ($u['serial_number'] ?? '')),
                    'property_number' => trim((string) ($u['property_number'] ?? '')),
                ])
                ->filter(fn ($u) => $u['serial_number'] !== '' || $u['property_number'] !== '')
                ->values();

            if ($part->requires_unit_tracking) {
                if ($units->count() !== $qty) {
                    throw new \InvalidArgumentException("Tracked part '{$part->item_name}' needs one serial + property number per quantity.");
                }
                $serials = $units->pluck('serial_number')->filter()->values();
                $properties = $units->pluck('property_number')->filter()->values();
                if ($serials->unique()->count() !== $serials->count()
                    || $properties->unique()->count() !== $properties->count()) {
                    throw new \InvalidArgumentException("Serial and property numbers must be unique within this receipt for '{$part->item_name}'.");
                }
                $clash = \App\Models\PartUnit::where('part_id', $part->id)
                    ->where(function ($q) use ($serials, $properties) {
                        $q->whereIn('serial_number', $serials)->orWhereIn('property_number', $properties);
                    })
                    ->exists();
                if ($clash) {
                    throw new \InvalidArgumentException("A unit with one of those serial / property numbers already exists for '{$part->item_name}'.");
                }
            }

            $prepared[] = compact('part', 'qty', 'destination', 'units', 'unitCost');
        }

        return $prepared;
    }

    /**
     * May this user receive this PR right now? (status + threshold + role)
     */
    public function canReceive(PurchaseRequest $purchaseRequest, User $user): bool
    {
        if ($purchaseRequest->status !== PurchaseRequest::STATUS_FINALIZED) {
            return false;
        }

        // Supply Officer: full authority on every finalized PR (both tracks).
        if ($user->canProcessSupply()) {
            return true;
        }

        // Small-purchase fast track: owner (IT/SA/SA requester) may receive
        // their own sub-threshold PR once it has been finalized for printing.
        if ($this->isSmallPurchase($purchaseRequest) && $purchaseRequest->isOwnedBy($user)) {
            return true;
        }

        return false;
    }

    /**
     * Human-readable explanation when canReceive() fails (UI hints / tests).
     */
    public function denialReason(PurchaseRequest $purchaseRequest, User $user): string
    {
        if ($purchaseRequest->status === PurchaseRequest::STATUS_SUBMITTED) {
            return 'PR must be finalized before goods can be received.';
        }
        if ($purchaseRequest->status !== PurchaseRequest::STATUS_FINALIZED) {
            return 'Only finalized purchase requests can be received.';
        }
        if (! $this->isSmallPurchase($purchaseRequest)) {
            return 'Requests of PHP 10,000 and above are received by the Supply Officer after procurement.';
        }

        return 'Only the request owner or the Supply Officer can receive this purchase request.';
    }

    /**
     * True when the whole PR value sits below the fast-track threshold.
     */
    public function isSmallPurchase(PurchaseRequest $purchaseRequest): bool
    {
        return $purchaseRequest->isSmallPurchase();
    }

    /**
     * Register a brand-new part during receiving ("Not in the list? Create
     * new..."). Duplicate protection: case-insensitive exact-name match is
     * rejected with guidance toward the existing record. Region inherited
     * from the linked asset (fallback: requester's own).
     */
    private function createPartOnTheFly(PurchaseRequest $purchaseRequest, array $line): \App\Models\Part
    {
        $name = trim((string) ($line['new_part_name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Enter a name for the new inventory item (or match it to an existing part).');
        }
        if (mb_strlen($name) > 190) {
            throw new \InvalidArgumentException('New item name is too long (max 190 characters).');
        }
        $unit = trim((string) ($line['new_part_unit'] ?? 'pcs'));
        if (! in_array($unit, ['pcs', 'box', 'set', 'pair', 'pack'], true)) {
            $unit = 'pcs';
        }

        // Duplicate guard — case-insensitive exact match within the catalog.
        $existing = \App\Models\Part::whereRaw('LOWER(item_name) = ?', [mb_strtolower($name)])->first();
        if ($existing) {
            throw new \InvalidArgumentException("'{$name}' already exists in Parts Stock as '{$existing->item_name}'. Pick it from the list instead of creating a duplicate.");
        }

        $ticket = $purchaseRequest->request ?: $purchaseRequest->requisition?->ticket;
        $region = $ticket?->linkedAsset?->region
            ?? $purchaseRequest->requester?->region
            ?? null;
        $branch = $ticket?->linkedAsset?->branch
            ?? $purchaseRequest->requester?->branch
            ?? null;

        $part = \App\Models\Part::create([
            'item_name' => $name,
            'unit' => $unit,
            'on_hand_qty' => 0,
            'reorder_level' => 0,
            'requires_unit_tracking' => true, // accountable property: serial + property no. collected below
            'region' => $region,
            'branch' => $branch,
        ]);

        AuditLog::log(
            'Part Created During Receiving',
            'Parts & Consumables',
            "Created {$part->item_name} ({$part->unit}) while receiving {$purchaseRequest->pr_number}."
        );

        return $part;
    }

    /**
     * Validation has passed - apply stock / unit bookkeeping per line.
     *
     * @param  array<int, array<string, mixed>>  $prepared
     */
    private function applyLines(PurchaseRequest $purchaseRequest, array $prepared, ?int $assetId, $custodianId, ?int $ticketId): void
    {
        foreach ($prepared as $entry) {
            /** @var \App\Models\Part $part */
            $part = $entry['part'];
            $qty = $entry['qty'];
            $destination = $entry['destination'];
            $unitValue = $entry['unitCost'] ?? null;

            if ($destination === 'stock-in') {
                $locked = \App\Models\Part::whereKey($part->id)->lockForUpdate()->firstOrFail();
                $locked->increment('on_hand_qty', $qty);

                foreach ($entry['units'] as $u) {
                    \App\Models\PartUnit::create($u + [
                        'part_id' => $locked->id,
                        'unit_value' => $unitValue,
                        'status' => 'in_stock',
                    ]);
                }

                \App\Models\PartMovement::create([
                    'part_id' => $locked->id,
                    'qty_change' => $qty,
                    'reason' => 'Stock-in from ' . $purchaseRequest->pr_number,
                    'reference_type' => 'purchase_request',
                    'reference_id' => $purchaseRequest->id,
                    'performed_by' => Auth::id(),
                ]);
            } else {
                // Direct-to-asset: units skip general stock entirely and are
                // recorded as already issued to the linked asset + custodian,
                // which surfaces them on the asset's Installed Parts card.
                foreach ($entry['units'] as $u) {
                    \App\Models\PartUnit::create($u + [
                        'part_id' => $part->id,
                        'unit_value' => $unitValue,
                        'status' => 'issued',
                        'issued_to' => $custodianId,
                        'asset_id' => $assetId,
                        'request_id' => $ticketId,
                        'issued_at' => now(),
                    ]);

                    // Phase 4: record a Lifecycle History entry on the asset so
                    // the asset profile's tracking shows the part was installed
                    // (with its serial + originating PR).
                    if ($assetId) {
                        \App\Models\InventoryHistory::create([
                            'asset_id' => $assetId,
                            'action' => 'Part Installed',
                            'performed_by' => Auth::id(),
                            'new_user_id' => $custodianId ?: null,
                            'remarks' => 'Installed ' . $part->item_name
                                . (trim((string) ($u['serial_number'] ?? '')) !== ''
                                    ? ' (SN:' . $u['serial_number'] . ')' : '')
                                . ' via ' . ($purchaseRequest->pr_number ?? ''),
                        ]);
                    }
                }

                // Phase 5: also log a parts movement so the parts History modal
                // is not empty for direct-to-asset installs - qty 0 because the
                // unit never entered inventory (it went straight to the asset).
                \App\Models\PartMovement::create([
                    'part_id' => $part->id,
                    'qty_change' => 0,
                    'reason' => 'Installed to asset #' . ($assetId ?? '?') . ' via ' . $purchaseRequest->pr_number,
                    'reference_type' => 'purchase_request',
                    'reference_id' => $purchaseRequest->id,
                    'performed_by' => Auth::id(),
                ]);
            }
        }
    }
}
