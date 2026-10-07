<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\User;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QR Print + Scan Hub Plan §9.7 (PHYSICAL_COUNT_CUSTODIAN_GROUP.md) — lifecycle
 * matrix: "printed once" must hold through transfers and status changes.
 *
 *  - Reassign/transfer  → custodian panels follow live; QR payload UNCHANGED
 *    (zero reprint — the QR encodes only {APP_URL}/r/{asset_id}).
 *  - Status → Spare     → asset stays scannable, stays in Other Assets and in
 *    the batch data feed (only For Disposal/Scrapped drop out).
 *  - Status → For Disposal → leaves Other Assets + Set panel, but a direct
 *    scan of its own QR still renders the page (audit visibility).
 *  - Set counters/sticker flag must track LIVE components only (a disposed
 *    component may not inflate SET(n)/covers-n or keep a stale SET flag).
 */
class QrLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    public function test_reassign_moves_custodian_panels_but_never_changes_qr(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $juan = $this->user(['full_name' => 'Juan Dela Cruz']);
        $maria = $this->user(['full_name' => 'Maria Santos']);
        $parent = $this->asset(['item_name' => 'Lifecycle Desktop', 'assigned_to_user' => $juan->id]);
        $child = $this->asset(['item_name' => 'Lifecycle Monitor', 'assigned_to_user' => $juan->id]);
        $child->forceFill(['parent_asset_id' => $parent->asset_id])->save();
        $this->asset(['item_name' => 'Juan Only Printer', 'assigned_to_user' => $juan->id]);

        $qrBefore = QrCodeService::generateForAsset($parent);

        // BEFORE: hub groups under Juan.
        $html = $this->actingAs($supply)
            ->get(route('qr.redirect', $parent->asset_id))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Other Assets of Juan Dela Cruz', $html);
        $this->assertStringContainsString('Juan Only Printer', $html);
        $this->assertStringNotContainsString('Other Assets of Maria Santos', $html);

        // TRANSFER the whole set to Maria.
        $parent->forceFill(['assigned_to_user' => $maria->id])->save();
        $child->forceFill(['assigned_to_user' => $maria->id])->save();

        // AFTER: panels follow the new custodian — same printed sticker.
        $html = $this->actingAs($supply)
            ->get(route('qr.redirect', $parent->asset_id))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Other Assets of Maria Santos', $html);
        $this->assertStringContainsString('Lifecycle Monitor', $html); // set panel intact
        $this->assertStringNotContainsString('Other Assets of Juan Dela Cruz', $html);

        // Batch feed follows too (client-side grouping source).
        $json = $this->actingAs($supply)
            ->get(route('inventory.data', ['per_page' => 100]))
            ->assertOk()
            ->json();
        $row = collect($json['assets'])->firstWhere('asset_id', $parent->asset_id);
        $this->assertSame('Maria Santos', $row['assigned_to_name']);

        // ZERO REPRINT: payload = url + id only → byte-identical after transfer.
        $qrAfter = QrCodeService::generateForAsset($parent->fresh());
        $this->assertSame($qrBefore, $qrAfter, 'QR must never change on reassignment (printed once)');
    }

    public function test_unassigning_makes_asset_spare_and_stays_scannable(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $juan = $this->user(['full_name' => 'Juan Dela Cruz']);
        $work = $this->asset(['item_name' => 'Primary Workstation', 'assigned_to_user' => $juan->id]);
        $this->asset(['item_name' => 'Juan Remaining Unit', 'assigned_to_user' => $juan->id]);
        $spare = $this->asset(['item_name' => 'Unit Foxtrot', 'assigned_to_user' => $juan->id]);

        // "Ginawang Spare" sa app = tanggal sa custodian. Model rule
        // (InventoryAsset::booted) auto-converts: Active + walang user → Spare.
        $spare->forceFill(['assigned_to_user' => null])->save();
        $this->assertSame('Spare', $spare->fresh()->status, 'unassigning must auto-convert to Spare');

        // Batch feed: present, Spare, walang custodian → "Unassigned / Spare" group.
        $json = $this->actingAs($supply)
            ->get(route('inventory.data', ['per_page' => 100]))
            ->assertOk()
            ->json();
        $row = collect($json['assets'])->firstWhere('asset_id', $spare->asset_id);
        $this->assertNotNull($row);
        $this->assertSame('Spare', $row['status']);
        $this->assertSame('', $row['assigned_to_name']);

        // It drops OUT of Juan's custodian list (assigned-assets-only rule),
        // while his remaining assets still group normally.
        $html = $this->actingAs($supply)
            ->get(route('qr.redirect', $work->asset_id))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Other Assets of Juan Dela Cruz', $html);
        $this->assertStringContainsString('Juan Remaining Unit', $html);
        $this->assertStringNotContainsString('Unit Foxtrot', $html);

        // Direct scan still renders: Spare badge, no custodian, no other-assets.
        $html = $this->actingAs($supply)
            ->get(route('qr.redirect', $spare->asset_id))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('status-Spare', $html);
        $this->assertStringContainsString('Asset not assigned to any user', $html);
        $this->assertStringNotContainsString('Other Assets of', $html);
    }

    public function test_for_disposal_leaves_other_assets_and_set_panel_but_stays_scannable(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $juan = $this->user(['full_name' => 'Juan Dela Cruz']);
        $parent = $this->asset(['item_name' => 'Disposal Parent Set', 'assigned_to_user' => $juan->id]);
        $child = $this->asset(['item_name' => 'Disposal Child Monitor', 'assigned_to_user' => $juan->id]);
        $child->forceFill(['parent_asset_id' => $parent->asset_id])->save();
        $this->asset(['item_name' => 'Disposal Live Printer', 'assigned_to_user' => $juan->id]);

        $child->forceFill(['status' => 'For Disposal'])->save();

        // Parent hub: set panel drops the disposed component entirely (count 0 → no section).
        $html = $this->actingAs($supply)
            ->get(route('qr.redirect', $parent->asset_id))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('Disposal Child Monitor', $html);
        $this->assertStringNotContainsString('Set Components (', $html);

        // Sibling's hub still lists the live printer (custodian grouping intact).
        $html = $this->actingAs($supply)
            ->get(route('qr.redirect', $parent->asset_id))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Disposal Live Printer', $html);

        // Direct scan of the disposed asset still renders (audit visibility).
        $this->actingAs($supply)
            ->get(route('qr.redirect', $child->asset_id))
            ->assertOk()
            ->assertSee('For Disposal');
    }

    public function test_set_counters_and_sticker_flag_exclude_disposed_components(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $parent = $this->asset(['item_name' => 'Counter Parent']);
        $live = $this->asset(['item_name' => 'Counter Live Child']);
        $dead = $this->asset(['item_name' => 'Counter Dead Child']);
        $live->forceFill(['parent_asset_id' => $parent->asset_id])->save();
        $dead->forceFill(['parent_asset_id' => $parent->asset_id, 'status' => 'For Disposal'])->save();

        // Batch feed counter = LIVE components only (SET badge + covers-n source).
        $json = $this->actingAs($supply)
            ->get(route('inventory.data', ['per_page' => 100]))
            ->assertOk()
            ->json();
        $row = collect($json['assets'])->firstWhere('asset_id', $parent->asset_id);
        $this->assertSame(1, $row['components_count'], 'disposed components must not inflate SET(n)/covers');

        // Sticker SET flag follows the live set: visible while a live child exists…
        $this->actingAs($supply)
            ->get(route('inventory.qr-sticker', $parent->asset_id) . '?fragment=1')
            ->assertOk()
            ->assertSee('scan for list');

        // …and disappears once the last live component is disposed.
        $live->forceFill(['status' => 'For Disposal'])->save();
        $this->actingAs($supply)
            ->get(route('inventory.qr-sticker', $parent->asset_id) . '?fragment=1')
            ->assertOk()
            ->assertDontSee('scan for list');
    }

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'QL User ' . $this->counter,
            'email' => 'ql-user-' . $this->counter . '@test.com',
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
            'item_name' => 'QL Asset',
            'serial_number' => 'QL-SN-' . $this->counter,
            'property_number' => 'PROP-QL-' . $this->counter,
            'par_number' => 'PAR-QL-' . $this->counter,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
            'status' => 'Active',
        ], $attributes));
    }
}
