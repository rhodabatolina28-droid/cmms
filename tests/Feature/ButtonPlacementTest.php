<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ButtonPlacementTest extends TestCase
{
    use RefreshDatabase;

    private $counter = 0;

    private function makeUser(array $attrs = [])
    {
        $this->counter++;
        return User::create(array_merge([
            'full_name' => 'Button Test User ' . $this->counter,
            'email' => 'button' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'can_supply' => false,
            'region' => 'NCR',
            'branch' => null,
            'office' => null,
            'department' => null,
        ], $attrs));
    }

    public function test_inventory_register_asset_sits_next_to_export_in_filter_ribbon(): void
    {
        $supply = $this->makeUser(['role' => 'supply_officer', 'can_supply' => true]);

        $this->actingAs($supply);

        $resp = $this->get(route('inventory.index'));
        $resp->assertOk();

        $html = $resp->getContent();

        // The Register Asset button must live inside the filter ribbon, right
        // after the Export button — not in the card header anymore.
        $this->assertMatchesRegularExpression(
            '/<div class="filter-ribbon">.*id="exportInvLink".*Export.*id="addAssetBtn".*Register Asset.*<\/div>/s',
            $html
        );

        // The header must no longer contain the Register Asset button.
        preg_match('/<div class="card-header-accent">.*?<\/div>/s', $html, $header);
        $this->assertNotEmpty($header, 'card-header-accent div not found');
        $this->assertStringNotContainsString('Register Asset', $header[0]);
    }

    public function test_personnel_add_button_sits_next_to_status_filter(): void
    {
        $admin = $this->makeUser(['role' => 'admin', 'can_supply' => true]);

        $this->actingAs($admin);

        $resp = $this->get(route('personnel.index'));
        $resp->assertOk();

        $html = $resp->getContent();

        // The Add New Personnel button must live inside the filter ribbon,
        // right after the "All Status" dropdown.
        $this->assertMatchesRegularExpression(
            '/<div class="filter-ribbon">.*id="filterStatus".*All Status.*id="addPersonnelBtn".*Add New Personnel.*<\/div>/s',
            $html
        );

        // The card header must no longer contain the add button.
        preg_match('/<div class="card-header-accent">.*?<\/div>/s', $html, $header);
        $this->assertNotEmpty($header, 'card-header-accent div not found');
        $this->assertStringNotContainsString('Add New Personnel', $header[0]);
    }
}