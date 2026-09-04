<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScanFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Manually truncate only the tables under test (fast — avoids full re-migration).
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('inventory_assets')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
    protected function makeUser(string $role = 'user', string $office = 'RID', string $branch = 'RCMB'): User
    {
        return User::create([
            'name'      => $role === 'user' ? 'Maria Santos' : 'IT Officer',
            'full_name' => $role === 'user' ? 'Maria Santos' : 'IT Officer',
            'email'     => $role . '_' . uniqid() . '@cmms.test',
            'password'  => bcrypt('password'),
            'role'      => $role,
            'position'  => 'Staff',
            'region'    => 'NCR',
            'branch'    => $branch,
            'office'    => $office,
            'department' => 'DEPT',
            'is_active' => true,
        ]);
    }

    protected function makeAsset(array $overrides = []): InventoryAsset
    {
        return InventoryAsset::create(array_merge([
            'asset_id'         => rand(100, 9999),
            'item_name'         => 'HP Pavilion Desktop',
            'serial_number'     => 'SN-TEST-' . rand(100, 999),
            'category'          => 'Desktop',
            'status'            => 'Serviceable',
            'region'            => 'NCR',
            'branch'            => 'RCMB',
            'office'            => 'RID',
        ], $overrides));
    }

    public function test_end_user_scan_shows_preview_with_other_assets(): void
    {
        $user = $this->makeUser('user');
        $scanned = $this->makeAsset(['assigned_to_user' => $user->id]);
        $other1  = $this->makeAsset(['assigned_to_user' => $user->id, 'item_name' => 'Monitor Dell']);
        $other2  = $this->makeAsset(['assigned_to_user' => $user->id, 'item_name' => 'Keyboard Logitech']);
        $spare   = $this->makeAsset(['assigned_to_user' => null, 'item_name' => 'Spare Mouse']);

        $res = $this->actingAs($user)->get('/r/' . $scanned->asset_id);

        $res->assertOk();
        $res->assertSee('Asset Scanned');
        $res->assertSee('HP Pavilion Desktop');
        $res->assertSee('Report Repair');
        $res->assertSee('Monitor Dell');
        $res->assertSee('Keyboard Logitech');
        // spare/unassigned assets are NOT in the group
        $res->assertDontSee('Spare Mouse');
        // both other assets link to their own ict.create
        $res->assertSee('asset_id=' . $other1->asset_id, false);
        $res->assertSee('asset_id=' . $other2->asset_id, false);
        $res->assertDontSee('asset_id=' . $spare->asset_id, false);
    }

    public function test_owner_scan_other_asset_links_to_repair_form(): void
    {
        $user = $this->makeUser('user');
        $asset = $this->makeAsset(['assigned_to_user' => $user->id]);

        $res = $this->actingAs($user)->get('/r/' . $asset->asset_id);

        $res->assertOk();
        $res->assertSee('asset_id=' . $asset->asset_id, false);
        $res->assertSee('/requests/ict/create', false);
    }

    public function test_non_owner_user_sees_branded_notice(): void
    {
        $owner = $this->makeUser('user');
        $intruder = $this->makeUser('user', 'COA', 'RCMB');
        $asset = $this->makeAsset(['assigned_to_user' => $owner->id]);

        $res = $this->actingAs($intruder)->get('/r/' . $asset->asset_id);

        $res->assertOk();
        $res->assertSee('Asset Not Assigned');
        $res->assertSee('This asset is not assigned to you');
        // no repair form on a non-owned asset
        $res->assertDontSee('Report Repair');
    }

    public function test_it_scan_still_renders_asset_info_page(): void
    {
        $user = $this->makeUser('it');
        $asset = $this->makeAsset(['assigned_to_user' => $user->id]);

        $res = $this->actingAs($user)->get('/r/' . $asset->asset_id);

        $res->assertOk();
        $res->assertSee('Asset Info', false);
    }

    public function test_it_out_of_branch_sees_branded_notice(): void
    {
        $it   = $this->makeUser('it', 'RID', 'CAR');
        $asset = $this->makeAsset(['assigned_to_user' => $this->makeUser('user')->id, 'branch' => 'RCMB']);

        $res = $this->actingAs($it)->get('/r/' . $asset->asset_id);

        $res->assertOk();
        $res->assertSee('Asset Out of Scope');
    }
}