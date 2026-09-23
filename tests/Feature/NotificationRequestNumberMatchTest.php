<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Support\RequestHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D9.42 Phase 3(ii) — the notification bell must read the STORED request number.
 *
 * The bell matched only '/REQ-[A-Z0-9-]+/', so once numbers became
 * '{ICT|PM}-REGION-BRANCH-date-NNNN' the match returned nothing: the bell lost
 * the ticket number, and clicking the notification fell through to the ticket
 * LIST instead of opening the ticket. Both lookups now go through
 * RequestHelpers::extractRequestNumber(), which still accepts the legacy
 * 'REQ-NCR-RCMB-2026-0001' and D9.24 'REQ-2026-09-17-0001' shapes.
 *
 * Notifications here are created with request_id = NULL on purpose: that is the
 * code path where the number has to be recovered from the message text.
 */
class NotificationRequestNumberMatchTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(string $role = 'user'): User
    {
        $this->counter++;

        return User::create([
            'full_name' => 'D942 Notif ' . ucfirst($role) . ' ' . $this->counter,
            'email'     => 'd942-notif-' . $role . '-' . $this->counter . '@test.com',
            'password'  => bcrypt('password'),
            'role'      => $role,
            'is_active' => true,
            'region'    => 'NCR',
            'branch'    => 'RCMB',
            'office'    => 'RESEARCH AND INFORMATION DIVISION',
        ]);
    }

    private function ticket(User $owner, string $number, string $type = 'ICT'): RequestModel
    {
        $this->counter++;

        return RequestModel::create([
            'user_id'        => $owner->id,
            'request_number' => $number,
            'type'           => $type,
            'requestor_name' => $owner->full_name,
            'region'         => 'NCR',
            'branch'         => 'RCMB',
            'office'         => 'RESEARCH AND INFORMATION DIVISION',
            'status'         => 'Ongoing',
            'is_deleted'     => false,
            'description'    => 'D9.42 notification test #' . $this->counter,
        ]);
    }

    /** A notification with NO request_id — the number lives in the message only. */
    private function loose(User $recipient, string $message, string $type = 'Request Updated'): Notification
    {
        return Notification::send($recipient->id, null, $type, $message);
    }

    private function bellJson(User $viewer): array
    {
        return $this->actingAs($viewer)
            ->getJson(route('notifications.get'))
            ->assertOk()
            ->json('notifications');
    }

    public function test_the_bell_reports_the_ticket_number_for_a_new_format_ict_message(): void
    {
        $recipient = $this->user();

        $this->loose($recipient, 'Your ICT Repair request ICT-NCR-RCMB-2026-09-23-0001 is now Ongoing.');

        $rows = $this->bellJson($recipient);

        $this->assertCount(1, $rows);
        $this->assertSame('ICT-NCR-RCMB-2026-09-23-0001', $rows[0]['request_number']);
    }

    public function test_the_bell_reports_the_ticket_number_for_a_new_format_pm_message(): void
    {
        $recipient = $this->user();

        $this->loose(
            $recipient,
            'A new PM request PM-NCR-RCMB-2026-09-23-0001 has been scheduled. Please check your dashboard.'
        );

        $rows = $this->bellJson($recipient);

        $this->assertSame('PM-NCR-RCMB-2026-09-23-0001', $rows[0]['request_number']);
    }

    public function test_the_bell_still_reads_legacy_and_d9_24_numbers(): void
    {
        $recipient = $this->user();

        $this->loose($recipient, 'Your request REQ-NCR-RCMB-2026-0001 was updated.');
        $this->loose($recipient, 'Your request REQ-2026-09-17-0001 was updated.');

        $numbers = array_column($this->bellJson($recipient), 'request_number');

        $this->assertContains('REQ-NCR-RCMB-2026-0001', $numbers);
        $this->assertContains('REQ-2026-09-17-0001', $numbers);
    }

    public function test_a_message_without_a_request_number_reports_none(): void
    {
        $recipient = $this->user();

        $this->loose($recipient, 'Your profile details were updated by the System Admin.');

        $rows = $this->bellJson($recipient);

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['request_number']);
    }

    public function test_a_purchase_request_number_is_not_swallowed_by_the_ticket_lookup(): void
    {
        $recipient = $this->user();

        // PR-… is a procurement number, not a service request number; the
        // pre-existing fallback must keep reporting it as-is.
        $this->loose($recipient, 'PR-2026-0012 is now Delivered — please acknowledge.', 'PR Delivered');

        $rows = $this->bellJson($recipient);

        $this->assertSame('PR-2026-0012', $rows[0]['request_number']);
    }

    public function test_a_trailing_separator_is_not_swallowed_into_the_number(): void
    {
        $recipient = $this->user();

        $this->loose($recipient, 'ICT-NCR-RCMB-2026-09-23-0001 - please review and assign IT personnel.');

        $rows = $this->bellJson($recipient);

        $this->assertSame('ICT-NCR-RCMB-2026-09-23-0001', $rows[0]['request_number']);
    }

    public function test_clicking_a_new_format_ict_notification_opens_the_ticket(): void
    {
        $requestor = $this->user();
        $viewer = $this->user();
        $ticket = $this->ticket($requestor, 'ICT-NCR-RCMB-2026-09-23-0001');

        $this->loose($viewer, "New ICT Repair ({$ticket->request_number}) needs your attention.");

        $rows = $this->bellJson($viewer);

        $this->assertSame(
            route('ict.edit', $ticket->id),
            $rows[0]['url'],
            'The bell must open the ticket, not fall through to the ticket list.'
        );
    }

    public function test_clicking_a_new_format_ict_notification_sends_an_admin_to_the_show_page(): void
    {
        $requestor = $this->user();
        $admin = $this->user('admin');
        $ticket = $this->ticket($requestor, 'ICT-NCR-RCMB-2026-09-23-0001');

        $this->loose($admin, "New ICT Repair ({$ticket->request_number}) needs your attention.");

        $rows = $this->bellJson($admin);

        $this->assertSame(route('ict.show', $ticket->id), $rows[0]['url']);
    }

    public function test_clicking_a_new_format_pm_notification_opens_the_maintenance_ticket(): void
    {
        $requestor = $this->user();
        $viewer = $this->user();
        $ticket = $this->ticket($requestor, 'PM-NCR-RCMB-2026-09-23-0001', 'Preventive Maintenance');

        $this->loose($viewer, "A new PM request {$ticket->request_number} has been scheduled.");

        $rows = $this->bellJson($viewer);

        $this->assertSame(route('maintenance.show', $ticket->id), $rows[0]['url']);
    }

    public function test_clicking_a_legacy_notification_still_opens_the_ticket(): void
    {
        $requestor = $this->user();
        $viewer = $this->user();
        $ticket = $this->ticket($requestor, 'REQ-NCR-RCMB-2026-0001');

        $this->loose($viewer, "Your ICT Repair request ({$ticket->request_number}) was updated.");

        $rows = $this->bellJson($viewer);

        $this->assertSame(route('ict.edit', $ticket->id), $rows[0]['url']);
    }

    public function test_a_number_with_no_matching_ticket_falls_back_to_the_list(): void
    {
        $viewer = $this->user();

        // Number belongs to no row: the bell must degrade gracefully to the
        // generic ICT list instead of building a url for a missing id.
        $this->loose($viewer, 'Your request ICT-NCR-RCMB-2026-09-23-0099 was updated.');

        $rows = $this->bellJson($viewer);

        $this->assertSame('ICT-NCR-RCMB-2026-09-23-0099', $rows[0]['request_number']);
        $this->assertSame(route('ict.index'), $rows[0]['url']);
    }

    public function test_a_purchase_request_notification_opens_the_purchase_request(): void
    {
        $viewer = $this->user();

        $this->loose($viewer, 'PR-2026-0012 has been delivered.', 'PR Delivered');

        $rows = $this->bellJson($viewer);

        // No matching PR row exists, so the requisition list is the safe target.
        $this->assertSame(route('requisitions.index'), $rows[0]['url']);
    }

    /**
     * Direct helper coverage. The bell tests above prove the wiring; these pin
     * the recogniser itself so a future refactor of the regex is caught here
     * instead of by a broken bell in production.
     */
    public function test_extract_request_number_recognises_every_stored_format(): void
    {
        $this->assertSame(
            'ICT-NCR-RCMB-2026-09-23-0001',
            RequestHelpers::extractRequestNumber('Your ICT request ICT-NCR-RCMB-2026-09-23-0001 is now Completed.')
        );
        $this->assertSame(
            'PM-NCR-RCMB-2026-09-01-0007',
            RequestHelpers::extractRequestNumber('A new PM request PM-NCR-RCMB-2026-09-01-0007 has been scheduled.')
        );
        $this->assertSame(
            'REQ-NCR-RCMB-2026-0028',
            RequestHelpers::extractRequestNumber('Ticket REQ-NCR-RCMB-2026-0028 was referred to an external provider.')
        );
        $this->assertSame(
            'REQ-2026-09-17-0001',
            RequestHelpers::extractRequestNumber('Ticket REQ-2026-09-17-0001 was resubmitted for approval.')
        );
    }

    public function test_extract_request_number_never_swallows_a_trailing_separator(): void
    {
        $this->assertSame(
            'ICT-NCR-RCMB-2026-09-23-0001',
            RequestHelpers::extractRequestNumber('ICT-NCR-RCMB-2026-09-23-0001 - please review')
        );
        $this->assertSame(
            'PM-NCR-RCMB-2026-09-23-0002',
            RequestHelpers::extractRequestNumber('PM-NCR-RCMB-2026-09-23-0002. Awaiting your approval.')
        );
        $this->assertSame(
            'ICT-NCR-RCMB-2026-09-23-0003',
            RequestHelpers::extractRequestNumber('(ICT-NCR-RCMB-2026-09-23-0003)')
        );
    }

    public function test_extract_request_number_ignores_unrelated_references(): void
    {
        // PR-… is procurement, and a word merely ENDING in a prefix token must
        // not be mistaken for a service request number.
        $this->assertNull(RequestHelpers::extractRequestNumber('PR-2026-0016 finalized — ready to print.'));
        $this->assertNull(RequestHelpers::extractRequestNumber('CONFLICT-123 token text'));
        $this->assertNull(RequestHelpers::extractRequestNumber('No reference here at all.'));
        $this->assertNull(RequestHelpers::extractRequestNumber(null));
        $this->assertNull(RequestHelpers::extractRequestNumber('   '));
    }

    public function test_an_explicit_url_always_wins_over_extraction(): void
    {
        $requestor = $this->user();
        $viewer = $this->user();
        $ticket = $this->ticket($requestor, 'ICT-NCR-RCMB-2026-09-23-0001');

        Notification::create([
            'user_id'    => $viewer->id,
            'request_id' => null,
            'type'       => 'Custom',
            'message'    => "About {$ticket->request_number}",
            'url'        => route('maintenance.index'),
            'is_read'    => false,
        ]);

        $rows = $this->bellJson($viewer);

        $this->assertSame(
            route('maintenance.index'),
            $rows[0]['url'],
            'A stored url is authoritative — extraction is only the fallback.'
        );
    }
}
