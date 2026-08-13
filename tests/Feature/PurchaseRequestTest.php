<?php

namespace Tests\Feature;

use App\Actions\PurchaseRequest\ApprovePurchaseRequestAction;
use App\Actions\PurchaseRequest\CancelPurchaseRequestAction;
use App\Actions\PurchaseRequest\CreatePurchaseRequestAction;
use App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
use App\Models\Part;
use App\Models\PurchaseRequest;
use App\Models\Requisition;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PurchaseRequestTest extends TestCase
{
    use RefreshDatabase;

    private $counter = 0;

    private function makeUser(array $attrs = [])
    {
        $this->counter++;
        return \App\Models\User::create(array_merge([
            'full_name' => 'PR Test User ' . $this->counter,
            'email' => 'prt' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'can_supply' => false,
            'region' => 'NCR',
        ], $attrs));
    }

    private function makeTicket(\App\Models\User $it)
    {
        return \App\Models\Request::create([
            'user_id' => $it->id,
            'assigned_to' => $it->id,
            'request_number' => 'REQ-NCR-' . date('Y') . '-' . str_pad($this->counter, 3, '0', STR_PAD_LEFT),
            'type' => 'ICT',
            'requestor_name' => 'End User',
            'description' => 'Test repair',
            'status' => \App\Models\Request::STATUS_ONGOING,
            'region' => 'NCR',
        ]);
    }

    /**
     * Part na 1 lang ang on_hand pero 5 ang hinihingi → deficit 4.
     */
    private function setupShortRequisition(): array
    {
        $supply = $this->makeUser(['role' => 'supply_officer']);
        $it = $this->makeUser(['role' => 'it']);
        $part = Part::create([
            'item_name' => 'RAM 16GB DDR4',
            'unit' => 'pcs',
            'on_hand_qty' => 1,
            'reorder_level' => 5,
        ]);
        $ticket = $this->makeTicket($it);
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

        return ['supply' => $supply, 'part' => $part, 'requisition' => $requisition];
    }

    public function test_create_pr_from_deficit()
    {
        $d = $this->setupShortRequisition();
        $this->actingAs($d['supply']);

        $resp = (new CreatePurchaseRequestAction)->execute($d['requisition']);

        $this->assertEquals(200, $resp->getStatusCode());
        $this->assertTrue($resp->getData(true)['success']);

        $pr = PurchaseRequest::where('requisition_id', $d['requisition']->id)->first();
        $this->assertNotNull($pr);
        $this->assertEquals(PurchaseRequest::STATUS_PENDING, $pr->status);
        $this->assertStringStartsWith('PR-' . date('Y'), $pr->pr_number);
        $this->assertEquals(4, $pr->items[0]['quantity'], 'Dapat ang deficit (5-1) ang naka-order');
    }

    public function test_create_pr_is_noop_when_stock_is_enough()
    {
        $supply = $this->makeUser(['role' => 'supply_officer']);
        $it = $this->makeUser(['role' => 'it']);
        $part = Part::create(['item_name' => 'NVMe SSD 1TB', 'unit' => 'pcs', 'on_hand_qty' => 20, 'reorder_level' => 3]);
        $ticket = $this->makeTicket($it);
        $requisition = Requisition::create([
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_PENDING,
            'items' => [[
                'description' => 'NVMe SSD 1TB',
                'quantity' => 2,
                'source' => 'parts-stock',
                'part_id' => $part->id,
            ]],
        ]);
        $this->actingAs($supply);

        $resp = (new CreatePurchaseRequestAction)->execute($requisition);

        $this->assertEquals(422, $resp->getStatusCode());
        $this->assertDatabaseCount('purchase_requests', 0);
    }

    public function test_approve_then_receive_stocks_in_part()
    {
        $d = $this->setupShortRequisition();
        $this->actingAs($d['supply']);

        (new CreatePurchaseRequestAction)->execute($d['requisition']);
        $pr = PurchaseRequest::where('requisition_id', $d['requisition']->id)->first();

        $this->assertDatabaseHas('parts_stock', ['id' => $d['part']->id, 'on_hand_qty' => 1]);

        (new ApprovePurchaseRequestAction)->execute($pr);
        $this->assertEquals(PurchaseRequest::STATUS_APPROVED, $pr->refresh()->status);

        (new ReceivePurchaseRequestAction)->execute($pr);

        $this->assertEquals(PurchaseRequest::STATUS_RECEIVED, $pr->refresh()->status);
        $this->assertEquals(5, $d['part']->refresh()->on_hand_qty, '1 + 4 deficit = 5 on-hand after receive');

        $this->assertDatabaseHas('parts_stock_movements', [
            'part_id' => $d['part']->id,
            'qty_change' => 4,
            'reference_type' => 'purchase',
            'reference_id' => $pr->id,
        ]);
    }

    public function test_cancel_pr()
    {
        $d = $this->setupShortRequisition();
        $this->actingAs($d['supply']);

        (new CreatePurchaseRequestAction)->execute($d['requisition']);
        $pr = PurchaseRequest::where('requisition_id', $d['requisition']->id)->first();

        (new CancelPurchaseRequestAction)->execute($pr);

        $this->assertEquals(PurchaseRequest::STATUS_CANCELLED, $pr->refresh()->status);
        $this->assertEquals(1, $d['part']->refresh()->on_hand_qty, 'Cancel ay hindi nag-stock-in');
    }

    public function test_non_supply_cannot_create_pr()
    {
        $supply = $this->makeUser(['role' => 'supply_officer']);
        $it = $this->makeUser(['role' => 'it']);
        $part = Part::create(['item_name' => 'NVMe SSD 1TB', 'unit' => 'pcs', 'on_hand_qty' => 1, 'reorder_level' => 3]);
        $ticket = $this->makeTicket($it);
        $requisition = Requisition::create([
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_PENDING,
            'items' => [[
                'description' => 'NVMe SSD 1TB',
                'quantity' => 3,
                'source' => 'parts-stock',
                'part_id' => $part->id,
            ]],
        ]);

        $this->actingAs($it);
        $resp = (new CreatePurchaseRequestAction)->execute($requisition);

        $this->assertEquals(403, $resp->getStatusCode());
        $this->assertDatabaseCount('purchase_requests', 0);
    }
}