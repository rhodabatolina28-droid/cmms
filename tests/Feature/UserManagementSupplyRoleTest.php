<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the Supply Officer flag: the Edit System Account modal has no
 * can_supply control, so saving a Division Admin must keep the stored flag
 * and only change it when the role really changes.
 */
class UserManagementSupplyRoleTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'full_name' => 'Supply Role Test ' . $n,
            'email' => 'supply-role-' . $n . '@test.local',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
            'region' => 'NCR',
            'branch' => 'RCMB',
            'office' => 'ADMINISTRATIVE DIVISION',
            'department' => 'INTERNAL SERVICES DEPARTMENT',
        ], $attrs));
    }

    private function payload(User $u, string $role): array
    {
        return [
            'full_name' => $u->full_name,
            'email' => $u->email,
            'role' => $role,
            'region' => $u->region,
            'branch' => $u->branch,
            'office' => $u->office,
            'department' => $u->department,
        ];
    }

    public function test_saving_a_supply_officer_admin_keeps_can_supply(): void
    {
        $super = $this->user(['role' => 'super_admin', 'full_name' => 'System Admin']);
        $supply = $this->user(['role' => 'admin', 'can_supply' => true, 'full_name' => 'Adrian Valdez']);

        $this->actingAs($super)
            ->putJson(route('super_admin.users.update', $supply->id), $this->payload($supply, 'admin'))
            ->assertOk()
            ->assertJsonPath('success', true);

        $supply->refresh();
        $this->assertSame('admin', $supply->role);
        $this->assertTrue(
            (bool) $supply->can_supply,
            'Saving an admin without touching the supply role must not wipe can_supply.'
        );
    }

    public function test_choosing_supply_officer_still_grants_can_supply(): void
    {
        $super = $this->user(['role' => 'super_admin', 'full_name' => 'System Admin']);
        $target = $this->user(['role' => 'admin', 'can_supply' => false, 'full_name' => 'Admin Two']);

        $this->actingAs($super)
            ->putJson(route('super_admin.users.update', $target->id), $this->payload($target, 'supply_officer'))
            ->assertOk();

        $target->refresh();
        $this->assertSame('admin', $target->role);
        $this->assertTrue((bool) $target->can_supply);
    }

    public function test_changing_role_away_from_admin_clears_can_supply(): void
    {
        $super = $this->user(['role' => 'super_admin', 'full_name' => 'System Admin']);
        $target = $this->user(['role' => 'admin', 'can_supply' => true, 'full_name' => 'Admin Three']);

        $this->actingAs($super)
            ->putJson(route('super_admin.users.update', $target->id), $this->payload($target, 'user'))
            ->assertOk();

        $this->assertFalse((bool) $target->refresh()->can_supply);
    }
}
