<?php

namespace App\Console\Commands;

use App\Models\CsmSurvey;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateCsmSurveyPdfs extends Command
{
    /**
     * D5d: generate archived PDF copies for CSM surveys that are missing
     * their pdf_path (private disk, month-year folders, record-date rule).
     */
    protected $signature = 'csm:generate-pdfs {--id= : Generate for a specific survey id only}';

    protected $description = 'Generate archived PDF copies for CSM surveys missing pdf_path (private storage)';

    public function handle(): int
    {
        $query = CsmSurvey::with('request.user')->whereNull('pdf_path');

        if ($id = $this->option('id')) {
            $query->where('id', (int) $id);
        }

        $surveys = $query->get();

        if ($surveys->isEmpty()) {
            $this->info('No surveys missing PDF copies.');
            return self::SUCCESS;
        }

        $this->info("Generating archived PDFs for {$surveys->count()} survey(s)...");

        $ok = 0;
        $fail = 0;

        foreach ($surveys as $survey) {
            try {
                $pdf = Pdf::loadView('pdf.csm-form', ['survey' => $survey])
                    ->setPaper('a4', 'portrait');

                // Record-date rule (D5.1b): folder month = survey created_at
                $relative = 'csm-copies/'
                    . $survey->created_at->format('Y') . '/'
                    . $survey->created_at->format('F') . '/'
                    . 'CSM-' . ($survey->request?->request_number ?? 'SURVEY-' . $survey->id) . '.pdf';

                Storage::disk('local')->put($relative, $pdf->output());
                $survey->update(['pdf_path' => $relative]);

                $this->info("  ✓ survey #{$survey->id} -> {$relative}");
                $ok++;
            } catch (\Throwable $e) {
                $this->error("  ✗ survey #{$survey->id}: " . $e->getMessage());
                $fail++;
            }
        }

        $this->info("Done. OK: {$ok}, Failed: {$fail}.");
        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}