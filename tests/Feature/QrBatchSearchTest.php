<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batch QR Sticker Print — search bar (2026-10-08 user report: "pag nag-type
 * ako ng name or serial/Par dapat may lumabas").
 *
 * Fixes locked here:
 *  - the search input must be wired via addEventListener('input') inside the
 *    nonce'd script — the inline oninput attribute is BLOCKED by the strict
 *    production CSP (script-src nonce, no unsafe-inline: SecurityHeaders L26);
 *  - matchSearch mirrors the server-side fields (item_name / serial_number /
 *    par_number / property_number — GetInventoryAssetsAction) PLUS the custodian
 *    name (assigned_to_name) — the page groups BY custodian, so typing a person's
 *    name must surface their group;
 *  - typing during the paged loadAllAssets() loop must survive page fetches
 *    (filterTable() after load, not renderTable(allAssets));
 *  - an explicit no-results row when nothing matches (search or status/category
 *    filters) instead of a silently blank table.
 */
class QrBatchSearchTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    public function test_search_is_csp_safe_and_covers_name_serial_par_and_custodian(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);

        $html = $this->actingAs($supply)
            ->get(route('inventory.qr-batch'))
            ->assertOk()
            ->getContent();

        // 1) CSP-safe wiring: listener registered in the nonce'd script; the
        //    inline event-handler attribute (blocked in prod CSP) is gone.
        $this->assertStringContainsString("getElementById('searchInput').addEventListener('input'", $html);
        $this->assertStringNotContainsString('oninput="filterTable()"', $html);

        // 2) matchSearch fields: server-side parity + custodian name.
        $this->assertStringContainsString('a.assigned_to_name', $html);
        $this->assertStringContainsString('a.property_number', $html);
        $this->assertStringContainsString('a.serial_number', $html);
        $this->assertStringContainsString('a.par_number', $html);
        $this->assertStringContainsString('a.item_name', $html);

        // 3) Paged load must re-apply the active search — no clobbering back to
        //    the unfiltered list after each page arrives.
        $this->assertStringNotContainsString('renderTable(allAssets)', $html);

        // 4) Explicit no-results feedback when a filter/search matches nothing
        //    (renderTable's existing empty-state row).
        $this->assertStringContainsString('No assets found.', $html);
    }

    public function test_payload_carries_every_client_side_search_field(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $owner = $this->user(['full_name' => 'Juan Dela Cruz']);
        $asset = $this->asset([
            'item_name' => 'Search Target Desktop',
            'assigned_to_user' => $owner->id,
        ]);

        $row = collect($this->actingAs($supply)
            ->get(route('inventory.data', ['per_page' => 100]))
            ->assertOk()
            ->json('assets'))
            ->firstWhere('asset_id', $asset->asset_id);

        $this->assertNotNull($row);
        $this->assertArrayHasKey('item_name', $row);
        $this->assertArrayHasKey('serial_number', $row);
        $this->assertArrayHasKey('par_number', $row);
        $this->assertArrayHasKey('property_number', $row);
        $this->assertArrayHasKey('assigned_to_name', $row);
        $this->assertSame('Juan Dela Cruz', $row['assigned_to_name']);
    }

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'QBS User ' . $this->counter,
            'email' => 'qbs-user-' . $this->counter . '@test.com',
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
            'item_name' => 'QBS Asset',
            'serial_number' => 'QBS-SN-' . $this->counter,
            'property_number' => 'PROP-QBS-' . $this->counter,
            'par_number' => 'PAR-QBS-' . $this->counter,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
            'status' => 'Spare',
        ], $attributes));
    }
}