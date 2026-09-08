<?php

namespace App\Actions\PhysicalCount;

use App\Models\InventoryAsset;
use App\Models\PhysicalCountSession;
use App\Models\Scopes\InventoryScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * D7b: generates the archived final "Physical Count Report" PDF for a
 * completed count session (the Annual Physical Inventory record).
 *
 * - count-pdfs/{year}/COUNT-{sessionId}.pdf on the private ('local') disk.
 *   YEARLY folders (not monthly) — the document is an annual inventory record.
 * - Year from completed_at (record-date rule D5.1b), never now().
 * - Idempotent: one archive per session (guard).
 */
class ArchiveCountReportAction
{
    use Concerns\BuildsCustodianGroups;

    public function generate(PhysicalCountSession $session): ?string
    {
        if ($session->status !== 'Completed') {
            return null;
        }

        if ($session->report_pdf_path) {
            return $session->report_pdf_path; // already archived — no duplicates
        }

        // Scope the asset set to the session's owner (the supply officer who
        // started the count) — same visibility the report had while running.
        $actor = $session->startedBy;

        $allAssets = InventoryAsset::with('assignedUser');
        InventoryScope::scopeAssetsToActor($allAssets, $actor);
        $allAssets = $allAssets->orderBy('category')->orderBy('item_name')->get();

        $summary = [
            'total'   => $allAssets->count(),
            'counted' => $session->counts->count(),
            'present' => $session->counts->where('status', 'Present')->count(),
            'missing' => $session->counts->where('status', 'Missing')->count(),
            'damaged' => $session->counts->where('status', 'Damaged')->count(),
        ];

        $custodianGroups = $this->buildCustodianGroups($allAssets, $session->counts);

        $pdf = Pdf::loadView('pdf.physical-count-report', [
            'session'         => $session,
            'summary'         => $summary,
            'custodianGroups' => $custodianGroups,
        ])->setPaper('a4', 'landscape');

        $relative = 'count-pdfs/'
            . ($session->completed_at ?? $session->updated_at)?->format('Y') . '/'
            . 'COUNT-' . $session->id . '.pdf';

        Storage::disk('local')->put($relative, $pdf->output());

        $session->update(['report_pdf_path' => $relative]);

        return $relative;
    }
}
