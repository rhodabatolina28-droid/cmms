<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * PM-M1 — PM form mobile cascade consolidation (2026-10-08 mobile UX plan).
 *
 * The PM form had THREE competing mobile stylesheets:
 *   1) inline @media block in requests/maintenance/form.blade.php (#pmForm rules)
 *   2) resources/css/maint-form/_responsive.css (the form's own module)
 *   3) a "PREVENTIVE MAINTENANCE FORM MOBILE OVERRIDES" block inside the GLOBAL
 *      resources/css/mobile-responsive/_phone-portrait.css — loaded LAST with
 *      heavy !important, so it won ties and contradicted the module (e.g. the
 *      module stacked device-info cells into blocks while the global block kept
 *      min-width:500px on the grid => forced horizontal side-scroll through a
 *      form that was already stacked).
 *
 * PM-M1 moves ownership of the PM-form mobile rules into the module (single
 * source of truth) and kills the forced horizontal scroll. Desktop is untouched:
 * every rule lives inside @media (max-width:767px), and every moved selector is
 * used ONLY by this form (verified: grid-table / bond-paper / device-info-grid /
 * tasks-table / input-row appear in PM-form files only; .form-container — which
 * the CSM form also uses — stays in the global file).
 */
class PmFormMobileTest extends TestCase
{
    private function responsiveCss(): string
    {
        return file_get_contents(base_path('resources/css/maint-form/_responsive.css'));
    }

    private function phonePortraitCss(): string
    {
        return file_get_contents(base_path('resources/css/mobile-responsive/_phone-portrait.css'));
    }

    /** The global phone stylesheet must no longer own PM-form rules. */
    public function test_global_phone_portrait_no_longer_owns_pm_form_rules(): void
    {
        $global = $this->phonePortraitCss();

        $this->assertStringNotContainsString(
            'PREVENTIVE MAINTENANCE FORM MOBILE OVERRIDES',
            $global,
            'PM-form mobile block must be consolidated into maint-form/_responsive.css (PM-M1).'
        );
        $this->assertStringNotContainsString(
            'NATIVE HORIZONTAL SCROLL FOR MAINTENANCE CHECKLIST',
            $global,
            'Checklist scroll rules belong to the form module, not the global file (PM-M1).'
        );
    }

    /** Device Information stacks fully — no forced 500px side-scroll. */
    public function test_device_info_stacks_without_forced_horizontal_scroll(): void
    {
        $css = $this->responsiveCss();

        $this->assertStringNotContainsString(
            'min-width: 500px',
            $css,
            'Device info must not force a 500px-wide horizontal scroll on phones (PM-M1).'
        );
        // The container chrome (white card + dark border + bottom margin) used to
        // live only in the global block — it must be ported, not lost.
        $this->assertMatchesRegularExpression(
            '/\.device-info-grid\s*\{[^}]*margin-bottom:\s*20px/s',
            $css,
            'device-info-grid container chrome must be ported into the module.'
        );
    }

    /** Ported rules must exist in the module with mobile readability floors. */
    public function test_ported_rules_and_readability_floors_live_in_module(): void
    {
        $css = $this->responsiveCss();

        // Floating-card chrome for Technician/End User sections (ported).
        $this->assertMatchesRegularExpression(
            '/\.grid-table\s*\{[^}]*border-radius:\s*12px/s',
            $css,
            'grid-table floating-card chrome must be ported into the module.'
        );

        // Checklist horizontal-scroll contract (ported 850px).
        $this->assertMatchesRegularExpression(
            '/\.tasks-table\s*\{[^}]*min-width:\s*850px/s',
            $css,
            'checklist min-width must keep the effective 850px scroll contract.'
        );

        // Form labels: was 0.7rem (11.2px) via the global #pmForm rule — ported
        // at a 13px readability floor.
        $this->assertMatchesRegularExpression(
            '/#pmForm label\s*\{[^}]*font-size:\s*13px/s',
            $css,
            '#pmForm labels must be ported with a 13px floor (was 11.2px).'
        );

        // Device labels: the inline #pmForm rule outranks the module, so it must
        // carry the 13px floor too (was 12px).
        $form = file_get_contents(base_path('resources/views/requests/maintenance/form.blade.php'));
        $this->assertMatchesRegularExpression(
            '/#pmForm \.label-cell\s*\{[^}]*font-size:\s*13px/s',
            $form,
            'inline .label-cell rule must carry the 13px floor (id-specificity beats the module).'
        );
    }

    /**
     * PM-M2 — full-width device inputs + Technician/End User readability floors.
     *
     * Measurements on the rendered form (ticket #86, mobile emulation) exposed
     * two gaps left by PM-M1:
     *  1) only the device-info CELLS were blockified — the grid itself, its
     *     inner tables (table-full) and rows stayed in a table formatting
     *     context, so cells shrink-wrapped and inputs collapsed to their
     *     intrinsic width (~235px) instead of spanning the column;
     *  2) section headers rendered at 11.52px (0.72rem), the end-user backup
     *     note at 11.2px (0.7rem) and the signature caption at a mere 9.6px
     *     (0.6rem) — below comfortable touch readability on phones.
     * Desktop is untouched: every rule lives inside @media (max-width:767px)
     * (or the ≤390px block, where the floor is 12px).
     */
    public function test_device_info_spans_full_width_and_section_text_floors(): void
    {
        $css = $this->responsiveCss();

        // 1) Blockify chain: grid + inner tables + rows + cells (pre-M1 winners).
        $this->assertMatchesRegularExpression(
            '/\.device-info-grid\s*,\s*\.device-info-grid table,\s*\.device-info-grid table tbody,\s*\.device-info-grid table tr,\s*\.device-info-grid table td\s*\{[^}]*display:\s*block/s',
            $css,
            'device-info grid/inner tables/rows/tds must all blockify (PM-M2) or inputs shrink to intrinsic width.'
        );
        // …including the grid's OWN tbody — a table-row-group with block children
        // still triggers anonymous-table shrink-wrapping of col-left/col-right.
        $this->assertMatchesRegularExpression(
            '/\.device-info-grid > tbody\s*\{[^}]*display:\s*block/s',
            $css,
            'device-info grid\'s own tbody must blockify too, or columns shrink-wrap (PM-M2).'
        );
        // The row/cell blockify declarations must NOT carry !important — the form's
        // JS hides conditional rows (.monitor-2-row / .printer-2-row) with inline
        // style.display = 'none', and an author !important would outrank it and
        // keep the hidden rows visible. (Author non-important still beats the UA
        // table-row default, so layout is unaffected.)
        $this->assertMatchesRegularExpression(
            '/\.device-info-grid table tr,\s*\.device-info-grid table td\s*\{\s*display:\s*block;/s',
            $css,
            'inner row/cell blockify must be display: block WITHOUT !important so JS inline display:none toggles still work (PM-M2).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.device-info-grid table tr,\s*\.device-info-grid table td\s*\{\s*display:\s*block\s*!important/s',
            $css,
            'display: block !important on device rows would defeat the monitor/printer row toggles (PM-M2).'
        );

        // 2) Readability floors inside the ≤767px block.
        $this->assertMatchesRegularExpression(
            '/\.section-label\s*\{[^}]*font-size:\s*12\.5px/s',
            $css,
            '.section-label must be ≥12.5px on phones (was 11.52px / 0.72rem).'
        );
        $this->assertMatchesRegularExpression(
            '/\.section-bar-minimal\s*\{[^}]*font-size:\s*12\.5px/s',
            $css,
            '.section-bar-minimal must be ≥12.5px on phones (was 11.52px).'
        );
        $this->assertMatchesRegularExpression(
            '/\.note-text-minimal\s*\{[^}]*font-size:\s*12\.5px/s',
            $css,
            'End-user backup note must be ≥12.5px on phones (was 11.2px).'
        );
        $this->assertMatchesRegularExpression(
            '/\.sig-caption\s*\{[^}]*font-size:\s*11\.5px/s',
            $css,
            '"Signature over Printed Name" caption must be ≥11.5px on phones (was 9.6px).'
        );

        // The ≤390px block must not shrink section bars back down (was 0.65rem = 10.4px).
        $this->assertDoesNotMatchRegularExpression(
            '/\.section-bar-minimal\s*\{[^}]*font-size:\s*0\.65rem/s',
            $css,
            '≤390px block must keep a 12px floor on section bars.'
        );
    }
}
