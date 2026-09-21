<?php

namespace App\Services;

use App\Models\CsmSurvey;
use App\Models\Request as RequestModel;
use Illuminate\Support\Carbon;

/**
 * D9.32 — assembles everything the 2-page CSM Monthly Summary PDF needs:
 * per-question counts on the ARTA 5-point scale, averages with descriptive
 * bands, month-over-month deltas, the weakest question and the respondent
 * profile. All math delegates to CsmStatsService (single source of truth).
 * Aggregate-only by design — no names and no ticket references anywhere.
 */
class CsmMonthlyReportService
{
    /** Everything the PDF view and the SA notification message need. */
    public function build(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth()->endOfDay();
        $prevStart = $month->copy()->subMonthNoOverflow()->startOfMonth();
        $prevEnd = $month->copy()->subMonthNoOverflow()->endOfMonth()->endOfDay();

        $surveys = $this->surveysBetween($start, $end);
        $prevSurveys = $this->surveysBetween($prevStart, $prevEnd);

        $questions = $this->questionRows($surveys, $prevSurveys);
        $overall = CsmStatsService::averageForSurveys($surveys);
        $overallPrev = CsmStatsService::averageForSurveys($prevSurveys);

        // Response-rate denominator: completed (and admin-approved) requests
        // that FINISHED inside the reported month — same review semantics as
        // the dashboard snapshot, but windowed to the month.
        $completed = RequestModel::query()
            ->where('status', RequestModel::STATUS_COMPLETED)
            ->where('division_admin_review_status', 'Approved')
            ->whereBetween('completed_at', [$start, $end])
            ->count();

        $weakest = CsmStatsService::weakestColumn($surveys);
        $sorted = collect($questions)->sortBy('average')->values();
        $weakestRow = $weakest !== null ? ($questions[$weakest['column']] ?? null) : null;

        return [
            'month' => $start,
            'monthLabel' => $start->format('F Y'),
            'hasData' => $surveys->count() > 0,
            'respondents' => $surveys->count(),
            'overall' => $overall !== null ? round($overall, 1) : null,
            'overallPrev' => $overallPrev !== null ? round($overallPrev, 1) : null,
            'band' => CsmStatsService::artaBand($overall),
            'satisfiedPct' => CsmStatsService::percentSatisfied($surveys),
            'completedCount' => $completed,
            'responseRate' => $completed > 0 ? round(($surveys->count() / $completed) * 100) : null,
            'questions' => $questions,
            'weakest' => $weakestRow,
            'secondWeakest' => $sorted->get(1),
            'goodNews' => collect($questions)
                ->filter(fn ($row) => $row['average'] !== null)
                ->sortByDesc('average')
                ->take(2)
                ->values()
                ->all(),
            'male' => $surveys->where('sex', 'Male')->count(),
            'female' => $surveys->where('sex', 'Female')->count(),
        ];
    }

    /** Surveys created inside the window — SQD columns plus the profile fields. */
    private function surveysBetween(Carbon $start, Carbon $end)
    {
        return CsmSurvey::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(array_merge(['id', 'sex', 'age'], CsmStatsService::SQD_COLUMNS));
    }

    /** One row per SQD column, in form order (SDQ0–SDQ8). */
    private function questionRows($surveys, $prevSurveys): array
    {
        $prevAverages = CsmStatsService::perColumnAverages($prevSurveys);
        $rows = [];

        foreach (CsmStatsService::SQD_COLUMNS as $column) {
            $counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
            $total = 0;
            $scorable = 0;

            foreach ($surveys as $survey) {
                $score = CsmStatsService::scoreFor($survey->{$column} ?? null);

                if ($score === null) {
                    continue;
                }

                $counts[$score]++;
                $total += $score;
                $scorable++;
            }

            $average = $scorable > 0 ? $total / $scorable : null;

            $rows[$column] = [
                'column' => $column,
                'label' => CsmStatsService::labelFor($column),
                'question' => CsmStatsService::questionFor($column),
                'counts' => $counts,
                'scorable' => $scorable,
                'average' => $average !== null ? round($average, 1) : null,
                'band' => CsmStatsService::artaBand($average),
                'disagreeCount' => $counts[2] + $counts[1],
                'prevAverage' => isset($prevAverages[$column]) ? round($prevAverages[$column], 1) : null,
            ];
        }

        return $rows;
    }

    /** Private-disk path + human filename for the month's report. */
    public static function storagePath(Carbon $month): string
    {
        return 'csm-reports/' . $month->format('Y-m')
            . '/CSM-Report-' . $month->format('F-Y') . '.pdf';
    }
}
