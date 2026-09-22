<?php

namespace App\Services;

use App\Models\CsmSurvey;
use Illuminate\Support\Carbon;

/**
 * D9.34 — CSM Weekly Digest math (Phase 5). Runs every Monday 07:05 for the
 * previous Mon–Sun week.
 *
 * ARTA-aligned rules (same CsmStatsService math as the dashboard, the severe
 * alert and the monthly PDF):
 *  - Overall watch: week average enters the Neutral band or lower (<= 3.40)
 *    OR falls by DROP_THRESHOLD (0.3) versus the previous week — with a
 *    MIN_WEEK_SAMPLE (5) guard so a handful of answers can't cry wolf.
 *  - Per-question BIG WARNING: at least 3 clients disagree on one question
 *    in the week OR the question average is <= WARNING_MAX_AVG (2.60) — with
 *    a MIN_QUESTION_SAMPLE (3) guard. Fires even when the overall is healthy.
 *  - Recovery: the week climbed out of a watch/low band back to a good band.
 *  - Milestone: MILESTONE_WEEKS (4) consecutive weeks at MILESTONE_AVG (4.5+).
 *
 * Aggregate-only by design — no names and no ticket references anywhere.
 */
class CsmWeeklyDigestService
{
    public const MIN_WEEK_SAMPLE = 5;      // overall watch needs a real sample
    public const MIN_QUESTION_SAMPLE = 3;  // per-question warning needs a real sample
    public const DROP_THRESHOLD = 0.3;     // week-over-week fall worth flagging
    public const ALERT_MAX_AVG = 3.40;     // Neutral band floor (ARTA)
    public const WARNING_MAX_AVG = 2.60;   // question average that is a red flag
    public const MILESTONE_AVG = 4.5;      // N straight weeks at/above = milestone
    public const MILESTONE_WEEKS = 4;

    /**
     * Build the digest for the week containing $anchor (any day works; the
     * window is that week's Monday 00:00 through Sunday 23:59).
     */
    public function build(Carbon $anchor): array
    {
        $start = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $end = $start->copy()->addDays(6)->endOfDay();

        $surveys = $this->surveysBetween($start, $end);
        $prevStart = $start->copy()->subWeek();
        $prevEnd = $prevStart->copy()->addDays(6)->endOfDay();
        $prevSurveys = $this->surveysBetween($prevStart, $prevEnd);

        $overall = CsmStatsService::averageForSurveys($surveys);
        $overallPrev = CsmStatsService::averageForSurveys($prevSurveys);
        $band = CsmStatsService::artaBand($overall);
        $bandPrev = CsmStatsService::artaBand($overallPrev);

        $overallAlert = $surveys->count() >= self::MIN_WEEK_SAMPLE
            && $overall !== null
            && ($overall <= self::ALERT_MAX_AVG
                || ($overallPrev !== null && ($overallPrev - $overall) >= self::DROP_THRESHOLD));

        return [
            'weekStart' => $start,
            'weekLabel' => $start->format('M j') . ' - ' . $end->format('M j, Y'),
            'hasData' => $surveys->count() > 0,
            'respondents' => $surveys->count(),
            'overall' => $overall !== null ? round($overall, 1) : null,
            'overallPrev' => $overallPrev !== null ? round($overallPrev, 1) : null,
            'band' => $band,
            'bandPrev' => $bandPrev,
            'overallAlert' => $overallAlert,
            'warnings' => $this->questionWarnings($surveys),
            'recovered' => in_array($bandPrev['color'], ['watch', 'low'], true)
                && $band['color'] === 'good',
            'milestone' => $this->milestone($start),
        ];
    }

    /**
     * Per-question BIG WARNINGs for the week: >= 3 disagreeing clients on one
     * question, or the question average at/under WARNING_MAX_AVG — both need
     * at least MIN_QUESTION_SAMPLE scorable answers.
     */
    private function questionWarnings($surveys): array
    {
        $warnings = [];

        foreach (CsmStatsService::SQD_COLUMNS as $column) {
            $scorable = 0;
            $disagree = 0;
            $total = 0;

            foreach ($surveys as $survey) {
                $score = CsmStatsService::scoreFor($survey->{$column} ?? null);

                if ($score === null) {
                    continue;
                }

                $scorable++;
                $total += $score;

                if ($score <= 2) {
                    $disagree++;
                }
            }

            $average = $scorable > 0 ? $total / $scorable : null;

            if ($scorable >= self::MIN_QUESTION_SAMPLE
                && ($disagree >= 3 || ($average !== null && $average <= self::WARNING_MAX_AVG))) {
                $warnings[] = [
                    'column' => $column,
                    'question' => CsmStatsService::questionFor($column),
                    'disagreeCount' => $disagree,
                    'average' => round((float) $average, 1),
                ];
            }
        }

        return $warnings;
    }

    /** True when each of the last MILESTONE_WEEKS weeks (ending this week) averages >= MILESTONE_AVG. */
    private function milestone(Carbon $weekStart): bool
    {
        for ($i = 0; $i < self::MILESTONE_WEEKS; $i++) {
            $start = $weekStart->copy()->subWeeks($i);
            $average = CsmStatsService::averageForSurveys(
                $this->surveysBetween($start, $start->copy()->addDays(6)->endOfDay())
            );

            if ($average === null || $average < self::MILESTONE_AVG) {
                return false;
            }
        }

        return true;
    }

    /** Surveys created inside the window — same selection as the monthly report. */
    private function surveysBetween(Carbon $start, Carbon $end)
    {
        return CsmSurvey::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(array_merge(['id', 'sex', 'age'], CsmStatsService::SQD_COLUMNS));
    }
}
