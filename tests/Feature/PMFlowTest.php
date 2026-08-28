<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\PMSchedule;
use App\Models\PMCycle;
use App\Models\PMDivisionSchedule;
use App\Models\InventoryAsset;
use App\Models\Request as RequestModel;
use App\Services\GeneratePMScheduleService;
use Carbon\Carbon;

class PMFlowTest extends TestCase
{
    use RefreshDatabase;

    private GeneratePMScheduleService $pmService;
    private User $superAdmin;
    private PMSchedule $schedule;
    private int $userCounter = 0;

    // =========================================================================
    // SETUP
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        $this->pmService = app(GeneratePMScheduleService::class);

        $this->superAdmin = $this->makeUser([
            'full_name'  => 'Super Admin',
            'email'      => 'superadmin@test.com',
            'role'       => 'super_admin',
            'branch'     => null,
            'office'     => 'ADMIN',
            'department' => 'ADMIN',
        ]);

        $this->schedule = PMSchedule::create([
            'schedule_name'    => 'Test PM Schedule',
            'asset_categories' => [],
            'frequency'        => 'Quarterly',
            'is_active'        => true,
            'is_paused'        => false,
            'created_by'       => $this->superAdmin->id,
        ]);

        $this->actingAs($this->superAdmin);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Create a user directly — no Faker to avoid abstract class issues.
     */
    private function makeUser(array $attrs = []): User
    {
        $this->userCounter++;
        return User::create(array_merge([
            'full_name'  => 'Test User ' . $this->userCounter,
            'email'      => 'user' . $this->userCounter . '@test.com',
            'password'   => bcrypt('password'),
            'role'       => 'user',
            'is_active'  => true,
            'office'     => null,
            'department' => null,
            'branch'     => null,
        ], $attrs));
    }

    /**
     * Create a user with one active asset assigned to them.
     */
    private function createUserWithAsset(
        string $division,
        string $branch = null,
        string $dateAcquired = '2022-01-01',
        string $status = 'Active'
    ): array {
        $user = $this->makeUser([
            'office'     => $division,
            'department' => $division,
            'branch'     => $branch,
        ]);

        $asset = InventoryAsset::create([
            'category'         => 'Laptop',
            'item_name'        => 'Test Laptop ' . $this->userCounter,
            'serial_number'    => 'SN-' . $this->userCounter . '-' . time(),
            'property_number'  => 'PN-' . $this->userCounter . '-' . time(),
            'par_number'       => 'PAR-' . $this->userCounter . '-' . time(),
            'brand'            => 'TestBrand',
            'model'            => 'TestModel',
            'acquisition_cost' => 50000,
            'status'           => $status,
            'assigned_to_user' => $user->id,
            'office'           => $division,
            'department'       => $division,
            'branch'           => $branch,
            'date_acquired'    => $dateAcquired,
        ]);

        return compact('user', 'asset');
    }

    /**
     * Mark a request as Completed.
     */
    private function completeRequest(RequestModel $req): void
    {
        $req->update(['status' => 'Completed']);
    }

    // =========================================================================
    // TEST 1: Generate creates work orders for active asset users
    // =========================================================================

    public function test_generate_creates_work_orders_for_active_assets()
    {
        $this->createUserWithAsset('RESEARCH AND INFORMATION DIVISION');
        $this->createUserWithAsset('RESEARCH AND INFORMATION DIVISION');

        $created = $this->pmService->generate($this->schedule);

        $this->assertCount(2, $created, 'Should create one work order per user in the division');
        $this->assertDatabaseHas('requests', [
            'type'             => 'Preventive Maintenance',
            'is_auto_generated'=> 1,
            'status'           => 'Scheduled',
            'pm_schedule_id'   => $this->schedule->id,
        ]);
    }

    // =========================================================================
    // TEST 2: Spare and disposed assets are excluded
    // =========================================================================

    public function test_spare_and_disposed_assets_excluded_from_pm()
    {
        // Active + assigned = included
        $this->createUserWithAsset('ADMIN DIVISION', null, '2022-01-01', 'Active');

        // For Disposal = excluded (even with user assigned, service filters status !== 'Active')
        // Note: model booted() hook converts Spare+user to Active, so use a
        // status the model preserves as-is: 'For Disposal'
        ['user' => $disposalUser] = $this->createUserWithAsset('ADMIN DIVISION', null, '2022-01-01', 'Active');
        \App\Models\InventoryAsset::where('assigned_to_user', $disposalUser->id)
            ->update(['status' => 'For Disposal']); // bypass model hook via direct DB update

        // Unassigned active asset = excluded (no user)
        InventoryAsset::create([
            'category'         => 'Laptop',
            'item_name'        => 'Spare No User',
            'serial_number'    => 'SN-SPARE-X',
            'property_number'  => 'PN-SPARE-X',
            'par_number'       => 'PAR-SPARE-X',
            'brand'            => 'Brand',
            'model'            => 'Model',
            'acquisition_cost' => 30000,
            'status'           => 'Active',
            'assigned_to_user' => null,
            'region'           => null,
            'office'           => 'ADMIN DIVISION',
        ]);

        $created = $this->pmService->generate($this->schedule);

        $this->assertCount(1, $created, 'Only the Active+assigned user should receive a PM work order');
    }

    // =========================================================================
    // TEST 3: First generation creates a new PM cycle
    // =========================================================================

    public function test_first_generation_creates_a_new_pm_cycle()
    {
        $this->createUserWithAsset('RESEARCH AND INFORMATION DIVISION');

        $this->assertNull($this->schedule->current_cycle_id, 'No cycle should exist before first generation');

        $this->pmService->generate($this->schedule);
        $this->schedule->refresh();

        $this->assertNotNull($this->schedule->current_cycle_id, 'A cycle should be created after generation');
        $this->assertDatabaseHas('pm_cycles', [
            'pm_schedule_id' => $this->schedule->id,
            'cycle_number'   => 1,
        ]);
    }

    // =========================================================================
    // TEST 4: Anti-spam — no duplicate when requests are pending
    // =========================================================================

    public function test_no_duplicate_generation_while_requests_pending()
    {
        $this->createUserWithAsset('RESEARCH AND INFORMATION DIVISION');

        $first = $this->pmService->generate($this->schedule);
        $this->schedule->refresh();
        $this->assertCount(1, $first);

        $second = $this->pmService->generate($this->schedule);
        $this->assertEmpty($second, 'Should not generate when pending requests already exist');

        $this->assertEquals(1, RequestModel::where('pm_schedule_id', $this->schedule->id)->count());
    }

    // =========================================================================
    // TEST 5: Division completion advances to next division
    // =========================================================================

    public function test_completing_division_advances_to_next_division()
    {
        ['user' => $userA] = $this->createUserWithAsset('DIVISION A', null, '2020-01-01');
        ['user' => $userB] = $this->createUserWithAsset('DIVISION B', null, '2023-01-01');

        $this->pmService->generate($this->schedule);
        $this->schedule->refresh();

        $this->assertEquals('DIVISION A', $this->schedule->current_focus_division);

        RequestModel::where('user_id', $userA->id)->update(['status' => 'Completed']);
        [$nextDiv, $cycleComplete] = $this->pmService->checkAndAdvance($this->schedule);
        $this->schedule->refresh();

        $this->assertEquals('DIVISION B', $nextDiv, 'Should advance to DIVISION B');
        $this->assertFalse($cycleComplete, 'Cycle should not be complete yet');
        $this->assertNull($this->schedule->current_focus_division, 'Focus cleared for next generation');

        $divRecord = PMDivisionSchedule::where('division_name', 'DIVISION A')->first();
        $this->assertNotNull($divRecord, 'DIVISION A should be recorded in pm_division_schedules');
        $this->assertNotNull($divRecord->last_completed_at, 'DIVISION A should be marked completed');
    }

    // =========================================================================
    // TEST 6: Full cycle completes when all divisions are done
    // =========================================================================

    public function test_full_cycle_completes_when_all_divisions_done()
    {
        ['user' => $userA] = $this->createUserWithAsset('DIVISION A', null, '2020-01-01');
        ['user' => $userB] = $this->createUserWithAsset('DIVISION B', null, '2023-01-01');

        // Generate + complete Division A
        $this->pmService->generate($this->schedule);
        $this->schedule->refresh();
        $cycleId = $this->schedule->current_cycle_id;

        RequestModel::where('user_id', $userA->id)->update(['status' => 'Completed']);
        $this->pmService->checkAndAdvance($this->schedule);
        $this->schedule->refresh();

        // Generate + complete Division B
        $this->pmService->generate($this->schedule);
        $this->schedule->refresh();

        RequestModel::where('user_id', $userB->id)->update(['status' => 'Completed']);
        [, $cycleComplete] = $this->pmService->checkAndAdvance($this->schedule);
        $this->schedule->refresh();

        $this->assertTrue($cycleComplete, 'Cycle should be complete after all divisions done');
        $this->assertNull($this->schedule->current_cycle_id, 'current_cycle_id cleared after full cycle');
        $this->assertNull($this->schedule->current_focus_division, 'Focus division cleared after full cycle');

        $cycle = PMCycle::find($cycleId);
        $this->assertNotNull($cycle->completed_at, 'Cycle completed_at should be recorded');
    }

    // =========================================================================
    // TEST 7: Cooldown prevents premature new cycle
    // =========================================================================

    public function test_cooldown_prevents_premature_new_cycle()
    {
        ['user' => $userA] = $this->createUserWithAsset('DIVISION A');

        $this->pmService->generate($this->schedule);
        $this->schedule->refresh();
        RequestModel::where('user_id', $userA->id)->update(['status' => 'Completed']);
        $this->pmService->checkAndAdvance($this->schedule);
        $this->schedule->refresh();

        // Cycle is now complete — cooldown active
        $result = $this->pmService->generate($this->schedule);

        $this->assertArrayHasKey('__cooldown__', $result, 'Should return cooldown signal');
        $this->assertEquals(1, PMCycle::where('pm_schedule_id', $this->schedule->id)->count(), 'No new cycle during cooldown');
    }

    // =========================================================================
    // TEST 8: Disposed asset after completion — division still shows Completed
    // =========================================================================

    public function test_disposed_asset_after_completion_division_still_shows_completed()
    {
        ['user' => $user, 'asset' => $asset] = $this->createUserWithAsset('RESEARCH AND INFORMATION DIVISION');

        $this->pmService->generate($this->schedule);
        $this->schedule->refresh();
        $cycleId = $this->schedule->current_cycle_id;

        RequestModel::where('user_id', $user->id)->update(['status' => 'Completed']);
        $this->pmService->checkAndAdvance($this->schedule);

        // Dispose the asset AFTER PM completion
        $asset->update(['status' => 'For Disposal']);

        $divRecord = PMDivisionSchedule::where('pm_cycle_id', $cycleId)
            ->where('division_name', 'RESEARCH AND INFORMATION DIVISION')
            ->first();

        $this->assertNotNull($divRecord, 'Division record must persist after asset disposal');
        $this->assertNotNull($divRecord->last_completed_at, 'Division must still be marked completed');
    }

    // =========================================================================
    // TEST 9: Next cycle excludes users with disposed assets
    // =========================================================================

    public function test_next_cycle_excludes_users_with_disposed_assets()
    {
        ['user' => $userA, 'asset' => $assetA] = $this->createUserWithAsset('DIVISION A', null, '2020-01-01');
        ['user' => $userB]                      = $this->createUserWithAsset('DIVISION A', null, '2021-01-01');

        // Run full cycle for DIVISION A (both users)
        $this->pmService->generate($this->schedule);
        $this->schedule->refresh();
        RequestModel::where('pm_schedule_id', $this->schedule->id)->update(['status' => 'Completed']);
        [, $cycleComplete] = $this->pmService->checkAndAdvance($this->schedule);
        $this->schedule->refresh();

        $this->assertTrue($cycleComplete, 'Cycle should be complete after all done');
        $this->assertNull($this->schedule->current_cycle_id, 'current_cycle_id cleared');

        // Dispose userA's asset BEFORE the next cycle starts
        $assetA->update(['status' => 'For Disposal']);

        // Expire cooldown by pushing next_scheduled_at to yesterday
        PMDivisionSchedule::where('pm_schedule_id', $this->schedule->id)
            ->update(['next_scheduled_at' => Carbon::yesterday()->toDateString()]);

        // Generate next cycle
        $newCreated = $this->pmService->generate($this->schedule);
        $this->schedule->refresh();

        // Should not be empty — userB should get a PM
        $this->assertNotEmpty($newCreated, 'Next cycle should generate at least 1 work order');

        // Only look at requests from the NEW cycle (by request_number)
        $newUserIds = RequestModel::whereIn('request_number', $newCreated)
            ->pluck('user_id')
            ->toArray();

        $this->assertNotContains($userA->id, $newUserIds, 'Disposed-asset user should NOT be in next cycle');
        $this->assertContains($userB->id, $newUserIds, 'Active-asset user should be in next cycle');
    }

    // =========================================================================
    // TEST 10: Paused schedule — checkAndAdvance does nothing
    // =========================================================================

    public function test_paused_schedule_does_not_advance()
    {
        ['user' => $user] = $this->createUserWithAsset('DIVISION A');

        $this->pmService->generate($this->schedule);
        $this->schedule->refresh();

        RequestModel::where('user_id', $user->id)->update(['status' => 'Completed']);
        $this->schedule->update(['is_paused' => true]);

        [$nextDiv, $cycleComplete] = $this->pmService->checkAndAdvance($this->schedule);

        $this->assertNull($nextDiv, 'Paused schedule should not advance to next division');
        $this->assertFalse($cycleComplete, 'Paused schedule should not complete cycle');
    }

    // =========================================================================
    // TEST 11: Oldest asset division is processed first
    // =========================================================================

    public function test_oldest_asset_division_is_processed_first()
    {
        $this->createUserWithAsset('DIVISION B', null, '2019-01-01'); // older
        $this->createUserWithAsset('DIVISION A', null, '2023-01-01'); // newer

        $this->pmService->generate($this->schedule);
        $this->schedule->refresh();

        $this->assertEquals(
            'DIVISION B',
            $this->schedule->current_focus_division,
            'Division with oldest assets should be processed first'
        );
    }

    // =========================================================================
    // TEST 12: Generated PM request has correct fields
    // =========================================================================

    public function test_generated_pm_request_has_correct_fields()
    {
        ['user' => $user] = $this->createUserWithAsset('INFORMATION TECHNOLOGY');

        $created = $this->pmService->generate($this->schedule);
        $this->schedule->refresh();

        $this->assertCount(1, $created);

        $req = RequestModel::where('request_number', $created[0])->first();
        $this->assertNotNull($req);
        $this->assertEquals('Preventive Maintenance', $req->type);
        $this->assertEquals('Scheduled', $req->status);
        $this->assertEquals(1, $req->is_auto_generated);
        $this->assertEquals($this->schedule->id, $req->pm_schedule_id);
        $this->assertEquals($user->id, $req->user_id);
        $this->assertNotNull($req->detail_id, 'PM request must be linked to a preventive_maintenance record');
    }

    // =========================================================================
    // TEST 13: Unassigned assets are excluded
    // =========================================================================

    public function test_unassigned_assets_are_excluded()
    {
        InventoryAsset::create([
            'category'         => 'Laptop',
            'item_name'        => 'Unassigned Laptop',
            'serial_number'    => 'SN-UNASSIGNED',
            'property_number'  => 'PN-UNASSIGNED',
            'par_number'       => 'PAR-UNASSIGNED',
            'brand'            => 'Brand',
            'model'            => 'Model',
            'acquisition_cost' => 30000,
            'status'           => 'Active',
            'assigned_to_user' => null,
            'office'           => 'SOME DIVISION',
        ]);

        $created = $this->pmService->generate($this->schedule);

        $this->assertEmpty($created, 'Unassigned assets should not generate PM work orders');
    }

    // =========================================================================
    // TEST 14: Completed division is not re-generated in same cycle
    // =========================================================================

    public function test_completed_division_not_regenerated_in_same_cycle()
    {
        ['user' => $userA] = $this->createUserWithAsset('DIVISION A', null, '2020-01-01');
        ['user' => $userB] = $this->createUserWithAsset('DIVISION B', null, '2023-01-01');

        // Wave 1 — Division A
        $this->pmService->generate($this->schedule);
        $this->schedule->refresh();
        RequestModel::where('user_id', $userA->id)->update(['status' => 'Completed']);
        $this->pmService->checkAndAdvance($this->schedule);

        // Wave 2 — Division B
        $secondWave = $this->pmService->generate($this->schedule);
        $this->schedule->refresh();

        $this->assertCount(1, $secondWave, 'Second wave should only generate for Division B');

        $req = RequestModel::where('request_number', $secondWave[0])->first();
        $this->assertEquals($userB->id, $req->user_id, 'Second wave request should belong to Division B user');

        $this->assertEquals(2, RequestModel::where('pm_schedule_id', $this->schedule->id)->count(), 'Total: 1 per division');
    }

    // =========================================================================
    // TEST 15: next_scheduled_at is computed correctly (Quarterly = +3 months)
    // =========================================================================

    public function test_next_scheduled_at_computed_correctly_for_quarterly_frequency()
    {
        ['user' => $user] = $this->createUserWithAsset('DIVISION A');

        $this->pmService->generate($this->schedule);
        $this->schedule->refresh();
        $cycleId = $this->schedule->current_cycle_id;

        RequestModel::where('user_id', $user->id)->update(['status' => 'Completed']);
        $this->pmService->checkAndAdvance($this->schedule);

        $divRecord = PMDivisionSchedule::where('pm_cycle_id', $cycleId)
            ->where('division_name', 'DIVISION A')
            ->first();

        $this->assertNotNull($divRecord->next_scheduled_at, 'next_scheduled_at must be set');

        // Expected: completed_date + 3 months, but the service skips weekends
        // (government offices are closed Sat/Sun), so the next date may roll
        // forward to the next weekday.
        $completed = Carbon::parse($divRecord->last_completed_at);
        $expectedBase = $completed->copy()->addMonths(3);
        $next = $divRecord->next_scheduled_at;

        $this->assertTrue(
            $next->greaterThanOrEqualTo($expectedBase->startOfDay()),
            'next_scheduled_at must be at least completed_date + 3 months'
        );
        $this->assertFalse(
            $next->isWeekend(),
            'next_scheduled_at must not fall on a weekend (weekday skip rule)'
        );
        // It must never roll more than 2 days forward (Sat -> Mon)
        $this->assertTrue(
            $next->lessThanOrEqualTo($expectedBase->copy()->addDays(2)->endOfDay()),
            'next_scheduled_at rolled too far beyond completed_date + 3 months'
        );
    }
}
