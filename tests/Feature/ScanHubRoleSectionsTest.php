<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scan hub (/r/{id}, scan/asset-info) role-based sections — 2026-10-08 decision.
 *
 *  - Supply Officer / admin (canProcessSupply): Other Assets + [View Full
 *    Inventory Profile] (→ inventory.detail) ONLY — NO "Preventive Maintenance"
 *    section, NO "Recent Service History", NO Conduct PM;
 *  - IT / System Admin: keep the PM section + Recent Service History (their
 *    workflow); super_admin keeps the profile link (→ super_admin.inventory.detail);
 *  - IT: NO profile button (unchanged — only supply/admin + super_admin get it).
 *
 * Viewer-role gating: ScanController passes $user = Auth::user() (the VIEWER),
 * so the blade gates on $user->role, not the asset owner's role.
 */
class ScanHubRoleSectionsTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    public function test_supply_sees_only_other_assets_and_profile_link(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $owner = $this->user(['full_name' => 'Juan Dela Cruz']);
        $asset = $this->asset(['assigned_to_user' => $owner->id]);
        $this->asset(['item_name' => 'Roles Other Printer', 'assigned_to_user' => $owner->id]);

        $html = $this->actingAs($supply)
            ->get(route('qr.redirect', $asset->asset_id))
            ->assertOk()
            ->getContent();

        // Kept: custodian panel + profile link (parity with System Admin).
        $this->assertStringContainsString('Other Assets of Juan Dela Cruz', $html);
        $this->assertStringContainsString('View Full Inventory Profile', $html);
        $this->assertStringContainsString(route('inventory.detail', $asset->asset_id), $html);

        // Removed for supply/admin: PM section + Recent Service History + Conduct PM.
        $this->assertStringNotContainsString('Preventive Maintenance', $html);
        $this->assertStringNotContainsString('No PM record yet', $html);
        $this->assertStringNotContainsString('Recent Service History', $html);
        $this->assertStringNotContainsString('No service history found.', $html);
        $this->assertStringNotContainsString('Conduct PM', $html);
    }

    public function test_super_admin_keeps_pm_history_and_profile_link(): void
    {
        $admin = $this->user(['role' => 'super_admin']);
        $owner = $this->user(['full_name' => 'Juan Dela Cruz']);
        $asset = $this->asset(['assigned_to_user' => $owner->id]);

        $html = $this->actingAs($admin)
            ->get(route('qr.redirect', $asset->asset_id))
            ->assertOk()
            ->getContent();

        // Kept: PM section + service history + profile link (super_admin route).
        $this->assertStringContainsString('Preventive Maintenance', $html);
        $this->assertStringContainsString('Recent Service History', $html);
        $this->assertStringContainsString('View Full Inventory Profile', $html);
        $this->assertStringContainsString(route('super_admin.inventory.detail', $asset->asset_id), $html);

        // No upcoming PM in fixture → no Conduct PM action.
        $this->assertStringNotContainsString('Conduct PM', $html);
    }

    public function test_it_keeps_pm_and_history_without_profile_button(): void
    {
        $it = $this->user(['role' => 'it']);
        $owner = $this->user(['full_name' => 'Juan Dela Cruz']);
        $asset = $this->asset(['assigned_to_user' => $owner->id]);

        $html = $this->actingAs($it)
            ->get(route('qr.redirect', $asset->asset_id))
            ->assertOk()
            ->getContent();

        // Kept: PM section + service history (IT workflow untouched).
        $this->assertStringContainsString('Preventive Maintenance', $html);
        $this->assertStringContainsString('Recent Service History', $html);

        // Profile button is for supply/admin + super_admin only (unchanged for IT).
        $this->assertStringNotContainsString('View Full Inventory Profile', $html);
    }

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'Roles User ' . $this->counter,
            'email' => 'roles-user-' . $this->counter . '@test.com',
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
        $this->counter++;

        return InventoryAsset::create(array_merge([
            'category' => 'Desktop',
            'item_name' => 'Roles Asset',
            'serial_number' => 'ROLES-SN-' . $this->counter,
            'property_number' => 'PROP-ROLES-' . $this->counter,
            'par_number' => 'PAR-ROLES-' . $this->counter,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
            'status' => 'Active',
        ], $attributes));
    }
}