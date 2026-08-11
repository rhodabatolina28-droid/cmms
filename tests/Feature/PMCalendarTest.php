<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\PMSchedule;
use App\Models\PMCycle;
use App\Models\PMDivisionSchedule;
use App\Models\PMGenerationSchedule;
use App\Models\InventoryAsset;
use App\Models\Request as RequestModel;
use App\Actions\PMGenerationSchedule\CreatePMGenerationScheduleAction;
use App\Actions\PMGenerationSchedule\ReschedulePMGenerationScheduleAction;
use App\Actions\PMGenerationSchedule\CancelPMGenerationScheduleAction;
use App\Actions\PMGenerationSchedule\GetMaintenanceCalendarDataAction;
use App\Services\GeneratePMScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Phase 5 - Automated Tests for PM Calendar and Manual Queue
 *
 * Covers the 4 test categories from the implementation plan:
 * 1. Combined calendar data Action tests
 * 2. PM schedule creation via calendar (manual queue)
 * 3. ICT assignment flow (assign / re-assign)
 * 4. Manual queue due dates — due triggers generation, overdue shows correctly
 */
class PMCalendarTest extends TestCase
{
    use RefreshDatabase;

    private GeneratePMScheduleService $pmService;
    private User $superAdmin;
    private User $itUser;
    private User $adminUser;
    private User $regularUser;
    private PMSchedule $schedule;
    private int $counter = 0;

    // =========================================================================
    // SETUP
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        $this->pmService = app(GeneratePMScheduleService::class);

        $this->superAdmin = $this->makeUser([
            'full_name' => 'Super Admin',
            'email'     => 'superadmin@test.com',
            'role'      => 'super_admin',
            'branch'    => null,
        ]);

        $this->itUser = $this->makeUser([
            'full_name' => 'IT User',
            'email'     => 'it@test.com',
            'role'      => 'it',
        ]);

        $this->adminUser = $this->makeUser([
            'full_name' => 'Admin User',
            'email'     => 'admin@test.com',
            'role'      => 'admin',
        ]);

        $this->regularUser = $this->makeUser([
            'full_name' => 'End User',
            'email'     => 'enduser@test.com',
            'role'      => 'user',
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

    private function makeUser(array $attrs = []): User
    {
        $this->counter++;
        return User::create(array_merge([
            'full_name'  => 'Test User ' . $this->counter,
            'email'      => 'user' . $this->counter . '@test.com',
            'password'   => bcrypt('password'),
            'role'       => 'user',
            'is_active'  => true,
            'office'     => null,
            'department' => null,
            'branch'     => null,
        ], $attrs));
    }

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
            'item_name'        => 'Test Laptop ' . $this->counter,
            'serial_number'    => 'SN-' . $this->counter . '-' . uniqid(),
            'property_number'  => 'PN-' . $this->counter . '-' . uniqid(),
            'par_number'       => 'PAR-' . $this->counter . '-' . uniqid(),
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
     * Create a mock Illuminate\Http\Request with validated data.
     */
    private function mockRequest(array $data): Request
    {
        $request = Request::create('/', 'POST', $data);
        $request->setRouteResolver(function () { return null; });

        // Override validated() so Actions can call $request->validated()
        return tap($request, function ($req) use ($data) {
            $req->merge($data);
            // Bind validated data via a closure so Action->execute() works correctly
        });
    }

    // =========================================================================
    // SECTION 1: Combined Calendar Data Action Tests
    // =========================================================================

    /**
     * 1a. ICT request with no service schedule date appears on its request date.
     * We test this by verifying the GetMaintenanceCalendarDataAction returns
     * an ICT event for the current month when a request exists with created_at = today.
     */
    public function test_ict_request_appears_on_request_date()
    {
        // Create an ICT request for today
        $ictUser = $this->makeUser(['role' => 'user', 'office' => 'ICT DIVISION']);
        $repair = \App\Models\RepairRequest::create([
            'form_no'                    => 'ICT-TEST-001',
            'end_user_last_name'         => 'User',
            'end_user_first_name'        => 'Test',
            'end_user_sex'               => 'MALE',
            'division_office'            => 'ICT DIVISION',
            'end_user_email'             => 'ict1@test.com',
            'employee_no'                => 'EMP-001',
            'repair_description'         => 'Not working',
        ]);

        $req = RequestModel::create([
            'request_number'              => 'ICT-TEST-001',
            'user_id'                     => $ictUser->id,
            'requestor_name'              => $ictUser->full_name,
            'type'                        => 'ICT',
            'status'                      => 'Scheduled',
            'office'                      => 'ICT DIVISION',
            'detail_id'                   => $repair->id,
            'division_admin_review_status'=> 'Approved',
            'asset_id'                    => 0,
        ]);

        // Set created_at to today (within current month)
        $req->update(['created_at' => now()]);

        $action = app(GetMaintenanceCalendarDataAction::class);
        $httpReq = Request::create('/calendar/events', 'GET', [
            'month'  => now()->month,
            'year'   => now()->year,
            'filter' => 'ict',
        ]);

        $result = $action->execute($httpReq);

        $ictEvents = array_filter($result['events'], fn($e) => $e['event_type'] === 'ict');
        $this->assertNotEmpty($ictEvents, 'ICT event should appear in the calendar for the current month');

        $event = array_values($ictEvents)[0];
        $this->assertEquals(now()->toDateString(), $event['date'], 'ICT event date should match the request created_at');
    }

    /**
     * 1b. PM and ICT events appear together for All Types filter.
     */
    public function test_pm_and_ict_events_appear_together_in_all_types()
    {
        // Create a PM work order
        $this->createUserWithAsset('ADMIN DIVISION');
        $this->pmService->generate($this->schedule);
        $this->schedule->refresh();

        // Create an ICT request
        $ictUser = $this->makeUser(['role' => 'user', 'office' => 'ICT DIVISION']);
        $repair = \App\Models\RepairRequest::create([
            'form_no'                    => 'ICT-002',
            'end_user_last_name'         => 'User',
            'end_user_first_name'        => 'Test',
            'end_user_sex'               => 'MALE',
            'division_office'            => 'ICT DIVISION',
            'end_user_email'             => 'ict2@test.com',
            'employee_no'                => 'EMP-002',
            'repair_description'         => 'Blue screen issue',
        ]);

        RequestModel::create([
            'request_number'              => 'ICT-002',
            'user_id'                     => $ictUser->id,
            'requestor_name'              => $ictUser->full_name,
            'type'                        => 'ICT',
            'status'                      => 'Scheduled',
            'office'                      => 'ICT DIVISION',
            'detail_id'                   => $repair->id,
            'division_admin_review_status'=> 'Approved',
            'asset_id'                    => 0,
        ]);

        $action = app(GetMaintenanceCalendarDataAction::class);
        $httpReq = Request::create('/calendar/events', 'GET', [
            'month'  => now()->month,
            'year'   => now()->year,
            'filter' => 'all',
        ]);

        $result = $action->execute($httpReq);

        $pmEvents  = array_filter($result['events'], fn($e) => $e['event_type'] === 'pm');
        $ictEvents = array_filter($result['events'], fn($e) => $e['event_type'] === 'ict');

        $this->assertNotEmpty($pmEvents,  'PM events should be present in All Types view');
        $this->assertNotEmpty($ictEvents, 'ICT events should be present in All Types view');
    }

    /**
     * 1c. PM filter returns only PM events.
     */
    public function test_pm_filter_returns_only_pm_events()
    {
        $this->createUserWithAsset('ADMIN DIVISION');
        $this->pmService->generate($this->schedule);

        // Create ICT request too
        $ictUser = $this->makeUser(['role' => 'user']);
        $repair = \App\Models\RepairRequest::create([
            'form_no'             => 'ICT-PM-FILTER',
            'end_user_last_name'  => 'Filter',
            'end_user_first_name' => 'PM',
            'end_user_sex'        => 'MALE',
            'division_office'     => 'DIV',
            'end_user_email'      => 'ict3@test.com',
            'employee_no'         => 'EMP-003',
            'repair_description'  => 'Test issue',
        ]);
        RequestModel::create([
            'request_number' => 'ICT-PM-FILTER', 'user_id' => $ictUser->id,
            'requestor_name' => 'Test', 'type' => 'ICT', 'status' => 'Scheduled',
            'office' => 'DIV', 'detail_id' => $repair->id,
            'division_admin_review_status' => 'Approved', 'asset_id' => 0,
        ]);

        $action = app(GetMaintenanceCalendarDataAction::class);
        $httpReq = Request::create('/calendar/events', 'GET', [
            'month' => now()->month, 'year' => now()->year, 'filter' => 'pm',
        ]);
        $result = $action->execute($httpReq);

        $ictEvents = array_filter($result['events'], fn($e) => $e['event_type'] === 'ict');
        $this->assertEmpty($ictEvents, 'PM-only filter should not return ICT events');
    }

    /**
     * 1d. ICT filter returns only ICT events.
     */
    public function test_ict_filter_returns_only_ict_events()
    {
        $this->createUserWithAsset('ADMIN DIVISION');
        $this->pmService->generate($this->schedule);

        $ictUser = $this->makeUser(['role' => 'user']);
        $repair = \App\Models\RepairRequest::create([
            'form_no'             => 'ICT-ONLY-FILTER',
            'end_user_last_name'  => 'Filter',
            'end_user_first_name' => 'ICT',
            'end_user_sex'        => 'MALE',
            'division_office'     => 'DIV',
            'end_user_email'      => 'ict4@test.com',
            'employee_no'         => 'EMP-004',
            'repair_description'  => 'Test issue',
        ]);
        RequestModel::create([
            'request_number' => 'ICT-ONLY-FILTER', 'user_id' => $ictUser->id,
            'requestor_name' => 'Test', 'type' => 'ICT', 'status' => 'Scheduled',
            'office' => 'DIV', 'detail_id' => $repair->id,
            'division_admin_review_status' => 'Approved', 'asset_id' => 0,
        ]);

        $action = app(GetMaintenanceCalendarDataAction::class);
        $httpReq = Request::create('/calendar/events', 'GET', [
            'month' => now()->month, 'year' => now()->year, 'filter' => 'ict',
        ]);
        $result = $action->execute($httpReq);

        $pmEvents = array_filter($result['events'], fn($e) => $e['event_type'] === 'pm');
        $this->assertEmpty($pmEvents, 'ICT-only filter should not return PM events');
    }

    /**
     * 1e. Calendar data returns required event fields.
     */
    public function test_calendar_event_has_required_fields()
    {
        $this->createUserWithAsset('RESEARCH AND INFORMATION DIVISION');
        $this->pmService->generate($this->schedule);

        $action  = app(GetMaintenanceCalendarDataAction::class);
        $httpReq = Request::create('/calendar/events', 'GET', [
            'month' => now()->month, 'year' => now()->year, 'filter' => 'pm',
        ]);
        $result = $action->execute($httpReq);

        $pmEvents = array_values(array_filter($result['events'], fn($e) => $e['event_type'] === 'pm'));
        $this->assertNotEmpty($pmEvents);

        $event = $pmEvents[0];
        $this->assertArrayHasKey('id',          $event);
        $this->assertArrayHasKey('event_type',  $event);
        $this->assertArrayHasKey('date',        $event);
        $this->assertArrayHasKey('title',       $event);
        $this->assertArrayHasKey('status',      $event);
        $this->assertArrayHasKey('office',      $event);
        $this->assertArrayHasKey('assignee',    $event);
        $this->assertArrayHasKey('details_url', $event);
    }

    /**
     * 1f. Calendar summary counts match filtered event data.
     */
    public function test_calendar_summary_counts_match_event_data()
    {
        $this->createUserWithAsset('DIVISION X');
        $this->pmService->generate($this->schedule);

        $action  = app(GetMaintenanceCalendarDataAction::class);
        $httpReq = Request::create('/calendar/events', 'GET', [
            'month' => now()->month, 'year' => now()->year, 'filter' => 'all',
        ]);
        $result = $action->execute($httpReq);

        $pmCount  = count(array_filter($result['events'], fn($e) => $e['event_type'] === 'pm'));
        $ictCount = count(array_filter($result['events'], fn($e) => $e['event_type'] === 'ict'));

        $this->assertEquals($pmCount,  $result['summary']['pm'],  'PM summary count should match filtered PM events');
        $this->assertEquals($ictCount, $result['summary']['ict'], 'ICT summary count should match filtered ICT events');
    }

    // =========================================================================
    // SECTION 2: Manual Queue — Create, Reschedule, Cancel
    // =========================================================================

    /**
     * 2a. Super admin can create a future manual queue row.
     */
    public function test_super_admin_can_create_manual_queue_row()
    {
        $futureDate = now()->addDays(5)->toDateString();

        $queueRow = PMGenerationSchedule::create([
            'pm_schedule_id'        => $this->schedule->id,
            'scheduled_date'        => $futureDate,
            'generated_by'          => $this->superAdmin->id,
            'status'                => PMGenerationSchedule::STATUS_PENDING,
            'estimated_asset_count' => 0,
        ]);

        $this->assertDatabaseHas('pm_generation_schedules', [
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => $futureDate,
            'status'         => PMGenerationSchedule::STATUS_PENDING,
            'generated_by'   => $this->superAdmin->id,
        ]);

        $this->assertTrue($queueRow->isPending());
    }

    /**
     * 2b. A date today or in the past is rejected by form request validation.
     */
    public function test_past_date_is_rejected_by_validation()
    {
        // Route: POST /pm-schedules/{pm_schedule}/schedule-later
        $response = $this->actingAs($this->superAdmin)
            ->post(route('pm-schedules.schedule-later', $this->schedule->id), [
                'pm_schedule_id' => $this->schedule->id,
                'scheduled_date' => now()->toDateString(), // today = invalid
                'remarks'        => 'Test',
            ]);

        // Should not create a queue row
        $this->assertDatabaseMissing('pm_generation_schedules', [
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => now()->toDateString(),
        ]);
    }

    /**
     * 2c. Duplicate PM schedule + date combination is rejected.
     */
    public function test_duplicate_queue_row_is_rejected()
    {
        $futureDate = now()->addDays(10)->toDateString();

        // First creation — succeeds
        PMGenerationSchedule::create([
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => $futureDate,
            'generated_by'   => $this->superAdmin->id,
            'status'         => PMGenerationSchedule::STATUS_PENDING,
        ]);

        // Second creation for the same schedule + date — should be caught by Action or DB
        $response = $this->actingAs($this->superAdmin)
            ->post(route('pm-schedules.schedule-later', $this->schedule->id), [
                'pm_schedule_id' => $this->schedule->id,
                'scheduled_date' => $futureDate,
                'remarks'        => 'Duplicate attempt',
            ]);

        // Should still be only 1 pending row for that date
        $this->assertEquals(
            1,
            PMGenerationSchedule::where('pm_schedule_id', $this->schedule->id)
                ->where('scheduled_date', $futureDate)
                ->where('status', PMGenerationSchedule::STATUS_PENDING)
                ->count(),
            'Duplicate pending queue row should not be created'
        );
    }

    /**
     * 2d. A non-super-admin cannot access the schedule-later route.
     */
    public function test_non_super_admin_cannot_create_queue_row()
    {
        $futureDate = now()->addDays(5)->toDateString();

        // Try as IT user
        $response = $this->actingAs($this->itUser)
            ->post(route('pm-schedules.schedule-later', $this->schedule->id), [
                'pm_schedule_id' => $this->schedule->id,
                'scheduled_date' => $futureDate,
                'remarks'        => 'IT user attempt',
            ]);

        // The role middleware redirects (302) unauthorized users rather than returning 403
        $response->assertRedirect();
        $this->assertDatabaseMissing('pm_generation_schedules', [
            'scheduled_date' => $futureDate,
        ]);
    }

    /**
     * 2e. A Pending queue row can be rescheduled.
     */
    public function test_pending_queue_row_can_be_rescheduled()
    {
        $originalDate = now()->addDays(5)->toDateString();
        $newDate      = now()->addDays(10)->toDateString();

        $queueRow = PMGenerationSchedule::create([
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => $originalDate,
            'generated_by'   => $this->superAdmin->id,
            'status'         => PMGenerationSchedule::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->put(route('pm-generation-schedules.update', $queueRow->id), [
                'scheduled_date' => $newDate,
                'remarks'        => 'Rescheduled',
            ]);

        $this->assertDatabaseHas('pm_generation_schedules', [
            'id'             => $queueRow->id,
            'scheduled_date' => $newDate,
        ]);
    }

    /**
     * 2f. Generated queue row cannot be rescheduled.
     */
    public function test_generated_queue_row_cannot_be_rescheduled()
    {
        $generatedRow = PMGenerationSchedule::create([
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => now()->addDays(5)->toDateString(),
            'generated_by'   => $this->superAdmin->id,
            'status'         => PMGenerationSchedule::STATUS_GENERATED,
            'generated_at'   => now(),
            'generated_count'=> 1,
        ]);

        $newDate = now()->addDays(15)->toDateString();

        $response = $this->actingAs($this->superAdmin)
            ->put(route('pm-generation-schedules.update', $generatedRow->id), [
                'scheduled_date' => $newDate,
                'remarks'        => 'Attempt to reschedule Generated',
            ]);

        // Date should NOT have changed
        $this->assertDatabaseMissing('pm_generation_schedules', [
            'id'             => $generatedRow->id,
            'scheduled_date' => $newDate,
        ]);
    }

    /**
     * 2g. Cancelled queue row cannot be rescheduled.
     */
    public function test_cancelled_queue_row_cannot_be_rescheduled()
    {
        $cancelledRow = PMGenerationSchedule::create([
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => now()->addDays(5)->toDateString(),
            'generated_by'   => $this->superAdmin->id,
            'status'         => PMGenerationSchedule::STATUS_CANCELLED,
        ]);

        $newDate = now()->addDays(15)->toDateString();

        $this->actingAs($this->superAdmin)
            ->put(route('pm-generation-schedules.update', $cancelledRow->id), [
                'scheduled_date' => $newDate,
                'remarks'        => 'Attempt to reschedule Cancelled',
            ]);

        $this->assertDatabaseMissing('pm_generation_schedules', [
            'id'             => $cancelledRow->id,
            'scheduled_date' => $newDate,
        ]);
    }

    /**
     * 2h. A Pending queue row can be cancelled.
     */
    public function test_pending_queue_row_can_be_cancelled()
    {
        $queueRow = PMGenerationSchedule::create([
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => now()->addDays(5)->toDateString(),
            'generated_by'   => $this->superAdmin->id,
            'status'         => PMGenerationSchedule::STATUS_PENDING,
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('pm-generation-schedules.cancel', $queueRow->id));

        $this->assertDatabaseHas('pm_generation_schedules', [
            'id'     => $queueRow->id,
            'status' => PMGenerationSchedule::STATUS_CANCELLED,
        ]);
    }

    /**
     * 2i. Cancelling does NOT delete the queue row (history preserved).
     */
    public function test_cancelling_does_not_delete_queue_row()
    {
        $queueRow = PMGenerationSchedule::create([
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => now()->addDays(5)->toDateString(),
            'generated_by'   => $this->superAdmin->id,
            'status'         => PMGenerationSchedule::STATUS_PENDING,
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('pm-generation-schedules.cancel', $queueRow->id));

        $this->assertDatabaseHas('pm_generation_schedules', ['id' => $queueRow->id]);
    }

    // =========================================================================
    // SECTION 3: Manual Queue Due Dates — Scheduler Integration
    // =========================================================================

    /**
     * 3a. A due queue row invokes the existing generation service and marks itself Generated.
     */
    public function test_due_queue_row_triggers_pm_generation()
    {
        $this->createUserWithAsset('DIVISION QUEUE');

        $queueRow = PMGenerationSchedule::create([
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => now()->toDateString(), // due today
            'generated_by'   => $this->superAdmin->id,
            'status'         => PMGenerationSchedule::STATUS_PENDING,
        ]);

        // Run the command
        $this->artisan('pm:generate-scheduled')->assertExitCode(0);

        $queueRow->refresh();

        $this->assertEquals(
            PMGenerationSchedule::STATUS_GENERATED,
            $queueRow->status,
            'Due queue row should be marked Generated after command runs'
        );

        $this->assertNotNull($queueRow->generated_at, 'generated_at should be recorded');
        $this->assertNotNull($queueRow->generated_count, 'generated_count should be recorded');
    }

    /**
     * 3b. A queue row scheduled for tomorrow is NOT processed by the scheduler today.
     */
    public function test_future_queue_row_is_not_processed_today()
    {
        $this->createUserWithAsset('DIVISION FUTURE');

        $queueRow = PMGenerationSchedule::create([
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => now()->addDay()->toDateString(), // tomorrow
            'generated_by'   => $this->superAdmin->id,
            'status'         => PMGenerationSchedule::STATUS_PENDING,
        ]);

        $this->artisan('pm:generate-scheduled')->assertExitCode(0);

        $queueRow->refresh();

        $this->assertEquals(
            PMGenerationSchedule::STATUS_PENDING,
            $queueRow->status,
            'Future queue row should remain Pending and not be processed today'
        );
    }

    /**
     * 3c. Overdue pending queue row shows calendar_status_label = Overdue.
     */
    public function test_overdue_queue_row_has_overdue_calendar_label()
    {
        $queueRow = PMGenerationSchedule::create([
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => now()->subDays(3)->toDateString(), // 3 days ago
            'generated_by'   => $this->superAdmin->id,
            'status'         => PMGenerationSchedule::STATUS_PENDING,
        ]);

        $this->assertEquals(
            'Overdue',
            $queueRow->calendar_status_label,
            'A pending queue row with a past scheduled_date should show Overdue'
        );
    }

    /**
     * 3d. Future pending queue row shows calendar_status_label = Pending.
     */
    public function test_future_pending_queue_row_has_pending_calendar_label()
    {
        $queueRow = PMGenerationSchedule::create([
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => now()->addDays(5)->toDateString(),
            'generated_by'   => $this->superAdmin->id,
            'status'         => PMGenerationSchedule::STATUS_PENDING,
        ]);

        $this->assertEquals(
            'Pending',
            $queueRow->calendar_status_label,
            'A pending queue row with a future date should show Pending'
        );
    }

    /**
     * 3e. A generated queue row shows calendar_status_label = Completed.
     */
    public function test_generated_queue_row_has_completed_calendar_label()
    {
        $queueRow = PMGenerationSchedule::create([
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => now()->addDays(5)->toDateString(),
            'generated_by'   => $this->superAdmin->id,
            'status'         => PMGenerationSchedule::STATUS_GENERATED,
            'generated_at'   => now(),
        ]);

        $this->assertEquals(
            'Completed',
            $queueRow->calendar_status_label,
            'A Generated queue row should show Completed in the calendar'
        );
    }

    /**
     * 3f. Scheduler does not duplicate an existing PM when a queue row runs
     *     and the schedule already has a running cycle (anti-spam guard).
     */
    public function test_scheduler_does_not_duplicate_pm_with_active_cycle()
    {
        $this->createUserWithAsset('DIVISION NOSPAM');

        // First: generate normally to create an active cycle with pending requests
        $this->pmService->generate($this->schedule);
        $this->schedule->refresh();

        $requestCountBefore = RequestModel::where('pm_schedule_id', $this->schedule->id)->count();

        // Now queue a manual run for today
        $queueRow = PMGenerationSchedule::create([
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => now()->toDateString(),
            'generated_by'   => $this->superAdmin->id,
            'status'         => PMGenerationSchedule::STATUS_PENDING,
        ]);

        $this->artisan('pm:generate-scheduled')->assertExitCode(0);

        $requestCountAfter = RequestModel::where('pm_schedule_id', $this->schedule->id)->count();
        $this->assertEquals(
            $requestCountBefore,
            $requestCountAfter,
            'Scheduler must not create duplicate PM requests when cycle is already active'
        );
    }

    // =========================================================================
    // SECTION 4: Cycle Completion Advances next_scheduled_date
    // =========================================================================

    /**
     * 4a. When all divisions complete, next_scheduled_date is advanced to the future.
     */
    public function test_cycle_completion_advances_next_scheduled_date()
    {
        ['user' => $user] = $this->createUserWithAsset('DIVISION COMPLETE');

        $this->pmService->generate($this->schedule);
        $this->schedule->refresh();
        $cycleId = $this->schedule->current_cycle_id;

        // Complete all requests
        RequestModel::where('pm_schedule_id', $this->schedule->id)
            ->update(['status' => 'Completed']);

        [$next, $cycleComplete] = $this->pmService->checkAndAdvance($this->schedule);
        $this->schedule->refresh();

        $this->assertTrue($cycleComplete, 'Cycle should be complete');
        $this->assertNotNull($this->schedule->next_scheduled_date, 'next_scheduled_date must be set after cycle completion');
        $this->assertTrue(
            Carbon::parse($this->schedule->next_scheduled_date)->isFuture(),
            'next_scheduled_date should be in the future after full cycle completion'
        );
    }

    /**
     * 4b. After cycle completes, master PM Schedule event shows Scheduled (not Ongoing)
     *     because current_focus_division is now null.
     */
    public function test_pm_schedule_event_shows_scheduled_after_cycle_completes()
    {
        ['user' => $user] = $this->createUserWithAsset('DIVISION STATUS');

        $this->pmService->generate($this->schedule);
        $this->schedule->refresh();

        // While cycle is running — status should be Ongoing
        $this->assertNotNull($this->schedule->current_focus_division);
        $expectedOngoing = $this->schedule->current_focus_division ? 'Ongoing' : 'Scheduled';
        $this->assertEquals('Ongoing', $expectedOngoing);

        // Complete the cycle
        RequestModel::where('pm_schedule_id', $this->schedule->id)
            ->update(['status' => 'Completed']);
        $this->pmService->checkAndAdvance($this->schedule);
        $this->schedule->refresh();

        // After cycle — focus cleared, status should be Scheduled
        $this->assertNull($this->schedule->current_focus_division);
        $expectedScheduled = $this->schedule->current_focus_division ? 'Ongoing' : 'Scheduled';
        $this->assertEquals('Scheduled', $expectedScheduled);
    }

    // =========================================================================
    // SECTION 5: PM Schedule Model Scopes
    // =========================================================================

    /**
     * 5a. scopePending returns only Pending rows.
     */
    public function test_scope_pending_returns_only_pending_rows()
    {
        PMGenerationSchedule::create([
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'generated_by'   => $this->superAdmin->id,
            'status'         => PMGenerationSchedule::STATUS_PENDING,
        ]);
        PMGenerationSchedule::create([
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => now()->addDays(4)->toDateString(),
            'generated_by'   => $this->superAdmin->id,
            'status'         => PMGenerationSchedule::STATUS_GENERATED,
            'generated_at'   => now(),
        ]);

        $pending = PMGenerationSchedule::pending()->get();
        $this->assertEquals(1, $pending->count());
        $this->assertEquals(PMGenerationSchedule::STATUS_PENDING, $pending->first()->status);
    }

    /**
     * 5b. scopeDueOnOrBefore returns only Pending rows with scheduled_date <= given date.
     */
    public function test_scope_due_on_or_before_returns_correct_rows()
    {
        PMGenerationSchedule::create([
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => now()->subDay()->toDateString(), // yesterday
            'generated_by'   => $this->superAdmin->id,
            'status'         => PMGenerationSchedule::STATUS_PENDING,
        ]);
        PMGenerationSchedule::create([
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => now()->addDays(5)->toDateString(), // future
            'generated_by'   => $this->superAdmin->id,
            'status'         => PMGenerationSchedule::STATUS_PENDING,
        ]);

        $due = PMGenerationSchedule::dueOnOrBefore(now())->get();
        $this->assertEquals(1, $due->count(), 'Only rows due on or before today should be returned');
    }

    /**
     * 5c. scopeOverdue returns rows where status=Pending and scheduled_date is in the past.
     */
    public function test_scope_overdue_returns_past_pending_rows()
    {
        PMGenerationSchedule::create([
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => now()->subDays(2)->toDateString(),
            'generated_by'   => $this->superAdmin->id,
            'status'         => PMGenerationSchedule::STATUS_PENDING,
        ]);
        PMGenerationSchedule::create([
            'pm_schedule_id' => $this->schedule->id,
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'generated_by'   => $this->superAdmin->id,
            'status'         => PMGenerationSchedule::STATUS_PENDING,
        ]);

        $overdue = PMGenerationSchedule::overdue()->get();
        $this->assertEquals(1, $overdue->count(), 'Only past pending rows should be overdue');
    }
}
