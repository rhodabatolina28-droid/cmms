<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D9.36 — super admin Management Tools: blue on hover / click.
 *
 * The panel already lived in a card and was icon-free, but `.mgmt-tool-link:hover`
 * only shifted to a light grey (`#f8fafc`) with a faint border — there was no blue
 * feedback at all, unlike the admin dashboard's Management Tools buttons
 * (`.btn-action-premium:hover { background:#0038A8; color:#fff }`).
 *
 * This test pins the shared behaviour:
 *  (a) the four tool links stay inside the Management Tools card,
 *  (b) the links fill with #0038A8 on hover / click / keyboard focus,
 *  (c) title and description invert so the text stays readable while blue.
 */
class SuperAdminManagementToolsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::create([
            "full_name" => "Mgmt Tools SA",
            "email" => "mgmt-tools-sa@test.com",
            "password" => bcrypt("password"),
            "role" => "super_admin",
            "is_active" => true,
            "region" => "NCR",
            "branch" => "Main Office",
            "office" => "RESEARCH AND INFORMATION DIVISION",
        ]);
    }

    private function dashboardHtml(): string
    {
        return $this->actingAs($this->superAdmin())
            ->get(route("dashboard.super-admin"))
            ->assertOk()
            ->getContent();
    }

    public function test_tool_links_live_inside_the_management_tools_card(): void
    {
        $html = $this->dashboardHtml();

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        // The heading anchors us to the right card (there is one Management Tools panel).
        $heading = $xpath->query("//h3[normalize-space(text())='Management Tools']");
        $this->assertSame(1, $heading->length, "the Management Tools heading must render exactly once");

        $links = $xpath->query(".//a[contains(@class,'mgmt-tool-link')]", $heading->item(0)->parentNode);
        $this->assertSame(4, $links->length, "all four tool links must live inside the card");

        $labels = [];
        foreach ($links as $link) {
            $labels[] = trim($xpath->query(".//div[contains(@class,'mgmt-tool-title')]", $link)->item(0)->textContent);
        }

        $this->assertSame(["Master List", "Manage Users", "PM Schedules", "Maintenance Calendar"], $labels);
    }

    public function test_tool_links_turn_blue_on_hover_click_and_keyboard_focus(): void
    {
        $html = $this->dashboardHtml();

        $this->assertMatchesRegularExpression(
            '/\.mgmt-tool-link:hover,\s*\.mgmt-tool-link:active,\s*\.mgmt-tool-link:focus-visible\s*\{\s*background:\s*#0038A8;/',
            $html,
            "hover / click / keyboard focus must fill the tool link with #0038A8"
        );

        $this->assertMatchesRegularExpression(
            '/\.mgmt-tool-link:hover \.mgmt-tool-title,[\s\S]*?color:\s*#fff;/',
            $html,
            "the tool title must invert to white while the link is blue"
        );

        $this->assertMatchesRegularExpression(
            '/\.mgmt-tool-link:hover \.mgmt-tool-desc,[\s\S]*?color:\s*rgba\(255,\s*255,\s*255,\s*0\.85\);/',
            $html,
            "the tool description must lighten while the link is blue"
        );
    }
}
