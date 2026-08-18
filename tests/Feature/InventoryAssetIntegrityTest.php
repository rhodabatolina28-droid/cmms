<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAssetIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'Asset User ' . $this->counter,
            'email' => 'asset-user-' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'can_supply' => false,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
        ], $attributes));
    }

    private function asset(array $attributes = []): InventoryAsset
    {
        return InventoryAsset::create(array_merge([
            'category' => 'Desktop',
            'item_name' => 'Desktop Set Parent',
            'serial_number' => 'SET-CPU-' . (++$this->counter),
            'property_number' => 'PROP-SET-' . $this->counter,
            'par_number' => 'PAR-2026-SET-' . $this->counter,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
            'status' => 'Spare',
        ], $attributes));
    }

    public function test_manual_component_inherits_the_parent_par_and_custodian(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $custodian = $this->user();
        $parent = $this->asset([
            'assigned_to_user' => $custodian->id,
            'status' => 'Active',
            'acquisition_cost' => 45000,
            'property_number' => 'PROP-SET-100',
            'par_number' => 'PAR-2026-0100',
        ]);

        $this->actingAs($supply)
            ->postJson(route('inventory.store'), [
                'category' => 'Monitor',
                'item_name' => 'Set Monitor',
                'serial_number' => 'SET-MON-100',
                'property_number' => 'PROP-SET-100',
                'parent_asset_id' => $parent->asset_id,
                'status' => 'Active',
                'acquisition_cost' => 8000,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $component = InventoryAsset::where('serial_number', 'SET-MON-100')->firstOrFail();
        $this->assertSame($parent->asset_id, $component->parent_asset_id);
        $this->assertSame($parent->par_number, $component->par_number);
        $this->assertSame($custodian->id, $component->assigned_to_user);
    }

    public function test_unrelated_asset_cannot_reuse_a_set_property_number(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $this->asset(['property_number' => 'PROP-SET-200']);

        $this->actingAs($supply)
            ->postJson(route('inventory.store'), [
                'category' => 'Laptop',
                'item_name' => 'Unrelated Laptop',
                'serial_number' => 'LAPTOP-200',
                'property_number' => 'PROP-SET-200',
                'status' => 'Spare',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This property number already belongs to an unrelated asset or PAR set.');
    }

    public function test_parent_transfer_updates_components_history_and_custodian_notifications(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $oldCustodian = $this->user();
        $newCustodian = $this->user();
        $parent = $this->asset([
            'assigned_to_user' => $oldCustodian->id,
            'status' => 'Active',
            'acquisition_cost' => 45000,
            'property_number' => 'PROP-SET-300',
            'par_number' => 'PAR-2026-0300',
        ]);
        $component = $this->asset([
            'category' => 'Monitor',
            'item_name' => 'Set Monitor',
            'serial_number' => 'SET-MON-300',
            'property_number' => 'PROP-SET-300',
            'par_number' => $parent->par_number,
            'parent_asset_id' => $parent->asset_id,
            'assigned_to_user' => $oldCustodian->id,
            'status' => 'Active',
            'acquisition_cost' => 8000,
        ]);

        $this->actingAs($supply)
            ->putJson(route('inventory.update', $parent->asset_id), [
                'item_name' => $parent->item_name,
                'serial_number' => $parent->serial_number,
                'property_number' => $parent->property_number,
                'category' => $parent->category,
                'status' => 'Active',
                'assigned_to_user' => $newCustodian->id,
                'acquisition_cost' => 45000,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $parent->refresh();
        $component->refresh();
        $this->assertSame($newCustodian->id, $component->assigned_to_user);
        $this->assertSame($parent->par_number, $component->par_number);
        $this->assertDatabaseHas('inventory_history', [
            'asset_id' => $component->asset_id,
            'action' => 'Set Custodian Updated',
            'previous_user_id' => $oldCustodian->id,
            'new_user_id' => $newCustodian->id,
        ]);
        $this->assertSame(1, Notification::where('user_id', $oldCustodian->id)->where('type', 'Asset Transfer')->count());
        $this->assertSame(1, Notification::where('user_id', $newCustodian->id)->where('type', 'Asset Transfer')->count());
    }

    public function test_spare_asset_needs_accountability_fields_before_activation(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $asset = $this->asset(['property_number' => null, 'serial_number' => 'SPARE-400']);

        $this->actingAs($supply)
            ->putJson(route('inventory.update', $asset->asset_id), [
                'item_name' => $asset->item_name,
                'serial_number' => $asset->serial_number,
                'category' => $asset->category,
                'status' => 'Active',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Before an asset can become Active, provide its custodian, property number, acquisition cost.');

        $this->assertSame('Spare', $asset->refresh()->status);
    }

    public function test_parent_picker_only_lists_eligible_parents(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $parent = $this->asset([
            'item_name' => 'Eligible Parent CPU',
            'assigned_to_user' => $this->user()->id,
            'status' => 'Active',
            'acquisition_cost' => 45000,
        ]);
        // Standalone but has no PAR — not a valid set parent
        $this->asset(['serial_number' => 'NO-PAR-900', 'par_number' => null, 'property_number' => null]);
        // A component — cannot be used as a parent
        $this->asset([
            'category' => 'Monitor',
            'item_name' => 'Set Monitor',
            'serial_number' => 'MON-900',
            'par_number' => $parent->par_number,
            'parent_asset_id' => $parent->asset_id,
        ]);

        $this->actingAs($supply)
            ->getJson(route('inventory.parent-assets'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assets.0.asset_id', $parent->asset_id)
            ->assertJsonCount(1, 'assets');
    }

}
