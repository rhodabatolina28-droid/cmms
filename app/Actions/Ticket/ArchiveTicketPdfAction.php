<?php

namespace App\Actions\Ticket;

use App\Models\Request as RequestModel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * D6: generates the archived final PDF copy for a completed ticket.
 *
 * - ICT  → pdf/ict-form           → ict-pdfs/{year}/{Month}/ARCH-ICT-{number}.pdf
 * - PM   → pdf/maintenance-form   → pm-pdfs/{year}/{Month}/ARCH-PM-{number}.pdf
 *
 * Month folders follow the RECORD-DATE rule (D5.1b): the ticket's
 * completed_at, never the generation now().
 */
class ArchiveTicketPdfAction
{
    public static function generate(RequestModel $ticket): ?string
    {
        if ($ticket->status !== RequestModel::STATUS_COMPLETED) {
            return null;
        }

        if ($ticket->archive_pdf_path) {
            return $ticket->archive_pdf_path; // already archived — no duplicates
        }

        $data = self::viewData($ticket);

        $pdf = Pdf::loadView($data['view'], $data['vars'])->setPaper('legal', 'portrait');

        $typeFolder = $ticket->type === 'Preventive Maintenance' ? 'pm-pdfs' : 'ict-pdfs';
        $relative = $typeFolder . '/'
            . ($ticket->completed_at ?? $ticket->updated_at)?->format('Y') . '/'
            . ($ticket->completed_at ?? $ticket->updated_at)?->format('F') . '/'
            . 'ARCH-' . $ticket->request_number . '.pdf';

        Storage::disk('local')->put($relative, $pdf->output());

        $ticket->update(['archive_pdf_path' => $relative]);

        return $relative;
    }

    private static function viewData(RequestModel $ticket): array
    {
        if ($ticket->type === 'Preventive Maintenance') {
            $pm = \App\Models\PreventiveMaintenance::find($ticket->detail_id);
            $tasks = json_decode($pm->maintenance_tasks_json ?? '{}', true) ?: [];

            return [
                'view' => 'pdf.maintenance-form',
                'vars' => ['request' => $ticket, 'pm' => $pm, 'tasks' => $tasks],
            ];
        }

        $repairRequest = \App\Models\RepairRequest::find($ticket->detail_id);

        return [
            'view' => 'pdf.ict-form',
            'vars' => ['request' => $ticket, 'repairRequest' => $repairRequest],
        ];
    }
}