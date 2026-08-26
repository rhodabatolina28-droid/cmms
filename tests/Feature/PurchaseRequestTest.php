<?php

namespace Tests\Feature;

use App\Actions\PurchaseRequest\CreatePurchaseRequestAction;
use App\Models\Part;
use App\Models\PurchaseRequest;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PurchaseRequestTest extends TestCase
{
    use RefreshDatabase;

    private $counter = 0;

    private function makeUser(array $attrs = [])
    {
        $this->counter++;
        return User::create(array_merge([
            'full_name' => 'PR Test User ' . $this->counter,
            'email' => 'prt' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'can_supply' => false,
            'region' => 'NCR',
        ], $attrs));
    }

    /** Create a PR document via the new form action (status: submitted). */
    private function makePr(User $creator, array $overrides = []): PurchaseRequest
    {
        Auth::login($creator);

        return (new CreatePurchaseRequestAction)->createFromForm($creator, array_merge([
            'items' => [
                ['description' => 'SSD 512GB SATA', 'quantity' => 2, 'unit_cost' => 2750.00],
            ],
            'purpose' => 'Replacement part for failing drive.',
        ], $overrides));
    }

    public function test_prefill_from_requisition_returns_deficit_lines(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $part = Part::create([
            'item_name' => 'RAM 16GB DDR4',
            'unit' => 'pcs',
            'on_hand_qty' => 1,
            'reorder_level' => 5,
        ]);
        $ticket = \App\Models\Request::create([
            'user_id' => $it->id,
            'assigned_to' => $it->id,
            'request_number' => 'REQ-NCR-PREFILL-1',
            'type' => 'ICT',
            'requestor_name' => 'End User',
            'description' => 'Test repair',
            'status' => \App\Models\Request::STATUS_ONGOING,
            'region' => 'NCR',
        ]);
        $requisition = Requisition::create([
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_APPROVED,
            'items' => [[
                'description' => 'RAM 16GB DDR4',
                'quantity' => 5,
                'source' => 'parts-stock',
                'part_id' => $part->id,
            ]],
        ]);

        $prefill = (new CreatePurchaseRequestAction)->prefillFromRequisition($requisition);

        $this->assertCount(1, $prefill['items']);
        $this->assertSame('RAM 16GB DDR4', $prefill['items'][0]['description']);
        $this->assertSame(4, $prefill['items'][0]['quantity']); // 5 requested - 1 on hand
        $this->assertSame($part->id, $prefill['items'][0]['part_id']);
    }

    public function test_it_user_creates_pr_via_form_and_it_lands_submitted(): void
    {
        $it = $this->makeUser(['role' => 'it']);

        $response = $this->actingAs($it)->post(route('purchase_requests.store'), [
            'items' => [
                ['description' => 'Manual thermal sensor', 'quantity' => 1, 'unit_cost' => 850.50],
            ],
            'purpose' => 'Urgent sensor replacement.',
        ]);

        $response->assertRedirect();

        $pr = PurchaseRequest::first();
        $this->assertNotNull($pr);
        $this->assertSame(PurchaseRequest::STATUS_SUBMITTED, $pr->status);
        $this->assertSame($it->id, $pr->created_by);
        $this->assertSame($it->id, $pr->requested_by);
        $this->assertEquals(850.50, (float) $pr->total_amount);
        $this->assertMatchesRegularExpression('/^PR-\d{4}-\d{4}$/', $pr->pr_number);
    }

    public function test_blank_padding_rows_are_ignored_when_storing_pr(): void
    {
        $it = $this->makeUser(['role' => 'it']);

        // Simulates the A60 sheet: one real item + blank padding rows
        // (empty descriptions and/or untouched default quantities).
        $response = $this->actingAs($it)->post(route('purchase_requests.store'), [
            'items' => [
                ['description' => '', 'quantity' => '', 'unit_cost' => ''],
                ['description' => 'Only real item', 'quantity' => 2, 'unit_cost' => 100],
                ['description' => null, 'quantity' => '1', 'unit_cost' => null],
                ['description' => '', 'quantity' => '', 'unit_cost' => ''],
            ],
        ]);

        $response->assertRedirect(); // no validation errors

        $pr = PurchaseRequest::first();
        $this->assertNotNull($pr);
        $this->assertCount(1, $pr->items);
        $this->assertSame('Only real item', $pr->items[0]['description']);
        $this->assertSame(2, (int) $pr->items[0]['quantity']);
        $this->assertEquals(200.00, (float) $pr->total_amount);
    }

    public function test_supply_finalizes_a_submitted_pr(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $supply = $this->makeUser(['role' => 'supply_officer']);

        $pr = $this->makePr($it);

        $response = $this->actingAs($supply)->post(
            route('purchase_requests.finalize', $pr)
        );

        $response->assertRedirect();
        $pr->refresh();
        $this->assertSame(PurchaseRequest::STATUS_FINALIZED, $pr->status);
        $this->assertSame($supply->id, $pr->finalized_by);
        $this->assertNotNull($pr->finalized_at);
    }

    public function test_supply_edits_submitted_pr_items_and_total(): void
    {
        $supply = $this->makeUser(['role' => 'supply_officer']);
        $it = $this->makeUser(['role' => 'it']);
        $pr = $this->makePr($it);

        // Edit form renders for Supply Officer
        $this->actingAs($supply)
            ->get(route('purchase_requests.edit', $pr))
            ->assertOk()
            ->assertSee($pr->pr_number);

        // Save corrections (with a blank padding row like the real form sends)
        $response = $this->actingAs($supply)->post(route('purchase_requests.update', $pr), [
            'items' => [
                ['description' => '', 'quantity' => '', 'unit_cost' => ''],
                ['description' => 'Corrected GPU', 'quantity' => 3, 'unit_cost' => 20000],
            ],
            'purpose' => 'Corrected specs.',
            'fund_cluster' => '101',
        ]);

        $response->assertRedirect();
        $pr->refresh();
        $this->assertSame(PurchaseRequest::STATUS_SUBMITTED, $pr->status);
        $this->assertCount(1, $pr->items);
        $this->assertSame('Corrected GPU', $pr->items[0]['description']);
        $this->assertEquals(60000.00, (float) $pr->total_amount);
        $this->assertSame('101', $pr->fund_cluster);

        // IT owner may also open the edit form while submitted
        $this->actingAs($it)
            ->get(route('purchase_requests.edit', $pr))
            ->assertOk();
    }

    public function test_finalized_pr_is_locked_from_editing(): void
    {
        $supply = $this->makeUser(['role' => 'supply_officer']);
        $it = $this->makeUser(['role' => 'it']);
        $pr = $this->makePr($it);
        $pr->update(['status' => PurchaseRequest::STATUS_FINALIZED, 'finalized_by' => $supply->id]);

        $this->actingAs($it)->get(route('purchase_requests.edit', $pr))->assertForbidden();
        $this->actingAs($supply)->post(route('purchase_requests.update', $pr), [
            'items' => [['description' => 'X', 'quantity' => 1]],
        ])->assertForbidden();
    }

    public function test_it_cannot_edit_someone_elses_pr(): void
    {
        $itA = $this->makeUser(['role' => 'it']);
        $itB = $this->makeUser(['role' => 'it']);
        $pr = $this->makePr($itB);

        $this->actingAs($itA)
            ->get(route('purchase_requests.edit', $pr))
            ->assertForbidden();
    }

    public function test_regular_user_cannot_create_or_finalize(): void
    {
        $regular = $this->makeUser(['role' => 'user']);
        $it = $this->makeUser(['role' => 'it']);
        $pr = $this->makePr($it);

        $this->actingAs($regular)->get(route('purchase_requests.create'))->assertForbidden();
        $this->actingAs($regular)->post(route('purchase_requests.store'), [
            'items' => [['description' => 'X', 'quantity' => 1]],
        ])->assertForbidden();
        $this->actingAs($regular)->post(route('purchase_requests.finalize', $pr))->assertForbidden();

        $pr->refresh();
        $this->assertSame(PurchaseRequest::STATUS_SUBMITTED, $pr->status);
    }

    public function test_supply_workspace_tab_shows_standalone_and_legacy_prs(): void
    {
        $supply = $this->makeUser(['role' => 'supply_officer']);
        $it = $this->makeUser(['role' => 'it']);

        // Standalone submitted PR (created by IT, same region).
        $pr = $this->makePr($it);

        // Legacy record from the old workflow.
        PurchaseRequest::create([
            'pr_number' => 'PR-2025-0001',
            'status' => 'received',
            'items' => [['description' => 'HDD 1TB', 'quantity' => 1]],
            'requested_by' => $supply->id,
        ]);

        $response = $this->actingAs($supply)
            ->get(route('requisitions.index', ['view' => 'purchase-requests']));

        $response->assertOk();
        $response->assertSee($pr->pr_number);
        // Legacy marker was removed from the UI — records render with plain statuses.
        $response->assertDontSee('(legacy)');
    }

    public function test_it_sees_only_own_prs_in_purchase_requests_tab(): void
    {
        // The requisitions page has a "Purchase Requests" tab listing the
        // signed-in IT user's OWN PR documents — never another user's.
        $itA = $this->makeUser(['role' => 'it']);
        $itB = $this->makeUser(['role' => 'it']);

        $own = $this->makePr($itA);
        $other = $this->makePr($itB);

        $response = $this->actingAs($itA)->get(route('requisitions.index', ['tab' => 'myprs']));
        $response->assertOk();
        $response->assertSee($own->pr_number);
        $response->assertDontSee($other->pr_number);
    }
}
