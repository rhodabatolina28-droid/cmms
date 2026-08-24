<?php

namespace Tests\Feature;

use App\Models\Requisition;
use App\Models\Request as Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisitionReviewValidationTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function makeUser(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'Review Validation User ' . $this->counter,
            'email' => 'review-validation-' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'can_supply' => false,
            'region' => 'NCR',
            'branch' => 'RCMB',
        ], $attributes));
    }

    private function makePendingRequisition(): Requisition
    {
        $it = $this->makeUser(['role' => 'it']);
        $ticket = Ticket::create([
            'user_id' => $this->makeUser()->id,
            'assigned_to' => $it->id,
            'request_number' => 'JO-RV-2026-' . str_pad((string) (++$this->counter), 4, '0', STR_PAD_LEFT),
            'type' => 'ICT',
            'requestor_name' => 'Review Requestor',
            'description' => 'Validation test repair',
            'status' => Ticket::STATUS_AWAITING_PARTS,
            'region' => 'NCR',
        ]);

        return Requisition::create([
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_PENDING,
            'items' => [['description' => 'Generic item', 'quantity' => 1]],
        ]);
    }

    public function test_reject_without_reason_is_rejected_by_validation(): void
    {
        $supply = $this->makeUser(['role' => 'admin', 'can_supply' => true]);
        $requisition = $this->makePendingRequisition();

        $this->actingAs($supply)
            ->postJson(route('requisitions.review', $requisition->id), [
                'action' => 'reject',
                'remarks' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('remarks');

        $this->assertSame(Requisition::STATUS_PENDING, $requisition->fresh()->status);
    }

    public function test_reject_with_reason_records_disapproval(): void
    {
        $supply = $this->makeUser(['role' => 'admin', 'can_supply' => true]);
        $requisition = $this->makePendingRequisition();

        $response = $this->actingAs($supply)
            ->postJson(route('requisitions.review', $requisition->id), [
                'action' => 'reject',
                'remarks' => 'Item no longer needed; ticket was cancelled.',
            ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(Requisition::STATUS_REJECTED, $requisition->fresh()->status);
        $this->assertSame('Item no longer needed; ticket was cancelled.', $requisition->fresh()->remarks);
    }
}
