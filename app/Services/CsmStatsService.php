<?php

namespace App\Services;

/**
 * D9.31: Single source of truth for CSM satisfaction math.
 *
 * The CSM form stores every SQD answer as a text label (ARTA
 * citizen-satisfaction scale). Scoring normalizes labels
 * case-insensitively (and trims stray whitespace) so legacy/variant
 * spellings such as "Neither Agree Nor Disagree" still count instead
 * of silently dropping out of averages. Unknown/blank values are
 * skipped, never guessed.
 */
class CsmStatsService
{
    /** Exact labels as stored by the form — used for validation and display. */
    public const LABEL_SCORES = [
        'Strongly Agree' => 5,
        'Agree' => 4,
        'Neither Agree nor Disagree' => 3,
        'Disagree' => 2,
        'Strongly Disagree' => 1,
    ];

    /**
     * Valid-but-unscored answer: the form offers an N/A checkbox per SQD row
     * (ARTA standard) for questions that do not apply. It is stored as-is
     * and excluded from averages (scoreFor() returns null for it).
     */
    public const NOT_APPLICABLE = 'N/A';

    public const SQD_COLUMNS = ['sqd1', 'sqd2', 'sqd3', 'sqd4', 'sqd5', 'sqd6', 'sqd7', 'sqd8', 'sqd9'];

    /**
     * Human-readable SQD question names, as numbered on the printed form.
     * Note the offset: DB column sqd1 holds the form's "SDQ0" question.
     */
    public const SQD_LABELS = [
        'sqd1' => 'SDQ0',
        'sqd2' => 'SDQ1',
        'sqd3' => 'SDQ2',
        'sqd4' => 'SDQ3',
        'sqd5' => 'SDQ4',
        'sqd6' => 'SDQ5',
        'sqd7' => 'SDQ6',
        'sqd8' => 'SDQ7',
        'sqd9' => 'SDQ8',
    ];

    /**
     * Full question text as printed on the ARTA form, WITHOUT the SDQ number
     * prefix. Plain-language reports (D9.32 PDF) and alert emails (D9.33/34)
     * quote these verbatim — never DB column codes.
     */
    public const SQD_QUESTIONS = [
        'sqd1' => 'I am satisfied with the service that I availed.',
        'sqd2' => 'I spent a reasonable amount of time for my transaction.',
        'sqd3' => "The office followed the transaction's requirements and steps based on the information provided.",
        'sqd4' => 'The steps (including payment) I needed to do for my transaction were easy and simple.',
        'sqd5' => "I easily found information about my transaction from the office's website.",
        'sqd6' => 'I paid a reasonable amount of fees for my transaction.',
        'sqd7' => 'I am confident my online transaction was secure.',
        'sqd8' => "The office's online support was available, and (if asked questions) online support was quick to respond.",
        'sqd9' => 'I got what I needed from the government office, or (if denied) denial of request was sufficiently explained to me.',
    ];

    /**
     * Official ARTA Service Quality Dimension names for each question.
     */
    public const SQD_DIMENSIONS = [
        'sqd1' => 'Overall Satisfaction',
        'sqd2' => 'Responsiveness',
        'sqd3' => 'Reliability',
        'sqd4' => 'Access & Facilities',
        'sqd5' => 'Communication',
        'sqd6' => 'Costs',
        'sqd7' => 'Integrity',
        'sqd8' => 'Support',
        'sqd9' => 'Outcome',
    ];

    /** Severe = Strongly Disagree on at least this many of the 9 SQD questions. */
    public const SEVERE_SD_THRESHOLD = 3;

    /**
     * Form column holding the ARTA overall-satisfaction question.
     * The printed form numbers it "SDQ0" ("I am satisfied with the service
     * that I availed.") and the DB column is sqd1 — note the offset. This is
     * the item ARTA/CSC reports use for the headline "% satisfied" figure;
     * sqd8 (SDQ7) is only about online support and must not be used for it.
     */
    public const SATISFACTION_COLUMN = 'sqd1';

    /**
     * ARTA/CSC descriptive rating bands (Likert interpretation used in PH
     * government client-satisfaction reports). D9.31c: these replaced the
     * earlier arbitrary 4.5/4.0 traffic-light thresholds so the dashboard
     * speaks the same language as the printed CSM form and its reports.
     */
    public const ARTA_BANDS = [
        'very_satisfied' => ['label' => 'Very Satisfied', 'min' => 4.21, 'range' => '4.21–5.00', 'color' => 'good'],
        'satisfied' => ['label' => 'Satisfied', 'min' => 3.41, 'range' => '3.41–4.20', 'color' => 'good'],
        'neutral' => ['label' => 'Neutral', 'min' => 2.61, 'range' => '2.61–3.40', 'color' => 'watch'],
        'dissatisfied' => ['label' => 'Dissatisfied', 'min' => 1.81, 'range' => '1.81–2.60', 'color' => 'low'],
        'very_dissatisfied' => ['label' => 'Very Dissatisfied', 'min' => 1.00, 'range' => '1.00–1.80', 'color' => 'low'],
    ];

    /**
     * Minimum surveys per period before a month-over-month arrow is shown.
     * With a single respondent the delta swings wildly, so the dashboard
     * stays quiet until both months hold a usable sample.
     */
    public const MIN_TREND_SAMPLE = 3;

    /**
     * Normalize a stored label to its 1–5 score.
     * Returns null for unknown/empty labels (excluded from averages).
     */
    public static function scoreFor(?string $label): ?int
    {
        if ($label === null) {
            return null;
        }

        $key = mb_strtolower(trim($label));

        return self::SCALE_NORMALIZED()[$key] ?? null;
    }

    /** Case-insensitive lookup map derived from LABEL_SCORES. */
    private static function SCALE_NORMALIZED(): array
    {
        static $map = null;

        if ($map === null) {
            $map = [];
            foreach (self::LABEL_SCORES as $label => $score) {
                $map[mb_strtolower(trim($label))] = $score;
            }
        }

        return $map;
    }

    /** Exact option labels, in descending score order (for display). */
    public static function optionLabels(): array
    {
        return array_keys(self::LABEL_SCORES);
    }

    /** Labels accepted by validation: the 5 scored options + the N/A escape. */
    public static function validationLabels(): array
    {
        return array_merge(self::optionLabels(), [self::NOT_APPLICABLE]);
    }

    /** Scores for one survey row (model or stdClass), unknown answers skipped. */
    public static function surveyScores(object $survey): array
    {
        $scores = [];

        foreach (self::SQD_COLUMNS as $column) {
            $score = self::scoreFor($survey->{$column} ?? null);

            if ($score !== null) {
                $scores[$column] = $score;
            }
        }

        return $scores;
    }

    /** How many of the 9 SQD answers are Strongly Disagree. */
    public static function severeCount(object $survey): int
    {
        return count(array_filter(self::surveyScores($survey), fn ($score) => $score === 1));
    }

    public static function isSevere(object $survey): bool
    {
        return self::severeCount($survey) >= self::SEVERE_SD_THRESHOLD;
    }

    /**
     * Mean score across every answered cell of every survey row.
     * Returns null when there is nothing scorable (callers decide the fallback).
     */
    public static function averageForSurveys(iterable $surveys): ?float
    {
        $total = 0;
        $count = 0;

        foreach ($surveys as $survey) {
            foreach (self::surveyScores($survey) as $score) {
                $total += $score;
                $count++;
            }
        }

        return $count > 0 ? $total / $count : null;
    }

    /**
     * Per-SQD-column averages across the given rows.
     * Returns ['sqd1' => 4.2, ...] for columns that have at least one score.
     */
    public static function perColumnAverages(iterable $surveys): array
    {
        $totals = [];
        $counts = [];

        foreach ($surveys as $survey) {
            foreach (self::surveyScores($survey) as $column => $score) {
                $totals[$column] = ($totals[$column] ?? 0) + $score;
                $counts[$column] = ($counts[$column] ?? 0) + 1;
            }
        }

        $averages = [];
        foreach ($totals as $column => $total) {
            $averages[$column] = $total / $counts[$column];
        }

        return $averages;
    }

    /**
     * The weakest SQD column (lowest average) or null when no scores exist.
     * Returns ['column' => 'sqd4', 'average' => 4.2, 'label' => 'SDQ3'].
     */
    public static function weakestColumn(iterable $surveys): ?array
    {
        $averages = self::perColumnAverages($surveys);

        if ($averages === []) {
            return null;
        }

        asort($averages);
        $column = array_key_first($averages);

        return [
            'column' => $column,
            'average' => $averages[$column],
            'label' => self::labelFor($column),
        ];
    }

    /** Display name for an SQD column ('sqd3' => 'SDQ2'). */
    public static function labelFor(string $column): string
    {
        return self::SQD_LABELS[$column] ?? strtoupper($column);
    }

    /** Plain question text for an SQD column (no SDQ prefix). */
    public static function questionFor(string $column): string
    {
        return self::SQD_QUESTIONS[$column] ?? self::labelFor($column);
    }

    /** Official ARTA Service Quality Dimension title for an SQD column ('sqd3' => 'Reliability'). */
    public static function dimensionFor(string $column): string
    {
        return self::SQD_DIMENSIONS[$column] ?? '';
    }

    /**
     * ARTA band for an average — the descriptive rating a government reader
     * expects ("Satisfied", "Neutral", ...). Returns
     * ['key' => 'satisfied', 'label' => 'Satisfied', 'range' => '3.41–4.20',
     *  'color' => 'good'] or key 'none' when nothing is scorable yet.
     */
    public static function artaBand(?float $average): array
    {
        if ($average === null) {
            return ['key' => 'none', 'label' => 'No data', 'range' => '', 'color' => 'none'];
        }

        foreach (self::ARTA_BANDS as $key => $band) {
            if ($average >= $band['min']) {
                return ['key' => $key] + $band;
            }
        }

        // Below the lowest band floor (defensive; the scale starts at 1.0).
        $lowest = self::ARTA_BANDS['very_dissatisfied'];

        return ['key' => 'very_dissatisfied'] + $lowest;
    }

    /**
     * ARTA headline stat: the share of clients satisfied with the service —
     * surveys whose overall-satisfaction answer (SDQ0, DB column sqd1) is
     * "Strongly Agree" or "Agree", over surveys that gave a scorable answer on
     * that column (N/A and blank respondents are excluded from both sides,
     * mirroring how averages treat them). Returns null when nobody is scorable.
     */
    public static function percentSatisfied(iterable $surveys): ?float
    {
        $satisfied = 0;
        $scorable = 0;

        foreach ($surveys as $survey) {
            $score = self::scoreFor($survey->{self::SATISFACTION_COLUMN} ?? null);

            if ($score === null) {
                continue;
            }

            $scorable++;

            if ($score >= 4) {
                $satisfied++;
            }
        }

        return $scorable > 0 ? round(($satisfied / $scorable) * 100) : null;
    }

    /**
     * Direction of a period-over-period change: 'up', 'down' or 'flat'.
     * A change smaller than 0.05 rounds to the same 1-decimal average, so it
     * counts as flat instead of showing a misleading arrow.
     */
    public static function trendDirection(float $delta): string
    {
        if (abs($delta) < 0.05) {
            return 'flat';
        }

        return $delta > 0 ? 'up' : 'down';
    }
}
