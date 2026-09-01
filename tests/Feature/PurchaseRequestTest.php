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


    /** Phase A: PR raised from a requisition silently inherits its job order ticket. */
    public function test_pr_created_from_requisition_inherits_job_order_link(): void
    {
        $it = $this->makeUser(['role' => 'it']);

        $ticket = \App\Models\Request::create([
            'user_id' => $it->id,
            'assigned_to' => $it->id,
            'request_number' => 'REQ-NCR-JOLINK-1',
            'type' => 'ICT',
            'requestor_name' => 'End User',
            'description' => 'Test repair for linkage',
            'status' => \App\Models\Request::STATUS_ONGOING,
            'region' => 'NCR',
        ]);

        $requisition = Requisition::create([
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_APPROVED,
            'items' => [
                ['description' => 'RX 6700 XT AMD', 'quantity' => 1, 'source' => 'manual'],
            ],
        ]);

        $pr = $this->makePr($it, ['requisition_id' => $requisition->id]);

        $this->assertSame($ticket->id, $pr->request_id);
        $this->assertSame($ticket->id, $pr->request?->id); // relation resolves to same ticket
    }

    public function test_receiving_pr_auto_issues_linked_requisition(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $supply = $this->makeUser(['role' => 'supply_officer', 'can_supply' => true]);

        $ticket = \App\Models\Request::create([
            'user_id' => $it->id,
            'assigned_to' => $it->id,
            'request_number' => 'REQ-NCR-AUTOISSUE-1',
            'type' => 'ICT',
            'requestor_name' => 'End User',
            'description' => 'Parts will arrive via PR',
            'status' => \App\Models\Request::STATUS_ONGOING,
            'region' => 'NCR',
        ]);

        $requisition = Requisition::create([
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_APPROVED,
            'items' => [
                ['description' => 'SSD 512GB', 'quantity' => 1, 'source' => 'parts-stock', 'part_id' => $this->makePart()->id],
            ],
        ]);

        $pr = $this->makeFinalizedPr($it, 45000.00);
        $pr->update(['requisition_id' => $requisition->id, 'request_id' => $ticket->id]);

        $this->actingAs($supply);

        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        $result = $action->execute($pr->fresh(), $supply, []);

        $this->assertTrue($result['success'], $result['message'] ?? '');

        // The linked requisition must now show as issued in the Supply
        // Officer → Requisition Review queue (previously stayed pending/approved).
        $this->assertSame(Requisition::STATUS_ISSUED, $requisition->fresh()->status);
        $this->assertSame($supply->id, $requisition->fresh()->reviewed_by);

        // The IT requester is notified that parts are now issued.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $it->id,
            'type' => 'Parts Request — Issued',
        ]);
    }

    /** Phase A: manual PRs (no requisition) stay unlinked - null request_id. */
    public function test_manual_pr_without_requisition_has_no_job_order_link(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $pr = $this->makePr($it);

        $this->assertNull($pr->request_id);
        $this->assertNull($pr->request?->id);
    }

    /** Phase B helper: minimal inventory asset inside the supply scope (NCR). */
    private function makeAsset(string $suffix): \App\Models\InventoryAsset
    {
        return \App\Models\InventoryAsset::create([
            'category' => 'Desktop',
            'item_name' => 'Workstation ' . $suffix,
            'serial_number' => 'SN-' . $suffix,
            'property_number' => 'PN-' . $suffix,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
            'status' => 'Active',
        ]);
    }

    /** Phase B: a PR raised against an asset's job order shows on that asset's profile. */
    public function test_pr_appears_on_linked_asset_profile(): void
    {
        $supply = $this->makeUser(['role' => 'supply_officer', 'can_supply' => true]);
        $it = $this->makeUser(['role' => 'it']);

        $asset = $this->makeAsset('PRVIS1');

        $ticket = \App\Models\Request::create([
            'user_id' => $it->id,
            'assigned_to' => $it->id,
            'request_number' => 'REQ-NCR-PRVIS-1',
            'type' => 'ICT',
            'requestor_name' => 'End User',
            'description' => 'Repair job for visibility test',
            'status' => \App\Models\Request::STATUS_ONGOING,
            'region' => 'NCR',
            'linked_asset_id' => $asset->asset_id,
        ]);

        $requisition = Requisition::create([
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_APPROVED,
            'items' => [
                ['description' => 'RX 6700 XT AMD', 'quantity' => 1, 'source' => 'manual'],
            ],
        ]);

        $pr = $this->makePr($it, ['requisition_id' => $requisition->id]);

        $response = $this->actingAs($supply)->get(route('inventory.detail', $asset->asset_id));
        $response->assertOk();
        $response->assertSee($pr->pr_number);      // PR document link surfaces on the asset card
        $response->assertSee('SSD 512GB SATA');     // item summary visible
        $response->assertSee('Requested via Purchase Request');
    }

    /** Phase B: the same PR must NOT surface on unrelated assets. */
    public function test_pr_does_not_appear_on_unrelated_asset_profile(): void
    {
        $supply = $this->makeUser(['role' => 'supply_officer', 'can_supply' => true]);
        $it = $this->makeUser(['role' => 'it']);

        $assetA = $this->makeAsset('PRVISA');
        $assetB = $this->makeAsset('PRVISB');

        $ticket = \App\Models\Request::create([
            'user_id' => $it->id,
            'assigned_to' => $it->id,
            'request_number' => 'REQ-NCR-PRVIS-2',
            'type' => 'ICT',
            'requestor_name' => 'End User',
            'description' => 'Repair job scoped to asset A',
            'status' => \App\Models\Request::STATUS_ONGOING,
            'region' => 'NCR',
            'linked_asset_id' => $assetA->asset_id,
        ]);

        $requisition = Requisition::create([
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_APPROVED,
            'items' => [
                ['description' => 'RX 6700 XT AMD', 'quantity' => 1, 'source' => 'manual'],
            ],
        ]);

        $pr = $this->makePr($it, ['requisition_id' => $requisition->id]);

        // Asset A shows it...
        $this->actingAs($supply)->get(route('inventory.detail', $assetA->asset_id))
            ->assertOk()
            ->assertSee($pr->pr_number);

        // ...asset B does not.
        $this->get(route('inventory.detail', $assetB->asset_id))
            ->assertOk()
            ->assertDontSee($pr->pr_number);
    }

    // ------------------------------------------------------------------
    // Phase C4 - Receive authorization (₱10k threshold rule)
    // ------------------------------------------------------------------

    private function makeFinalizedPr(User $creator, float $total): PurchaseRequest
    {
        $pr = $this->makePr($creator, [
            'items' => [
                ['description' => 'RX 6700 XT AMD', 'quantity' => 1, 'unit_cost' => $total],
            ],
        ]);
        $supply = $this->makeUser(['role' => 'supply_officer', 'can_supply' => true]);
        (new \App\Actions\PurchaseRequest\FinalizePurchaseRequestAction)->execute($pr, $supply);
        $pr = $pr->fresh();

        // Default C6 receipt so threshold tests isolate the auth rule, not
        // the receipt gate (the gate has its own dedicated tests).
        if ($total < 10000) {
            \App\Models\PrAttachment::create([
                'purchase_request_id' => $pr->id,
                'filename'            => 'receipt.pdf',
                'filepath'            => "pr-attachments/{$pr->id}/receipt-test.pdf",
                'filetype'            => 'application/pdf',
                'label'               => 'Official receipt',
                'uploaded_by'         => $creator->id,
            ]);
        }

        return $pr;
    }

    public function test_below_threshold_owner_can_receive_finalized_pr(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        // ₱2,750 < ₱10k → fast track.
        $pr = $this->makeFinalizedPr($it, 2750.00);

        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        $result = $action->execute($pr, $it);

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertSame(PurchaseRequest::STATUS_DELIVERED, $pr->fresh()->status);
        $this->assertSame($it->id, $pr->fresh()->delivered_by);
        $this->assertNotNull($pr->fresh()->delivered_at);
    }

    public function test_at_or_above_threshold_owner_cannot_receive(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        // ₱17,999 ≥ ₱10k → Procurement track, Supply Officer only.
        $pr = $this->makeFinalizedPr($it, 17999.96);

        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        $result = $action->execute($pr, $it);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Supply Officer', $result['message']);
        $this->assertSame(PurchaseRequest::STATUS_FINALIZED, $pr->fresh()->status);
    }

    public function test_non_owner_it_cannot_receive_even_small_pr(): void
    {
        $owner = $this->makeUser(['role' => 'it']);
        $otherIt = $this->makeUser(['role' => 'it']);
        $pr = $this->makeFinalizedPr($owner, 2750.00);

        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        $result = $action->execute($pr, $otherIt);

        $this->assertFalse($result['success']);
        $this->assertSame(PurchaseRequest::STATUS_FINALIZED, $pr->fresh()->status);
    }

    public function test_receive_requires_finalized_status(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $pr = $this->makePr($it); // still submitted

        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        $result = $action->execute($pr, $it);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('finalized', $result['message']);
        $this->assertSame(PurchaseRequest::STATUS_SUBMITTED, $pr->fresh()->status);
    }

    public function test_supply_receives_large_pr(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $supply = $this->makeUser(['role' => 'supply_officer', 'can_supply' => true]);
        // ₱45,000 ≥ ₱10k → only Supply can receive.
        $pr = $this->makeFinalizedPr($it, 45000.00);

        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        $result = $action->execute($pr, $supply);

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertSame(PurchaseRequest::STATUS_DELIVERED, $pr->fresh()->status);
        $this->assertSame($supply->id, $pr->fresh()->delivered_by);
    }

    public function test_received_pr_cannot_be_received_again(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $pr = $this->makeFinalizedPr($it, 2750.00);

        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        $first = $action->execute($pr, $it);
        $this->assertTrue($first['success']);

        // Second attempt: status is now delivered, no longer finalized.
        $second = $action->execute($pr->fresh(), $it);
        $this->assertFalse($second['success']);
    }

    public function test_small_pr_receive_blocked_without_receipt(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $pr = $this->makeFinalizedPr($it, 2750.00);
        $pr->attachments()->delete(); // strip the default receipt

        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        $result = $action->execute($pr, $it);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('receipt', strtolower($result['message']));
        $this->assertSame(PurchaseRequest::STATUS_FINALIZED, $pr->fresh()->status);
    }

    public function test_large_pr_receive_does_not_require_receipt(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $supply = $this->makeUser(['role' => 'supply_officer', 'can_supply' => true]);
        // ≥10k: receipt optional (procurement keeps paper trail outside).
        $pr = $this->makeFinalizedPr($it, 45000.00);
        $pr->attachments()->delete();

        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        $result = $action->execute($pr, $supply);

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertSame(PurchaseRequest::STATUS_DELIVERED, $pr->fresh()->status);
    }

    public function test_receipt_cannot_be_uploaded_after_delivery(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $pr = $this->makeFinalizedPr($it, 2750.00);

        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        $action->execute($pr, $it);
        $pr = $pr->fresh();

        $uploader = new \App\Actions\PurchaseRequest\UploadPrAttachmentAction;
        $this->assertFalse($uploader->canUpload($pr->fresh(), $it));
    }

    // ------------------------------------------------------------------
    // Phase C5 - per-line destination handling (stock-in / direct-asset)
    // ------------------------------------------------------------------

    private function makePart(array $attrs = []): Part
    {
        return Part::create(array_merge([
            'item_name' => 'RX 6700 XT AMD',
            'unit' => 'pcs',
            'on_hand_qty' => 0,
            'reorder_level' => 0,
            'requires_unit_tracking' => false,
            'region' => 'NCR',
        ], $attrs));
    }

    public function test_stock_in_path_increments_on_hand_and_records_movement(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $pr = $this->makeFinalizedPr($it, 2750.00);
        $part = $this->makePart(['item_name' => 'SSD 512GB SATA']);

        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        $result = $action->execute($pr->fresh(), $it, [[
            'part_id' => $part->id,
            'destination' => 'stock-in',
            'units' => [],
        ]]);

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $part->refresh();
        $this->assertSame(1, (int) $part->on_hand_qty);

        $movement = \App\Models\PartMovement::where('reference_type', 'purchase_request')
            ->where('reference_id', $pr->id)
            ->first();
        $this->assertNotNull($movement);
        $this->assertSame(1, (int) $movement->qty_change);
    }

    public function test_direct_to_asset_creates_issued_units_visible_on_asset_card(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $asset = $this->makeAsset('PRCVDIR');

        $ticket = \App\Models\Request::create([
            'user_id' => $it->id,
            'assigned_to' => $it->id,
            'request_number' => 'REQ-NCR-PRCVDIR-1',
            'type' => 'ICT',
            'requestor_name' => 'Custodian User',
            'description' => 'GPU replacement job',
            'status' => \App\Models\Request::STATUS_ONGOING,
            'region' => 'NCR',
            'linked_asset_id' => $asset->asset_id,
        ]);

        $pr = $this->makePr($it);
        $pr->update(['request_id' => $ticket->id]);
        $supply = $this->makeUser(['role' => 'supply_officer', 'can_supply' => true]);
        (new \App\Actions\PurchaseRequest\FinalizePurchaseRequestAction)->execute($pr, $supply);
        \App\Models\PrAttachment::create([
            'purchase_request_id' => $pr->id,
            'filename' => 'receipt.pdf',
            'filepath' => "pr-attachments/{$pr->id}/r.pdf",
            'filetype' => 'application/pdf',
            'uploaded_by' => $it->id,
        ]);
        $custodianId = $asset->assigned_to_user;

        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        $result = $action->execute($pr->fresh(), $it, [[
            'part_id' => $this->makePart()->id,
            'destination' => 'direct-asset',
            'units' => [
                ['serial_number' => 'SN-DIR-001', 'property_number' => 'PN-DIR-001'],
            ],
        ]]);

        $this->assertTrue($result['success'], $result['message'] ?? '');

        $unit = \App\Models\PartUnit::where('serial_number', 'SN-DIR-001')->first();
        $this->assertNotNull($unit);
        $this->assertSame('issued', $unit->status);
        $this->assertSame((int) $asset->asset_id, (int) $unit->asset_id);
        if ($custodianId) {
            $this->assertSame((int) $custodianId, (int) $unit->issued_to);
        }

        // Phase 4: the asset's Lifecycle History records the install.
        $history = \App\Models\InventoryHistory::where('asset_id', $asset->asset_id)
            ->where('action', 'Part Installed')
            ->first();
        $this->assertNotNull($history);
        $this->assertStringContainsString('SN-DIR-001', (string) $history->remarks);
        $this->assertStringContainsString($pr->pr_number, (string) $history->remarks);

        // Phase 5: the PR unit cost flows into the unit's value, and a
        // parts movement entry exists so the History modal is not empty.
        $this->assertNotNull($unit->unit_value);
        $this->assertSame(2750.0, (float) $unit->unit_value);
        $movement = \App\Models\PartMovement::where('part_id', $unit->part_id)
            ->where('reference_type', 'purchase_request')
            ->where('reference_id', $pr->id)
            ->first();
        $this->assertNotNull($movement);
        $this->assertSame(0, (int) $movement->qty_change); // never entered stock
    }

    public function test_tracked_part_requires_serial_per_quantity(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $pr = $this->makeFinalizedPr($it, 2750.00);
        $tracked = $this->makePart(['requires_unit_tracking' => true]);

        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        $result = $action->execute($pr->fresh(), $it, [[
            'part_id' => $tracked->id,
            'destination' => 'stock-in',
            'units' => [
                ['serial_number' => 'SN-A', 'property_number' => 'PN-A'],
            ], // qty is 1 but the tracked rule needs exactly 1 - ok, so remove to force block
        ]]);

        // qty 1 with 1 unit passes; verify success then test under-supply separately.
        $this->assertTrue($result['success'], $result['message'] ?? '');

        $pr2 = $this->makeFinalizedPr($it, 3000.00);
        $result2 = $action->execute($pr2->fresh(), $it, [[
            'part_id' => $tracked->id,
            'destination' => 'stock-in',
            'units' => [],
        ]]);
        $this->assertFalse($result2['success']);
        $this->assertStringContainsString('serial', strtolower($result2['message']));
        $this->assertSame(PurchaseRequest::STATUS_FINALIZED, $pr2->fresh()->status);
    }

    public function test_duplicate_serial_against_existing_stock_rejected(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $pr = $this->makeFinalizedPr($it, 2750.00);
        $tracked = $this->makePart(['requires_unit_tracking' => true]);
        \App\Models\PartUnit::create([
            'part_id' => $tracked->id,
            'serial_number' => 'SN-DUP',
            'property_number' => 'PN-OLD',
            'status' => 'in_stock',
        ]);

        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        $result = $action->execute($pr->fresh(), $it, [[
            'part_id' => $tracked->id,
            'destination' => 'stock-in',
            'units' => [
                ['serial_number' => 'SN-DUP', 'property_number' => 'PN-N1'],
            ],
        ]]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already exists', $result['message']);
        // Nothing half-applied: status unchanged, on-hand untouched.
        $this->assertSame(0, (int) $tracked->fresh()->on_hand_qty);
        $this->assertSame(PurchaseRequest::STATUS_FINALIZED, $pr->fresh()->status);
    }

    public function test_full_receive_via_http_records_audit_log(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $pr = $this->makeFinalizedPr($it, 2750.00);
        $part = $this->makePart();

        $response = $this->actingAs($it)
            ->post(route('purchase_requests.receive', $pr), [
                'lines' => [
                    ['part_id' => $part->id, 'destination' => 'stock-in', 'units' => []],
                ],
            ]);

        $response->assertRedirect();
        $pr->refresh();
        $this->assertSame(PurchaseRequest::STATUS_DELIVERED, $pr->status);

        $log = \App\Models\AuditLog::where('action', 'Received Purchase Request')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString($pr->pr_number, (string) $log->details);
    }

    // ------------------------------------------------------------------
    // Create-new-part on the fly (not in Parts list)
    // ------------------------------------------------------------------

    public function test_receive_creates_new_part_when_not_in_parts_list(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $pr = $this->makeFinalizedPr($it, 2750.00);

        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        $result = $action->execute($pr->fresh(), $it, [[
            'part_id' => 'new',
            'new_part_name' => 'RTX 4060 Ti Special',
            'new_part_unit' => 'pcs',
            'destination' => 'stock-in',
            'units' => [
                ['serial_number' => 'SN-NEW-1', 'property_number' => 'PN-NEW-1'],
            ],
        ]]);

        $this->assertTrue($result['success'], $result['message'] ?? '');

        $part = \App\Models\Part::where('item_name', 'RTX 4060 Ti Special')->first();
        $this->assertNotNull($part);
        $this->assertSame(1, (int) $part->on_hand_qty);

        $unit = \App\Models\PartUnit::where('part_id', $part->id)->first();
        $this->assertNotNull($unit);
        $this->assertSame('SN-NEW-1', $unit->serial_number);

        // Audit trail records the on-the-fly creation.
        $createdLog = \App\Models\AuditLog::where('action', 'Part Created During Receiving')->first();
        $this->assertNotNull($createdLog);
    }

    public function test_create_new_part_blocked_on_duplicate_name(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $pr = $this->makeFinalizedPr($it, 2750.00);
        $existing = $this->makePart(['item_name' => 'RX 6700 XT AMD']);

        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        $result = $action->execute($pr->fresh(), $it, [[
            'part_id' => 'new',
            'new_part_name' => 'rx 6700 xt amd', // case-insensitive duplicate
            'new_part_unit' => 'pcs',
            'destination' => 'stock-in',
            'units' => [],
        ]]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already exists', $result['message']);
        $this->assertSame(PurchaseRequest::STATUS_FINALIZED, $pr->fresh()->status);
        // No accidental second record.
        $this->assertSame(1, \App\Models\Part::whereRaw('LOWER(item_name) = ?', ['rx 6700 xt amd'])->count());
    }

    public function test_created_serialized_new_part_requires_serial(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $pr = $this->makeFinalizedPr($it, 2750.00);

        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        // New parts are always accountable: no serial supplied -> blocked.
        $result = $action->execute($pr->fresh(), $it, [[
            'part_id' => 'new',
            'new_part_name' => 'Brand New Board',
            'new_part_unit' => 'pcs',
            'destination' => 'stock-in',
            'units' => [], // missing serial/property
        ]]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('serial', strtolower($result['message']));
    }

    public function test_create_form_prefills_from_part_id(): void
    {
        $supply = $this->makeUser(['role' => 'supply_officer']);
        $part = Part::create([
            'item_name' => 'RX 6700 XT AMD',
            'unit' => 'pcs',
            'on_hand_qty' => 0,
            'reorder_level' => 2,
        ]);
        \App\Models\PartUnit::create([
            'part_id' => $part->id,
            'serial_number' => 'RX-0001',
            'property_number' => 'NONE-1',
            'unit_value' => 17999.96,
            'status' => 'issued',
        ]);

        $response = $this->actingAs($supply)->get(route('purchase_requests.create', ['part_id' => $part->id]));

        $response->assertOk();
        // The page embeds the prefill items as JSON supplied to the JS row builder.
        $body = $response->getContent();
        $this->assertStringContainsString('RX 6700 XT AMD', $body);
        $this->assertStringContainsString('17999.96', $body);
        $this->assertStringContainsString('"part_id":' . $part->id, $body);
        // Suggested qty = reorder (2) - on_hand (0) = 2
        $json = json_decode(substr($body, strpos($body, 'const prefillItems')), true);
        // Extract the blade @json literal to confirm quantity.
        $this->assertMatchesRegularExpression('/"quantity":2/', $body);
    }

    public function test_part_id_lands_in_stored_pr_items(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $part = Part::create([
            'item_name' => 'SSD 1TB NVMe',
            'unit' => 'pcs',
            'on_hand_qty' => 0,
        ]);

        $pr = $this->makePr($it, [
            'items' => [[
                'description' => 'SSD 1TB NVMe',
                'quantity' => 1,
                'unit_cost' => 3500.00,
                'part_id' => $part->id,
            ]],
        ]);

        $stored = $pr->refresh()->items;
        $this->assertSame((int) $stored[0]['part_id'], (int) $part->id);
    }

    public function test_supply_officer_can_store_pr_with_catalog_part_id(): void
    {
        // Regression: the store() validation used `exists:parts,id`, but the
        // parts catalog lives in `parts_stock`. Submitting a PR line with a
        // part_id therefore threw a QueryException (missing `parts` table) -> 500.
        $supply = $this->makeUser(['role' => 'supply_officer', 'can_supply' => true, 'region' => 'NCR']);
        $part = Part::create([
            'item_name' => 'SSD 512GB SATA',
            'unit' => 'pcs',
            'on_hand_qty' => 0,
        ]);

        $response = $this->actingAs($supply)->post(route('purchase_requests.store'), [
            'items' => [[
                'description' => 'SSD 512GB SATA',
                'quantity' => 2,
                'unit_cost' => 3500.00,
                'part_id' => $part->id,
            ]],
            'purpose' => 'Ongoing GPU replacement.',
        ]);

        // Must NOT be a 500; should redirect with a success flash.
        $response->assertRedirect();

        $pr = PurchaseRequest::first();
        $this->assertNotNull($pr);
        $this->assertEquals(2, (int) $pr->items[0]['quantity']);
        $this->assertSame((int) $part->id, (int) $pr->items[0]['part_id']);
    }

    public function test_submit_notifies_supply_users_excluding_creator(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $supply = $this->makeUser(['role' => 'supply_officer', 'region' => 'NCR', 'branch' => 'RCMB', 'full_name' => 'Supply One']);
        $otherSupply = $this->makeUser(['role' => 'supply_officer', 'region' => 'NCR', 'branch' => 'RCMB', 'full_name' => 'Supply Two']);

        // IT outside NCR should not be notified as supply.
        $this->makeUser(['role' => 'supply_officer', 'region' => 'Region IV-A', 'full_name' => 'Other Supply']);

        $pr = $this->makePr($it);

        $rows = \App\Models\Notification::where('user_id', $supply->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('PR Submitted', $rows->first()->type);
        $this->assertStringContainsString($pr->pr_number, $rows->first()->message);

        // The other same-region supply user is notified too.
        $this->assertCount(1, \App\Models\Notification::where('user_id', $otherSupply->id)->get());
        // The creator (IT) gets no PR-submitted notification.
        $this->assertCount(0, \App\Models\Notification::where('user_id', $it->id)->where('type', 'PR Submitted')->get());
    }

    public function test_finalize_notifies_requester(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $supply = $this->makeUser(['role' => 'supply_officer']);
        $pr = $this->makePr($it);

        (new \App\Actions\PurchaseRequest\FinalizePurchaseRequestAction)->execute($pr->fresh(), $supply);

        $rows = \App\Models\Notification::where('user_id', $it->id)->where('type', 'PR Finalized')->get();
        $this->assertCount(1, $rows);
        $this->assertStringContainsString($pr->pr_number, $rows->first()->message);
    }

    public function test_receive_form_marks_serialized_parts_for_the_units_grid(): void
    {
        // Regression: the Record Delivery page used to show the serial/property
        // grid only for direct-to-asset, so "Add to inventory" submissions for
        // tracked parts failed backend validation ("needs one serial + property
        // number per quantity"). Each part option now carries data-tracked and
        // the grid shows for any destination when the part is serialized.
        $it = $this->makeUser(['role' => 'it']);
        $pr = $this->makeFinalizedPr($it, 2750.00);
        Part::create([
            'item_name' => 'SSD 1TB M.2 NVMe',
            'unit' => 'pcs',
            'on_hand_qty' => 0,
            'requires_unit_tracking' => true,
        ]);

        $response = $this->actingAs($it)->get(route('purchase_requests.receiveForm', $pr));

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringContainsString('data-tracked="1"', $body);
        $this->assertStringContainsString('SSD 1TB M.2 NVMe', $body);
        // Grid visibility is driven by part tracking, not destination.
        $this->assertStringContainsString('rxSyncUnits', $body);
        $this->assertStringNotContainsString("sel.value === 'direct-asset'", $body);
    }

    public function test_supply_sees_own_created_and_pm_origin_prs(): void
    {
        // Regression: the Supply Workspace PURCHASE REQUESTS tab hid (a) PRs
        // created by the supply officer themselves and (b) PRs raised from PM
        // tickets, because auto-generated PM tickets historically carry no
        // region (and sometimes no branch) so the ticket-based org filter
        // excluded them. Supply runs the procurement desk: no narrowing.
        $supply = $this->makeUser(['role' => 'supply_officer', 'can_supply' => true, 'region' => 'NCR', 'branch' => 'RCMB']);
        $it = $this->makeUser(['role' => 'it', 'region' => 'NCR']);

        $ticket = \App\Models\Request::create([
            'user_id' => $it->id,
            'assigned_to' => $it->id,
            'request_number' => 'PM-NCR-NULLREG-1',
            'type' => 'Preventive Maintenance',
            'requestor_name' => 'PM End User',
            'description' => 'Bundled PM',
            'status' => \App\Models\Request::STATUS_ONGOING,
            'region' => null,
            'branch' => null,
            'is_auto_generated' => true,
        ]);
        $requisition = Requisition::create([
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_APPROVED,
            'items' => [['description' => 'SSD', 'quantity' => 1, 'source' => 'manual']],
        ]);

        $ownPr = $this->makePr($supply); // standalone, created by supply
        $pmPr = $this->makePr($it, ['requisition_id' => $requisition->id]); // PM-origin

        $data = (new \App\Actions\PurchaseRequest\ListPurchaseRequestsAction)
            ->execute(new \Illuminate\Http\Request(), $supply);

        $ids = $data['requests']->getCollection()->pluck('id')->all();
        $this->assertContains($ownPr->id, $ids, 'Supply officer must see PRs they created.');
        $this->assertContains($pmPr->id, $ids, 'Supply officer must see PRs raised from PM tickets.');

        // Status counts are scoped the same way.
        $this->assertGreaterThan(0, $data['counts']['submitted']);
    }

    public function test_delivered_pr_receive_form_opens_view_only_with_proof(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        // Get a received (delivered) PR. Receive requires a finalized PR first;
        // below-threshold owner may receive it.
        $pr = $this->makeFinalizedPr($it, 2750.00);
        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        $result = $action->execute($pr->fresh(), $it, [
            ['part_id' => $this->makePart()->id, 'destination' => 'stock-in', 'units' => [['serial_number' => 'RX-VIEW-01', 'property_number' => 'PN-VIEW-01']]],
        ]);
        $this->assertTrue($result['success'], $result['message'] ?? '');
        $pr = $pr->fresh();
        $this->assertTrue($pr->isDelivered());

        // canViewDelivery is true for the owner on a delivered PR.
        $this->assertFalse($action->canReceive($pr, $it));
        $this->assertTrue($action->canViewDelivery($pr, $it));

        // The receiveForm renders in read-only mode (no form/confirm button),
        // while still exposing the Proof of purchase card.
        $response = $this->actingAs($it)->get(route('purchase_requests.receiveForm', $pr));
        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringContainsString('Delivery record', $body);
        $this->assertStringContainsString('Proof of purchase', $body);
        $this->assertStringNotContainsString('Confirm delivery', $body);
        $this->assertStringNotContainsString('name="lines[0][destination]"', $body);
    }

    public function test_delivered_pr_show_has_view_delivery_action(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $pr = $this->makeFinalizedPr($it, 2750.00);
        $action = new \App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
        $action->execute($pr->fresh(), $it, [
            ['part_id' => $this->makePart()->id, 'destination' => 'stock-in', 'units' => [['serial_number' => 'RX-VIEW-02', 'property_number' => 'PN-VIEW-02']]],
        ]);
        $pr = $pr->fresh();

        $response = $this->actingAs($it)->get(route('purchase_requests.show', $pr));
        $response->assertOk();
        // The action bar now links to the (view-only) delivery record.
        $this->assertStringContainsString(
            route('purchase_requests.receiveForm', $pr->id),
            $response->getContent()
        );
        $this->assertStringContainsString('View delivery', $response->getContent());
    }
}
