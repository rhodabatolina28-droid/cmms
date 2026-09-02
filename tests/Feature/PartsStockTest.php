<?php

namespace Tests\Feature;

use App\Actions\PurchaseRequest\CreatePurchaseRequestAction;
use App\Models\Part;
use App\Models\PurchaseRequest;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PartsStockTest extends TestCase
{
    use RefreshDatabase;

    private $counter = 0;

    private function makeUser(array $attrs = [])
    {
        $this->counter++;
        return \App\Models\User::create(array_merge([
            'full_name' => 'Test User ' . $this->counter,
            'email' => 'user' . $this->counter . '@test.com',
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

    private function adminWithSupply()
    {
        return $this->makeUser(['role' => 'admin', 'can_supply' => true]);
    }

    /** Create a PR document via the form action (status: submitted by default). */
    private function makePr(\App\Models\User $creator, array $overrides = []): PurchaseRequest
    {
        \Illuminate\Support\Facades\Auth::login($creator);

        return (new CreatePurchaseRequestAction)->createFromForm($creator, array_merge([
            'items' => [[
                'description' => 'SSD 1TB NVMe',
                'quantity' => 2,
                'unit_cost' => 3500.00,
            ]],
            'purpose' => 'Stock replenishment.',
        ], $overrides));
    }

    public function test_supply_officer_can_create_part()
    {
        $this->actingAs($this->supplyOfficer());

        $resp = $this->postJson(route('inventory.parts.store'), [
            'item_name' => 'NVMe SSD 1TB',
            'unit' => 'pcs',
            'category' => 'Storage',
            'on_hand_qty' => 8,
            'reorder_level' => 3,
        ]);

        $resp->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('parts_stock', [
            'item_name' => 'NVMe SSD 1TB',
            'on_hand_qty' => 8,
        ]);
    }

    public function test_stock_in_increments_on_hand_and_creates_movement()
    {
        $user = $this->supplyOfficer();
        $part = Part::create([
            'item_name' => 'Toner HP',
            'unit' => 'pc',
            'on_hand_qty' => 2,
            'reorder_level' => 5,
        ]);

        $this->actingAs($user);

        $resp = $this->postJson(route('inventory.parts.stock-in', ['part' => $part->id]), [
            'qty' => 10,
            'reason' => 'Purchase received',
            'reference_type' => 'purchase',
        ]);

        $resp->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals(12, $part->refresh()->on_hand_qty);

        $this->assertDatabaseHas('parts_stock_movements', [
            'part_id' => $part->id,
            'qty_change' => 10,
            'reason' => 'Purchase received',
            'performed_by' => $user->id,
        ]);
    }

    public function test_stock_out_decrements_and_creates_movement()
    {
        $user = $this->supplyOfficer();
        $part = Part::create([
            'item_name' => 'RAM 16GB DDR4',
            'unit' => 'pcs',
            'on_hand_qty' => 12,
            'reorder_level' => 2,
        ]);

        $this->actingAs($user);

        $resp = $this->postJson(route('inventory.parts.stock-out', ['part' => $part->id]), [
            'qty' => 3,
            'reason' => 'Issue to requisition ICT-2026-0010',
            'reference_type' => 'requisition',
        ]);

        $resp->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals(9, $part->refresh()->on_hand_qty);

        $this->assertDatabaseHas('parts_stock_movements', [
            'part_id' => $part->id,
            'qty_change' => -3,
        ]);
    }

    public function test_stock_out_is_blocked_when_insufficient()
    {
        $user = $this->supplyOfficer();
        $part = Part::create([
            'item_name' => 'Screws M3',
            'unit' => 'box',
            'on_hand_qty' => 1,
            'reorder_level' => 0,
        ]);

        $this->actingAs($user);

        $resp = $this->postJson(route('inventory.parts.stock-out', ['part' => $part->id]), [
            'qty' => 5,
            'reason' => 'Issue too big',
        ]);

        // Blocked: still 422 and quantity unchanged.
        $resp->assertStatus(422)->assertJson(['success' => false]);
        $this->assertEquals(1, $part->refresh()->on_hand_qty);
        $this->assertDatabaseMissing('parts_stock_movements', [
            'part_id' => $part->id,
            'qty_change' => -5,
        ]);
    }

    public function test_non_supply_user_cannot_create_part()
    {
        $this->actingAs($this->makeUser(['role' => 'user']));

        $resp = $this->postJson(route('inventory.parts.store'), [
            'item_name' => 'Nope',
            'unit' => 'pcs',
        ]);

        $resp->assertStatus(403);
        $this->assertDatabaseCount('parts_stock', 0);
    }

    public function test_admin_without_supply_flag_cannot_create_part()
    {
        $this->actingAs($this->makeUser(['role' => 'admin', 'can_supply' => false]));

        $resp = $this->postJson(route('inventory.parts.store'), [
            'item_name' => 'Nope 2',
            'unit' => 'pcs',
        ]);

        $resp->assertStatus(403);
        $this->assertDatabaseCount('parts_stock', 0);
    }

    public function test_parts_index_page_loads_for_supply()
    {
        $this->actingAs($this->supplyOfficer());

        $resp = $this->get(route('inventory.parts'));

        $resp->assertOk();
        $resp->assertSee('Parts &amp; Consumables', false);
    }

    public function test_parts_data_pagination_returns_json()
    {
        $supply = $this->makeUser(['role' => 'supply_officer', 'region' => 'NCR']);

        // 16 parts para magkaroon ng 2 pahina (per_page = 15) — lahat ay NCR scope.
        for ($i = 1; $i <= 16; $i++) {
            Part::create([
                'item_name' => 'Test Part ' . $i,
                'unit' => 'pcs',
                'category' => 'Storage',
                'on_hand_qty' => 5,
                'reorder_level' => 2,
                'region' => 'NCR',
                'is_active' => true,
            ]);
        }

        $this->actingAs($supply);

        // Page 1 — 15 rows, may metadata ng pagination.
        $resp = $this->getJson(route('inventory.parts.data'));
        $resp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 16)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('per_page', 15)
            ->assertJsonCount(15, 'parts');

        // Page 2 (alphabetical: ...Test Part 8, 9 — kaya 'Test Part 9' ang huling item).
        $resp2 = $this->getJson(route('inventory.parts.data', ['page' => 2]));
        $resp2->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('total', 16)
            ->assertJsonCount(1, 'parts')
            ->assertJsonPath('parts.0.item_name', 'Test Part 9');
    }

    public function test_filters_work_on_parts_data()
    {
        $supply = $this->makeUser(['role' => 'supply_officer', 'region' => 'NCR']);

        Part::create([
            'item_name' => 'RAM 16GB DDR4', 'unit' => 'pcs', 'category' => 'Memory',
            'on_hand_qty' => 12, 'reorder_level' => 5, 'region' => 'NCR', 'is_active' => true,
        ]);
        Part::create([
            'item_name' => 'SSD 1TB', 'unit' => 'pcs', 'category' => 'Storage',
            'on_hand_qty' => 3, 'reorder_level' => 5, 'region' => 'NCR', 'is_active' => true,
        ]);
        // HDD-like critical (on_hand 0).
        Part::create([
            'item_name' => 'HDD 1TB', 'unit' => 'pcs', 'category' => 'Storage',
            'on_hand_qty' => 0, 'reorder_level' => 5, 'region' => 'NCR', 'is_active' => true,
        ]);

        $this->actingAs($supply);

        // Category filter — rows only sa napiling category.
        $this->getJson(route('inventory.parts.data', ['category' => 'Memory']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('parts.0.item_name', 'RAM 16GB DDR4');

        // Search filter.
        $this->getJson(route('inventory.parts.data', ['search' => 'SSD']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('parts.0.item_name', 'SSD 1TB');

        // Status filter — critical (on_hand 0).
        $this->getJson(route('inventory.parts.data', ['status' => 'critical']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('parts.0.item_name', 'HDD 1TB');
    }

    public function test_super_admin_readonly_index_loads()
    {
        $this->actingAs($this->makeUser(['role' => 'super_admin']));

        $resp = $this->get(route('super_admin.parts'));

        $resp->assertOk();
    }
public function test_parts_data_endpoint_returns_filtered_rows_and_stats()
    {
        $supply = $this->makeUser(['role' => 'supply_officer', 'region' => 'NCR']);

        Part::create(['item_name' => 'RAM 16GB DDR4', 'unit' => 'pcs', 'category' => 'Memory', 'on_hand_qty' => 2, 'reorder_level' => 5, 'region' => 'NCR', 'is_active' => true]);
        Part::create(['item_name' => 'SSD 1TB', 'unit' => 'pcs', 'category' => 'Storage', 'on_hand_qty' => 10, 'reorder_level' => 2, 'region' => 'NCR', 'is_active' => true]);

        $this->actingAs($supply);

        // Category filter affects rows AND the summary stats (data-driven).
        $resp = $this->getJson(route('inventory.parts.data', ['category' => 'Storage']));
        $resp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('stats.totalParts', 1)
            ->assertJsonPath('stats.totalOnHand', 10)
            ->assertJsonPath('parts.0.item_name', 'SSD 1TB')
            ->assertJsonPath('parts.0.level', 'ok');

        // Unfiltered stats reflect the whole scoped set.
        $this->getJson(route('inventory.parts.data'))
            ->assertOk()
            ->assertJsonPath('stats.totalParts', 2)
            ->assertJsonPath('stats.lowStockCount', 1)   // RAM is low (2 < 5)
            ->assertJsonPath('stats.criticalCount', 0);

        // Status filter narrows rows as well.
        $this->getJson(route('inventory.parts.data', ['status' => 'low']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('parts.0.item_name', 'RAM 16GB DDR4');
    }

    public function test_parts_data_endpoint_denies_non_supply()
    {
        $this->actingAs($this->makeUser(['role' => 'user']));

        $resp = $this->getJson(route('inventory.parts.data'));

        $resp->assertStatus(403);
    }

    public function test_super_admin_can_view_part_movements()
    {
        $super = $this->makeUser(['role' => 'super_admin']);
        $part = Part::create([
            'item_name' => 'Toner HP', 'unit' => 'pc', 'category' => 'Consumables',
            'on_hand_qty' => 2, 'reorder_level' => 5,
        ]);

        $this->actingAs($super);

        $this->getJson(route('super_admin.parts.movements', ['part' => $part->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('item_name', 'Toner HP')
            ->assertJsonPath('on_hand_qty', 2);
    }

    public function test_supply_cannot_use_super_admin_movements()
    {
        $supply = $this->makeUser(['role' => 'supply_officer']);
        $part = Part::create(['item_name' => 'RAM 8GB', 'unit' => 'pcs', 'on_hand_qty' => 4, 'reorder_level' => 2]);

        $this->actingAs($supply);

        $this->getJson(route('super_admin.parts.movements', ['part' => $part->id]))
            ->assertStatus(403);
    }

    public function test_super_admin_history_endpoint_available()
    {
        $super = $this->makeUser(['role' => 'super_admin']);
        $part = Part::create(['item_name' => 'SSD 1TB', 'unit' => 'pcs', 'on_hand_qty' => 3, 'reorder_level' => 1]);

        $this->actingAs($super);

        // Ang history sa super admin view ay gumagamit na ng super_admin.parts.movements
        // (hindi na ang role:admin na inventory.parts.movements) — ito ang dahilan ng "Unable to load".
        $resp = $this->getJson(route('super_admin.parts.movements', ['part' => $part->id]));
        $resp->assertOk()->assertJsonPath('success', true);

        $resp403 = $this->getJson(route('inventory.parts.movements', ['part' => $part->id]));
        $resp403->assertStatus(403);
    }

    public function test_parts_data_exposes_in_flight_prs_for_linked_part(): void
    {
        $supply = $this->supplyOfficer();
        $part = Part::create([
            'item_name' => 'SSD 1TB NVMe', 'unit' => 'pcs', 'category' => 'Storage',
            'on_hand_qty' => 0, 'reorder_level' => 3, 'region' => 'NCR',
        ]);

        // In-flight PR (submitted) referencing the part via part_id.
        $submitted = $this->makePr($supply, ['items' => [[
            'description' => 'SSD 1TB NVMe', 'quantity' => 2, 'unit_cost' => 3500.00,
            'part_id' => $part->id,
        ]]]);

        // A delivered PR for the same part must NOT be reported as in-flight.
        $delivered = $this->makePr($supply, ['items' => [[
            'description' => 'SSD 1TB NVMe', 'quantity' => 1, 'unit_cost' => 3500.00,
            'part_id' => $part->id,
        ]]]);
        $delivered->forceFill([
            'status' => PurchaseRequest::STATUS_DELIVERED,
            'delivered_at' => now(),
            'delivered_by' => $supply->id,
        ])->save();

        $this->actingAs($supply);

        $resp = $this->getJson(route('inventory.parts.data', ['search' => 'SSD 1TB NVMe']));
        $resp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'parts')
            ->assertJsonCount(1, 'parts.0.in_flight_prs')
            ->assertJsonPath('parts.0.in_flight_prs.0.pr_number', $submitted->pr_number)
            ->assertJsonPath('parts.0.in_flight_prs.0.id', $submitted->id);
    }

    public function test_parts_data_excludes_draft_prs_from_in_flight(): void
    {
        $supply = $this->supplyOfficer();
        $part = Part::create([
            'item_name' => 'RAM 8GB', 'unit' => 'pcs', 'on_hand_qty' => 0, 'region' => 'NCR',
        ]);

        $draft = $this->makePr($supply, ['items' => [[
            'description' => 'RAM 8GB', 'quantity' => 2, 'part_id' => $part->id,
        ]]]);
        $draft->forceFill(['status' => PurchaseRequest::STATUS_DRAFT])->save();

        $this->actingAs($supply);

        $this->getJson(route('inventory.parts.data', ['search' => 'RAM 8GB']))
            ->assertOk()
            ->assertJsonCount(1, 'parts')
            ->assertJsonPath('parts.0.item_name', 'RAM 8GB')
            ->assertJsonCount(0, 'parts.0.in_flight_prs');
    }
}