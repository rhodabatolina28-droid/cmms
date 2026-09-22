<?php

namespace App\Services;

use App\Models\CsmSurvey;
use App\Models\Notification;
use App\Models\User;

/**
 * D9.33 — Real-time severe CSM alert (Phase 4).
 *
 * Fires the moment ONE survey is severe: Strongly Disagree on at least
 * CsmStatsService::SEVERE_SD_THRESHOLD (3) of the 9 SQD questions. Every
 * Super Admin gets a bell + email listing the full question text of each
 * Disagree / Strongly Disagree answer — aggregate-only (a question cannot
 * identify a respondent; names and ticket numbers never appear, same
 * confidentiality rule as the weekly digest and monthly PDF).
 *
 * Daily bundle rule: at most ONE alert per calendar day no matter how many
 * severe surveys arrive. Deduped by querying the notifications table itself
 * (type-based) — no extra migration, no new table.
 */
class CsmSevereAlertService
{
    public const NOTIFICATION_TYPE = 'CSM Severe Alert';

    /**
     * Check one freshly saved survey and alert if it is severe.
     * Returns true when an alert went out. Never throws: a failing alert
     * must not disturb the survey submission flow.
     */
    public function check(CsmSurvey $survey): bool
    {
        try {
            if (! CsmStatsService::isSevere($survey)) {
                return false;
            }

            if (Notification::where('type', self::NOTIFICATION_TYPE)
                ->whereDate('created_at', today())
                ->exists()) {
                return false;
            }

            $url = route('dashboard.super-admin');

            $admins = User::where('role', 'super_admin')->get();
            foreach ($admins as $admin) {
                Notification::send(
                    $admin->id,
                    null,
                    self::NOTIFICATION_TYPE,
                    $this->buildMessage($survey),
                    $url
                );
            }

            return $admins->isNotEmpty();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'CSM severe alert failed (survey #' . $survey->id . '): ' . $e->getMessage()
            );

            return false;
        }
    }

    /**
     * Human-readable alert body: how many Strongly Disagrees, then every
     * Disagree / Strongly Disagree answer with the full printed question
     * text (never DB column codes — plain language like the monthly PDF).
     */
    private function buildMessage(CsmSurvey $survey): string
    {
        $parts = [];
        $number = 0;

        foreach (CsmStatsService::SQD_COLUMNS as $column) {
            $score = CsmStatsService::scoreFor($survey->{$column} ?? null);

            if ($score === null || $score > 2) {
                continue;
            }

            $number++;
            $parts[] = sprintf(
                '%d) "%s" — %s',
                $number,
                CsmStatsService::questionFor($column),
                $survey->{$column}
            );
        }

        return sprintf(
            'Severe CSM alert: %d of 9 answers were Strongly Disagree. Failed questions: %s',
            CsmStatsService::severeCount($survey),
            implode(' · ', $parts)
        );
    }
}
