<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QR Print + Scan Hub Plan §9.4 (PHYSICAL_COUNT_CUSTODIAN_GROUP.md) — Phase A.
 *
 * Batch QR Sticker Print becomes:
 *  - custodian-grouped (group header per assigned user, "Unassigned / Spare"
 *    bucket, per-group select-all) — same pattern as Physical Count grouping;
 *  - set-aware: only the PARENT is selectable ("1 parent QR = whole set"),
 *    component rows are indented + disabled and read "component of #X";
 *  - counter reports stickers AND covered pieces (selected + their components).
 *
 * Data contract: inventory.data must expose custodian (name/office) and set
 * fields (parent_asset_id, components_count) so the page can group/select.
 */
class QrBatchPrintTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    public function test_inventory_data_exposes_custodian_and_set_fields(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $owner = $this->user([
            'full_name' => 'Juan Dela Cruz',
            'office' => 'Property & Supply',
        ]);
        $parent = $this->asset(['item_name' => 'Parent Set Asset', 'assigned_to_user' => $owner->id]);
        $child = $this->asset(['item_name' => 'Child Component', 'assigned_to_user' => $owner->id]);
        $child->forceFill(['parent_asset_id' => $parent->asset_id])->save();

        $json = $this->actingAs($supply)
            ->get(route('inventory.data', ['per_page' => 100]))
            ->assertOk()
            ->json();

        $this->assertTrue($json['success']);
        $byId = collect($json['assets'])->keyBy('asset_id');

        // Custodian fields (grouping header)
        $this->assertSame('Juan Dela Cruz', $byId[$parent->asset_id]['assigned_to_name']);
        $this->assertSame('Property & Supply', $byId[$parent->asset_id]['assigned_to_office']);

        // Set fields (set-aware selection)
        $this->assertNull($byId[$parent->asset_id]['parent_asset_id']);
        $this->assertSame(1, $byId[$parent->asset_id]['components_count']);
        $this->assertSame($parent->asset_id, $byId[$child->asset_id]['parent_asset_id']);
    }

    public function test_batch_page_renders_custodian_groups_and_set_aware_rows(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);

        $response = $this->actingAs($supply)->get(route('inventory.qr-batch'));

        $response->assertOk();
        $html = $response->getContent();

        // 1) Custodian grouping (§9.4 item 1)
        $this->assertStringContainsString('groupByCustodian', $html);
        $this->assertStringContainsString('Unassigned / Spare', $html);
        $this->assertStringContainsString('group-row', $html);

        // 2) Set-aware selection (§9.4 item 2): parents selectable, components
        //    indented + disabled with an explicit "component of #X" note.
        $this->assertStringContainsString('▣ SET', $html);
        $this->assertStringContainsString('component of #', $html);
        $this->assertStringContainsString('disabled', $html);

        // 3) Counter (§9.4 item 3): stickers AND covered pieces.
        $this->assertStringContainsString('covers ${', $html);

        // 4) Regression guards — existing flow must stay intact.
        $this->assertStringContainsString('loadAllAssets', $html);
        $this->assertStringContainsString('Print Selected', $html);
        $this->assertStringContainsString('/inventory/qr-sticker/', $html);
        $this->assertStringContainsString("e.target.closest('input[type=\"checkbox\"]')", $html);
    }

    /**
     * Phase M1 — mobile UX: the grouped/set-aware table must not require
     * horizontal scrolling on phones.
     *
     *  - table renders as stacked cards on ≤767px (group header = card header
     *    with a always-visible "select" button, selected/covered tint on the
     *    ROW not the cells, checkbox no longer scrolls out of view);
     *  - count + Print move into a sticky bottom bar (always reachable without
     *    scrolling back to the top);
     *  - the old "Swipe table horizontally" hint is gone on mobile.
     */
    public function test_batch_page_has_mobile_card_layout_and_sticky_print_bar(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);

        $response = $this->actingAs($supply)->get(route('inventory.qr-batch'));

        $response->assertOk();
        $html = $response->getContent();

        // 1) Sticky bottom print bar (server markup + JS wiring in updateUI)
        $this->assertStringContainsString('id="mobilePrintBar"', $html);
        $this->assertStringContainsString('id="mobileSelectedCount"', $html);
        $this->assertStringContainsString('id="mobilePrintBtn"', $html);
        $this->assertStringContainsString("getElementById('mobileSelectedCount')", $html);

        // 2) Table → cards on mobile (CSS-only transform, same render path)
        $this->assertStringContainsString('tr.asset-row.selected', $html);       // row-level selected tint
        $this->assertStringContainsString('.group-row .group-select', $html);    // group select button in card header
        $this->assertStringContainsString('header-actions .btn-print', $html);   // header print hidden (lives in bar now)

        // 3) Header count/print duplicated into the bar text
        $this->assertStringContainsString('covers 0 pcs', $html);
    }

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'QBP User ' . $this->counter,
            'email' => 'qbp-user-' . $this->counter . '@test.com',
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
            'item_name' => 'QBP Asset',
            'serial_number' => 'QBP-SN-' . $this->counter,
            'property_number' => 'PROP-QBP-' . $this->counter,
            'par_number' => 'PAR-QBP-' . $this->counter,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
            'status' => 'Spare',
        ], $attributes));
    }
}
