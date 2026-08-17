<?php

namespace Tests\Feature;

use App\Actions\Inventory\PartsStock\IssuePartsForRequisitionAction;
use App\Models\Part;
use App\Models\PartUnit;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartsUnitsTest extends TestCase
{
    use RefreshDatabase;

    private $counter = 0;

    private function makeUser(array $attrs = [])
    {
        $this->counter++;
        return User::create(array_merge([
            'full_name' => 'Unit User ' . $this->counter,
            'email' => 'unit' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'can_supply' => false,
            'region' => 'NCR',
            'branch' => null,
            'office' => null,
            'department' => null,
        ], $attrs));
    }

    private function supplyOfficer()
    {
        return $this->makeUser(['role' => 'supply_officer']);
    }

    private function makePart(int $onHand = 0)
    {
        return Part::create([
            'item_name' => 'RAM HPE16GB', 'unit' => 'pcs', 'category' => 'Memory',
            'on_hand_qty' => $onHand, 'reorder_level' => 5, 'region' => 'NCR', 'is_active' => true,
        ]);
    }

    private function makeTicketForConsistency(Part $part): Requisition
    {
        $it = $this->makeUser(['role' => 'it']);
        $ticket = \App\Models\Request::create([
            'user_id' => $it->id,
            'assigned_to' => $it->id,
            'request_number' => 'REQ-CONS-' . date('Y') . '-' . str_pad($this->counter, 3, '0', STR_PAD_LEFT),
            'type' => 'ICT',
            'requestor_name' => 'End User',
            'description' => 'Consistency test',
            'status' => \App\Models\Request::STATUS_ONGOING,
            'region' => 'NCR',
        ]);

        return Requisition::create([
            'request_id' => $ticket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_APPROVED,
            'items' => [
                ['source' => 'parts-stock', 'part_id' => $part->id, 'quantity' => 2, 'description' => 'RAM HPE16GB'],
            ],
        ]);
    }

    public function test_add_unit_creates_unit_and_increments_on_hand()
    {
        $this->actingAs($this->supplyOfficer());
        $part = $this->makePart();

        $resp = $this->postJson(route('inventory.parts.units.store', ['part' => $part->id]), [
            'serial_number' => 'KR8220Y2BS',
            'property_number' => '2022-01-02-0001',
            'unit_value' => 22490.00,
        ]);

        $resp->assertStatus(200)->assertJsonPath('success', true)->assertJsonPath('on_hand_qty', 1);
        $this->assertSame(1, PartUnit::where('part_id', $part->id)->where('status', 'in_stock')->count());
        $this->assertSame(1, $part->refresh()->on_hand_qty);
        $this->assertSame('KR8220Y2BS', PartUnit::first()->serial_number);
    }

    public function test_duplicate_serial_rejected()
    {
        $this->actingAs($this->supplyOfficer());
        $part = $this->makePart();
        PartUnit::create(['part_id' => $part->id, 'serial_number' => 'DUP-1', 'status' => 'in_stock']);

        $resp = $this->postJson(route('inventory.parts.units.store', ['part' => $part->id]), ['serial_number' => 'DUP-1']);
        $resp->assertStatus(422);
        $this->assertSame(1, PartUnit::where('part_id', $part->id)->count());
    }

    public function test_stock_in_with_units_creates_units_and_on_hand()
    {
        $this->actingAs($this->supplyOfficer());
        $part = $this->makePart();

        $resp = $this->postJson(route('inventory.parts.stock-in', ['part' => $part->id]), [
            'qty' => 3,
            'reason' => 'Purchase received',
            'units' => [
                ['serial_number' => 'S1', 'property_number' => 'P1'],
                ['serial_number' => 'S2', 'property_number' => 'P2'],
            ],
        ]);

        $resp->assertStatus(200)->assertJsonPath('success', true)->assertJsonPath('on_hand_qty', 3);
        $this->assertSame(2, PartUnit::where('part_id', $part->id)->count()); // 2 units, 1 generic slot
        $this->assertSame(3, $part->refresh()->on_hand_qty);
    }

    public function test_stock_out_marks_selected_units_issued()
    {
        $this->actingAs($this->supplyOfficer());
        $part = $this->makePart(3);
        $u1 = PartUnit::create(['part_id' => $part->id, 'serial_number' => 'S1', 'status' => 'in_stock']);
        $u2 = PartUnit::create(['part_id' => $part->id, 'serial_number' => 'S2', 'status' => 'in_stock']);
        $u3 = PartUnit::create(['part_id' => $part->id, 'serial_number' => 'S3', 'status' => 'in_stock']);

        $resp = $this->postJson(route('inventory.parts.stock-out', ['part' => $part->id]), [
            'qty' => 2,
            'reason' => 'Issue to requisition',
            'unit_ids' => [$u1->id, $u2->id],
        ]);

        $resp->assertStatus(200)->assertJsonPath('success', true)->assertJsonPath('on_hand_qty', 1);
        $this->assertSame('issued', $u1->refresh()->status);
        $this->assertSame('issued', $u2->refresh()->status);
        $this->assertSame('in_stock', $u3->refresh()->status);
        $this->assertSame(1, $part->refresh()->on_hand_qty);
    }

    public function test_stock_out_auto_picks_oldest_units_when_no_unit_ids()
    {
        $this->actingAs($this->supplyOfficer());
        $part = $this->makePart(3);
        $u1 = PartUnit::create(['part_id' => $part->id, 'serial_number' => 'OLD-1', 'status' => 'in_stock', 'created_at' => now()->subDays(5)]);
        $u2 = PartUnit::create(['part_id' => $part->id, 'serial_number' => 'OLD-2', 'status' => 'in_stock', 'created_at' => now()->subDays(3)]);
        $u3 = PartUnit::create(['part_id' => $part->id, 'serial_number' => 'NEW-1', 'status' => 'in_stock', 'created_at' => now()]);

        $this->postJson(route('inventory.parts.stock-out', ['part' => $part->id]), [
            'qty' => 2, 'reason' => 'Issue',
        ])->assertStatus(200);

        $this->assertSame('issued', $u1->refresh()->status);
        $this->assertSame('issued', $u2->refresh()->status);
        $this->assertSame('in_stock', $u3->refresh()->status);
    }

    public function test_units_endpoint_returns_units()
    {
        $this->actingAs($this->supplyOfficer());
        $part = $this->makePart(1);
        PartUnit::create(['part_id' => $part->id, 'serial_number' => 'A-1', 'property_number' => 'PN-1', 'status' => 'in_stock']);

        $resp = $this->getJson(route('inventory.parts.units', ['part' => $part->id]));
        $resp->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('units.0.serial_number', 'A-1')
            ->assertJsonPath('units.0.property_number', 'PN-1');
    }

    public function test_non_supply_cannot_add_unit()
    {
        $this->actingAs($this->makeUser(['role' => 'user']));
        $part = $this->makePart();

        $this->postJson(route('inventory.parts.units.store', ['part' => $part->id]), ['serial_number' => 'X'])
            ->assertStatus(403);
    }

    public function test_requisition_issue_keeps_on_hand_consistent_with_units()
    {
        $this->actingAs($this->supplyOfficer());
        $part = $this->makePart(3);
        PartUnit::create(['part_id' => $part->id, 'serial_number' => 'R1', 'status' => 'in_stock', 'created_at' => now()->subDays(4)]);
        PartUnit::create(['part_id' => $part->id, 'serial_number' => 'R2', 'status' => 'in_stock', 'created_at' => now()->subDays(3)]);
        PartUnit::create(['part_id' => $part->id, 'serial_number' => 'R3', 'status' => 'in_stock', 'created_at' => now()->subDays(2)]);

        $req = $this->makeTicketForConsistency($part);

        $result = (new IssuePartsForRequisitionAction)->execute($req, null);
        $this->assertTrue($result['success']);

        $part->refresh();
        $inStockCount = PartUnit::where('part_id', $part->id)->where('status', 'in_stock')->count();

        // Consistency: kung nag-iba ang on_hand, dapat tumugma rin ang bilang ng in_stock units.
        $this->assertSame(1, $part->on_hand_qty);
        $this->assertSame(1, $inStockCount);
        $this->assertSame(2, PartUnit::where('part_id', $part->id)->where('status', 'issued')->count());
    }

    public function test_stock_out_links_asset_and_request()
    {
        $this->actingAs($this->supplyOfficer());
        $part = $this->makePart(1);
        $u = PartUnit::create(['part_id' => $part->id, 'serial_number' => 'LINK-1', 'status' => 'in_stock']);

        $this->postJson(route('inventory.parts.stock-out', ['part' => $part->id]), [
            'qty' => 1, 'reason' => 'Repair', 'unit_ids' => [$u->id], 'asset_id' => 123, 'request_id' => 456,
        ])->assertStatus(200);

        $u->refresh();
        $this->assertSame('issued', $u->status);
        $this->assertSame(123, $u->asset_id);
        $this->assertSame(456, $u->request_id);
    }
}