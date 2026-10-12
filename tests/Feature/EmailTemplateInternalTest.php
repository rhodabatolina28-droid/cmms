<?php

namespace Tests\Feature;

use App\Mail\SystemNotificationMail;
use Tests\TestCase;

/**
 * Phase E1 (docs/UX_DEFECT_FIX_PLAN_OCT2026.md §12.8) — internal rebrand of the
 * shared email template resources/views/emails/default.blade.php.
 *
 * The template serves every notification type (~50 scenarios) from ONE view, so
 * these tests pin the approved literals (visual preview approved 2026-10-09):
 * eyebrow "CMMS Notification", "Hello {name}," greeting, nl2br message body
 * (kills the multi-line run-on), "Open Ticket" CTA, and the internal-use footer
 * (drops the DOLE line + CONFIDENTIALITY NOTICE boilerplate — this is an
 * internal ops notification, not a public advisory).
 *
 * The KEEP contract (agency header name, subtitle, Ticket No. details box,
 * #0038A8 furniture) is pinned by test_template_keeps_rebrand_furniture so the
 * rebrand commit 076e391 cannot regress silently.
 */
class EmailTemplateInternalTest extends TestCase
{
    private function renderMail(string $message = 'Your ICT request was updated.'): string
    {
        return (new SystemNotificationMail(
            'Maria Santos',
            'Request Updated',
            $message,
            'ICT-NCR-RCMB-2026-09-24-0001',
            'http://cmms.internal.local/tickets/1'
        ))->render();
    }

    public function test_template_eyebrow_reads_cmms_notification(): void
    {
        $rendered = $this->renderMail();

        $this->assertStringContainsString('header-eyebrow">CMMS Notification<', $rendered);
        $this->assertStringNotContainsString('Official Notification', $rendered);
    }

    public function test_template_greeting_is_hello_recipient(): void
    {
        $rendered = $this->renderMail();

        $this->assertStringContainsString('greeting">Hello Maria Santos,<', $rendered);
        $this->assertStringNotContainsString('Good day', $rendered);
    }

    /**
     * THE run-on fix: messages store real newlines (weekly summary, PM failure
     * alert) — the template must turn them into <br>, not print them raw.
     */
    public function test_template_renders_message_newlines_as_line_breaks(): void
    {
        $rendered = $this->renderMail("Line one of the summary.\nLine two of the summary.");

        $this->assertStringContainsString('Line one of the summary.<br', $rendered);
        $this->assertStringContainsString('Line two of the summary.', $rendered);
    }

    public function test_template_cta_reads_open_ticket(): void
    {
        $rendered = $this->renderMail();

        $this->assertStringContainsString('>Open Ticket<', $rendered);
        $this->assertStringNotContainsString('View Details', $rendered);
    }

    public function test_template_footer_is_internal_cmms_boilerplate(): void
    {
        $rendered = $this->renderMail();

        $this->assertStringContainsString('This is an automated CMMS message', $rendered);
        $this->assertStringContainsString('For internal use only.', $rendered);
        // Tanggal ang DOLE parent line + confidentiality boilerplate — internal
        // ops notification lang, hindi public advisory.
        $this->assertStringNotContainsString('Department of Labor and Employment', $rendered);
        $this->assertStringNotContainsString('CONFIDENTIALITY NOTICE', $rendered);
        $this->assertStringNotContainsString('This is an automated notification.', $rendered);
    }

    /** KEEP contract from rebrand 076e391 — dapat hindi mawala sa bagong design. */
    public function test_template_keeps_rebrand_furniture(): void
    {
        $rendered = $this->renderMail();

        $this->assertStringContainsString('header-name">National Conciliation and Mediation Board<', $rendered);
        $this->assertStringContainsString('Computerized Maintenance Management System', $rendered); // header subtitle + footer CMMS line
        $this->assertStringContainsString('details-label">Ticket No.<', $rendered);
        $this->assertStringContainsString('status-pill', $rendered);
        $this->assertStringContainsString('#0038A8', $rendered);
    }
}
