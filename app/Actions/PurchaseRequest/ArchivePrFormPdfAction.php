<?php

namespace App\Actions\PurchaseRequest;

use App\Models\PurchaseRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * D7c: generates the archived final PR FORM PDF (the document itself) for a
 * finalized purchase request.
 *
 * - Renders the NEW pdf.pr-form blade (DomPDF mirror of the browser-printed
 *   .prd-sheet) — official layout, item grid, purpose, signature table.
 * - pr-forms/{year}/{Month}/{pr_number}.pdf on the private ('local') disk.
 * - Month folders follow the RECORD-DATE rule (D5.1b): finalized_at,
 *   never the generation now().
 * - Idempotent: one archive per PR (separate column from the Delivery
 *   Confirmation archive — a PR produces BOTH documents across its life).
 */
class ArchivePrFormPdfAction
{
    public static function generate(PurchaseRequest $pr): ?string
    {
        if ($pr->status != PurchaseRequest::STATUS_FINALIZED
            && $pr->status != PurchaseRequest::STATUS_DELIVERED) {
            return null; // no completed PR form to certify yet
        }

        if ($pr->pr_form_pdf_path) {
            return $pr->pr_form_pdf_path; // already archived — no duplicates
        }

        $pr->load(['requester', 'finalizer', 'requisition']);

        $pdf = Pdf::loadView('pdf.pr-form', [
            'pr' => $pr,
        ])->setPaper('a4', 'portrait');

        $relative = 'pr-forms/'
            . ($pr->finalized_at ?? $pr->updated_at)?->format('Y') . '/'
            . ($pr->finalized_at ?? $pr->updated_at)?->format('F') . '/'
            . $pr->pr_number . '.pdf';

        Storage::disk('local')->put($relative, $pdf->output());

        $pr->update(['pr_form_pdf_path' => $relative]);

        return $relative;
    }
}