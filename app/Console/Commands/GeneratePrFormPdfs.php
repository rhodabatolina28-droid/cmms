<?php

namespace App\Console\Commands;

use App\Actions\PurchaseRequest\ArchivePrFormPdfAction;
use App\Models\PurchaseRequest;
use Illuminate\Console\Command;

class GeneratePrFormPdfs extends Command
{
    protected $signature = 'prs:generate-archive-form-pdfs
        {--force : Re-generate even if pr_form_pdf_path is already set}';

    protected $description = 'D7c: backfill archived PR FORM PDFs for finalized/delivered purchase requests (pr-forms/{year}/{month}, private disk)';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $query = PurchaseRequest::query()
            ->whereIn('status', [PurchaseRequest::STATUS_FINALIZED, PurchaseRequest::STATUS_DELIVERED])
            ->orderBy('id');

        if (! $force) {
            $query->whereNull('pr_form_pdf_path');
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No finalized PRs need a form archive PDF.');

            return self::SUCCESS;
        }

        $this->info("Archiving {$total} finalized PR(s)…");

        $ok = 0;
        $failed = 0;

        $query->chunkById(10, function ($prs) use (&$ok, &$failed, $force) {
            foreach ($prs as $pr) {
                try {
                    if ($force) {
                        $pr->forceFill(['pr_form_pdf_path' => null])->save();
                    }

                    $path = ArchivePrFormPdfAction::generate($pr->fresh());

                    if ($path) {
                        $ok++;
                        $this->line("  ✓ {$pr->pr_number} → {$path}");
                    } else {
                        $this->warn("  – {$pr->pr_number} skipped (not finalized)");
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