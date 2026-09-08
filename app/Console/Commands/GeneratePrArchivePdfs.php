<?php

namespace App\Console\Commands;

use App\Actions\PurchaseRequest\ArchiveDeliveryConfirmationPdfAction;
use App\Models\PurchaseRequest;
use Illuminate\Console\Command;

class GeneratePrArchivePdfs extends Command
{
    protected $signature = 'prs:generate-archive-pdfs
        {--force : Re-generate even if archive_pdf_path is already set}';

    protected $description = 'D7a: backfill archived Delivery Confirmation PDFs for delivered purchase requests (pr-pdfs/{year}/{month}, private disk)';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $query = PurchaseRequest::query()
            ->where('status', PurchaseRequest::STATUS_DELIVERED)
            ->orderBy('id');

        if (! $force) {
            $query->whereNull('archive_pdf_path');
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No delivered PRs need an archive PDF.');

            return self::SUCCESS;
        }

        $this->info("Archiving {$total} delivered PR(s)…");

        $ok = 0;
        $failed = 0;

        $query->chunkById(10, function ($prs) use (&$ok, &$failed, $force) {
            foreach ($prs as $pr) {
                try {
                    if ($force) {
                        $pr->forceFill(['archive_pdf_path' => null])->save();
                    }

                    $path = ArchiveDeliveryConfirmationPdfAction::generate($pr->fresh());

                    if ($path) {
                        $ok++;
                        $this->line("  ✓ {$pr->pr_number} → {$path}");
                    } else {
                        $this->warn("  – {$pr->pr_number} skipped (no delivery data)");
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error("  ✗ {$pr->pr_number}: {$e->getMessage()}");
                }
            }
        });

        $this->info("Done. Archived: {$ok}, failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
