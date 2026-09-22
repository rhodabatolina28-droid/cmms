<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\PMSchedule;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Services\GeneratePMScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BUG-PM-CONCERN-1 — PM tickets were labelled "ICT Support Request".
 *
 * Auto-generated PM tickets are created WITHOUT a description, and the
 * dashboards hard-coded an ICT-worded fallback for the empty-description
 * case — so every scheduled PM showed "ICT Support Request" in the
 * Concern / Subject column of the Recent Activity / Recent Requests tables.
 *
 * Contract pinned here:
 *  1. `concern_label` accessor: description passthrough, else a type-aware
 *     fallback (PM → "Preventive Maintenance", ICT → "ICT Support Request").
 *  2. The generation service writes a meaningful description up front
 *     (schedule name + focus division) — no more NULL descriptions.
 *  3. The user dashboard never labels a PM ticket as ICT.
 *  4. The admin dashboard ICT fallback survives the accessor switch.
 */
class PmConcernLabelTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function makeUser(array $attrs = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name'  => 'PM Label User ' . $this->counter,
            'email'      => 'pm-label-' . $this->counter . '@test.com',
            'password'   => bcrypt('password'),
            'role'       => 'user',
            'is_active'  => true,
            'region'     => 'NCR',
            'branch'     => 'Main Office',
            'office'     => 'RESEARCH AND INFORMATION DIVISION',
        ], $attrs));
    }

    private function request(User $for, array $attrs = []): RequestModel
    {
        $this->counter++;

        return RequestModel::create(array_merge([
            'user_id'        => $for->id,
            'request_number' => 'REQ-2026-09-22-' . str_pad((string) $this->counter, 4, '0', STR_PAD_LEFT),
            'type'           => 'ICT',
            'requestor_name' => $for->full_name,
            'region'         => 'NCR',
            'branch'         => 'Main Office',
            'office'         => 'RESEARCH AND INFORMATION DIVISION',
            'status'         => 'Pending',
            'description'    => null,
        ], $attrs));
    }

    public function test_concern_label_is_type_aware(): void
    {
        $user = $this->makeUser();

        $pm = $this->request($user, ['type' => 'Preventive Maintenance', 'description' => null]);
        $ict = $this->request($user, ['type' => 'ICT', 'description' => null]);
        $pmWithDesc = $this->request($user, [
            'type'        => 'Preventive Maintenance',
            'description' => 'Fan cleaning on workstation set',
        ]);

        $this->assertSame('Preventive Maintenance', $pm->concern_label);
        $this->assertSame('ICT Support Request', $ict->concern_label);
        $this->assertSame('Fan cleaning on workstation set', $pmWithDesc->concern_label);
    }



    public function test_auto_generated_pm_tickets_carry_a_meaningful_description(): void
    {
        $creator = $this->makeUser([
            'role'   => 'super_admin',
            'branch' => null,
            'office' => 'ADMIN',
        ]);

        $endUser = $this->makeUser();
        InventoryAsset::create([
            'category'         => 'Laptop',
            'item_name'        => 'Label Test Laptop',
            'serial_number'    => 'LBL-SN-1',
            'property_number'  => 'LBL-PN-1',
            'par_number'       => 'LBL-PAR-1',
            'status'           => 'Active',
            'assigned_to_user' => $endUser->id,
            'office'           => 'RESEARCH AND INFORMATION DIVISION',
            'branch'           => 'Main Office',
            'date_acquired'    => '2022-01-01',
        ]);

        $schedule = PMSchedule::create([
            'schedule_name'    => 'Test PM Schedule',
            'asset_categories' => [],
            'frequency'        => 'Quarterly',
            'is_active'        => true,
            'is_paused'        => false,
            'created_by'       => $creator->id,
        ]);

        $created = app(GeneratePMScheduleService::class)->generate($schedule);

        $this->assertCount(1, $created);

        $ticket = RequestModel::where('pm_schedule_id', $schedule->id)->first();

        $this->assertNotNull($ticket, 'generation must create a tracking request');
        $this->assertNotNull($ticket->description, 'auto-generated PM must not have a NULL description');
        $this->assertStringContainsString('Preventive Maintenance', $ticket->description);
        $this->assertStringContainsString('Test PM Schedule', $ticket->description);
    }

    public function test_user_dashboard_never_labels_pm_tickets_as_ict(): void
    {
        $user = $this->makeUser();

        $pm = $this->request($user, ['type' => 'Preventive Maintenance', 'status' => 'Ongoing']);
        // The ICT row has a real description, so the ONLY label that could
        // produce "ICT Support Request" on this page would be the PM fallback.
        $this->request($user, ['type' => 'ICT', 'description' => 'Cannot print documents']);

        $html = $this->actingAs($user)->get(route('dashboard.user'))->assertOk()->getContent();

        // The PM ticket row must render its ticket number…
        $this->assertStringContainsString($pm->display_number, $html);
        // …the ICT row passes its description through…
        $this->assertStringContainsString('Cannot print documents', $html);
        // …and the ICT fallback text must not leak onto the PM row.
        $this->assertStringNotContainsString('ICT Support Request', $html);
    }

    public function test_admin_dashboard_ict_fallback_survives_the_accessor_switch(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);
        $requestor = $this->makeUser();
        $this->request($requestor, ['type' => 'ICT']);

        $html = $this->actingAs($admin)->get(route('dashboard.admin'))->assertOk()->getContent();

        // Admin's table is ICT-only — the empty-description fallback must
        // still read "ICT Support Request" through the accessor.
        $this->assertStringContainsString('ICT Support Request', $html);
    }
}

