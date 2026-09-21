<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use App\Services\CsmMonthlyReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * D9.32 — generates the 2-page CSM Monthly Summary PDF for a month, stores it
 * on the private disk, and notifies every Super Admin (bell + email, via the
 * CSM* email exception in Notification::booted) with a summary and the
 * download link. Aggregate-only content — no names, no ticket references.
 */
class CsmMonthlyReport extends Command
{
    protected $signature = 'csm:monthly-report
        {month? : Report month in Y-m (default: previous month when run on the 1st, otherwise the current month)}';

    protected $description = 'Generate the CSM Monthly Summary PDF and notify the Super Admins (bell + email)';

    public function handle(): int
    {
        $month = $this->resolveMonth();
        $data = (new CsmMonthlyReportService)->build($month);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.csm-monthly-report', $data)
            ->setPaper('a4', 'portrait');

        $relative = CsmMonthlyReportService::storagePath($month);
        Storage::disk('local')->put($relative, $pdf->output());
        $this->info('CSM report written: storage/app/' . $relative);

        if (! $data['hasData']) {
            $this->warn('No survey responses for ' . $data['monthLabel'] . ' — report kept for the annual record.');
        }

        $url = route('csm.reports.download', [
            'year' => $month->format('Y'),
            'month' => $month->format('m'),
        ]);

        $message = sprintf(
            'Your CSM Monthly Report for %s is ready. Overall: %s/5 (%s) · %d response(s) · %s%% satisfied%s',
            $data['monthLabel'],
            $data['overall'] ?? '—',
            $data['band']['label'],
            $data['respondents'],
            $data['satisfiedPct'] ?? '0',
            $data['weakest'] ? ' · lowest-rated: "' . $data['weakest']['question'] . '"' : ''
        );

        $admins = User::where('role', 'super_admin')->get();
        foreach ($admins as $admin) {
            Notification::send($admin->id, null, 'CSM Monthly Report', $message, $url);
        }
        $this->info('Notified ' . $admins->count() . ' super admin(s) (bell + email).');

        return self::SUCCESS;
    }

    private function resolveMonth(): Carbon
    {
        $raw = $this->argument('month');

        if ($raw) {
            return Carbon::createFromFormat('Y-m', $raw)->startOfMonth();
        }

        // Scheduled on the 1st at 07:10 => report the PREVIOUS month;
        // run later in the month => report the month so far.
        $today = now();

        return $today->isDayOfMonth(1)
            ? $today->copy()->subMonthNoOverflow()->startOfMonth()
            : $today->copy()->startOfMonth();
    }
}
