<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QR Print + Scan Hub Plan §9.5 (PHYSICAL_COUNT_CUSTODIAN_GROUP.md) — Phase B.
 *
 * Sticker template becomes a fixed 1" x 1" (25.4mm) square with two variants:
 *  - standalone: QR + asset id + name + serial (if it fits);
 *  - set parent:  QR + asset id + name + "SET - scan for list" flag
 *    (NO component count on paper -> zero reprint when components change);
 *  - component:   referenced instead ("Component of #parent") - components
 *    never get their own sticker (C2: 1 parent QR = whole set).
 *
 * The single page (inventory.qr-sticker) and the batch print grid must share
 * ONE template: the batch flow fetches ?fragment=1 (same partial, no print
 * script) instead of rebuilding its own 95x45mm layout.
 */
class QrStickerSizeTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    public function test_single_sticker_is_one_inch_square_with_set_variants(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $standalone = $this->asset(['item_name' => 'Standalone Printer', 'serial_number' => 'SN-1INCH']);
        $parent = $this->asset(['item_name' => 'Parent Desktop']);
        $child = $this->asset(['item_name' => 'Child Monitor']);
        $child->forceFill(['parent_asset_id' => $parent->asset_id])->save();

        // Standalone: 1"x1" fixed size, serial shown, NO SET flag.
        $html = $this->actingAs($supply)
            ->get(route('inventory.qr-sticker', $standalone->asset_id))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('25.4mm', $html);
        $this->assertStringContainsString('SN-1INCH', $html);
        $this->assertStringNotContainsString('▣ SET', $html);
        // Old px-based card layout is gone.
        $this->assertStringNotContainsString('max-width: min(280px', $html);

        // Set parent: SET flag WITHOUT any count (count lives on the scan hub).
        $html = $this->actingAs($supply)
            ->get(route('inventory.qr-sticker', $parent->asset_id))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('▣ SET — scan for list', $html);
        $this->assertStringNotContainsString('SET(', $html);

        // Component: points at its parent instead of pretending to be standalone.
        $html = $this->actingAs($supply)
            ->get(route('inventory.qr-sticker', $child->asset_id))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Component of #' . $parent->asset_id, $html);
        $this->assertStringNotContainsString('▣ SET', $html);
    }

    public function test_batch_grid_and_fragment_share_the_one_inch_template(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $parent = $this->asset(['item_name' => 'Fragment Parent']);
        $child = $this->asset(['item_name' => 'Fragment Child']);
        $child->forceFill(['parent_asset_id' => $parent->asset_id])->save();

        // Fragment = same partial, WITHOUT the auto print() wrapper script.
        $frag = $this->actingAs($supply)
            ->get(route('inventory.qr-sticker', $parent->asset_id) . '?fragment=1')
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('class="sticker', $frag);
        $this->assertStringContainsString('▣ SET — scan for list', $frag);
        $this->assertStringNotContainsString('window.print()', $frag);

        // Batch page: 7-across 25.4mm grid replaces the old 95mm x 2 layout,
        // and the print flow fetches the shared fragment.
        $page = $this->actingAs($supply)
            ->get(route('inventory.qr-batch'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('repeat(7, 25.4mm)', $page);
        $this->assertStringContainsString('?fragment=1', $page);
        $this->assertStringContainsString('25.4mm', $page);
        $this->assertStringNotContainsString('95mm', $page);
        $this->assertStringNotContainsString('2 stickers per row', $page);
    }

    public function test_qr_is_scannable_size_with_proper_quiet_zone(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $asset = $this->asset(['item_name' => 'Scannable Asset']);

        // QR box enlarged to 17mm (was 15mm) — bigger modules for cameras.
        $single = $this->actingAs($supply)
            ->get(route('inventory.qr-sticker', $asset->asset_id))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('.sticker .qr { width: 17mm; height: 17mm; }', $single);

        // Quiet zone: QrCodeService must use margin(2). The SVG encodes it via
        // transform="scale(200 / (modules + 2*margin))" — for the short test
        // payload (http://localhost/r/{id}, version 2 = 25 modules):
        //   margin 1 → 200/27 = 7.407 ; margin 2 → 200/29 = 6.897.
        $this->assertMatchesRegularExpression('/transform="scale\(([\d.]+)\)"/', $single);
        preg_match('/transform="scale\(([\d.]+)\)"/', $single, $m);
        $this->assertEqualsWithDelta(200 / 29, (float) $m[1], 0.001, 'QrCodeService quiet zone must be margin(2)');

        // The batch grid uses the same shared cell CSS.
        $batch = $this->actingAs($supply)
            ->get(route('inventory.qr-batch'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('.sticker .qr { width: 17mm; height: 17mm; }', $batch);
        // Print-safety note (fit-to-page shrinks modules below scan threshold).
        $this->assertStringContainsString('100%', $batch);
    }

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'QSS User ' . $this->counter,
            'email' => 'qss-user-' . $this->counter . '@test.com',
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
            'item_name' => 'QSS Asset',
            'serial_number' => 'QSS-SN-' . $this->counter,
            'property_number' => 'PROP-QSS-' . $this->counter,
            'par_number' => 'PAR-QSS-' . $this->counter,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
            'status' => 'Spare',
        ], $attributes));
    }
}
