<?php

namespace Tests\Feature;

use App\Mail\SystemNotificationMail;
use App\Models\Notification;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * BUG-NOTIF-LINK (D9.42 follow-up, Sept 24 2026) — destination URL of a
 * notification click.
 *
 * resolveTargetUrl() checked the request-linked branch FIRST, and every
 * parts-family notification carries request_id (the parent ticket) — so
 * clicking "Parts Requisition" (Supply) or "Parts Request — Approved" (IT/SA)
 * landed on the ICT ticket form instead of the requisitions workspace. The
 * type-based `str_contains($type, 'Parts') → requisitions.index` branch below
 * it was unreachable for those rows.
 *
 * Same bug class caught while auditing the other 21 types: "PM Batch
 * Generated" is sent to IT/admin/supply too, but its type fallback pointed at
 * pm-schedules.index — a role:super_admin-only route (403-redirect for the
 * other recipients). The email "View Details" button (Notification::booted)
 * rebuilt the same wrong ICT URL, and the bell label said "Open Ticket" for
 * destinations that are not tickets.
 *
 * New precedence: stored url > parts family (role-aware workspace) > PM batch
 * (role-aware) > linked ticket > message-number lookup > PR > type fallbacks.
 */
class NotificationDestinationUrlTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(string $role, bool $canSupply = false): User
    {
        $this->counter++;

        return User::create([
            'full_name'  => 'Link Test ' . ucfirst($role) . ' ' . $this->counter,
            'email'      => 'link-test-' . $role . '-' . $this->counter . '@test.com',
            'password'   => bcrypt('password'),
            'role'       => $role,
            'can_supply' => $canSupply,
            'is_active'  => true,
            'region'     => 'NCR',
            'branch'     => 'RCMB',
            'office'     => 'RESEARCH AND INFORMATION DIVISION',
        ]);
    }

    private function ticket(User $owner): RequestModel
    {
        $this->counter++;

        return RequestModel::create([
            'user_id'        => $owner->id,
            'request_number' => 'ICT-NCR-RCMB-2026-09-24-' . str_pad((string) $this->counter, 4, '0', STR_PAD_LEFT),
            'type'           => 'ICT',
            'requestor_name' => $owner->full_name,
            'region'         => 'NCR',
            'branch'         => 'RCMB',
            'office'         => 'RESEARCH AND INFORMATION DIVISION',
            'status'         => 'Awaiting Parts',
            'is_deleted'     => false,
            'description'    => 'BUG-NOTIF-LINK destination test #' . $this->counter,
        ]);
    }

    /** First (newest) resolved URL in the bell payload for the viewer. */
    private function bellUrl(User $viewer): string
    {
        return $this->actingAs($viewer)
            ->getJson(route('notifications.get'))
            ->assertOk()
            ->json('notifications.0.url');
    }

    /** --- Parts family: destination is the requisitions workspace, not the ticket --- */

    public function test_parts_requisition_notification_for_supply_opens_the_requisition_queue_not_the_ict_form(): void
    {
        $supply = $this->user('supply_officer');
        $ticket = $this->ticket($this->user('it'));

        Notification::send(
            $supply->id,
            $ticket->id,
            'Parts Requisition',
            "New parts requisition for {$ticket->request_number} from someone."
        );

        $url = $this->bellUrl($supply);

        $this->assertSame(route('requisitions.index'), $url);
        $this->assertStringNotContainsString('/requests/ict/', $url);
    }

    public function test_parts_request_action_notification_for_it_opens_its_requisition_history(): void
    {
        $it = $this->user('it');
        $ticket = $this->ticket($it);

        Notification::send(
            $it->id,
            $ticket->id,
            'Parts Request — Approve',
            "Supply approved your parts request for {$ticket->request_number}. They will issue parts when ready."
        );

        $this->assertSame(route('requisitions.index', ['tab' => 'history']), $this->bellUrl($it));
    }

    public function test_parts_request_action_notification_for_super_admin_opens_history_tab(): void
    {
        $sa = $this->user('super_admin');
        $ticket = $this->ticket($sa);

        Notification::send(
            $sa->id,
            $ticket->id,
            'Parts Request — Issued',
            "Parts were issued for {$ticket->request_number}. You may continue repair work."
        );

        $this->assertSame(route('requisitions.index', ['tab' => 'history']), $this->bellUrl($sa));
    }

    public function test_auto_rejected_parts_request_routes_to_requisitions(): void
    {
        $it = $this->user('it');
        $ticket = $this->ticket($it);

        Notification::send(
            $it->id,
            $ticket->id,
            'Parts Request Rejected',
            "Your parts request for {$ticket->request_number} was auto-rejected because the ticket was Cancelled."
        );

        $this->assertSame(route('requisitions.index', ['tab' => 'history']), $this->bellUrl($it));
    }

    /** The resolved destination must be OPENABLE by its recipient (role middleware). */
    public function test_parts_notification_destination_is_reachable_by_its_recipient(): void
    {
        $it = $this->user('it');
        $ticketIt = $this->ticket($it);
        Notification::send($it->id, $ticketIt->id, 'Parts Request — Issue', "Parts were issued for {$ticketIt->request_number}.");

        $supply = $this->user('supply_officer');
        $ticketSupply = $this->ticket($this->user('it'));
        Notification::send($supply->id, $ticketSupply->id, 'Parts Requisition', "New parts requisition for {$ticketSupply->request_number}.");

        $this->actingAs($it)->get($this->bellUrl($it))->assertOk();
        $this->actingAs($supply)->get($this->bellUrl($supply))->assertOk();
    }

    /** --- PM batch: role-aware destination (pm-schedules is super_admin-only) --- */

    public function test_pm_batch_generated_for_it_opens_pm_tasks_not_the_sa_only_schedule_page(): void
    {
        $it = $this->user('it');

        Notification::send($it->id, null, 'PM Batch Generated', 'New PM tickets generated for RESEARCH AND INFORMATION DIVISION Division (3 users). Please conduct the PMs.');

        $url = $this->bellUrl($it);

        $this->assertSame(route('pm.tasks'), $url);
        $this->assertStringNotContainsString('/pm-schedules', $url);

        $this->actingAs($it)->get($url)->assertOk();
    }

    public function test_pm_batch_generated_for_super_admin_keeps_the_pm_schedules_page(): void
    {
        $sa = $this->user('super_admin');

        Notification::send($sa->id, null, 'PM Batch Generated', 'New PM tickets generated for RESEARCH AND INFORMATION DIVISION Division (3 users). Please conduct the PMs.');

        $this->assertSame(route('pm-schedules.index'), $this->bellUrl($sa));
    }

    public function test_pm_batch_generated_for_division_admin_opens_its_dashboard(): void
    {
        $admin = $this->user('admin');

        Notification::send($admin->id, null, 'PM Batch Generated', 'PM tickets have been generated for RESEARCH AND INFORMATION DIVISION Division (3 users). Please inform your personnel.');

        $url = $this->bellUrl($admin);

        // maintenance.index 403s plain admins inside the action; the neutral
        // landing for "inform your personnel" is the division dashboard.
        $this->assertSame(route('dashboard.admin'), $url);
        $this->assertStringNotContainsString('/pm-schedules', $url);

        $this->actingAs($admin)->get($url)->assertOk();
    }

    /** --- Regressions: ticket notifications must still open the ticket --- */

    public function test_job_order_assigned_still_opens_the_ticket(): void
    {
        $it = $this->user('it');
        $ticket = $this->ticket($it);

        Notification::send($it->id, $ticket->id, 'Job Order Assigned', "You have been assigned ICT Repair request {$ticket->request_number}.");

        $this->assertSame(route('ict.edit', $ticket->id), $this->bellUrl($it));
    }

    public function test_review_notification_for_super_admin_still_opens_the_ticket(): void
    {
        $sa = $this->user('super_admin');
        $ticket = $this->ticket($this->user('user'));

        Notification::send($sa->id, $ticket->id, 'New ICT Repair for Review', "New ICT Repair from SOMEONE ({$ticket->request_number}). Please review and assign IT personnel.");

        $this->assertSame(route('ict.show', $ticket->id), $this->bellUrl($sa));
    }

    public function test_stored_url_still_wins_over_resolution(): void
    {
        $it = $this->user('it');
        $stored = route('purchase_requests.show', 99);

        Notification::send($it->id, null, 'PR Submitted', 'PR-2026-0099 submitted — 2 item(s). Awaiting your review.', $stored);

        $this->assertSame($stored, $this->bellUrl($it));
    }

    /** --- Email button follows the same destination as the bell --- */

    public function test_parts_notification_email_button_points_to_the_requisitions_workspace(): void
    {
        Mail::fake();

        $supply = $this->user('supply_officer');
        $ticket = $this->ticket($this->user('it'));

        Notification::send(
            $supply->id,
            $ticket->id,
            'Parts Requisition',
            "New parts requisition for {$ticket->request_number} from someone."
        );

        Mail::assertQueued(SystemNotificationMail::class, function (SystemNotificationMail $mail) {
            return $mail->ticketUrl === route('requisitions.index')
                && ! str_contains((string) $mail->ticketUrl, '/requests/ict/');
        });
    }
}
