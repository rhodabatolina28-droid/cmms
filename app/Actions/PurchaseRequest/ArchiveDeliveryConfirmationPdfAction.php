<?php

namespace App\Actions\PurchaseRequest;

use App\Models\PurchaseRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * D7a: generates the archived final "Delivery Confirmation" PDF for a
 * delivered purchase request.
 *
 * - Renders the EXISTING pdf.delivery-confirmation blade (same as the
 *   on-demand download) — per-piece serial / property numbers included.
 * - pr-pdfs/{year}/{Month}/{pr_number}.pdf on the private ('local') disk.
 * - Month folders follow the RECORD-DATE rule (D5.1b): delivered_at,
 *   never the generation now().
 * - Idempotent: same path = overwrite; one archive per PR (guard).
 */
class ArchiveDeliveryConfirmationPdfAction
{
    public static function generate(PurchaseRequest $pr): ?string
    {
        // Only a CURRENT-flow delivered PR has receipt data to certify.
        // The legacy 'received' status is NOT archived (old records only).
        if ($pr->status !== PurchaseRequest::STATUS_DELIVERED) {
            return null;
        }

        if ($pr->archive_pdf_path) {
            return $pr->archive_pdf_path; // already archived — no duplicates
        }

        $pr->load(['requester', 'creator', 'finalizer', 'deliverer']);

        // Same unit query as the view-only delivery panel — the recorded
        // pieces (serial + property) are the source of truth.
        $unitsByPart = \App\Models\PartUnit::query()
            ->where('purchase_request_id', $pr->id)
            ->with(['part', 'asset'])
            ->get()
            ->groupBy('part_id');

        $lines = [];
        foreach ($pr->items ?? [] as $item) {
            $lines[] = (new DownloadDeliveryConfirmationPdfAction)->buildLine($item, $unitsByPart);
        }

        if (ob_get_length()) {
            ob_end_clean();
        }

        $pdf = Pdf::loadView('pdf.delivery-confirmation', [
            'pr'    => $pr,
            'lines' => $lines,
        ])->setPaper('a4', 'portrait');

        $relative = 'pr-pdfs/'
            . ($pr->delivered_at ?? $pr->updated_at)?->format('Y') . '/'
            . ($pr->delivered_at ?? $pr->updated_at)?->format('F') . '/'
            . $pr->pr_number . '.pdf';

        Storage::disk('local')->put($relative, $pdf->output());

        $pr->update(['archive_pdf_path' => $relative]);

        return $relative;
    }
}
