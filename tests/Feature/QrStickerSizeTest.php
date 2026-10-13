<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QR Print + Scan Hub Plan §9.5 (PHYSICAL_COUNT_CUSTODIAN_GROUP.md) — Phase B.
 *
 * Sticker template is a fixed 30mm x 30mm square (was 1"/25.4mm — user
 * amendment 2026-10-13: "medyo lakihan mo un sticker" + the batch sheet must
 * FIT ONE PAGE: the old 7x10 A4 grid needed 287mm vs Letter's 269mm usable,
 * so the last row(s) always spilled onto a second page). With 30mm cells,
 * 6 across x 8 rows = 261mm <= Letter's 267.4mm (margin 6mm) — 48 stickers
 * per sheet, and @page size:auto keeps it fitting on A4/Legal too.
 * Variants:
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

    public function test_single_sticker_is_30mm_square_with_set_variants(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $standalone = $this->asset(['item_name' => 'Standalone Printer', 'serial_number' => 'SN-1INCH']);
        $parent = $this->asset(['item_name' => 'Parent Desktop']);
        $child = $this->asset(['item_name' => 'Child Monitor']);
        $child->forceFill(['parent_asset_id' => $parent->asset_id])->save();

        // Standalone: 30mm x 30mm fixed size, serial shown, NO SET flag.
        $html = $this->actingAs($supply)
            ->get(route('inventory.qr-sticker', $standalone->asset_id))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('30mm', $html);
        $this->assertStringNotContainsString('25.4mm', $html);
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

    public function test_batch_grid_and_fragment_share_the_30mm_template(): void
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

        // Batch page: 6-across 30mm grid replaces the old 7-across 25.4mm A4
        // layout, and the print flow fetches the shared fragment.
        $page = $this->actingAs($supply)
            ->get(route('inventory.qr-batch'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('repeat(6, 30mm)', $page);
        $this->assertStringContainsString('?fragment=1', $page);
        $this->assertStringContainsString('30mm', $page);
        $this->assertStringNotContainsString('25.4mm', $page);
        $this->assertStringNotContainsString('repeat(7,', $page);
        $this->assertStringNotContainsString('95mm', $page);
        $this->assertStringNotContainsString('2 stickers per row', $page);
    }

    public function test_batch_sheet_fits_one_letter_page(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);

        // The overflow bug (user 2026-10-13: "bakit pag nag-print ako nang
        // marami, imbis isang papel lang, nakasya sa [maraming] papel"): the
        // old grid hard-coded @page A4 (297mm) while the office prints on
        // Letter (279.4mm) — 10 rows x 25.4mm + gaps + padding = 287mm
        // vs Letter's 269mm usable = the last row(s) spilled to page 2.
        // Contract now: size auto (Letter/A4/Legal all fit) + 6 x 8 cells of
        // 30mm with 3mm gaps = 261mm tall <= 267.4mm (Letter 279.4 - 2x6mm).
        $page = $this->actingAs($supply)
            ->get(route('inventory.qr-batch'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('@page {', $page);
        $this->assertStringContainsString('size: auto;', $page, 'Batch @page must not hard-code A4 — Letter is the office paper');
        $this->assertStringNotContainsString('size: A4 portrait', $page);
        $this->assertStringContainsString('margin: 6mm;', $page);
        $this->assertStringContainsString('grid-template-columns: repeat(6, 30mm);', $page);
        $this->assertStringContainsString('gap: 3mm;', $page);
        // No stray bottom padding beyond the math above.
        $this->assertStringNotContainsString('padding-bottom: 6mm', $page);

        // The math itself — keep it honest if anyone touches the grid.
        $cell = 30; $gap = 3; $margin = 6;
        $letterWidth = 215.9; $letterHeight = 279.4;
        $this->assertLessThanOrEqual($letterWidth - 2 * $margin, 6 * $cell + 5 * $gap, '6-across must fit Letter width');
        $this->assertLessThanOrEqual($letterHeight - 2 * $margin, 8 * $cell + 7 * $gap, '8 rows must fit Letter height on ONE page');
    }

    public function test_batch_print_kills_invisible_blank_pages(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);

        // User 2026-10-13: "madaming papel na blanco, walang QR, kahit isa
        // lang ang sticker ko" — the old @media print used
        // `body * { visibility: hidden; }` which only hides INK: the tall
        // asset table kept its layout height, so every print emitted page
        // after page of invisible (blank) content. The grid must instead
        // REMOVE everything else from flow: display:none on body children
        // outside #printSection = exactly N pages for N sticker rows.
        $page = $this->actingAs($supply)
            ->get(route('inventory.qr-batch'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('body > *:not(#printSection)', $page);
        $this->assertStringContainsString('display: none !important', $page);
        $this->assertStringNotContainsString('visibility: hidden', $page, 'The visibility hack prints blank pages — use display:none');
    }

    public function test_qr_is_scannable_size_with_proper_quiet_zone(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $asset = $this->asset(['item_name' => 'Scannable Asset']);

        // QR box enlarged to 20mm (was 17mm at the 1" sticker) — bigger modules
        // for cameras at the new 30mm cell.
        $single = $this->actingAs($supply)
            ->get(route('inventory.qr-sticker', $asset->asset_id))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('.sticker .qr { width: 20mm; height: 20mm; }', $single);

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
        $this->assertStringContainsString('.sticker .qr { width: 20mm; height: 20mm; }', $batch);
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
