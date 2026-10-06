<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug 3 (Phase 3, Oct 2026) — ICT form QR scan flow.
 *
 * (a) Cam/Scan button died because resources/js/qr-scanner.js exposes nothing
 *     (no export, no window assignment) → Rollup tree-shook the Vite entry to
 *     a 0-byte bundle → `new AssetScanner()` in the inline IIFE threw before
 *     scanBtn.addEventListener() ran. Guard test locks the source fix AND the
 *     built artifact size so the 0-byte regression cannot come back silently.
 *     (The inline IIFE also runs BEFORE the deferred module executes — the
 *     blade wraps scanner init in DOMContentLoaded for that.)
 *
 * (b) CreateIctFormAction dropped ?asset_id= whenever the asset was missing
 *     from $myAssets — but $myAssets excludes status 'For Repair', so a
 *     user-owned asset being repaired lost its preselection AND had no
 *     <option>/ictAssetsMap entry for the auto-fill. The drop must only happen
 *     for unowned assets or non-linkable ones (parity with
 *     RequestHelpers::linkedAssetValidationError: For Disposal/Scrapped/
 *     Disposed are blocked — 'For Repair' is NOT).
 */
class IctScanPrefillTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function makeUser(string $role = 'user', string $branch = 'RCMB'): User
    {
        $this->counter++;

        return User::create([
            'name'      => 'ICT Scan User ' . $this->counter,
            'full_name' => 'ICT Scan User ' . $this->counter,
            'email'     => 'ict-scan-' . $this->counter . '@test.com',
            'password'  => bcrypt('password'),
            'role'      => $role,
            'position'  => 'Staff',
            'region'    => 'NCR',
            'branch'    => $branch,
            'office'    => 'RID',
            'department' => 'DEPT',
            'is_active' => true,
        ]);
    }

    private function makeAsset(array $overrides = []): InventoryAsset
    {
        $this->counter++;

        return InventoryAsset::create(array_merge([
            'asset_id'      => 90000 + $this->counter,
            'item_name'     => 'ICT Scan Unit ' . $this->counter,
            'serial_number' => 'ICT-SN-' . $this->counter,
            'category'      => 'Desktop',
            'status'        => 'Active',
            'region'        => 'NCR',
            'branch'        => 'RCMB',
            'office'        => 'RID',
        ], $overrides));
    }

    /** Pull the create-form view data straight off the response. */
    private function viewData($response): array
    {
        $response->assertOk();

        return $response->original->getData();
    }

    /**
     * THE reported defect: your own For Repair asset scanned from /r/{id} must
     * stay preselected on the ICT form — and must exist as a dropdown option
     * + ictAssetsMap entry so the auto-fill has data to work with.
     */
    public function test_owner_for_repair_asset_stays_preselected_and_in_dropdown(): void
    {
        $user  = $this->makeUser();
        $asset = $this->makeAsset(['assigned_to_user' => $user->id, 'status' => 'For Repair']);

        $res = $this->actingAs($user)
            ->get('/requests/ict/create?asset_id=' . $asset->asset_id);

        $data = $this->viewData($res);

        // 1) Preselection kept
        $this->assertEquals($asset->asset_id, $data['preselectedAssetId'],
            'Owner For Repair asset was dropped from preselectedAssetId (Bug 3b)');

        // 2) Dropdown must contain the option (JS cannot select a missing option)
        $res->assertSee('value="' . $asset->asset_id . '"', false);

        // 3) Auto-fill map must contain the asset details
        $this->assertArrayHasKey($asset->asset_id, $data['ictAssetsMap'],
            'ictAssetsMap missing the preselected asset — auto-fill would have no data (Bug 3b)');
    }

    /**
     * Safety stays: an asset owned by someone else is never preselected
     * (ownership parity with linkedAssetValidationError).
     */
    public function test_foreign_asset_is_not_preselected(): void
    {
        $owner    = $this->makeUser();
        $intruder = $this->makeUser();
        $asset    = $this->makeAsset(['assigned_to_user' => $owner->id, 'status' => 'For Repair']);

        $res = $this->actingAs($intruder)
            ->get('/requests/ict/create?asset_id=' . $asset->asset_id);

        $data = $this->viewData($res);
        $this->assertNull($data['preselectedAssetId'],
            'Foreign asset must NOT be preselected');
    }

    /**
     * Disposal-side statuses stay blocked even for the owner (parity with
     * linkedAssetValidationError, which blocks For Disposal/Scrapped/Disposed
     * but NOT For Repair).
     */
    public function test_owner_scrapped_asset_is_not_preselected(): void
    {
        $user  = $this->makeUser();
        $asset = $this->makeAsset(['assigned_to_user' => $user->id, 'status' => 'Scrapped']);

        $res = $this->actingAs($user)
            ->get('/requests/ict/create?asset_id=' . $asset->asset_id);

        $data = $this->viewData($res);
        $this->assertNull($data['preselectedAssetId'],
            'Scrapped asset must stay blocked (same rule as linkedAssetValidationError)');
    }

    /**
     * No asset_id param → unchanged behavior (null preselect, form renders).
     */
    public function test_form_without_param_renders_normally(): void
    {
        $user  = $this->makeUser();
        $this->makeAsset(['assigned_to_user' => $user->id]);

        $res = $this->actingAs($user)->get('/requests/ict/create');

        $data = $this->viewData($res);
        $this->assertNull($data['preselectedAssetId']);
        $this->assertArrayHasKey('ictAssetsMap', $data);
    }

    /**
     * Bundle guard (Bug 3a / 3c): the source must assign window.AssetScanner
     * (side-effect → Rollup cannot tree-shake the entry to 0 bytes; also the
     * global the inline blade script consumes). If a build exists, its
     * qr-scanner asset must be non-empty and contain the class.
     */
    public function test_qr_scanner_source_exposes_global_and_bundle_not_empty(): void
    {
        $source = base_path('resources/js/qr-scanner.js');
        $this->assertFileExists($source);
        $this->assertStringContainsString(
            'window.AssetScanner',
            file_get_contents($source),
            'resources/js/qr-scanner.js must assign window.AssetScanner (tree-shake guard + global for the inline ICT script)'
        );

        $manifestPath = public_path('build/manifest.json');
        if (! file_exists($manifestPath)) {
            $this->markTestSkipped('No Vite build present (public/build/manifest.json missing) — source guard only.');
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $this->assertIsArray($manifest);

        // Entry keys are relative paths like "resources/js/qr-scanner.js".
        $entryKey = collect($manifest)->keys()
            ->first(fn ($k) => str_ends_with($k, 'resources/js/qr-scanner.js'));
        $this->assertNotNull($entryKey, 'qr-scanner.js missing from Vite manifest');

        $built = public_path('build/' . $manifest[$entryKey]['file']);
        $this->assertFileExists($built);

        $size = filesize($built);
        $this->assertGreaterThan(0, $size,
            'Built qr-scanner bundle is 0 bytes — the class was tree-shaken again (Bug 3a regression)');

        $this->assertStringContainsString(
            'AssetScanner',
            file_get_contents($built),
            'Built qr-scanner bundle does not contain the AssetScanner class'
        );
    }
}

