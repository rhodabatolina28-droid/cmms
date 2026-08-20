<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

public function test_user_org_scope_change_syncs_assigned_assets_and_components(): void
    {
        $super = $this->user(['role' => 'super_admin', 'region' => 'NCR', 'branch' => 'RCMB']);
        $target = $this->user(['role' => 'user', 'region' => 'NCR', 'branch' => 'RCMB', 'office' => 'RESEARCH AND INFORMATION DIVISION', 'department' => 'INTERNAL SERVICES DEPARTMENT']);
        $otherUser = $this->user(['role' => 'user', 'region' => 'NCR', 'branch' => 'RCMB', 'office' => 'FINANCIAL AND MANAGEMENT DIVISION', 'department' => 'INTERNAL SERVICES DEPARTMENT']);

        $parent = $this->asset([
            'assigned_to_user' => $target->id,
            'status' => 'Active',
            'acquisition_cost' => 45000,
            'property_number' => 'PROP-SYNC-1',
            'par_number' => 'PAR-SYNC-1',
            'region' => 'NCR', 'branch' => 'RCMB',
            'office' => 'RESEARCH AND INFORMATION DIVISION',
            'department' => 'INTERNAL SERVICES DEPARTMENT',
        ]);
        $component = $this->asset([
            'item_name' => 'Sync Monitor',
            'assigned_to_user' => $target->id,
            'status' => 'Active',
            'parent_asset_id' => $parent->asset_id,
            'acquisition_cost' => 8000,
            'property_number' => 'PROP-SYNC-1',
            'par_number' => 'PAR-SYNC-1',
            'office' => 'RESEARCH AND INFORMATION DIVISION',
            'department' => 'INTERNAL SERVICES DEPARTMENT',
        ]);
        // Another user's asset must NOT be moved.
        $otherUserAsset = $this->asset([
            'assigned_to_user' => $otherUser->id,
            'status' => 'Active',
            'office' => 'FINANCIAL AND MANAGEMENT DIVISION',
            'department' => 'INTERNAL SERVICES DEPARTMENT',
        ]);

        $this->actingAs($super)
            ->putJson(route('super_admin.users.update', $target->id), [
                'full_name' => $target->full_name,
                'email' => $target->email,
                'role' => 'user',
                'region' => 'NCR',
                'branch' => 'RCMB',
                'office' => 'FINANCIAL AND MANAGEMENT DIVISION',
                'department' => 'INTERNAL SERVICES DEPARTMENT',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        // Parent + component moved to the user's new office.
        $this->assertSame('FINANCIAL AND MANAGEMENT DIVISION', $parent->refresh()->office);
        $this->assertSame('FINANCIAL AND MANAGEMENT DIVISION', $component->refresh()->office);
        // Other user's asset untouched.
        $this->assertSame('FINANCIAL AND MANAGEMENT DIVISION', $otherUserAsset->refresh()->office);
        // History row recorded for the moved parent.
                $this->assertDatabaseHas('inventory_history', [
            'asset_id' => $parent->asset_id,
            'action' => 'Org Scope Updated',
        ]);
    }

    /**
     * Verification Phase (read-only): the verify command must report existing
     * set-integrity violations without mutating anything. Mirrors the
     * transactional probe validated against a live DB.
     */
    public function test_verification_command_reports_integrity_violations(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $uid = $supply->id;
        $other = $this->user()->id;
        $third = $this->user()->id;

        // Parent with components but NO par + a child that mismatches par, custodian and org.
        $parent = $this->asset([
            'item_name' => 'P1 No PAR', 'serial_number' => 'P1-NOPAR',
            'par_number' => null, 'assigned_to_user' => $uid, 'status' => 'Active',
            'acquisition_cost' => 45000,
        ]);
        $component = $this->asset([
            'item_name' => 'C1 mismatch', 'serial_number' => 'C1-MISMATCH',
            'category' => 'Monitor', 'parent_asset_id' => $parent->asset_id,
            'par_number' => 'WRONG-PAR', 'assigned_to_user' => $other, 'status' => 'Active',
            'region' => 'NCR2', 'branch' => 'B2', 'office' => 'O2', 'department' => 'D2',
        ]);

        // Orphan: real parent then soft-delete -> component still points at it.
        $orphanParent = $this->asset(['item_name' => 'P-orphan', 'serial_number' => 'PORPHAN', 'par_number' => 'PAR-ORPHAN']);
        $this->asset([
            'item_name' => 'Orphan C', 'serial_number' => 'ORPHAN-1', 'category' => 'Monitor',
            'parent_asset_id' => $orphanParent->asset_id, 'par_number' => 'PAR-ORPHAN',
            'assigned_to_user' => $uid, 'status' => 'Active',
        ]);
        $orphanParent->delete();

        // Nested: a component (C2) used as a parent of another component (C3).
        $p2 = $this->asset(['item_name' => 'P2', 'serial_number' => 'P2-NEST', 'par_number' => 'PAR-NEST', 'assigned_to_user' => $uid, 'status' => 'Active']);
        $c2 = $this->asset(['item_name' => 'C2-as-parent', 'serial_number' => 'C2-NEST', 'category' => 'Monitor', 'parent_asset_id' => $p2->asset_id, 'par_number' => 'PAR-NEST', 'assigned_to_user' => $uid, 'status' => 'Active']);
        $this->asset(['item_name' => 'C3 under C2', 'serial_number' => 'C3-NEST', 'category' => 'Monitor', 'parent_asset_id' => $c2->asset_id, 'par_number' => 'PAR-NEST', 'assigned_to_user' => $uid, 'status' => 'Active']);

        // Property number reused across two unrelated standalone roots.
        $this->asset(['item_name' => 'P3 reuse', 'serial_number' => 'P3-REUSE', 'property_number' => 'PROP-REUSE', 'category' => 'Laptop']);
        $this->asset(['item_name' => 'P4 reuse', 'serial_number' => 'P4-REUSE', 'property_number' => 'PROP-REUSE', 'category' => 'Laptop']);

        // Status/custodian inconsistency (must bypass the boot auto-correction).
        InventoryAsset::withoutEvents(fn () => InventoryAsset::create([
            'category' => 'Printer/Scanner', 'item_name' => 'Active no custodian', 'serial_number' => 'A1-NOCUST',
            'status' => 'Active', 'region' => 'NCR', 'branch' => 'Main Office',
        ]));
        InventoryAsset::withoutEvents(fn () => InventoryAsset::create([
            'category' => 'Laptop', 'item_name' => 'Spare with custodian', 'serial_number' => 'A2-WITHCUST',
            'status' => 'Spare', 'assigned_to_user' => $third, 'region' => 'NCR', 'branch' => 'Main Office',
        ]));

        Artisan::call('inventory:verify-asset-sets', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertSame(1, $payload['summary']['orphan_components']);
        $this->assertSame(1, $payload['summary']['nested_component_as_parent']);
        $this->assertSame(1, $payload['summary']['parent_missing_par']);
        $this->assertSame(1, $payload['summary']['component_par_mismatch']);
        $this->assertSame(1, $payload['summary']['component_custodian_mismatch']);
        $this->assertSame(1, $payload['summary']['component_org_mismatch']);
        $this->assertSame(2, $payload['summary']['property_number_cross_set_reuse']);
        $this->assertSame(2, $payload['summary']['status_custodian_inconsistency']);
        $this->assertSame(10, $payload['total']);

                // Read-only: the scan must not have mutated any seeded data.
        $this->assertDatabaseHas('inventory_assets', [
            'asset_id' => $component->asset_id,
            'par_number' => 'WRONG-PAR',
            'assigned_to_user' => $other,
            'status' => 'Active',
        ]);
    }
}
