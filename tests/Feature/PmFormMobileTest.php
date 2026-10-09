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
}
