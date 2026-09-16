<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\RepairRequest;
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

    private function ictTicket(User $requestor, string $number, ?string $reviewStatus = null, array $extra = []): RequestModel
    {
        return RequestModel::create(array_merge([
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
        ], $extra));
    }

    private function assetAssignedTo(User $user): InventoryAsset
    {
        $this->counter++;

        return InventoryAsset::create([
            'category' => 'Laptop',
            'item_name' => 'D920 Laptop ' . $this->counter,
            'serial_number' => 'D920-' . $this->counter,
            'region' => $user->region,
            'branch' => $user->branch,
            'office' => $user->office,
            'status' => 'Spare',
            'assigned_to_user' => $user->id,
        ]);
    }

    private function repairRequest(User $requestor): RepairRequest
    {
        return RepairRequest::create([
            'end_user_last_name' => 'D920',
            'end_user_first_name' => 'Tester',
            'end_user_sex' => 'MALE',
            'division_office' => $requestor->office,
            'end_user_email' => $requestor->email,
            'employee_no' => 'EMP-D920',
            'repair_description' => 'D9.20 test repair description',
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

    public function test_new_ict_request_is_auto_approved_and_notifies_system_admin(): void
    {
        $sa = $this->user(['role' => 'super_admin']);
        $admin = $this->user(['role' => 'admin']);
        $requestor = $this->user();
        $asset = $this->assetAssignedTo($requestor);

        $response = $this->actingAs($requestor)->postJson(route('ict.store'), [
            'linked_asset_id' => $asset->asset_id,
            'endUserLastName' => 'D920',
            'endUserFirstName' => 'Tester',
            'endUserSex' => 'MALE',
            'divisionOffice' => $requestor->office,
            'endUserEmail' => $requestor->email,
            'employeeNo' => 'EMP-D920',
            'repairDescription' => 'D9.20 test repair description',
        ]);
        $response->assertOk()->assertJson(['success' => true]);

        $ticket = RequestModel::where('linked_asset_id', $asset->asset_id)->firstOrFail();

        $this->assertSame('Approved', $ticket->division_admin_review_status, 'D9.20: new ICT tickets skip division review');
        $this->assertNotNull($ticket->reviewed_at);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $sa->id,
            'request_id' => $ticket->id,
            'type' => 'New ICT Repair for Review',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $admin->id,
            'request_id' => $ticket->id,
        ]);
    }

    public function test_resubmitted_ict_request_is_auto_approved_and_notifies_system_admin(): void
    {
        $sa = $this->user(['role' => 'super_admin']);
        $requestor = $this->user();
        $repair = $this->repairRequest($requestor);
        $ticket = $this->ictTicket($requestor, 'REQ-NCR-RCMB-2026-9008', 'Rejected', [
            'status' => 'Rejected',
            'detail_id' => $repair->id,
        ]);

        $response = $this->actingAs($requestor)
            ->putJson(route('ict.update', $ticket->id), ['repairDescription' => 'Updated details after rejection.']);
        $response->assertOk()->assertJson(['success' => true]);

        $ticket->refresh();
        $this->assertSame('Approved', $ticket->division_admin_review_status, 'D9.20: resubmits go straight back to the System Admin');
        $this->assertSame('Pending', $ticket->status);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $sa->id,
            'request_id' => $ticket->id,
            'type' => 'New ICT Repair for Review',
        ]);
    }

    public function test_division_admin_can_no_longer_quick_update_status(): void
    {
        $admin = $this->user(['role' => 'admin']);
        $it = $this->user(['role' => 'it']);
        $requestor = $this->user();
        $ticket = $this->ictTicket($requestor, 'REQ-NCR-RCMB-2026-9011', 'Approved', ['assigned_to' => $it->id]);

        $this->actingAs($admin)
            ->postJson(route('admin.requests.update-status'), ['id' => $ticket->id, 'status' => 'Ongoing'])
            ->assertStatus(403); // D9.20: role no longer allowed to use quick status at all

        $this->assertSame('Pending', $ticket->fresh()->status, 'D9.20: division admins are view-only on requests');
    }

    public function test_supply_officer_can_still_quick_update_status(): void
    {
        $supply = $this->user(['role' => 'admin', 'can_supply' => true]);
        $it = $this->user(['role' => 'it']);
        $requestor = $this->user();
        $ticket = $this->ictTicket($requestor, 'REQ-NCR-RCMB-2026-9012', 'Approved', ['assigned_to' => $it->id]);

        // 'remarks' is still accepted by the form request — sending it proves the
        // removed `remarks` DB write no longer breaks the endpoint (D9.20 bug fix).
        $this->actingAs($supply)
            ->postJson(route('admin.requests.update-status'), [
                'id' => $ticket->id,
                'status' => 'Ongoing',
                'remarks' => 'Supply follow-up.',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('Ongoing', $ticket->fresh()->status);
    }

    public function test_system_admin_can_quick_update_any_ticket_in_their_branch(): void
    {
        $sa = $this->user(['role' => 'super_admin']);
        $it = $this->user(['role' => 'it']);
        $requestor = $this->user(['office' => 'FINANCIAL AND MANAGEMENT DIVISION']);
        $ticket = $this->ictTicket($requestor, 'REQ-NCR-RCMB-2026-9013', 'Approved', ['assigned_to' => $it->id]);

        $this->actingAs($sa)
            ->postJson(route('admin.requests.update-status'), ['id' => $ticket->id, 'status' => 'Ongoing'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('Ongoing', $ticket->fresh()->status);
    }

    public function test_new_ict_notification_never_goes_back_to_the_requestor(): void
    {
        $sa = $this->user(['role' => 'super_admin']);
        $ticket = $this->ictTicket($sa, 'REQ-NCR-RCMB-2026-9009', 'Approved');

        \App\Services\RequestNotificationService::notifySystemAdminOfNewIctRequest($ticket, $sa);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $sa->id,
            'request_id' => $ticket->id,
            'type' => 'New ICT Repair for Review',
        ]);
    }
}
