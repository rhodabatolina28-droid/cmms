<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BUG-NOTIF-FROM-1 — the bell showed "From: <yourself>" on your own tickets.
 *
 * The notification "From" was derived from `request->user` — the ticket's
 * REQUESTOR. For notifications addressed to the requestor about their own
 * ticket (status updates, assignments, rejections), that derived sender IS
 * the recipient, so the bell read "From: Juan Dela Cruz" inside Juan's own
 * bell. Contract pinned here:
 *
 *  1. Self-addressed "Request Updated" → sender is the ACTING IT personnel
 *     named in the message ("IT personnel X has been assigned..."), not the
 *     requestor.
 *  2. Self-addressed notification with no named actor → "System".
 *  3. Admin-directed notifications keep showing the requestor (unchanged).
 */
class NotificationFromLabelTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(string $role = 'user'): User
    {
        $this->counter++;

        return User::create([
            'full_name'  => 'Notif ' . ucfirst($role) . ' ' . $this->counter,
            'email'      => 'notif-' . $role . '-' . $this->counter . '@test.com',
            'password'   => bcrypt('password'),
            'role'       => $role,
            'is_active'  => true,
            'region'     => 'NCR',
            'branch'     => 'Main Office',
            'office'     => 'RESEARCH AND INFORMATION DIVISION',
        ]);
    }

    private function ictRequest(User $owner): RequestModel
    {
        $this->counter++;

        return RequestModel::create([
            'user_id'        => $owner->id,
            'request_number' => 'REQ-2026-09-22-' . str_pad((string) $this->counter, 4, '0', STR_PAD_LEFT),
            'type'           => 'ICT',
            'requestor_name' => $owner->full_name,
            'region'         => 'NCR',
            'branch'         => 'Main Office',
            'office'         => 'RESEARCH AND INFORMATION DIVISION',
            'status'         => 'Ongoing',
            'description'    => 'Monitor flickering',
        ]);
    }

    private function bellJson(User $viewer): array
    {
        return $this->actingAs($viewer)
            ->getJson(route('notifications.get'))
            ->assertOk()
            ->json('notifications');
    }

    public function test_self_addressed_status_notification_shows_the_acting_it_person(): void
    {
        $requestor = $this->user();
        $req = $this->ictRequest($requestor);

        Notification::send(
            $requestor->id,
            $req->id,
            'Request Updated',
            "Your ICT Repair request {$req->request_number} is now Ongoing. IT personnel Marites Santos-Reyes has been assigned to work on your ticket."
        );

        $rows = $this->bellJson($requestor);

        $this->assertCount(1, $rows);
        $this->assertSame('Marites Santos-Reyes', $rows[0]['sender']);
    }

    public function test_self_addressed_notification_without_actor_shows_system(): void
    {
        $requestor = $this->user();
        $req = $this->ictRequest($requestor);

        Notification::send(
            $requestor->id,
            $req->id,
            'Request Completed',
            "Your ICT Request {$req->request_number} status has been updated to Completed."
        );

        $rows = $this->bellJson($requestor);

        $this->assertCount(1, $rows);
        $this->assertSame('System', $rows[0]['sender']);
    }

    public function test_admin_directed_notification_still_shows_the_requestor(): void
    {
        $requestor = $this->user();
        $admin = $this->user('admin');
        $req = $this->ictRequest($requestor);

        Notification::send(
            $admin->id,
            $req->id,
            'New ICT Repair for Review',
            "New ICT Repair from " . strtoupper($requestor->full_name) . " ({$req->request_number}) — RESEARCH AND INFORMATION DIVISION."
        );

        $rows = $this->bellJson($admin);

        $this->assertCount(1, $rows);
        $this->assertSame($requestor->full_name, $rows[0]['sender']);
    }

    public function test_admin_assigning_it_shows_the_admin_as_the_sender(): void
    {
        $requestor = $this->user();
        $admin = $this->user('super_admin');
        $it = $this->user('it');
        $req = $this->ictRequest($requestor);

        // The assign action runs inside the admin's session → send() records
        // the ACTOR automatically (Auth::id()), not the ticket requestor.
        $this->actingAs($admin);
        Notification::send(
            $it->id,
            $req->id,
            'Job Order Assigned',
            "You have been assigned ICT Repair request {$req->request_number}."
        );

        $rows = $this->bellJson($it);

        $this->assertCount(1, $rows);
        $this->assertSame($admin->full_name, $rows[0]['sender']); // NOT the requestor
    }

    public function test_parts_request_by_it_shows_it_as_the_sender_not_the_ticket_requestor(): void
    {
        $requestor = $this->user();
        $it = $this->user('it');
        $supply = $this->user('supply_officer');
        $req = $this->ictRequest($requestor);

        // IT files a parts requisition → supply sees IT beside the icon,
        // never the end-user who merely submitted the original ticket.
        $this->actingAs($it);
        Notification::send(
            $supply->id,
            $req->id,
            'Parts Requisition',
            "New parts requisition for {$req->request_number} from {$it->full_name}."
        );

        $rows = $this->bellJson($supply);

        $this->assertCount(1, $rows);
        $this->assertSame($it->full_name, $rows[0]['sender']); // NOT the ticket requestor
    }

    public function test_submitted_ticket_shows_the_submitting_user_as_the_sender(): void
    {
        $requestor = $this->user();
        $admin = $this->user('super_admin');
        $req = $this->ictRequest($requestor);

        // User submits → forwarded to SA: "From" IS the submitting user
        // (actor == requestor here by coincidence, not by derivation).
        $this->actingAs($requestor);
        Notification::send(
            $admin->id,
            $req->id,
            'New ICT Repair for Review',
            "New ICT Repair from " . strtoupper($requestor->full_name) . " ({$req->request_number})."
        );

        $rows = $this->bellJson($admin);

        $this->assertCount(1, $rows);
        $this->assertSame($requestor->full_name, $rows[0]['sender']);
    }

    public function test_explicit_actor_equal_to_recipient_never_shows_own_name(): void
    {
        $requestor = $this->user();
        $req = $this->ictRequest($requestor);

        // Some flows notify the actor themselves (sender_id == recipient):
        // the bell must still not read "From: <yourself>".
        Notification::send(
            $requestor->id,
            $req->id,
            'Request Updated',
            "Your ICT Repair request {$req->request_number} status has been updated to Completed.",
            null,
            $requestor->id
        );

        $rows = $this->bellJson($requestor);

        $this->assertCount(1, $rows);
        $this->assertSame('System', $rows[0]['sender']);
    }
}
