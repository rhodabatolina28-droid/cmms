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

    public function test_parent_scan_shows_set_components_and_other_assets(): void
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

        // Regression: existing custodian panel stays.
        $this->assertStringContainsString('Other Assets of Juan Dela Cruz', $html);

        // User decision (2026-10-07): NO action buttons on the scan hub —
        // the [Scan QR] / [Print QR sticker] bar (and its camera overlay/JS)
        // was removed right after it shipped. Scanning stays a pure VIEW.
        $this->assertStringNotContainsString('id="hubScanBtn"', $html);
        $this->assertStringNotContainsString('Print QR sticker', $html);
        $this->assertStringNotContainsString('openHubScanner', $html);
        $this->assertStringNotContainsString('hubScanOverlay', $html);
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

    public function test_scan_and_print_buttons_absent_for_all_roles(): void
    {
        $it = $this->user(['role' => 'it']);
        $supply = $this->user(['role' => 'supply_officer']);
        $parent = $this->asset(['item_name' => 'Hub IT Parent']);

        // IT viewer: page renders, but NO hub action buttons / scanner JS.
        $html = $this->actingAs($it)
            ->get(route('qr.redirect', $parent->asset_id))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('id="hubScanBtn"', $html);
        $this->assertStringNotContainsString('Print QR sticker', $html);
        $this->assertStringNotContainsString('openHubScanner', $html);

        // Supply viewer: same — buttons removed for every role.
        $html = $this->actingAs($supply)
            ->get(route('qr.redirect', $parent->asset_id))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('id="hubScanBtn"', $html);
        $this->assertStringNotContainsString('Print QR sticker', $html);
        $this->assertStringNotContainsString('/inventory/qr-sticker/', $html);
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
