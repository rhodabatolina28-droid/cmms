<?php

namespace Tests\Feature;

use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D9.20 — ICT requests go straight to the System Admin.
 * Phase 1: the SA approve/reject path (division admins keep the legacy guard).
 */
class IctDirectToSystemAdminTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'D920 User ' . $this->counter,
            'email' => 'd920-user-' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'region' => 'NCR',
            'branch' => 'RCMB',
            'office' => 'RESEARCH AND INFORMATION DIVISION',
        ], $attributes));
    }

    private function ictTicket(User $requestor, string $number, ?string $reviewStatus = null): RequestModel
    {
        return RequestModel::create([
            'user_id' => $requestor->id,
            'request_number' => $number,
            'type' => 'ICT',
            'requestor_name' => $requestor->full_name,
            'region' => $requestor->region,
            'branch' => $requestor->branch,
            'office' => $requestor->office,
            'status' => 'Pending',
            'is_deleted' => false,
            'description' => 'D9.20 flow ticket ' . $number,
            'division_admin_review_status' => $reviewStatus,
        ]);
    }

    public function test_system_admin_can_approve_a_pending_ticket_in_any_division_of_their_branch(): void
    {
        $sa = $this->user(['role' => 'super_admin']);
        $requestor = $this->user(['office' => 'FINANCIAL AND MANAGEMENT DIVISION']);
        $ticket = $this->ictTicket($requestor, 'REQ-NCR-RCMB-2026-9001');

        $this->actingAs($sa)
            ->postJson(route('ict.review', $ticket->id), ['status' => 'Approved', 'notes' => 'Valid.'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $ticket->refresh();
        $this->assertSame('Approved', $ticket->division_admin_review_status);
        $this->assertSame($sa->id, (int) $ticket->reviewed_by_admin_id);
        $this->assertNotNull($ticket->reviewed_at);
    }

    public function test_system_admin_can_reject_an_auto_approved_ticket(): void
    {
        $sa = $this->user(['role' => 'super_admin']);
        $requestor = $this->user();
        $ticket = $this->ictTicket($requestor, 'REQ-NCR-RCMB-2026-9002', 'Approved');

        $this->actingAs($sa)
            ->postJson(route('ict.review', $ticket->id), ['status' => 'Rejected', 'notes' => 'Duplicate.'])
            ->assertOk();

        $ticket->refresh();
        $this->assertSame('Rejected', $ticket->division_admin_review_status);
        $this->assertSame('Rejected', $ticket->status);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $requestor->id,
            'request_id' => $ticket->id,
            'type' => 'Request Rejected',
        ]);
    }

    public function test_system_admin_cannot_review_outside_their_branch(): void
    {
        $sa = $this->user(['role' => 'super_admin']);
        $requestor = $this->user(['branch' => 'OTHER BRANCH']);
        $ticket = $this->ictTicket($requestor, 'REQ-NCR-RCMB-2026-9003');

        $this->actingAs($sa)
            ->postJson(route('ict.review', $ticket->id), ['status' => 'Approved'])
            ->assertStatus(403);

        $this->assertNull($ticket->fresh()->division_admin_review_status);
    }

    public function test_system_admin_approve_does_not_self_notify(): void
    {
        $sa = $this->user(['role' => 'super_admin']);
        $requestor = $this->user();
        $ticket = $this->ictTicket($requestor, 'REQ-NCR-RCMB-2026-9004');

        $this->actingAs($sa)
            ->postJson(route('ict.review', $ticket->id), ['status' => 'Approved'])
            ->assertOk();

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $sa->id,
            'request_id' => $ticket->id,
            'type' => 'Forwarded ICT Repair',
        ]);
    }

    public function test_division_admin_still_reviews_legacy_pending_ticket_and_forwards_to_system_admin(): void
    {
        $admin = $this->user(['role' => 'admin']);
        $sa = $this->user(['role' => 'super_admin']);
        $requestor = $this->user();
        $ticket = $this->ictTicket($requestor, 'REQ-NCR-RCMB-2026-9005');

        $this->actingAs($admin)
            ->postJson(route('ict.review', $ticket->id), ['status' => 'Approved'])
            ->assertOk();

        $this->assertSame('Approved', $ticket->fresh()->division_admin_review_status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $sa->id,
            'request_id' => $ticket->id,
            'type' => 'Forwarded ICT Repair',
        ]);
    }

    public function test_division_admin_cannot_re_review_an_already_reviewed_ticket(): void
    {
        $admin = $this->user(['role' => 'admin']);
        $requestor = $this->user();
        $ticket = $this->ictTicket($requestor, 'REQ-NCR-RCMB-2026-9006', 'Approved');

        $this->actingAs($admin)
            ->postJson(route('ict.review', $ticket->id), ['status' => 'Approved'])
            ->assertStatus(422);
    }

    public function test_end_user_cannot_review(): void
    {
        $requestor = $this->user();
        $ticket = $this->ictTicket($requestor, 'REQ-NCR-RCMB-2026-9007');

        $this->actingAs($requestor)
            ->postJson(route('ict.review', $ticket->id), ['status' => 'Approved'])
            ->assertStatus(403);
    }
}
