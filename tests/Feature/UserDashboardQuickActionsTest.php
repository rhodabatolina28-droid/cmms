<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D9.35 — user dashboard Quick Actions.
 *
 * Before: the "Quick Actions" label and its two buttons floated directly on the
 * page background (no card) and the buttons only changed their border on hover —
 * the card body stayed white. Admin's Management Tools panel does it properly:
 * one `.queue-panel` card and buttons that fill with #0038A8 the moment the mouse
 * pointer is over them (or on click).
 *
 * This test pins the user dashboard to the same behaviour:
 *  (a) the Quick Actions label + both buttons live inside ONE queue-panel card,
 *  (b) the buttons turn blue on hover / click / keyboard focus,
 *  (c) the text inside the button stays readable while blue,
 *  (d) the gated (no assigned assets) state stays red and never turns blue.
 */
class UserDashboardQuickActionsTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            "full_name" => "QA Card User " . $this->counter,
            "email" => "qa-card-user-" . $this->counter . "@test.com",
            "password" => bcrypt("password"),
            "role" => "user",
            "is_active" => true,
            "region" => "NCR",
            "branch" => "Main Office",
            "office" => "RESEARCH AND INFORMATION DIVISION",
        ], $attributes));
    }

    private function assignAsset(User $owner): InventoryAsset
    {
        $this->counter++;

        return InventoryAsset::create([
            "category" => "Desktop",
            "item_name" => "QA Desktop " . $this->counter,
            "serial_number" => "QA-SN-" . $this->counter,
            "property_number" => "QA-PROP-" . $this->counter,
            "par_number" => "QA-PAR-" . $this->counter,
            "region" => "NCR",
            "branch" => "Main Office",
            "office" => "RESEARCH AND INFORMATION DIVISION",
            "status" => "Active",
            "assigned_to_user" => $owner->id,
        ]);
    }

    private function dashboardHtml(User $user): string
    {
        return $this->actingAs($user)->get(route("dashboard.user"))->assertOk()->getContent();
    }

    /** @return \DOMNodeList */
    private function cardsIn(string $html)
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_clear_errors();

        return (new \DOMXPath($doc))->query("//div[contains(@class,'queue-panel')]");
    }

    public function test_quick_actions_and_buttons_sit_inside_one_card(): void
    {
        $user = $this->user();
        $this->assignAsset($user);

        $html = $this->dashboardHtml($user);

        $cards = $this->cardsIn($html);
        $this->assertSame(1, $cards->length, "the Quick Actions column must render exactly one queue-panel card");

        $card = $cards->item(0);
        $xpath = new \DOMXPath($card->ownerDocument);

        // The label is the first thing inside the card ...
        $label = $xpath->query(".//div[contains(@class,'ribbon-label')]", $card);
        $this->assertSame(1, $label->length, "the Quick Actions label must live inside the card");
        $this->assertSame("Quick Actions", trim($label->item(0)->textContent));

        // ... followed by both action buttons, also inside the card.
        $buttons = $xpath->query(".//a[contains(@class,'action-button-premium')]", $card);
        $this->assertSame(2, $buttons->length, "both quick action buttons must live inside the card");
    }

    public function test_buttons_turn_blue_on_hover_click_and_keyboard_focus(): void
    {
        $user = $this->user();
        $this->assignAsset($user);

        $html = $this->dashboardHtml($user);

        // Exact rule order + the blue fill, mirroring admin's .btn-action-premium:hover.
        $this->assertMatchesRegularExpression(
            '/\.action-button-premium:hover,\s*\.action-button-premium:active,\s*\.action-button-premium:focus-visible\s*\{\s*background:\s*#0038A8;/',
            $html,
            "hover / click / keyboard focus must fill the button with #0038A8"
        );

        // Secondary text stays readable while the button is blue.
        $this->assertMatchesRegularExpression(
            '/\.action-button-premium:hover \.action-subtitle,[\s\S]*?color:\s*rgba\(255,\s*255,\s*255,\s*0\.85\);/',
            $html,
            "the subtitle must lighten while the button is blue"
        );

        // The count chip must not stay dark blue on a dark blue button.
        $this->assertMatchesRegularExpression(
            '/\.action-button-premium:hover \.action-count-chip,[\s\S]*?color:\s*#fff\s*!important;/',
            $html,
            "the count chip must invert while the button is blue"
        );
    }

    public function test_gated_state_stays_red_and_never_turns_blue(): void
    {
        $user = $this->user(); // no assigned asset on purpose

        $html = $this->dashboardHtml($user);

        $this->assertStringContainsString("Requests Unavailable", $html);

        $cards = $this->cardsIn($html);
        $this->assertSame(1, $cards->length);
        $card = $cards->item(0);
        $xpath = new \DOMXPath($card->ownerDocument);

        // The card carries the rose gated state ...
        $restricted = $xpath->query(".//*[contains(@class,'action-restricted')]", $card);
        $this->assertSame(1, $restricted->length, "the gated card must render in action-restricted state");

        // ... and holds no actionable link at all (the sidebar has those links,
        // so the assertion is scoped to the card).
        $links = $xpath->query(".//a", $card);
        $this->assertSame(0, $links->length, "no quick action link may render while the gate is closed");

        // The hover/click rule keeps the rose background instead of the blue fill.
        $this->assertMatchesRegularExpression(
            '/\.action-restricted:hover,[\s\S]*?background:\s*#fff5f5\s*!important;/',
            $html,
            "the gated card must keep its rose background on hover"
        );
    }

    public function test_quick_action_links_and_asset_count_are_rendered(): void
    {
        $user = $this->user();
        $this->assignAsset($user);
        $this->assignAsset($user);
        $this->assignAsset($user);

        $html = $this->dashboardHtml($user);

        $this->assertStringContainsString(route("ict.create"), $html);
        $this->assertStringContainsString(route("profile.assets"), $html);
        $this->assertStringContainsString("3 active items assigned", $html);
        $this->assertStringContainsString("New Request", $html);
        $this->assertStringContainsString("My Assigned Equipment", $html);
    }
}
