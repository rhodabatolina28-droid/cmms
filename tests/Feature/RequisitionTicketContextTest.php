<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\Notification;
use App\Models\Part;
use App\Models\PartUnit;
use App\Models\PartMovement;

use App\Models\Requisition;
use App\Models\Request as Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisitionTicketContextTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function makeUser(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'Ticket Context User ' . $this->counter,
            'email' => 'ticket-context-' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'can_supply' => false,
            'region' => 'NCR',
        ], $attributes));
    }

    private function makeTicket(User $it, ?int $assetId = null, string $type = 'ICT'): Ticket
    {
        return Ticket::create([
            'user_id' => $this->makeUser()->id,
            'assigned_to' => $it->id,
            'linked_asset_id' => $assetId,
            'request_number' => 'REQ-NCR-2026-' . str_pad((string) $this->counter, 4, '0', STR_PAD_LEFT),
            'type' => $type,
            'requestor_name' => 'Ticket Requestor',
            'description' => 'Printer repair',
            'status' => Ticket::STATUS_ONGOING,
            'region' => 'NCR',
        ]);
    }

    private function requestPayload(Part $part): array
    {
        return [
            'items' => [[
                'description' => $part->item_name,
                'quantity' => 1,
                'source' => 'parts-stock',
                'part_id' => $part->id,
            ]],
        ];
    }

    public function test_it_cannot_submit_parts_request_without_a_linked_asset(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $ticket = $this->makeTicket($it);
        $part = Part::create(['item_name' => 'Printer Ink', 'unit' => 'tube', 'on_hand_qty' => 1]);

        $this->actingAs($it)
            ->postJson(route('requisitions.store', $ticket->id), $this->requestPayload($part))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'A linked asset is required before parts can be requested or issued for this ticket.');
    }

    public function test_it_can_submit_parts_request_when_linked_asset_has_custodian(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $custodian = $this->makeUser();
        $asset = InventoryAsset::create([
            'category' => 'Printer/Scanner',
            'item_name' => 'Epson L3250',
            'assigned_to_user' => $custodian->id,
            'region' => 'NCR',
            'status' => 'Active',
        ]);
        $ticket = $this->makeTicket($it, $asset->asset_id);
        $part = Part::create(['item_name' => 'EPSON 003 Ink Bottle - Black', 'unit' => 'tube', 'on_hand_qty' => 1]);

        $this->actingAs($it)
            ->postJson(route('requisitions.store', $ticket->id), $this->requestPayload($part))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('requisitions', [
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
        ]);
    }

    public function test_it_cannot_submit_parts_request_when_linked_asset_has_no_custodian(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $asset = InventoryAsset::create([
            'category' => 'Printer/Scanner',
            'item_name' => 'Epson L3250',
            'region' => 'NCR',
            'status' => 'Spare',
        ]);
        $ticket = $this->makeTicket($it, $asset->asset_id);
        $part = Part::create(['item_name' => 'EPSON 003 Ink Bottle - Black', 'unit' => 'tube', 'on_hand_qty' => 1]);

        $this->actingAs($it)
            ->postJson(route('requisitions.store', $ticket->id), $this->requestPayload($part))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Assign a custodian to the linked asset before parts can be requested or issued.');
    }

    public function test_supply_issue_is_blocked_when_an_existing_requisition_lacks_asset_context(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $supply = $this->makeUser(['role' => 'admin', 'can_supply' => true]);
        $ticket = $this->makeTicket($it);
        $part = Part::create(['item_name' => 'Printer Ink', 'unit' => 'tube', 'on_hand_qty' => 1]);
        $requisition = Requisition::create([
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_APPROVED,
            'items' => $this->requestPayload($part)['items'],
        ]);

        $this->actingAs($supply)
            ->postJson(route('requisitions.review', $requisition->id), ['action' => 'issue'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'A linked asset is required before parts can be requested or issued for this ticket.');

        $this->assertSame(Requisition::STATUS_APPROVED, $requisition->refresh()->status);
        $this->assertSame(1, $part->refresh()->on_hand_qty);
    }

    public function test_supply_issue_links_serialized_unit_to_ticket_asset_and_custodian(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $supply = $this->makeUser(['role' => 'admin', 'can_supply' => true]);
        $custodian = $this->makeUser();
        $asset = InventoryAsset::create([
            'category' => 'Printer/Scanner',
            'item_name' => 'Epson L3250',
            'assigned_to_user' => $custodian->id,
            'region' => 'NCR',
            'status' => 'Active',
        ]);
        $ticket = $this->makeTicket($it, $asset->asset_id);
        $ticket->update(['status' => Ticket::STATUS_AWAITING_PARTS]);
        $part = Part::create(['item_name' => 'EPSON 003 Ink Bottle - Black', 'unit' => 'tube', 'on_hand_qty' => 1]);
        $unit = PartUnit::create([
            'part_id' => $part->id,
            'serial_number' => 'PRN-003-0001',
            'property_number' => '2026-05-03-9001-RCMB',
            'status' => 'in_stock',
        ]);
        $requisition = Requisition::create([
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_APPROVED,
            'items' => $this->requestPayload($part)['items'],
        ]);

        $this->actingAs($supply)
            ->postJson(route('requisitions.review', $requisition->id), ['action' => 'issue'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $unit->refresh();
        $this->assertSame('issued', $unit->status);
        $this->assertSame($custodian->id, $unit->issued_to);
        $this->assertSame($asset->asset_id, $unit->asset_id);
        $this->assertSame($ticket->id, $unit->request_id);
        $this->assertNotNull($unit->issued_at);
        $this->assertSame(0, $part->refresh()->on_hand_qty);
        $this->assertDatabaseHas('parts_stock_movements', [
            'part_id' => $part->id,
            'qty_change' => -1,
            'reason' => 'Issue to requisition #' . $requisition->id,
            'reference_type' => 'requisition',
            'reference_id' => $requisition->id,
        ]);


        $this->assertSame(Requisition::STATUS_ISSUED, $requisition->refresh()->status);
        $this->assertSame(Ticket::STATUS_ONGOING, $ticket->refresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $custodian->id,
            'request_id' => $ticket->id,
            'type' => 'Parts Issued to Asset',
        ]);
    }

    public function test_issued_unit_keeps_original_custodian_after_asset_reassignment(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $supply = $this->makeUser(['role' => 'admin', 'can_supply' => true]);
        $originalCustodian = $this->makeUser();
        $newCustodian = $this->makeUser();
        $asset = InventoryAsset::create([
            'category' => 'Printer/Scanner',
            'item_name' => 'Epson L3250',
            'assigned_to_user' => $originalCustodian->id,
            'region' => 'NCR',
            'status' => 'Active',
        ]);
        $ticket = $this->makeTicket($it, $asset->asset_id);
        $ticket->update(['status' => Ticket::STATUS_AWAITING_PARTS]);
        $part = Part::create(['item_name' => 'Epson Maintenance Box', 'unit' => 'pcs', 'on_hand_qty' => 1]);
        $unit = PartUnit::create([
            'part_id' => $part->id,
            'serial_number' => 'MBX-0001',
            'property_number' => '2026-05-03-9002-RCMB',
            'unit_value' => 1200,
            'status' => 'in_stock',
        ]);
        $requisition = Requisition::create([
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_APPROVED,
            'items' => $this->requestPayload($part)['items'],
        ]);

        $this->actingAs($supply)
            ->postJson(route('requisitions.review', $requisition->id), ['action' => 'issue'])
            ->assertOk();

        $asset->update(['assigned_to_user' => $newCustodian->id]);

        $this->assertSame($newCustodian->id, $asset->refresh()->assigned_to_user);
        $this->assertSame($originalCustodian->id, $unit->refresh()->issued_to);
        $this->assertSame($asset->asset_id, $unit->asset_id);
        $this->assertSame($ticket->id, $unit->request_id);
        $this->assertSame(1, Notification::where('user_id', $originalCustodian->id)
            ->where('request_id', $ticket->id)
            ->where('type', 'Parts Issued to Asset')
            ->count());
    }

    public function test_it_cannot_submit_parts_request_when_not_assigned_to_the_ticket(): void
    {
        $assignedIt = $this->makeUser(['role' => 'it']);
        $otherIt = $this->makeUser(['role' => 'it']);
        $custodian = $this->makeUser();
        $asset = InventoryAsset::create([
            'category' => 'Printer/Scanner',
            'item_name' => 'Epson L3250',
            'assigned_to_user' => $custodian->id,
            'region' => 'NCR',
            'status' => 'Active',
        ]);
        $ticket = $this->makeTicket($assignedIt, $asset->asset_id);
        $part = Part::create(['item_name' => 'EPSON 003 Ink Bottle - Black', 'unit' => 'tube', 'on_hand_qty' => 1]);

        $this->actingAs($otherIt)
            ->postJson(route('requisitions.store', $ticket->id), $this->requestPayload($part))
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You can only request parts for ICT or an eligible PM job order assigned to you.');
    }

    public function test_it_cannot_submit_parts_request_for_a_completed_ticket(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $custodian = $this->makeUser();
        $asset = InventoryAsset::create([
            'category' => 'Printer/Scanner',
            'item_name' => 'Epson L3250',
            'assigned_to_user' => $custodian->id,
            'region' => 'NCR',
            'status' => 'Active',
        ]);
        $ticket = $this->makeTicket($it, $asset->asset_id);
        $ticket->update(['status' => Ticket::STATUS_COMPLETED]);
        $part = Part::create(['item_name' => 'EPSON 003 Ink Bottle - Black', 'unit' => 'tube', 'on_hand_qty' => 1]);

        $this->actingAs($it)
            ->postJson(route('requisitions.store', $ticket->id), $this->requestPayload($part))
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_it_cannot_submit_parts_request_for_a_cancelled_ticket(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $custodian = $this->makeUser();
        $asset = InventoryAsset::create([
            'category' => 'Printer/Scanner',
            'item_name' => 'Epson L3250',
            'assigned_to_user' => $custodian->id,
            'region' => 'NCR',
            'status' => 'Active',
        ]);
        $ticket = $this->makeTicket($it, $asset->asset_id);
        $ticket->update(['status' => Ticket::STATUS_CANCELLED]);
        $part = Part::create(['item_name' => 'EPSON 003 Ink Bottle - Black', 'unit' => 'tube', 'on_hand_qty' => 1]);

        $this->actingAs($it)
            ->postJson(route('requisitions.store', $ticket->id), $this->requestPayload($part))
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_issued_unit_appears_on_asset_profile_and_parts_used_cards(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $supply = $this->makeUser(['role' => 'admin', 'can_supply' => true]);
        $custodian = $this->makeUser();
        $asset = InventoryAsset::create([
            'category' => 'Printer/Scanner',
            'item_name' => 'Epson L3250',
            'assigned_to_user' => $custodian->id,
            'region' => 'NCR',
            'status' => 'Active',
        ]);
        $ticket = $this->makeTicket($it, $asset->asset_id);
        $ticket->update(['status' => Ticket::STATUS_AWAITING_PARTS]);
        $part = Part::create(['item_name' => 'EPSON 003 Ink Bottle - Black', 'unit' => 'tube', 'on_hand_qty' => 1]);
        $unit = PartUnit::create([
            'part_id' => $part->id,
            'serial_number' => 'PRN-004-0001',
            'property_number' => '2026-05-03-9004-RCMB',
            'unit_value' => 850,
            'status' => 'in_stock',
        ]);
        $requisition = Requisition::create([
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_APPROVED,
            'items' => $this->requestPayload($part)['items'],
        ]);

        $this->actingAs($supply)
            ->postJson(route('requisitions.review', $requisition->id), ['action' => 'issue'])
            ->assertOk();

        $unit->refresh();

        // Asset Profile — Installed Parts / Consumables card (query used by detail.blade.php)
        $assetCard = PartUnit::with(['part:id,item_name', 'issuedTo:id,full_name', 'request:id,request_number'])
            ->where('asset_id', $asset->asset_id)
            ->orderByDesc('issued_at')
            ->get();
        $this->assertSame(1, $assetCard->count(), 'Issued unit must appear on the Asset Profile card.');
        $this->assertSame($unit->id, $assetCard->first()->id);
        $this->assertSame($custodian->id, $assetCard->first()->issued_to);

        // Ticket — Parts Used card (query used by _parts_used_card.blade.php)
        $partsUsedCard = PartUnit::with(['part:id,item_name', 'issuedTo:id,full_name'])
            ->where('request_id', $ticket->id)
            ->orderByDesc('issued_at')
            ->get();
        $this->assertSame(1, $partsUsedCard->count(), 'Issued unit must appear on the ticket Parts Used card.');
        $this->assertSame($unit->id, $partsUsedCard->first()->id);

        // Unissued stock does not leak onto the cards, and stock count stays consistent.
        $this->assertSame('issued', $unit->status);
        $this->assertSame(0, PartUnit::where('part_id', $part->id)->where('status', 'in_stock')->count());
        $this->assertSame(0, $part->refresh()->on_hand_qty);
    }


    public function test_it_can_submit_parts_request_for_an_eligible_pm_ticket(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $custodian = $this->makeUser();
        $asset = InventoryAsset::create([
            'category' => 'Printer/Scanner',
            'item_name' => 'Epson L3250',
            'assigned_to_user' => $custodian->id,
            'region' => 'NCR',
            'status' => 'Active',
        ]);
        $ticket = $this->makeTicket($it, $asset->asset_id, 'Preventive Maintenance');
        $part = Part::create(['item_name' => 'EPSON 003 Ink Bottle - Black', 'unit' => 'tube', 'on_hand_qty' => 1]);

        $this->actingAs($it)
            ->postJson(route('requisitions.store', $ticket->id), $this->requestPayload($part))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('requisitions', [
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
        ]);
    }

    public function test_it_cannot_submit_parts_request_for_a_pm_ticket_without_a_linked_asset(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        // Auto-generated/bundled PM has no single linked asset, so it is not eligible.
        $ticket = $this->makeTicket($it, null, 'Preventive Maintenance');
        $part = Part::create(['item_name' => 'Printer Ink', 'unit' => 'tube', 'on_hand_qty' => 1]);

        $this->actingAs($it)
            ->postJson(route('requisitions.store', $ticket->id), $this->requestPayload($part))
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_pm_issue_assigns_unit_to_the_pm_ticket_asset_custodian(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $supply = $this->makeUser(['role' => 'admin', 'can_supply' => true]);
        $custodian = $this->makeUser();
        $asset = InventoryAsset::create([
            'category' => 'Printer/Scanner',
            'item_name' => 'Epson L3250',
            'assigned_to_user' => $custodian->id,
            'region' => 'NCR',
            'status' => 'Active',
        ]);
        $ticket = $this->makeTicket($it, $asset->asset_id, 'Preventive Maintenance');
        $ticket->update(['status' => Ticket::STATUS_AWAITING_PARTS]);
        $part = Part::create(['item_name' => 'EPSON 003 Ink Bottle - Black', 'unit' => 'tube', 'on_hand_qty' => 1]);
        $unit = PartUnit::create([
            'part_id' => $part->id,
            'serial_number' => 'PM-INK-0001',
            'property_number' => '2026-05-03-9005-RCMB',
            'unit_value' => 850,
            'status' => 'in_stock',
        ]);
        $requisition = Requisition::create([
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_APPROVED,
            'items' => $this->requestPayload($part)['items'],
        ]);

        $this->actingAs($supply)
            ->postJson(route('requisitions.review', $requisition->id), ['action' => 'issue'])
            ->assertOk();

        $unit->refresh();
        $this->assertSame('issued', $unit->status);
        $this->assertSame($custodian->id, $unit->issued_to);
        $this->assertSame($asset->asset_id, $unit->asset_id);
        $this->assertSame($ticket->id, $unit->request_id);
        $this->assertSame(0, $part->refresh()->on_hand_qty);
    }


    public function test_repeated_issue_is_blocked_and_does_not_double_deduct(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $supply = $this->makeUser(['role' => 'admin', 'can_supply' => true]);
        $custodian = $this->makeUser();
        $asset = InventoryAsset::create([
            'category' => 'Printer/Scanner',
            'item_name' => 'Epson L3250',
            'assigned_to_user' => $custodian->id,
            'region' => 'NCR',
            'status' => 'Active',
        ]);
        $ticket = $this->makeTicket($it, $asset->asset_id);
        $ticket->update(['status' => Ticket::STATUS_AWAITING_PARTS]);
        $part = Part::create(['item_name' => 'EPSON 003 Ink Bottle - Black', 'unit' => 'tube', 'on_hand_qty' => 1]);
        $unit = PartUnit::create([
            'part_id' => $part->id,
            'serial_number' => 'INK-REPEAT-1',
            'property_number' => '2026-05-03-9006-RCMB',
            'status' => 'in_stock',
        ]);
        $requisition = Requisition::create([
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_APPROVED,
            'items' => $this->requestPayload($part)['items'],
        ]);

        // First issue succeeds.
        $this->actingAs($supply)
            ->postJson(route('requisitions.review', $requisition->id), ['action' => 'issue'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $unit->refresh();
        $this->assertSame('issued', $unit->status);
        $this->assertSame(0, $part->refresh()->on_hand_qty);

        // Second issue is blocked; stock is not deducted again.
        $this->actingAs($supply)
            ->postJson(route('requisitions.review', $requisition->id), ['action' => 'issue'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Approve the request first, then use Issue when parts are released.');

        $this->assertSame(0, $part->refresh()->on_hand_qty);
        $this->assertSame(Requisition::STATUS_ISSUED, $requisition->refresh()->status);
    }

    // ── Duplicate-parts guard: block same ticket + same parts while open ──

    /** Reuse the SAME Part instance so part_id matches across repeated submissions. */
    private function submitPart(User $it, Ticket $ticket, Part $part): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($it)->postJson(route('requisitions.store', $ticket->id), $this->requestPayload($part));
    }

    private function makeStockPart(string $itemName): Part
    {
        return Part::create(['item_name' => $itemName, 'unit' => 'pcs', 'on_hand_qty' => 5]);
    }

    public function test_same_ticket_same_parts_with_pending_is_blocked(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $custodian = $this->makeUser();
        $asset = InventoryAsset::create([
            'category' => 'Desktop',
            'item_name' => 'HP ProBook',
            'assigned_to_user' => $custodian->id,
            'region' => 'NCR',
            'status' => 'Active',
        ]);
        $ticket = $this->makeTicket($it, $asset->asset_id);
        $part = $this->makeStockPart('RAM 8GB DDR4');

        $this->submitPart($it, $ticket, $part);
        $this->assertSame(1, Requisition::where('request_id', $ticket->id)->count());

        $this->submitPart($it, $ticket, $part)
            ->assertOk()
            ->assertJsonPath('already_submitted', true);

        $this->assertSame(1, Requisition::where('request_id', $ticket->id)->count());
    }

    public function test_same_ticket_different_parts_is_allowed(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $custodian = $this->makeUser();
        $asset = InventoryAsset::create([
            'category' => 'Desktop',
            'item_name' => 'HP ProBook',
            'assigned_to_user' => $custodian->id,
            'region' => 'NCR',
            'status' => 'Active',
        ]);
        $ticket = $this->makeTicket($it, $asset->asset_id);

        $this->submitPart($it, $ticket, $this->makeStockPart('RAM 8GB DDR4'));
        $this->submitPart($it, $ticket, $this->makeStockPart('SSD 512GB'));
        $this->submitPart($it, $ticket, $this->makeStockPart('Mouse'));

        $this->assertSame(3, Requisition::where('request_id', $ticket->id)->count());
    }

    public function test_different_ticket_same_parts_is_allowed(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $custodian = $this->makeUser();

        $mkTicket = function () use ($it, $custodian) {
            $asset = InventoryAsset::create([
                'category' => 'Desktop',
                'item_name' => 'HP ProBook',
                'assigned_to_user' => $custodian->id,
                'region' => 'NCR',
                'status' => 'Active',
            ]);
            return $this->makeTicket($it, $asset->asset_id);
        };

        $t1 = $mkTicket();
        $t2 = $mkTicket();
        $part = $this->makeStockPart('RAM 8GB DDR4');

        $this->submitPart($it, $t1, $part);
        $this->submitPart($it, $t2, $part);

        $this->assertSame(1, Requisition::where('request_id', $t1->id)->count());
        $this->assertSame(1, Requisition::where('request_id', $t2->id)->count());
    }

    public function test_same_ticket_same_parts_allowed_after_issued(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $custodian = $this->makeUser();
        $asset = InventoryAsset::create([
            'category' => 'Desktop',
            'item_name' => 'HP ProBook',
            'assigned_to_user' => $custodian->id,
            'region' => 'NCR',
            'status' => 'Active',
        ]);
        $ticket = $this->makeTicket($it, $asset->asset_id);
        $part = $this->makeStockPart('RAM 8GB DDR4');

        $this->submitPart($it, $ticket, $part);
        Requisition::where('request_id', $ticket->id)->update(['status' => Requisition::STATUS_ISSUED]);

        $this->submitPart($it, $ticket, $part)
            ->assertJsonPath('success', true);

        $this->assertSame(2, Requisition::where('request_id', $ticket->id)->count());
    }
}
