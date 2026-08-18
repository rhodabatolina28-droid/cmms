<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryExportTest extends TestCase
{
    use RefreshDatabase;

    private $counter = 0;

    private function makeUser(array $attrs = [])
    {
        $this->counter++;
        return User::create(array_merge([
            'full_name' => 'Inv Export User ' . $this->counter,
            'email' => 'invexp' . $this->counter . '@test.com',
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

    /** role:admin middleware + ExportInventoryAction's canProcessSupply() */
    private function adminSupplier()
    {
        return $this->makeUser(['role' => 'admin', 'can_supply' => true]);
    }

    private function asset(array $attrs = [])
    {
        return InventoryAsset::create(array_merge([
            'category' => 'Desktop',
            'item_name' => 'Desktop',
            'region' => 'NCR',
            'status' => 'Spare',
        ], $attrs));
    }

    public function test_export_includes_parent_set_column()
    {
        $this->actingAs($this->adminSupplier());

        $parent = $this->asset(['item_name' => 'CPU Parent', 'par_number' => 'PAR-1', 'status' => 'Active']);
        $component = $this->asset(['item_name' => 'Monitor Child', 'par_number' => 'PAR-1', 'parent_asset_id' => $parent->asset_id]);
        $standalone = $this->asset(['item_name' => 'Spare Laptop', 'status' => 'Spare']);

        $content = $this->get(route('inventory.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString('Parent Set', $content);
        $this->assertStringContainsString('CPU Parent', $content);
        $this->assertStringContainsString('Monitor Child', $content);
        $this->assertStringContainsString("Component of #{$parent->asset_id}", $content);
        $this->assertStringContainsString('Set Parent (#1 components)', $content);
        $this->assertStringContainsString('Spare Laptop', $content);
    }
}