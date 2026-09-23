<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\PMSchedule;
use App\Models\RepairRequest;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Services\GeneratePMScheduleService;
use App\Support\RequestHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D9.42 Phase 2 — per-region daily service request numbers.
 *
 * Stored format:  {ICT|PM}-{REGION}-{BRANCH}-{YYYY}-{MM}-{DD}-{NNNN}
 *   (ICT-NCR-RCMB-2026-09-23-0001 — region/branch embedded so the
 *    UNIQUE index holds across offices sharing one database.)
 * Screen format:  ICT-2026-09-23-0001    (display_number strips region+branch)
 * Full format:    ICT-NCR-RCMB-2026-09-23-0001 (full_display_number)
 *
 * Sequence scope: per (prefix, region, branch, day) — every office restarts
 * at 0001 daily and never consumes another office's counter.
 *
 * Legacy REQ-NCR-RCMB-2026-0001 and D9.24 REQ-2026-09-17-0001 numbers keep
 * parsing/displaying until the Phase 3 backfill renumbers them.
 */
class RequestNumberPerRegionTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(array $attrs = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name'   => 'D942 User ' . $this->counter,
            'email'       => 'd942-user-' . $this->counter . '@test.com',
            'password'    => bcrypt('password'),
            'role'        => 'user',
            'is_active'   => true,
            'region'      => 'NCR',
            'branch'      => 'RCMB',
            'office'      => 'RESEARCH AND INFORMATION DIVISION',
        ], $attrs));
    }

    private function ticket(string $number, User $owner): RequestModel
    {
        return RequestModel::create([
            'user_id'        => $owner->id,
            'request_number' => $number,
            'type'           => 'ICT',
            'requestor_name' => $owner->full_name,
            'region'         => $owner->region,
            'branch'         => $owner->branch,
            'office'         => $owner->office,
            'status'         => 'Pending',
            'is_deleted'     => false,
            'description'    => 'D9.42 per-region number test ' . $number,
        ]);
    }

    public function test_ict_number_embeds_region_branch_and_date(): void
    {
        $number = RequestHelpers::generateRequestNumber('ICT', $this->user());

        $this->assertMatchesRegularExpression('/^ICT-NCR-RCMB-\d{4}-\d{2}-\d{2}-\d{4}$/', $number);
        $this->assertStringStartsWith('ICT-NCR-RCMB-' . now()->format('Y-m-d') . '-', $number);
    }

    public function test_pm_number_uses_the_pm_prefix_with_the_same_shape(): void
    {
        $number = RequestHelpers::generateRequestNumber('Preventive Maintenance', $this->user());

        $this->assertMatchesRegularExpression('/^PM-NCR-RCMB-\d{4}-\d{2}-\d{2}-\d{4}$/', $number);
        $this->assertStringStartsWith('PM-NCR-RCMB-' . now()->format('Y-m-d') . '-', $number);
    }

    public function test_sequences_are_separate_per_region_and_branch_on_the_same_day(): void
    {
        $ncr = $this->user();
        $rii = $this->user(['region' => 'REGION II', 'branch' => 'Batuen']);

        // NCR consumes 0001 and 0002 today…
        $first = RequestHelpers::generateRequestNumber('ICT', $ncr);
        $this->ticket($first, $ncr);
        $second = RequestHelpers::generateRequestNumber('ICT', $ncr);
        $this->ticket($second, $ncr);

        $this->assertStringEndsWith('-0001', $first);
        $this->assertStringEndsWith('-0002', $second, 'Same office keeps counting within the day.');

        // …while REGION II still starts at 0001 (its own counter).
        $other = RequestHelpers::generateRequestNumber('ICT', $rii);

        $this->assertStringEndsWith('-0001', $other, 'Another region must not consume NCR sequence.');
        $this->assertStringContainsString('-RII-', $other);
    }

    public function test_branch_code_prefers_exact_then_longest_keyword(): void
    {
        // D9.42 BUG (latent while dev data was single-region): the old
        // first-match str_contains loop hit 'REGION I' before 'REGION II',
        // so every numbered region silently collapsed to 'RI'.
        $this->assertSame('RII', RequestHelpers::getBranchCode('REGION II - CAGAYAN VALLEY'));
        $this->assertSame('RI', RequestHelpers::getBranchCode('REGION I - ILOCOS SUR'));
        $this->assertSame('R4A', RequestHelpers::getBranchCode('REGION IV-A - CALABARZON'));
        $this->assertSame('RXI', RequestHelpers::getBranchCode('REGION XI - DAVAO'));
        $this->assertSame('RVII', RequestHelpers::getBranchCode('REGION VII'));
        $this->assertSame('NCR', RequestHelpers::getBranchCode('NCR'));
        $this->assertSame('RCMB', RequestHelpers::getBranchCode('RCMB'));
        $this->assertSame('SYS', RequestHelpers::getBranchCode(null));
    }

    public function test_null_region_and_branch_fall_back_to_sys(): void
    {
        $number = RequestHelpers::generateRequestNumber('ICT', null, null, null);

        $this->assertStringStartsWith('ICT-SYS-SYS-', $number);
    }

    public function test_screen_and_full_display_formats_for_the_new_number(): void
    {
        $ticket = $this->ticket('ICT-NCR-RCMB-2026-09-23-0007', $this->user());

        $this->assertSame('ICT-2026-09-23-0007', $ticket->display_number);
        $this->assertSame('ICT-NCR-RCMB-2026-09-23-0007', $ticket->full_display_number);
    }

    public function test_legacy_and_d924_numbers_keep_displaying(): void
    {
        $legacy = $this->ticket('REQ-NCR-RCMB-2026-0042', $this->user());
        $d924   = $this->ticket('REQ-2026-09-16-0001', $this->user());

        $this->assertSame('ICT-2026-0042', $legacy->display_number);
        $this->assertSame('ICT-2026-09-16-0001', $d924->display_number);
        $this->assertStringStartsWith('ICT-NCR-RCMB-2026-09-16-0001', $d924->full_display_number);
    }

    public function test_ict_store_writes_the_number_and_its_repair_mirror_together(): void
    {
        $requestor = $this->user();
        $this->counter++;
        $asset = InventoryAsset::create([
            'category'         => 'Laptop',
            'item_name'        => 'D942 Laptop ' . $this->counter,
            'serial_number'    => 'D942-' . $this->counter,
            'region'           => $requestor->region,
            'branch'           => $requestor->branch,
            'office'           => $requestor->office,
            'status'           => 'Spare',
            'assigned_to_user' => $requestor->id,
        ]);

        $this->actingAs($requestor)->postJson(route('ict.store'), [
            'linked_asset_id'   => $asset->asset_id,
            'endUserLastName'   => 'D942',
            'endUserFirstName'  => 'Tester',
            'endUserSex'        => 'MALE',
            'divisionOffice'    => $requestor->office,
            'endUserEmail'      => $requestor->email,
            'employeeNo'        => 'EMP-D942',
            'repairDescription' => 'D9.42 per-region mirror test',
        ])->assertOk()->assertJson(['success' => true]);

        $ticket = RequestModel::where('linked_asset_id', $asset->asset_id)->firstOrFail();

        $this->assertMatchesRegularExpression('/^ICT-NCR-RCMB-\d{4}-\d{2}-\d{2}-\d{4}$/', $ticket->request_number);

        $repair = RepairRequest::findOrFail($ticket->detail_id);
        $this->assertSame($ticket->request_number, $repair->service_request_no,
            'repair_requests.service_request_no must mirror the new per-region number.');
    }

    public function test_manual_pm_falls_back_to_the_auth_user_region(): void
    {
        // The manual PM form (CreateMaintenanceTicketAction) stores
        // Auth::user()->region/branch on the tracking row and hands that same
        // user to the helper. Pin the Auth fallback so the number can never
        // silently collapse to ICT/PM-SYS-SYS- on the manual path.
        $it = $this->user(['role' => 'it', 'region' => 'REGION VII', 'branch' => 'RCMB']);

        $this->actingAs($it);
        $number = RequestHelpers::generateRequestNumber('Preventive Maintenance');

        $this->assertStringStartsWith('PM-RVII-RCMB-' . now()->format('Y-m-d') . '-', $number);
    }

    public function test_scheduled_pm_uses_the_end_user_region_even_without_auth(): void
    {
        // Console/scheduler runs have NO Auth::user() — the number must come
        // from the same region/branch expression the tracking row stores.
        $creator = $this->user(['role' => 'super_admin', 'branch' => null, 'office' => 'ADMIN']);
        $endUser = $this->user();

        $this->counter++;
        InventoryAsset::create([
            'category'         => 'Laptop',
            'item_name'        => 'D942 PM Laptop ' . $this->counter,
            'serial_number'    => 'D942PM-' . $this->counter,
            'property_number'  => 'D942PM-PN-' . $this->counter,
            'par_number'       => 'D942PM-PAR-' . $this->counter,
            'status'           => 'Active',
            'assigned_to_user' => $endUser->id,
            'office'           => $endUser->office,
            'branch'           => $endUser->branch,
            'date_acquired'    => '2022-01-01',
        ]);

        $schedule = PMSchedule::create([
            'schedule_name'    => 'D942 PM Schedule',
            'asset_categories' => [],
            'frequency'        => 'Quarterly',
            'is_active'        => true,
            'is_paused'        => false,
            'created_by'       => $creator->id,
        ]);

        $this->assertNull(auth()->user(), 'precondition: this run must be Auth-less like the scheduler');

        app(GeneratePMScheduleService::class)->generate($schedule);

        $ticket = RequestModel::where('pm_schedule_id', $schedule->id)->firstOrFail();

        $this->assertMatchesRegularExpression('/^PM-NCR-RCMB-\d{4}-\d{2}-\d{2}-\d{4}$/', $ticket->request_number);
        $this->assertStringStartsWith(
            'PM-' . RequestHelpers::getBranchCode($ticket->region)
                 . '-' . RequestHelpers::getBranchCode($ticket->branch) . '-',
            $ticket->request_number,
            'The number region/branch must match the columns stored on the row.'
        );
    }
}
