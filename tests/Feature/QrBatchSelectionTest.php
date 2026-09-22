<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D9.37 — QR Batch Sticker Print (supply officer): selection double-toggle bug.
 *
 * Bug: the document-level `click` handler (row toggle) did not exclude checkbox
 * clicks, so every checkbox click was toggled twice (row handler + native
 * change) — the selection ended up empty and "Print Selected" never enabled.
 *
 * Also: the status filter offered "Defective" (0 assets in DB) and was missing
 * "Under Maintenance" (real status — those assets were invisible to the filter).
 */
class QrBatchSelectionTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    public function test_batch_page_has_checkbox_guard_and_real_status_options(): void
    {
        $supply = $this->user(['role' => 'supply_officer']);
        $this->asset(['item_name' => 'QR Batch Alpha']);
        $this->asset(['item_name' => 'QR Batch Beta', 'status' => 'Under Maintenance']);

        $response = $this->actingAs($supply)->get(route('inventory.qr-batch'));

        $response->assertOk();
        $html = $response->getContent();

        // 1) The click handler must ignore checkbox clicks (guard clause) so the
        //    native toggle + change handler own the checkbox state.
        $this->assertStringContainsString("e.target.closest('input[type=\"checkbox\"]')", $html);

        // 2) Status filter must offer the REAL DB statuses…
        $this->assertStringContainsString('<option value="Active">', $html);
        $this->assertStringContainsString('<option value="Spare">', $html);
        $this->assertStringContainsString('<option value="For Repair">', $html);
        $this->assertStringContainsString('<option value="Under Maintenance">', $html);

        // 3) …and must NOT offer a status that has zero assets ("Defective").
        $this->assertStringNotContainsString('<option value="Defective">', $html);

        // 4) Print flow essentials still present.
        $this->assertStringContainsString('Print Selected', $html);
        $this->assertStringContainsString('/inventory/qr-sticker/', $html);
    }

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'QR User ' . $this->counter,
            'email' => 'qr-user-' . $this->counter . '@test.com',
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
            'item_name' => 'QR Batch Asset',
            'serial_number' => 'QR-SN-' . $this->counter,
            'property_number' => 'PROP-QR-' . $this->counter,
            'par_number' => 'PAR-QR-' . $this->counter,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
            'status' => 'Spare',
        ], $attributes));
    }
}
