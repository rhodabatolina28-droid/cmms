<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UX request (Sept 29 2026) - sa USER side, ang "Open" sa ICT Repair Requests
 * (requests/index) at ang "View" sa My Assets (profile/assets) ay may icon pa
 * (fa-folder-open / fa-eye) at asul pa ang teksto (#1d4ed8).
 *
 * Hiniling: (1) tanggalin ang icon, (2) itimin ang teksto - pareho sa MOBILE at
 * DESKTOP. Ang kulay ay nakalagay sa BASE rule ng bawat page (hindi sa media
 * query) kaya isang pagbabago lang ang sumasakop sa lahat ng breakpoint; ang
 * mobile stylesheet ay padding/size/width lang ang hawak para sa mga button na
 * ito, kaya walang mag-aaswang kulay - iyon ang inilalagay ng huling test.
 */
class UserActionLabelTest extends TestCase
{
    use RefreshDatabase;

    private const OFFICE = 'RESEARCH AND INFORMATION DIVISION';

    private int $counter = 0;

    private function user(): User
    {
        $this->counter++;

        return User::create([
            'full_name' => 'Action Label Tester ' . $this->counter,
            'email'     => 'action-label-' . $this->counter . '@test.com',
            'password'  => bcrypt('password'),
            'role'      => 'user',
            'is_active' => true,
            'region'    => 'NCR',
            'branch'    => 'Main Office',
            'office'    => self::OFFICE,
        ]);
    }

    private function ict(User $owner): RequestModel
    {
        $this->counter++;

        return RequestModel::create([
            'user_id'        => $owner->id,
            'request_number' => 'REQ-2026-09-29-' . str_pad((string) $this->counter, 4, '0', STR_PAD_LEFT),
            'type'           => 'ICT',
            'requestor_name' => $owner->full_name,
            'region'         => 'NCR',
            'branch'         => 'Main Office',
            'office'         => self::OFFICE,
            'status'         => 'Ongoing',
            'description'    => 'Printer jammed in lobby',
        ]);
    }

    private function asset(User $owner): InventoryAsset
    {
        $this->counter++;

        return InventoryAsset::create([
            'category'         => 'Laptop',
            'item_name'        => 'Action Label Laptop ' . $this->counter,
            'serial_number'    => 'ALLBLK-' . $this->counter,
            'region'           => $owner->region,
            'branch'           => $owner->branch,
            'office'           => $owner->office,
            'status'           => 'Spare',
            'assigned_to_user' => $owner->id,
        ]);
    }

    /** Ang laman ng button - dapat puro teksto na lang, walang <i>. */
    private function buttonLabel(string $html, string $pattern): string
    {
        $this->assertSame(1, preg_match($pattern, $html, $m), 'Walang nahanap na action button.');

        return trim(preg_replace('/\s+/', ' ', $m[1]));
    }

    /** Pinagsamang declaration body ng lahat ng rule ng isang selector sa page. */
    private function cssFor(string $html, string $selector): string
    {
        preg_match_all('/' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $html, $m);

        return implode("\n", $m[1] ?? []);
    }

    public function test_open_action_on_ict_repair_requests_has_no_icon(): void
    {
        $user = $this->user();
        $this->ict($user);

        $html = $this->actingAs($user)->get(route('ict.index'))->assertOk()->getContent();

        $this->assertSame('Open', $this->buttonLabel($html, '/<a[^>]+class="btn-view-modern"[^>]*>(.*?)<\/a>/s'));
        $this->assertStringNotContainsString('fa-folder-open', $html);
    }

    public function test_view_action_on_my_assets_has_no_icon(): void
    {
        $user = $this->user();
        $this->asset($user);

        $html = $this->actingAs($user)->get(route('profile.assets'))->assertOk()->getContent();

        $this->assertSame('View', $this->buttonLabel($html, '/<button[^>]+class="btn-view"[^>]*>(.*?)<\/button>/s'));
        $this->assertStringNotContainsString('fa-eye', $html);
    }

    public function test_both_action_labels_are_black_and_never_blue(): void
    {
        $user = $this->user();
        $this->ict($user);
        $this->asset($user);

        $requests = $this->actingAs($user)->get(route('ict.index'))->assertOk()->getContent();
        $assets = $this->actingAs($user)->get(route('profile.assets'))->assertOk()->getContent();

        $pages = [
            ['.btn-view-modern', $requests, 'ICT Repair Requests'],
            ['.btn-view', $assets, 'My Assets'],
        ];

        foreach ($pages as [$selector, $html, $page]) {
            $css = $this->cssFor($html, $selector) . "\n" . $this->cssFor($html, $selector . ':hover');

            $this->assertNotSame('', $css, $page . ': walang CSS rule para sa ' . $selector);
            $this->assertStringContainsString('color: #000', $css, $page . ': dapat itim ang teksto ng ' . $selector);
            $this->assertStringNotContainsString('#1d4ed8', $css, $page . ': wala nang asul sa ' . $selector);
        }
    }

    public function test_no_stylesheet_reintroduces_a_color_for_these_action_buttons(): void
    {
        // Ang mobile stylesheet ay padding/size/width lang ang humahawak dito -
        // kung may magdagdag ng kulay sa resources/css, mawawala ang itim na
        // teksto sa mobile kahit tama ang page CSS. Ito ang bantay.
        $files = array_merge(
            glob(resource_path('css') . '/*.css') ?: [],
            glob(resource_path('css') . '/*/*.css') ?: []
        );
        $this->assertNotEmpty($files, 'Walang nahanap na stylesheet.');

        $offenders = [];

        foreach ($files as $file) {
            $css = (string) file_get_contents($file);
            if (stripos($css, 'btn-view') === false) {
                continue;
            }

            $css = preg_replace('!/\*.*?\*/!s', '', $css) ?? '';

            foreach (explode('}', $css) as $block) {
                if (!str_contains($block, 'btn-view')) {
                    continue;
                }

                [$selector, $body] = array_pad(preg_split('/\{/', $block, 2), 2, '');

                if (preg_match('/(^|[;\s])color\s*:/', (string) $body)) {
                    $offenders[] = basename($file) . ' :: ' . trim(preg_replace('/\s+/', ' ', $selector) ?? '');
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'May stylesheet na nagbabalik ng kulay sa .btn-view / .btn-view-modern: ' . implode(' | ', $offenders)
        );
    }
}

