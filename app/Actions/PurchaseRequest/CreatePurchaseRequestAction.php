<?php

namespace App\Actions\PurchaseRequest;

use App\Models\AuditLog;
use App\Models\Part;
use App\Models\PurchaseRequest;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Creates a PR document (status: submitted) from the PR form.
 *
 * Two entry points:
 *  - createFromForm(): the PR Form page (IT / Super Admin / Supply Officer)
 *  - prefillFromRequisition(): builds form data from a requisition's deficit
 *    lines, used to pre-fill the form (?requisition_id=N).
 */
class CreatePurchaseRequestAction
{
    /**
     * Build pre-fill form data from the short (deficit) parts-stock lines of
     * a requisition. Does NOT create anything — returns form data for the view.
     */
    public function prefillFromRequisition(Requisition $requisition): array
    {
        $items = [];

        foreach ($requisition->items ?? [] as $line) {
            if (($line['source'] ?? null) !== 'parts-stock' || empty($line['part_id'])) {
                continue;
            }

            $part = Part::find($line['part_id']);
            if (! $part) {
                continue;
            }

            $requested = (int) ($line['quantity'] ?? 1);
            $deficit = max(0, $requested - (int) $part->on_hand_qty);

            if ($deficit > 0) {
                $items[] = [
                    'description' => $part->item_name,
                    'unit' => $part->unit,
                    'quantity' => $deficit,
                    'unit_cost' => self::latestUnitCost($part->id),
                    'part_id' => $part->id,
                ];
            }
        }

        return [
            'items' => $items,
            'requested_by' => $requisition->requested_by,
            'purpose' => null,
        ];
    }

    /**
     * Create a PR document (status: submitted) from validated form input.
     *
     * @param  array{items: array<int, array{description: string, quantity: int, unit_cost: ?float, part_id?: ?int}>, purpose?: ?string, remarks?: ?string, requested_by?: ?int, requisition_id?: ?int}  $data
     * @return PurchaseRequest
     */
    public function createFromForm(User $user, array $data): PurchaseRequest
    {
        return DB::transaction(function () use ($user, $data) {
            $items = collect($data['items'])
                ->map(fn (array $line) => [
                    'description' => trim((string) $line['description']),
                    'unit' => trim((string) ($line['unit'] ?? '')) ?: null,
                    'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                    'unit_cost' => isset($line['unit_cost']) && $line['unit_cost'] !== null && $line['unit_cost'] !== ''
                        ? round((float) $line['unit_cost'], 2)
                        : null,
                    'part_id' => ! empty($line['part_id']) ? (int) $line['part_id'] : null,
                ])
                ->values()
                ->all();

            $total = collect($items)
                ->sum(fn (array $line) => ($line['unit_cost'] ?? 0) * $line['quantity']);

            // Requester: explicit, or the requisition's requester when linked,
            // or the creator themselves.
            $requisition = isset($data['requisition_id']) ? Requisition::find($data['requisition_id']) : null;
            $requestedBy = $data['requested_by']
                ?? $requisition?->requested_by
                ?? $user->id;

            $purchaseRequest = PurchaseRequest::create([
                'pr_number' => $this->nextPrNumber(),
                'requisition_id' => $data['requisition_id'] ?? null,
                'status' => PurchaseRequest::STATUS_SUBMITTED,
                'items' => $items,
                'purpose' => $data['purpose'] ?? null,
                'total_amount' => $total > 0 ? round($total, 2) : null,
                'fund_cluster' => $data['fund_cluster'] ?? null,
                'responsibility_center' => $data['responsibility_center'] ?? null,
                'office_unit' => $data['office_unit'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'requested_by' => $requestedBy,
                'created_by' => $user->id,
            ]);

            AuditLog::log(
                'Created Purchase Request',
                'Purchase Request',
                "Created {$purchaseRequest->pr_number} (" . count($items) . ' item(s), total ' .
                    ($total > 0 ? number_format($total, 2) : 'N/A') . ')'
            );

            return $purchaseRequest;
        });
    }

    /** Latest recorded unit value for a part (from its serialized units), else null. */
    public static function latestUnitCost(int $partId): ?float
    {
        $cost = DB::table('parts_stock_units')
            ->where('part_id', $partId)
            ->whereNotNull('unit_value')
            ->orderByDesc('id')
            ->value('unit_value');

        return $cost !== null ? (float) $cost : null;
    }

    /** Next sequential PR number for the current year: PR-YYYY-xxxx. */
    private function nextPrNumber(): string
    {
        $year = now()->year;
        $seq = (int) PurchaseRequest::where('pr_number', 'like', "PR-{$year}-%")->count() + 1;

        do {
            $number = sprintf('PR-%d-%04d', $year, $seq);
            $seq++;
        } while (PurchaseRequest::where('pr_number', $number)->exists());

        return $number;
    }

    /** Non-reserving preview of the next PR number for the form header. */
    public function previewNextPrNumber(): string
    {
        $year = now()->year;
        $last = PurchaseRequest::where('pr_number', 'like', "PR-{$year}-%")
            ->orderByDesc('pr_number')
            ->value('pr_number');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('PR-%d-%04d', $year, $seq);
    }
}
