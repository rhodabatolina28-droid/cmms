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
 * BIG WARNINGs, recovery note and milestone.
 *
 * Delivery: bell + email to every Super Admin (via the CSM* email exception in
 * Notification::booted) — same as the severe alert. D9.34c (user decision):
 * the bell stays, but the MESSAGE is short — flag counts instead of full
 * lists (the dashboard holds the detail). Deduped to one digest per day via
 * the notifications table (the schedule only fires Mondays, so that is one
 * per week). Aggregate-only.
 */
class CsmWeeklyCheck extends Command
{
    protected $signature = 'csm:weekly-check
        {week? : Any date inside the week to report, Y-m-d (default: last week)}';

    protected $description = 'Send the CSM weekly digest to the Super Admins (bell + email, short message)';

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

        $this->info('CSM weekly digest sent to ' . $admins->count() . ' super admin(s) (bell + email, short message).');

        return self::SUCCESS;
    }

    /** Short digest: one headline + at most a couple of flag sentences. */
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
                $parts[] = sprintf('Overall is low (%s/5, %s) — please review.', $data['overall'], $data['band']['label']);
            }

            if ($data['overallPrev'] !== null
                && ($data['overallPrev'] - $data['overall']) >= CsmWeeklyDigestService::DROP_THRESHOLD) {
                $parts[] = sprintf(
                    'Down %.1f from last week (%s to %s).',
                    $data['overallPrev'] - $data['overall'],
                    $data['overallPrev'],
                    $data['overall']
                );
            }
        }

        $warnings = $data['warnings'];
        $count = count($warnings);

        if ($count === 1) {
            $flagged = true;
            $parts[] = sprintf(
                '%d client(s) disagreed on "%s".',
                $warnings[0]['disagreeCount'],
                $warnings[0]['question']
            );
        } elseif ($count > 1) {
            $flagged = true;
            usort($warnings, fn ($a, $b) => $a['average'] <=> $b['average']);
            $worst = $warnings[0];
            $parts[] = sprintf(
                '%d of 9 questions flagged (worst: "%s" — %d disagreed, avg %s/5).',
                $count,
                $worst['question'],
                $worst['disagreeCount'],
                $worst['average']
            );
        }

        if ($data['recovered']) {
            $flagged = true;
            $parts[] = sprintf('Recovered to %s.', $data['band']['label']);
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
