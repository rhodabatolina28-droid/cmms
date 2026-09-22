<?php

namespace App\Console\Commands;

use App\Mail\SystemNotificationMail;
use App\Models\User;
use App\Services\CsmWeeklyDigestService;
use App\Services\RequestNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * D9.34 — CSM Weekly Digest (Phase 5). Scheduled Monday 07:05, reports the
 * previous Mon–Sun week: overall watch (ARTA-aligned thresholds), per-question
 * BIG WARNINGs, recovery note and milestone.
 *
 * Delivery (D9.34b, user decision): EMAIL ONLY, no bell — the digest is a
 * weekly read, not an interruption, and the message stays short (flag counts,
 * not full lists; the dashboard holds the detail). Sent directly via
 * SystemNotificationMail — bypassing Notification::send so no bell row is
 * created. Deduped to one digest per day via Cache (the schedule only fires
 * Mondays, so that is one per week). Aggregate-only.
 */
class CsmWeeklyCheck extends Command
{
    protected $signature = 'csm:weekly-check
        {week? : Any date inside the week to report, Y-m-d (default: last week)}';

    protected $description = 'Email the CSM weekly digest to the Super Admins (no bell, short message)';

    public function handle(): int
    {
        $anchor = $this->resolveWeek();
        $data = (new CsmWeeklyDigestService)->build($anchor);

        if (! $data['hasData']) {
            $this->warn('No CSM responses for the week of ' . $data['weekLabel'] . ' — digest skipped.');

            return self::SUCCESS;
        }

        $dedupKey = 'csm-weekly-digest-' . today()->toDateString();

        if (Cache::has($dedupKey)) {
            $this->comment('CSM weekly digest already sent today — deduped (1 per week).');

            return self::SUCCESS;
        }

        $message = $this->message($data);
        $url = route('dashboard.super-admin');
        $isLocal = app()->environment('local');

        $admins = User::where('role', 'super_admin')->get();
        $sent = 0;

        foreach ($admins as $admin) {
            // Production safety (same rule as Notification::booted): skip alias emails.
            if (! $isLocal && str_contains((string) $admin->email, '+')) {
                continue;
            }

            if ($isLocal) {
                RequestNotificationService::logLocalEmailPreview(
                    $admin->email,
                    'CSM Weekly Digest',
                    $message,
                    'N/A'
                );
            }

            Mail::to($admin->email)->queue(new SystemNotificationMail(
                $admin->full_name,
                'CSM Weekly Digest',
                $message,
                'N/A',
                $url,
                $admin->branch,
                $admin->region
            ));

            $sent++;
        }

        Cache::put($dedupKey, true, now()->endOfDay());

        $this->info('CSM weekly digest emailed to ' . $sent . ' super admin(s) (no bell, short message).');

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
