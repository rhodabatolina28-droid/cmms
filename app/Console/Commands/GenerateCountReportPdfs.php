<?php

namespace App\Console\Commands;

use App\Actions\PhysicalCount\ArchiveCountReportAction;
use App\Models\PhysicalCountSession;
use Illuminate\Console\Command;

class GenerateCountReportPdfs extends Command
{
    protected $signature = 'counts:generate-archive-pdfs
        {--force : Re-generate even if report_pdf_path is already set}';

    protected $description = 'D7b: backfill archived Physical Count Report PDFs for completed sessions (count-pdfs/{year}, private disk)';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $query = PhysicalCountSession::query()
            ->where('status', 'Completed')
            ->orderBy('id');

        if (! $force) {
            $query->whereNull('report_pdf_path');
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No completed count sessions need an archive PDF.');

            return self::SUCCESS;
        }

        $this->info("Archiving {$total} completed count session(s)…");

        $ok = 0;
        $failed = 0;

        $query->chunkById(10, function ($sessions) use (&$ok, &$failed, $force) {
            foreach ($sessions as $session) {
                try {
                    if ($force) {
                        $session->forceFill(['report_pdf_path' => null])->save();
                    }

                    $path = (new ArchiveCountReportAction)->generate($session->fresh());

                    if ($path) {
                        $ok++;
                        $this->line("  ✓ Session #{$session->id} → {$path}");
                    } else {
                        $this->warn("  – Session #{$session->id} skipped");
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error("  ✗ Session #{$session->id}: {$e->getMessage()}");
                }
            }
        });

        $this->info("Done. Archived: {$ok}, failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
