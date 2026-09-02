<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private $counter = 0;

    private function makeUser(array $attrs = [])
    {
        $this->counter++;
        return User::create(array_merge([
            'full_name' => 'Profile Test User ' . $this->counter,
            'email' => 'profile' . $this->counter . '@test.com',
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

    public function test_profile_update_redirects_back_with_success_flash(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'full_name' => 'Updated Name',
            'email' => 'updated@test.com',
            'position' => 'Supply Officer II',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Profile updated successfully.');

        $this->assertSame('Updated Name', $user->refresh()->full_name);
        $this->assertSame('updated@test.com', $user->refresh()->email);
    }

    public function test_profile_page_renders_sweetalert_trigger_on_success_flash(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        // Simulate the full update round-trip so the flash survives into the
        // redirected GET (just like a real browser).
        $this->put(route('profile.update'), [
            'full_name' => 'Updated Name',
            'email' => 'updated@test.com',
        ])->assertRedirect();

        $page = $this->get(route('profile.index'));
        $page->assertOk();
        $page->assertSee('id="globalAlertSuccess"', false);
        $page->assertSee('Profile updated successfully.', false);
    }
}