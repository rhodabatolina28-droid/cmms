<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D9.34c — notification bell look: the blue dot is the only unread marker.
 *
 * The 4px `::before` bar (admin module) and the 3px mobile `border-left` on
 * `.notif-item.unread` were both removed — every row in the dropdown is unread,
 * so the bar added a distracting vertical line on desktop and phone portrait.
 * This test pins (a) the layout override that suppresses the already-built
 * assets, (b) its cascade position after the Vite CSS, and (c) the cleaned
 * source modules, so a future `npm run build` cannot bring the line back.
 */
class NotificationBellStyleTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_items_have_no_left_vertical_line(): void
    {
        $sa = User::create([
            "full_name" => "Bell Style SA",
            "email" => "bell-style-sa@test.com",
            "password" => bcrypt("password"),
            "role" => "super_admin",
            "is_active" => true,
            "region" => "NCR",
            "branch" => "Main Office",
            "office" => "RESEARCH AND INFORMATION DIVISION",
        ]);

        $html = $this->actingAs($sa)->get(route("dashboard.super-admin"))->assertOk()->getContent();

        $pseudo = ".notif-item.unread::before { display: none; }";
        $override = ".notif-item.unread { border-left: none !important; }";

        $this->assertStringContainsString($pseudo, $html, "desktop ::before bar must be suppressed");
        $this->assertStringContainsString($override, $html, "border-left override must ship with the layout");

        $cssAt = strpos($html, "/build/assets/");
        $overrideAt = strpos($html, $override);
        $this->assertNotFalse($cssAt, "vite css must be linked");
        $this->assertGreaterThan($cssAt, $overrideAt, "override must load after the Vite CSS to win the cascade");

        // Source modules are cleaned too, so a future `npm run build` stays clean.
        $this->assertStringNotContainsString(
            ".notif-item.unread::before",
            file_get_contents(resource_path("css/admin/_ui.css"))
        );
        $this->assertStringNotContainsString(
            "border-left: 3px solid #0038A8 !important;",
            file_get_contents(resource_path("css/mobile-responsive/_phone-portrait.css"))
        );
    }
}
