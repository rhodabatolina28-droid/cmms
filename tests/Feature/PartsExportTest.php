<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\User;
use App\Models\PartUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartsExportTest extends TestCase
{
    use RefreshDatabase;

    private $counter = 0;

    private function makeUser(array $attrs = [])
    {
        $this->counter++;
        return User::create(array_merge([
            'full_name' => 'Export User ' . $this->counter,
            'email' => 'export' . $this->counter . '@test.com',
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

    public function test_export_csv_returns_rows_with_headers()
    {
        $this->actingAs($this->supplyOfficer());

        Part::create(['item_name' => 'RAM 16GB DDR4', 'unit' => 'pcs', 'category' => 'Memory', 'on_hand_qty' => 8, 'reorder_level' => 3, 'region' => 'NCR', 'is_active' => true]);
        Part::create(['item_name' => 'SSD 1TB', 'unit' => 'pcs', 'category' => 'Storage', 'on_hand_qty' => 0, 'reorder_level' => 2, 'region' => 'NCR', 'is_active' => true]);

        $resp = $this->get(route('inventory.parts.export'));
        $resp->assertOk();

        $content = $resp->streamedContent();
        $this->assertStringContainsString('Item Name', $content);
        $this->assertStringContainsString('On-hand Qty', $content);
        $this->assertStringContainsString('RAM 16GB DDR4', $content);
        $this->assertStringContainsString('SSD 1TB', $content);
        $this->assertStringContainsString('CRITICAL', $content);
    }

    public function test_export_respects_filters()
    {
        $this->actingAs($this->supplyOfficer());

        Part::create(['item_name' => 'RAM 16GB DDR4', 'unit' => 'pcs', 'category' => 'Memory', 'on_hand_qty' => 8, 'reorder_level' => 3, 'region' => 'NCR', 'is_active' => true]);
        Part::create(['item_name' => 'SSD 1TB', 'unit' => 'pcs', 'category' => 'Storage', 'on_hand_qty' => 5, 'reorder_level' => 2, 'region' => 'NCR', 'is_active' => true]);

        $resp = $this->get(route('inventory.parts.export', ['category' => 'Memory']));
        $resp->assertOk();

        $content = $resp->streamedContent();
        $this->assertStringContainsString('RAM 16GB DDR4', $content);
        $this->assertStringNotContainsString('SSD 1TB', $content);
    }

    public function test_export_denies_admin_without_supply()
    {
        $this->actingAs($this->makeUser(['role' => 'admin', 'can_supply' => false]));

        $resp = $this->get(route('inventory.parts.export'));
        $resp->assertStatus(403);
    }

    public function test_super_admin_can_export()
    {
        $this->actingAs($this->makeUser(['role' => 'super_admin']));

        Part::create(['item_name' => 'Toner HP', 'unit' => 'pc', 'on_hand_qty' => 3, 'reorder_level' => 5, 'region' => 'NCR', 'is_active' => true]);

        $resp = $this->get(route('super_admin.parts.export'));
        $resp->assertOk();
        $this->assertStringContainsString('Toner HP', $resp->streamedContent());
    }

    public function test_export_emits_one_row_per_serialized_unit_with_custodian()
    {
        $this->actingAs($this->supplyOfficer());

        $part = Part::create(['item_name' => 'RAM 16GB DDR4', 'unit' => 'pcs', 'category' => 'Memory', 'on_hand_qty' => 2, 'reorder_level' => 3, 'region' => 'NCR', 'is_active' => true]);
        $custodian = $this->makeUser();
        PartUnit::create(['part_id' => $part->id, 'serial_number' => 'RAM-S001', 'property_number' => 'PROP-1', 'unit_value' => 1200, 'status' => 'in_stock']);
        PartUnit::create(['part_id' => $part->id, 'serial_number' => 'RAM-S002', 'property_number' => 'PROP-2', 'unit_value' => 1200, 'status' => 'issued', 'issued_to' => $custodian->id]);

        $content = $this->get(route('inventory.parts.export'))->assertOk()->streamedContent();

        // Unit columns are present in the header.
        $this->assertStringContainsString('Serial No', $content);
        $this->assertStringContainsString('Unit Status', $content);
        $this->assertStringContainsString('Issued To', $content);

        // One row per unit: both serials present with their status and custodian.
        $this->assertStringContainsString('RAM-S001', $content);
        $this->assertStringContainsString('RAM-S002', $content);
        $this->assertStringContainsString('IN_STOCK', $content);
        $this->assertStringContainsString('ISSUED', $content);
        $this->assertStringContainsString($custodian->full_name, $content);
    }
}