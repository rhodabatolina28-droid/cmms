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

    /** Severe = Strongly Disagree on at least this many of the 9 SQD questions. */
    public const SEVERE_SD_THRESHOLD = 3;

    /** Rating bands shown on the dashboard (traffic-light thresholds). */
    public const GOOD_THRESHOLD = 4.5;

    public const WATCH_THRESHOLD = 4.0;

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

    /**
     * Traffic-light band for an average: 'good' (>= 4.5), 'watch' (>= 4.0),
     * 'low' (< 4.0) or 'none' when nothing is scorable yet.
     */
    public static function ratingBand(?float $average): string
    {
        if ($average === null) {
            return 'none';
        }

        if ($average >= self::GOOD_THRESHOLD) {
            return 'good';
        }

        return $average >= self::WATCH_THRESHOLD ? 'watch' : 'low';
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
