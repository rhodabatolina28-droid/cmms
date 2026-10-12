<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\PurchaseRequest;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Services\PurchaseRequestNotificationService;
use App\Services\RequestNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase E3 (docs/UX_DEFECT_FIX_PLAN_OCT2026.md §12.7) — the approved message
 * bank: concise, action-first internal ops English; prose names (no
 * strtoupper); numbers stay STORED-FULL (D9.42 — bell/email shorten at render).
 * TYPE strings are immutable (super_admin gate, CSM prefix, PM side-effects,
 * parts detection) and are NOT asserted here on purpose.
 *
 * Pins the copy previewed and approved on 2026-10-09 (S1/S2/S7 scenarios).
 */
class EmailMessageBankTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(string $role = 'user', string $branch = 'NCR'): User
    {
        $this->counter++;

        return User::create([
            'full_name' => 'Bank Test ' . ucfirst($role) . ' ' . $this->counter,
            'email'     => 'bank-' . $role . '-' . $this->counter . '@test.com',
            'password'  => bcrypt('password'),
            'role'      => $role,
            'is_active' => true,
            'region'    => 'NCR',
            'branch'    => $branch,
            'office'    => 'RESEARCH AND INFORMATION DIVISION',
        ]);
    }

    private function ticket(User $requestor, string $number = 'ICT-NCR-RCMB-2026-10-09-0001'): RequestModel
    {
        $this->counter++;

        return RequestModel::create([
            'user_id'        => $requestor->id,
            'request_number' => $number,
            'type'           => 'ICT',
            'requestor_name' => $requestor->full_name,
            'region'         => 'NCR',
            'branch'         => 'NCR',
            'office'         => 'RESEARCH AND INFORMATION DIVISION',
            'status'         => 'Ongoing',
            'is_deleted'     => false,
            'description'    => 'Bank test ticket #' . $this->counter,
        ]);
    }

    /** S1 — IT assigned to the requestor's ticket. */
    public function test_requestor_assignment_uses_the_approved_bank_copy(): void
    {
        $requestor = $this->user();
        $it        = $this->user('it');
        $ticket    = $this->ticket($requestor);

        RequestNotificationService::notifyRequestorItAssigned($ticket, $it);

        $message = Notification::where('user_id', $requestor->id)->latest('id')->first()->message;

        $this->assertSame(
            "Your ICT Repair request {$ticket->request_number} is assigned to {$it->full_name}. Open the ticket to track progress.",
            $message
        );
    }

    /** S2 — rejection with reason, follow-up instruction. */
    public function test_requestor_rejection_uses_the_approved_bank_copy(): void
    {
        $requestor = $this->user();
        $ticket    = $this->ticket($requestor);

        RequestNotificationService::notifyRequestorOfRejection($ticket, 'Duplicate ticket');

        $message = Notification::where('user_id', $requestor->id)->latest('id')->first()->message;

        $this->assertSame(
            "Your ICT Repair request {$ticket->request_number} was rejected — Duplicate ticket. Submit a new ticket if the issue persists.",
            $message
        );
    }

    /** New-ICT review alert: prose names, no strtoupper shouting. */
    public function test_new_ict_review_message_keeps_prose_names(): void
    {
        $requestor = $this->user();
        $sa        = $this->user('super_admin');
        $ticket    = $this->ticket($requestor);

        RequestNotificationService::notifySystemAdminOfNewIctRequest($ticket, $requestor);

        $message = Notification::where('user_id', $sa->id)->latest('id')->first()->message;

        $this->assertStringContainsString("from {$requestor->full_name}", $message);
        $this->assertStringNotContainsString(strtoupper($requestor->full_name), $message);
        $this->assertStringContainsString('Review and assign IT.', $message);
    }

    /** IT-side assignment: action-first. */
    public function test_it_assignment_copy_is_action_first(): void
    {
        $it     = $this->user('it');
        $owner  = $this->user();
        $ticket = $this->ticket($owner);

        RequestNotificationService::notifyItAssigned($ticket, $it);

        $message = Notification::where('user_id', $it->id)->latest('id')->first()->message;

        $this->assertSame(
            "ICT Repair request {$ticket->request_number} is assigned to you. Open the ticket and complete the technical sections.",
            $message
        );
    }

    /** S7 — PR submitted to Supply. */
    public function test_pr_submitted_uses_the_approved_bank_copy(): void
    {
        $creator = $this->user();
        $supply  = $this->user('supply_officer');

        $pr = new PurchaseRequest([
            'pr_number'    => 'PR-2026-0015',
            'status'       => PurchaseRequest::STATUS_SUBMITTED,
            'items'        => [['name' => 'Fan', 'qty' => 3]],
            'total_amount' => 12500,
        ]);
        $pr->created_by = $creator->id;
        $pr->requested_by = $creator->id;
        $pr->save();

        PurchaseRequestNotificationService::notifySubmitted($pr);

        $message = Notification::where('user_id', $supply->id)->latest('id')->first()->message;

        $this->assertSame('PR-2026-0015 submitted — 1 item, ₱12,500.00 total. Awaiting your review.', $message);
    }
}
