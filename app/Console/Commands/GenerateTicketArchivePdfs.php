<?php

namespace App\Console\Commands;

use App\Actions\Ticket\ArchiveTicketPdfAction;
use App\Models\Request as RequestModel;
use Illuminate\Console\Command;

class GenerateTicketArchivePdfs extends Command
{
    /**
     * D6b: generate archived final PDF copies for completed ICT/PM tickets
     * that are missing their archive_pdf_path (private disk, month-year folders).
     */
    protected $signature = 'tickets:generate-archive-pdfs {--type= : "ICT" or "Preventive Maintenance" only}';

    protected $description = 'Generate archived PDF copies for completed tickets missing archive_pdf_path';

    public function handle(): int
    {
        $query = RequestModel::where('status', RequestModel::STATUS_COMPLETED)
            ->whereNull('archive_pdf_path');

        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }

        $tickets = $query->get();

        if ($tickets->isEmpty()) {
            $this->info('No completed tickets missing archive PDFs.');
            return self::SUCCESS;
        }

        $this->info("Generating archived PDFs for {$tickets->count()} ticket(s)...");

        $ok = 0;
        $fail = 0;

        foreach ($tickets as $ticket) {
            try {
                $relative = ArchiveTicketPdfAction::generate($ticket);
                if ($relative) {
                    $this->info("  ✓ {$ticket->request_number} -> {$relative}");
                    $ok++;
                } else {
                    $this->error("  ✗ {$ticket->request_number}: could not archive");
                    $fail++;
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ {$ticket->request_number}: " . $e->getMessage());
                $fail++;
            }
        }

        $this->info("Done. OK: {$ok}, Failed: {$fail}.");
        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}