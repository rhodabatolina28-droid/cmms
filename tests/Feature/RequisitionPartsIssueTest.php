<?php

namespace Tests\Feature;

use App\Actions\Inventory\PartsStock\IssuePartsForRequisitionAction;
use App\Models\Part;
use App\Models\Requisition;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RequisitionPartsIssueTest extends TestCase
{
    use RefreshDatabase;

    private $counter = 0;

    private function makeUser(array $attrs = [])
    {
        $this->counter++;
        return \App\Models\User::create(array_merge([
            'full_name' => 'Reqt Test User ' . $this->counter,
            'email' => 'reqt' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'can_supply' => false,
            'region' => 'NCR',
        ], $attrs));
    }

    private function makeTicket(\App\Models\User $it, string $suffix = ''): \App\Models\Request
    {
        return \App\Models\Request::create([
            'user_id' => $it->id,
            'assigned_to' => $it->id,
            'request_number' => 'REQ-NCR-' . date('Y') . '-' . str_pad($this->counter, 3, '0', STR_PAD_LEFT) . $suffix,
            'type' => 'ICT',
            'requestor_name' => 'End User',
            'description' => 'Test repair',
            'status' => \App\Models\Request::STATUS_ONGOING,
            'region' => 'NCR',
        ]);
    }

    private function requisitionFromParts(int $partId, int $qty, string $description = 'NVMe SSD 1TB')
    {
        $supply = $this->makeUser(['role' => 'supply_officer']);
        $it = $this->makeUser(['role' => 'it']);
        $ticket = $this->makeTicket($it);

        $requisition = Requisition::create([
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_APPROVED,
            'items' => [[
                'description' => $description,
                'quantity' => $qty,
                'source' => 'parts-stock',
                'part_id' => $partId,
            ]],
        ]);

        return [
            'supply' => $supply,
            'it' => $it,
            'ticket' => $ticket,
            'requisition' => $requisition,
        ];
    }

    public function test_issue_deducts_parts_stock_and_logs_movement()
    {
        $part = Part::create([
            'item_name' => 'NVMe SSD 1TB',
            'unit' => 'pcs',
            'on_hand_qty' => 8,
            'reorder_level' => 3,
        ]);

        $data = $this->requisitionFromParts($part->id, 2);
        $result = (new IssuePartsForRequisitionAction)->execute($data['requisition'], $data['supply']->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(6, $part->refresh()->on_hand_qty);

        $this->assertDatabaseHas('parts_stock_movements', [
            'part_id' => $part->id,
            'qty_change' => -2,
            'reference_type' => 'requisition',
            'reference_id' => $data['requisition']->id,
        ]);
    }

    public function test_issue_ignores_non_parts_stock_lines()
    {
        $part = Part::create([
            'item_name' => 'Webcam',
            'unit' => 'pc',
            'on_hand_qty' => 5,
            'reorder_level' => 0,
        ]);

        $supply = $this->makeUser(['role' => 'supply_officer']);
        $it = $this->makeUser(['role' => 'it']);

        $ticket = \App\Models\Request::create([
            'user_id' => $it->id,
            'assigned_to' => $it->id,
            'request_number' => 'REQ-NCR-' . date('Y') . '-' . str_pad($this->counter, 3, '0', STR_PAD_LEFT),
            'type' => 'ICT',
            'requestor_name' => 'End User',
            'description' => 'Test repair 2',
            'status' => \App\Models\Request::STATUS_ONGOING,
            'region' => 'NCR',
        ]);

        // This line is NOT parts-stock — should not touch on_hand.
        $requisition = Requisition::create([
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_APPROVED,
            'items' => [[
                'description' => 'Webcam',
                'quantity' => 2,
                'source' => 'spare',
            ]],
        ]);

        $result = (new IssuePartsForRequisitionAction)->execute($requisition, $supply->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['issue_count']);
        $this->assertEquals(5, $part->refresh()->on_hand_qty);
        $this->assertDatabaseMissing('parts_stock_movements', ['part_id' => $part->id]);
    }

    public function test_issue_is_blocked_when_stock_insufficient()
    {
        $part = Part::create([
            'item_name' => 'RAM 16GB DDR4',
            'unit' => 'pcs',
            'on_hand_qty' => 1,
            'reorder_level' => 2,
        ]);

        $data = $this->requisitionFromParts($part->id, 5, 'RAM 16GB DDR4');
        $result = (new IssuePartsForRequisitionAction)->execute($data['requisition'], $data['supply']->id);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['deficits']);
        $this->assertEquals(1, $part->refresh()->on_hand_qty, 'Stock must be untouched when blocked');
        $this->assertDatabaseMissing('parts_stock_movements', ['part_id' => $part->id]);
    }

    public function test_it_index_renders_parts_stock_picker()
    {
        Part::create([
            'item_name' => 'NVMe SSD 1TB',
            'unit' => 'pcs',
            'on_hand_qty' => 8,
            'reorder_level' => 3,
            'region' => 'NCR',
            'is_active' => true,
        ]);

        $it = $this->makeUser(['role' => 'it']);
        $this->makeTicket($it);

        $this->actingAs($it)
            ->get(route('requisitions.index'))
            ->assertOk()
            ->assertSee('From Parts Stock', false);
    }

    public function test_supply_show_renders_parts_stock_panel()
    {
        $part = Part::create([
            'item_name' => 'NVMe SSD 1TB',
            'unit' => 'pcs',
            'on_hand_qty' => 8,
            'reorder_level' => 3,
        ]);

        $data = $this->requisitionFromParts($part->id, 2);

        $this->actingAs($data['supply'])
            ->get(route('requisitions.show', $data['requisition']->id))
            ->assertOk()
            ->assertSee('Parts &amp; Consumables Stock', false);
    }
}
