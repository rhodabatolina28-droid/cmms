<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D4c — Position dropdown (Create/Edit System Account).
 * Backend contract: position is nullable (unknown position = still creatable),
 * stored verbatim when sent, returned by the get_user endpoint (Edit modal
 * prefill), and keyword-detection follows every change.
 */
class PositionDropdownTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::create([
            'full_name' => 'Position Super Admin',
            'email' => 'position-sa@test.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'is_active' => true,
            'region' => 'NCR',
            'branch' => 'Main Office',
        ]);
    }

    private function target(array $attributes = []): User
    {
        return User::create(array_merge([
            'full_name' => 'Position Target',
            'email' => 'position-target@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'RESEARCH AND INFORMATION DIVISION',
            'department' => 'INTERNAL SERVICES DEPARTMENT',
            'position' => 'Computer Programmer I',
        ], $attributes));
    }

    public function test_create_with_catalog_position_stores_it_and_flags_official(): void
    {
        $this->actingAs($this->superAdmin())
            ->postJson(route('super_admin.users.store'), [
                'full_name' => 'Rid Chief',
                'email' => 'rid-chief@test.com',
                'password' => 'Password1',
                'role' => 'user',
                'office' => 'RESEARCH AND INFORMATION DIVISION',
                'department' => 'INTERNAL SERVICES DEPARTMENT',
                'position' => 'Chief, RID',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $user = User::where('email', 'rid-chief@test.com')->firstOrFail();
        $this->assertSame('Chief, RID', $user->position);
        $this->assertTrue($user->is_high_official, 'Catalog title must trigger high-official detection');
    }

    public function test_create_without_position_is_allowed_and_stays_regular(): void
    {
        // "Hindi ko pa alam ang position" — the field is optional; account
        // must still be created, detection must stay false (null = never official).
        $this->actingAs($this->superAdmin())
            ->postJson(route('super_admin.users.store'), [
                'full_name' => 'Unknown Position',
                'email' => 'unknown-position@test.com',
                'password' => 'Password1',
                'role' => 'user',
                'office' => 'RESEARCH AND INFORMATION DIVISION',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $user = User::where('email', 'unknown-position@test.com')->firstOrFail();
        $this->assertNull($user->position);
        $this->assertFalse($user->is_high_official);
    }

    public function test_edit_updates_position_and_detection_follows_the_change(): void
    {
        $admin = $this->superAdmin();
        $user = $this->target();
        $this->assertFalse($user->is_high_official);

        $this->actingAs($admin)
            ->putJson(route('super_admin.users.update', $user->id), [
                'full_name' => $user->full_name,
                'email' => $user->email,
                'role' => 'user',
                'office' => 'RESEARCH AND INFORMATION DIVISION',
                'department' => 'INTERNAL SERVICES DEPARTMENT',
                'position' => 'OIC, Chief, RID',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $fresh = $user->fresh();
        $this->assertSame('OIC, Chief, RID', $fresh->position);
        $this->assertTrue($fresh->is_high_official, 'Detection must follow the edited position live');
    }

    public function test_edit_can_demote_official_to_manual_regular_position(): void
    {
        $admin = $this->superAdmin();
        $user = $this->target(['position' => 'Chief, RID']);
        $this->assertTrue($user->is_high_official);

        // "Other / Not Listed" path — admin types a regular replacement.
        $this->actingAs($admin)
            ->putJson(route('super_admin.users.update', $user->id), [
                'full_name' => $user->full_name,
                'email' => $user->email,
                'role' => 'user',
                'office' => 'RESEARCH AND INFORMATION DIVISION',
                'position' => 'Records Officer',
            ])
            ->assertOk();

        $fresh = $user->fresh();
        $this->assertSame('Records Officer', $fresh->position);
        $this->assertFalse($fresh->is_high_official, 'Demoted official must lose the queue-jump');
    }

    public function test_get_user_endpoint_returns_position_for_edit_modal_prefill(): void
    {
        $user = $this->target();

        $this->actingAs($this->superAdmin())
            ->get(route('super_admin.users', ['get_user' => $user->id]))
            ->assertOk()
            ->assertJsonPath('user.position', 'Computer Programmer I');
    }
}
