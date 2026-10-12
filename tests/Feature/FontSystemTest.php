<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Font system contract (user decision, 2026-10-12):
 *   "Palitan ang fonts — Arial tayo sa buong system, wag bold,
 *    both roles, lalo na sidebar, headers, at welcome hero."
 *
 * Source-level pin on the CSS/blade files that own the system chrome:
 *  - every font stack that is not already Arial must now BE Arial
 *    (Inter / Segoe UI are gone);
 *  - sidebar, topbar/page headers and the welcome heroes render at
 *    font-weight 400 (normal) — the "flat" look the user asked for.
 *
 * Functional weights (table th, buttons, badges, KPI numbers, monospace
 * serial/PAR fields) are intentionally NOT part of this contract.
 */
class FontSystemTest extends TestCase
{
    private function src(string $rel): string
    {
        return file_get_contents(base_path($rel));
    }

    /** Assert a selector's block carries the expected font-weight. */
    private function assertRuleWeight(string $file, string $selector, string $weight): void
    {
        $this->assertMatchesRegularExpression(
            '/' . preg_quote($selector, '/') . '\s*\{[^}]*font-weight:\s*' . preg_quote($weight, '/') . '\b/s',
            $this->src($file),
            "{$selector} must be font-weight: {$weight} in {$file}"
        );
    }

    public function test_system_font_stacks_use_arial(): void
    {
        $files = [
            'resources/css/landing/_base.css',
            'resources/css/login/_base.css',
            'resources/css/maint-form/_base.css',
            'resources/css/cmms-official/_layout.css',
            'public/css/cmms-official.css',
        ];

        foreach ($files as $file) {
            $src = $this->src($file);

            $this->assertStringContainsString('Arial', $src, "{$file} must declare Arial.");
            $this->assertStringNotContainsString("'Inter'", $src, "{$file} must not load Inter.");
            $this->assertStringNotContainsString('Segoe UI', $src, "{$file} must not fall back to Segoe UI.");
        }
    }

    public function test_sidebar_is_not_bold(): void
    {
        $file = 'resources/css/admin/_layout.css';

        $this->assertRuleWeight($file, '.nav-link', '400');
        $this->assertRuleWeight($file, '.sidebar-title', '400');
        $this->assertRuleWeight($file, '.logo-section h2', '400');
        $this->assertRuleWeight($file, '.sidebar-role', 'normal');
    }

    public function test_headers_are_not_bold(): void
    {
        $this->assertRuleWeight('resources/views/layouts/app.blade.php', '.topbar-title', '400');
        $this->assertRuleWeight('resources/css/admin/_ui.css', '.topbar-left h3', '400');
        $this->assertRuleWeight('resources/css/admin/_ui.css', '.card-content h2', '400');
        $this->assertRuleWeight('resources/css/admin/_ui.css', '.table-box h4', '400');
    }

    public function test_dashboard_welcome_heroes_are_not_bold(): void
    {
        foreach (['admin', 'it'] as $d) {
            $file = "resources/views/dashboard/{$d}.blade.php";

            $this->assertRuleWeight($file, '.hero-role', '400');
            $this->assertRuleWeight($file, '.hero-name', '400');
            $this->assertRuleWeight($file, '.hero-stats-label', '400');
            $this->assertRuleWeight($file, '.hero-stats-value', '400');
        }

        $this->assertRuleWeight('resources/views/dashboard/user.blade.php', '.hero-name', '400');
        $this->assertRuleWeight('resources/views/dashboard/user.blade.php', '.hero-status-label', '400');
        $this->assertRuleWeight('resources/views/dashboard/user.blade.php', '.hero-status-value', '400');

        $this->assertRuleWeight('resources/views/dashboard/super-admin.blade.php', '.hero-role', '400');
        $this->assertRuleWeight('resources/views/dashboard/super-admin.blade.php', '.hero-stats-label', '400');
        $this->assertRuleWeight('resources/views/dashboard/super-admin.blade.php', '.hero-stats-value', '400');
    }

    public function test_landing_welcome_hero_is_not_bold(): void
    {
        $this->assertRuleWeight('resources/css/landing/_layout.css', '.hero h1', '400');
        $this->assertRuleWeight('resources/css/landing/_base.css', '.logo-text strong', '400');
    }
}
