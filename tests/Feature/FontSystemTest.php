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

        // Topbar real-time clock + date (user amendment 2026-10-13): the sweep
        // classifier has no clock/date/time keyword, so the clock survived at
        // 900 (the heaviest weight in the whole app) and the date at 700.
        $this->assertRuleWeight('resources/views/layouts/app.blade.php', '#realTimeClock', '400');
        $this->assertRuleWeight('resources/views/layouts/app.blade.php', '#realTimeDate', '400');
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

    /**
     * Notification bell panel (user amendment 2026-10-12, second pass):
     * the dropdown panel is KEEP-exempt in the sweep above (its selectors
     * carry the 'notif' KEEP token as functional badges/links), but the user
     * asked for the flat look here too — header title, type/time labels,
     * "View" link, mark-read links, the footer "View All Activities" link
     * and BOTH badges (bell counter + ticket-number chip) all render at 400.
     * Fonts: the panel inherits Arial from <body>; the ticket badge keeps
     * its monospace serial font — only the weights change.
     */
    public function test_notification_panel_is_not_bold(): void
    {
        $file = 'resources/views/layouts/app.blade.php';

        foreach ([
            '.notif-type',
            '.notif-req-badge',
            '.notif-time',
            '.notif-view-link',
            '.mark-read',
            '.notif-badge',
            '.notif-header-title',
            '.notif-count-label',
            '.notif-footer-link',
        ] as $selector) {
            $this->assertRuleWeight($file, $selector, '400');
        }

        $this->assertRuleWeight($file, '#markAllRead', '400');
    }

    /** Functional weights that survive the sweep (buttons, badges, signatures). */
    private function isKeepSelector(string $selector): bool
    {
        return (bool) preg_match(
            '/(^|[\s,>.#])(btn|button|badge|pill|chip|sig|signature|checkbox|swal|dropdown|tab|tabs|'
            . 'pag-btn|pagination|nav-link|notif|toggle|switch|sticker|checkbox)([\s,.$-]|$)/i',
            $selector
        );
    }

    /**
     * Chrome-touch selectors (headers, labels, STAT VALUES — and, per user
     * amendment 2026-10-12, TABLE headers/cells too) must never carry
     * font-weight >= 700: the flat Arial look across every module of every
     * role. KEEP-list wins for buttons/badges/signatures.
     */
    private function isChromeTouchSelector(string $selector): bool
    {
        if ($this->isKeepSelector($selector)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(h[1-6]|header|title|heading|label|lbl|greeting|summary|caption|subtitle|brand|logo|'
            . 'ops-(label|val)|detail-label|empty-state|hint|counted-note|selected-count|pm-info|'
            . 'amount-text|back-link|panel-link|nav-parent|form-(field|input)|search-input|page-header|'
            . 'report-card|analytics-title|mgmt-tool|h3-title|conforme-title|a60-title|a60-field|'
            . 'stat-?(big|value|label|sub|unit|icon|s)?|kpi-(value|label)|bar-(label|value)|'
            . 'meta-card|meta-row|session-bar|mobile-table-hint|value|total|count|amount|qty|price|'
            . 'th|thead|td|table)\b/i',
            $selector
        );
    }

    /**
     * Sweep contract: every bold-weight CSS block in the system views and
     * module CSS must be either KEEP-functional or non-chrome. Scans
     * line-by-line, tracking the owning selector; <script> regions and
     * pdf/emails/errors pages are out of scope.
     */
    public function test_module_headers_labels_stats_and_tables_are_not_bold(): void
    {
        $violations = [];

        $files = array_merge(
            iterator_to_array(new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(resource_path('views'))
            )),
            iterator_to_array(new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(resource_path('css'))
            ))
        );

        foreach ($files as $file) {
            $path = $file->getPathname();
            if (!$file->isFile() || (!str_ends_with($path, '.blade.php') && !str_ends_with($path, '.css'))) {
                continue;
            }
            if (preg_match('#[\\\\/](pdf|emails|errors)[\\\\/]#', $path)) {
                continue;
            }

            $lines = file($path);
            $selector = '';
            $inStyle = true; // .css files are pure style; blades toggle on </style>/<script>
            $inScript = false;

            foreach ($lines as $i => $line) {
                if (preg_match('/<script\b/i', $line)) { $inScript = true; continue; }
                if (preg_match('#</script>#i', $line)) { $inScript = false; $selector = ''; continue; }
                if (preg_match('/<style\b/i', $line)) { $inStyle = true; $selector = ''; continue; }
                if (preg_match('#</style>#i', $line)) { $inStyle = false; $selector = ''; continue; }
                if ($inScript || !$inStyle) { continue; }

                // Owns both multi-line (`sel {`) and single-line (`sel { … }`) rules.
                if (preg_match('/([^{};]+)\{(.*)$/', $line, $m)) {
                    $selector = trim($m[1]);
                }

                if (preg_match('/font-weight:\s*(700|800|900|bold)\b/i', $line)
                    && $selector !== ''
                    && $this->isChromeTouchSelector($selector)
                ) {
                    $violations[] = sprintf(
                        '%s:%d  [%s]',
                        str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path),
                        $i + 1,
                        mb_substr($selector, 0, 90)
                    );
                }
            }
        }

        $this->assertSame([], $violations, "Chrome-touch selectors still bold:\n" . implode("\n", $violations));
    }
}
