<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use App\Services\CsmWeeklyDigestService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * D9.34 — CSM Weekly Digest (Phase 5). Scheduled Monday 07:05, reports the
 * previous Mon–Sun week: overall watch (ARTA-aligned thresholds), per-question
 * BIG WARNINGs, recovery note and milestone. Bell + email to every Super
 * Admin via the CSM* email exception in Notification::booted. Deduped to
 * one digest per day (the schedule only fires Mondays, so that is one per
 * week; manual same-day re-runs are also suppressed). Aggregate-only.
 */
class CsmWeeklyCheck extends Command
{
    protected $signature = 'csm:weekly-check
        {week? : Any date inside the week to report, Y-m-d (default: last week)}';

    protected $description = 'Send the CSM weekly digest to the Super Admins (bell + email)';

    public function handle(): int
    {
        $anchor = $this->resolveWeek();
        $data = (new CsmWeeklyDigestService)->build($anchor);

        if (! $data['hasData']) {
            $this->warn('No CSM responses for the week of ' . $data['weekLabel'] . ' — digest skipped.');

            return self::SUCCESS;
        }

        if (Notification::where('type', 'CSM Weekly Digest')
            ->whereDate('created_at', today())
            ->exists()) {
            $this->comment('CSM weekly digest already sent today — deduped (1 per week).');

            return self::SUCCESS;
        }

        $url = route('dashboard.super-admin');
        $message = $this->message($data);

        $admins = User::where('role', 'super_admin')->get();
        foreach ($admins as $admin) {
            Notification::send($admin->id, null, 'CSM Weekly Digest', $message, $url);
        }

        $this->info('CSM weekly digest sent to ' . $admins->count() . ' super admin(s) (bell + email).');

        return self::SUCCESS;
    }

    /** One readable digest line: headline + every flag, joined by " · ". */
    private function message(array $data): string
    {
        $parts = [sprintf(
            'CSM weekly digest for %s: %d response(s), overall %s/5 (%s).',
            $data['weekLabel'],
            $data['respondents'],
            $data['overall'] ?? '—',
            $data['band']['label']
        )];

        $flagged = false;

        if ($data['overallAlert']) {
            $flagged = true;

            if ($data['overall'] <= CsmWeeklyDigestService::ALERT_MAX_AVG) {
                $parts[] = sprintf(
                    'Overall has entered %s territory (%s/5) — please review this week.',
                    $data['band']['label'],
                    $data['overall']
                );
            }

            if ($data['overallPrev'] !== null
                && ($data['overallPrev'] - $data['overall']) >= CsmWeeklyDigestService::DROP_THRESHOLD) {
                $parts[] = sprintf(
                    'Down %.1f from the previous week (%s to %s).',
                    $data['overallPrev'] - $data['overall'],
                    $data['overallPrev'],
                    $data['overall']
                );
            }
        }

        foreach ($data['warnings'] as $warning) {
            $flagged = true;
            $parts[] = sprintf(
                'Per-question warning: %d client(s) disagreed on "%s" (average %s/5).',
                $warning['disagreeCount'],
                $warning['question'],
                $warning['average']
            );
        }

        if ($data['recovered']) {
            $flagged = true;
            $parts[] = sprintf(
                'Recovery: back up to %s from last week\'s %s.',
                $data['band']['label'],
                $data['bandPrev']['label']
            );
        }

        if ($data['milestone']) {
            $flagged = true;
            $parts[] = sprintf(
                'Milestone: %s/5 or higher for %d straight weeks.',
                CsmWeeklyDigestService::MILESTONE_AVG,
                CsmWeeklyDigestService::MILESTONE_WEEKS
            );
        }

        if (! $flagged) {
            $parts[] = 'No action needed this week.';
        }

        return implode(' ', $parts);
    }

    /** Scheduled Monday morning reports LAST week; a Y-m-d arg pins any week. */
    private function resolveWeek(): Carbon
    {
        $raw = $this->argument('week');

        if ($raw) {
            return Carbon::createFromFormat('Y-m-d', $raw)->startOfDay();
        }

        return now()->subWeek();
    }
}
