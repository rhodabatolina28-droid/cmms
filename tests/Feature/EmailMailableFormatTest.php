<?php

namespace Tests\Feature;

use App\Mail\PMScheduledMail;
use App\Mail\SystemNotificationMail;
use Tests\TestCase;

/**
 * Phase E2 (docs/UX_DEFECT_FIX_PLAN_OCT2026.md §12.7-12.8) — mailable format
 * overhaul, approved via the 7-scenario visual preview (2026-10-09):
 *
 *  1. Subject separator " - " -> " · " (all scenarios; SystemNotificationMail
 *     is the hub for ~46 types).
 *  2. Short numbers everywhere — PMScheduledMail previously printed the RAW
 *     stored number (ICT-NCR-RCMB-…) in its subject.
 *  3. PR references drop the redundant "#" (the number already starts with
 *     "PR-", so "#PR-2026-0015" double-prefixed).
 *  4. PMScheduledMail body = the approved S3 copy and the REAL schedule date;
 *     scheduleDate null -> NO Date row (the old fallback printed today's date,
 *     which silently lied about the PM date).
 *  5. PMAdminNotificationMail = dead code (0 call sites) — deleted per §9.4.
 */
class EmailMailableFormatTest extends TestCase
{
    public function test_system_subject_uses_middle_dot_and_short_number(): void
    {
        $mail = new SystemNotificationMail(
            'Maria Santos',
            'Request Updated',
            'Your ICT Repair request ICT-NCR-RCMB-2026-09-24-0001 was updated.',
            'ICT-NCR-RCMB-2026-09-24-0001'
        );

        $mail->render();

        $this->assertSame('[NCMB] Request Updated · #ICT-2026-09-24-0001', $mail->subject);
        $this->assertStringNotContainsString(' - #', $mail->subject);
    }

    public function test_pr_subject_drops_the_redundant_hash(): void
    {
        $mail = new SystemNotificationMail(
            'Roberto Reyes',
            'PR Submitted',
            'PR-2026-0015 submitted — 2 items. Awaiting your review.',
            'PR-2026-0015'
        );

        $mail->render();

        $this->assertSame('[NCMB] PR Submitted · PR-2026-0015', $mail->subject);
        $this->assertStringNotContainsString('#PR-', $mail->subject);
    }

    public function test_pm_scheduled_subject_shortens_and_body_uses_the_schedule_date(): void
    {
        $mail = new PMScheduledMail(
            'Maria Santos',
            'PM-NCR-RCMB-2026-10-01-0004',
            'Administrative Division',
            '2026-10-15'
        );

        $html = $mail->render();

        $this->assertSame('[NCMB] PM Scheduled · #PM-2026-10-01-0004', $mail->subject);
        $this->assertStringContainsString(
            'PM for your workstation in Administrative Division is scheduled on October 15, 2026. Coordinate with your ICT Unit for your time slot.',
            $html
        );
        $this->assertStringContainsString('details-label">Date<', $html);
        $this->assertStringContainsString('October 15, 2026', $html);
    }

    /** The old fallback rendered TODAY as the "PM date" — a silent lie. */
    public function test_pm_scheduled_without_date_omits_the_date_row(): void
    {
        $mail = new PMScheduledMail(
            'Maria Santos',
            'PM-NCR-RCMB-2026-10-01-0004',
            'Administrative Division',
            null
        );

        $html = $mail->render();

        $this->assertStringNotContainsString('details-label">Date<', $html);
        $this->assertStringNotContainsString(now()->format('F d, Y'), $html);
        // The copy stays grammatical without the date.
        $this->assertStringContainsString('PM for your workstation in Administrative Division is scheduled.', $html);
    }

    /** §9.4: dead mailable (0 call sites) — deleted, never wired. */
    public function test_dead_pm_admin_mailable_is_deleted(): void
    {
        $this->assertFalse(class_exists(\App\Mail\PMAdminNotificationMail::class));
    }
}
