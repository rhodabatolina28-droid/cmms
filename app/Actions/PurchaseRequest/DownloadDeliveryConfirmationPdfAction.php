<?php

namespace App\Actions\PurchaseRequest;

use App\Models\PurchaseRequest;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

class DownloadDeliveryConfirmationPdfAction
{
    /**
     * Formal one-page "Delivery Confirmation" PDF for a delivered purchase
     * request: document header, received items with per-piece serial /
     * property numbers and destinations, the proof-of-purchase register,
     * and signature lines. Available to the same audience as the view-only
     * delivery record (owner, Supply Officer, Super Admin).
     */
    public function download(PurchaseRequest $purchaseRequest, User $user)
    {
        $gate = new ReceivePurchaseRequestAction;

        // Delivery confirmation requires an actual recorded delivery — a
        // merely-finalized PR has no receipt data to certify yet.
        if (! $purchaseRequest->isDelivered()
            || ! $gate->canViewDelivery($purchaseRequest, $user)) {
            return redirect()
                ->route('purchase_requests.show', $purchaseRequest->id)
                ->withErrors(['receive' => $gate->viewDenialReason($purchaseRequest, $user)]);
        }

        $purchaseRequest->load(['requester', 'creator', 'finalizer', 'deliverer', 'attachments.uploader']);

        // Same unit query as the view-only delivery panel — the recorded
        // pieces (serial + property) are the source of truth.
        $unitsByPart = \App\Models\PartUnit::query()
            ->where('purchase_request_id', $purchaseRequest->id)
            ->with(['part', 'asset'])
            ->get()
            ->groupBy('part_id');

        $lines = [];
        foreach ($purchaseRequest->items ?? [] as $item) {
            $lines[] = $this->buildLine($item, $unitsByPart);
        }

        if (ob_get_length()) {
            ob_end_clean();
        }

        $pdf = Pdf::loadView('pdf.delivery-confirmation', [
            'pr'    => $purchaseRequest,
            'lines' => $lines,
        ])->setPaper('a4', 'portrait');

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $purchaseRequest->pr_number . '-Delivery-Confirmation.pdf"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    /** One purchased line plus the physical pieces recorded against it. */
    private function buildLine(array $item, Collection $unitsByPart): array
    {
        $qty = max(1, (int) ($item['quantity'] ?? 1));

        $units = collect();
        if (! empty($item['part_id']) && isset($unitsByPart[$item['part_id']])) {
            $units = $unitsByPart[$item['part_id']]->values();
        } else {
            // Fall back to matching by item name for older PRs (or
            // "create new" lines where part_id was not stored).
            $matchName = trim((string) ($item['description'] ?? ''));
            if ($matchName !== '') {
                $units = $unitsByPart
                    ->first(fn ($group) => optional($group->first()->part)->item_name === $matchName, collect())
                    ->values();
            }
        }

        $installed = $units->firstWhere('status', 'issued');
        $stocked = $units->firstWhere('status', 'in_stock');

        $destination = $installed
            ? 'Installed on asset ' . ($installed->asset ? ($installed->asset->asset_code ?? ('#' . $installed->asset->asset_id)) : '')
            : ($stocked ? 'Add to inventory (stock)' : null);

        return [
            'description' => (string) ($item['description'] ?? ''),
            'qty'         => $qty,
            'unit'        => (string) ($item['unit'] ?? 'pcs'),
            'unit_cost'   => $item['unit_cost'] ?? null,
            'total'       => isset($item['unit_cost']) ? round($qty * (float) $item['unit_cost'], 2) : null,
            'units'       => $units->map(fn ($u) => [
                'serial'   => (string) $u->serial_number,
                'property' => (string) $u->property_number,
            ])->all(),
            'recorded'    => $units->count(),
            'destination' => $destination,
        ];
    }
}