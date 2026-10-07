<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QR Print + Scan Hub Plan §9.6 (PHYSICAL_COUNT_CUSTODIAN_GROUP.md) — Phase C.
 *
 * The scan hub (/r/{id}, scan/asset-info) gains:
 *  - a SET panel: parent shows "Set Components (n)" with links to each
 *    component (/r/{child}); a component shows "Component of #parent" with a
 *    parent link + sibling links — ONE parent QR = whole set access (C2);
 *  - hub actions: [Scan QR] (inline camera via public/js/html5-qrcode.min.js)
 *    and [Print QR sticker] (→ inventory.qr-sticker) — the print action is
 *    role-gated to canProcessSupply (same gate as the batch print page);
 *  - EXISTING untouched: header details, "Other Assets of {user}" panel,
 *    service history, guest → login → back-to-/r/{id} flow.
 */
class ScanSetHubTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    public function test_parent_scan_shows_set_components_and_print_action(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $owner = $this->user(['full_name' => 'Juan Dela Cruz']);
        $parent = $this->asset(['item_name' => 'Hub Parent Desktop', 'assigned_to_user' => $owner->id]);
        $child = $this->asset(['item_name' => 'Hub Child Monitor', 'assigned_to_user' => $owner->id]);
        $child->forceFill(['parent_asset_id' => $parent->asset_id])->save();
        $this->asset(['item_name' => 'Hub Other Printer', 'assigned_to_user' => $owner->id]);

        $html = $this->actingAs($supply)
            ->get(route('qr.redirect', $parent->asset_id))
            ->assertOk()
            ->getContent();

        // Set panel: parent view — components listed and linkable.
        $this->assertStringContainsString('Set Components (', $html);
        $this->assertStringContainsString('Hub Child Monitor', $html);
        $this->assertStringContainsString('/r/' . $child->asset_id, $html);
        $this->assertStringNotContainsString('Component of #' . $parent->asset_id, $html);

        // Hub actions: scan button always; print sticker for supply gate.
        $this->assertStringContainsString('id="hubScanBtn"', $html);
        $this->assertStringContainsString('Print QR sticker', $html);
        $this->assertStringContainsString('/inventory/qr-sticker/' . $parent->asset_id, $html);

        // Regression: existing custodian panel stays.
        $this->assertStringContainsString('Other Assets of Juan Dela Cruz', $html);
    }

    public function test_component_scan_shows_parent_and_siblings(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $owner = $this->user(['full_name' => 'Juan Dela Cruz']);
        $parent = $this->asset(['item_name' => 'Hub Parent Desktop', 'assigned_to_user' => $owner->id]);
        $child = $this->asset(['item_name' => 'Hub Child Monitor', 'assigned_to_user' => $owner->id]);
        $sibling = $this->asset(['item_name' => 'Hub Sibling Keyboard', 'assigned_to_user' => $owner->id]);
        $child->forceFill(['parent_asset_id' => $parent->asset_id])->save();
        $sibling->forceFill(['parent_asset_id' => $parent->asset_id])->save();

        $html = $this->actingAs($supply)
            ->get(route('qr.redirect', $child->asset_id))
            ->assertOk()
            ->getContent();

        // Component view: parent reference + sibling links, no own set panel.
        $this->assertStringContainsString('Component of #' . $parent->asset_id, $html);
        $this->assertStringContainsString('/r/' . $parent->asset_id, $html);
        $this->assertStringContainsString('Hub Sibling Keyboard', $html);
        $this->assertStringNotContainsString('Set Components (', $html);
    }

    public function test_print_action_is_gated_to_supply_but_scan_is_shown_to_it(): void
    {
        $it = $this->user(['role' => 'it']);
        $parent = $this->asset(['item_name' => 'Hub IT Parent']);

        $html = $this->actingAs($it)
            ->get(route('qr.redirect', $parent->asset_id))
            ->assertOk()
            ->getContent();

        // IT sees the scan hub action + set section context…
        $this->assertStringContainsString('id="hubScanBtn"', $html);
        // …but NOT the print action (no supply access → route would 403).
        $this->assertStringNotContainsString('/inventory/qr-sticker/', $html);
        $this->assertStringNotContainsString('Print QR sticker', $html);
    }

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'SSH User ' . $this->counter,
            'email' => 'ssh-user-' . $this->counter . '@test.com',
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
            'item_name' => 'SSH Asset',
            'serial_number' => 'SSH-SN-' . $this->counter,
            'property_number' => 'PROP-SSH-' . $this->counter,
            'par_number' => 'PAR-SSH-' . $this->counter,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
            'status' => 'Active',
        ], $attributes));
    }
}
