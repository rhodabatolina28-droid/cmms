<?php

namespace Tests\Feature;

use App\Models\PreventiveMaintenance;
use App\Models\RepairRequest;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D9.42 Phase 2 — `php artisan requests:renumber` backfill.
 *
 * Moves legacy numbers to the per-region daily format
 * {ICT|PM}-{REGION}-{BRANCH}-{YYYY}-{MM}-{DD}-{NNNN}, mirrors included, and
 * refuses to overwrite a number that another request already holds.
 */
class RequestRenumberCommandTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(array $attrs = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'D942 Renumber ' . $this->counter,
            'email'     => 'd942-renumber-' . $this->counter . '@test.com',
            'password'  => bcrypt('password'),
            'role'      => 'user',
            'is_active' => true,
            'region'    => 'NCR',
            'branch'    => 'RCMB',
            'office'    => 'RESEARCH AND INFORMATION DIVISION',
        ], $attrs));
    }

    private function ticket(string $number, User $owner, array $attrs = []): RequestModel
    {
        return RequestModel::create(array_merge([
            'user_id'        => $owner->id,
            'request_number' => $number,
            'type'           => 'ICT',
            'requestor_name' => $owner->full_name,
            'region'         => $owner->region,
            'branch'         => $owner->branch,
            'office'         => $owner->office,
            'status'         => 'Pending',
            'is_deleted'     => false,
            'description'    => 'D9.42 renumber test ' . $number,
        ], $attrs));
    }

    /** created_at is not fillable — set it through the query builder. */
    private function happenedAt(RequestModel $ticket, string $datetime): RequestModel
    {
        RequestModel::withTrashed()->whereKey($ticket->getKey())->update(['created_at' => $datetime]);

        return $ticket->refresh();
    }

    private function repairDetail(?string $serviceRequestNo = null): RepairRequest
    {
        $this->counter++;

        return RepairRequest::create([
            'service_request_no'  => $serviceRequestNo,
            'end_user_last_name'  => 'D942',
            'end_user_first_name' => 'Mirror' . $this->counter,
            'end_user_sex'        => 'MALE',
            'division_office'     => 'RESEARCH AND INFORMATION DIVISION',
            'end_user_email'      => 'd942-mirror-' . $this->counter . '@test.com',
            'employee_no'         => 'EMP-' . $this->counter,
            'repair_description'  => 'D9.42 mirror row',
        ]);
    }

    private function pmDetail(array $attrs = []): PreventiveMaintenance
    {
        $this->counter++;

        return PreventiveMaintenance::create(array_merge([
            'end_user_name' => 'D942 PM ' . $this->counter,
        ], $attrs));
    }

    private function number(int $id): ?string
    {
        return RequestModel::withTrashed()->whereKey($id)->value('request_number');
    }

    public function test_dry_run_reports_the_plan_and_writes_nothing(): void
    {
        $owner = $this->user();
        $ticket = $this->happenedAt($this->ticket('REQ-NCR-RCMB-2026-0001', $owner), '2026-01-05 09:00:00');

        $this->artisan('requests:renumber')->assertExitCode(0);

        $this->assertSame('REQ-NCR-RCMB-2026-0001', $this->number($ticket->id), 'Dry run must not touch the database.');
    }

    public function test_force_renumbers_legacy_rows_with_a_daily_sequence(): void
    {
        $owner = $this->user();
        $first = $this->ticket('REQ-NCR-RCMB-2026-0001', $owner);
        $second = $this->ticket('REQ-NCR-RCMB-2026-0002', $owner);
        $this->happenedAt($first, '2026-01-05 09:00:00');
        $this->happenedAt($second, '2026-01-05 10:00:00');

        $this->artisan('requests:renumber --force')->assertExitCode(0);

        $this->assertSame('ICT-NCR-RCMB-2026-01-05-0001', $this->number($first->id));
        $this->assertSame('ICT-NCR-RCMB-2026-01-05-0002', $this->number($second->id));
    }

    public function test_the_sequence_restarts_on_each_day(): void
    {
        $owner = $this->user();
        $dayOne = $this->happenedAt($this->ticket('REQ-NCR-RCMB-2026-0001', $owner), '2026-01-05 09:00:00');
        $dayTwo = $this->happenedAt($this->ticket('REQ-NCR-RCMB-2026-0002', $owner), '2026-01-06 09:00:00');

        $this->artisan('requests:renumber --force')->assertExitCode(0);

        $this->assertSame('ICT-NCR-RCMB-2026-01-05-0001', $this->number($dayOne->id));
        $this->assertSame('ICT-NCR-RCMB-2026-01-06-0001', $this->number($dayTwo->id));
    }

    public function test_ict_mirror_service_request_no_follows_the_new_number(): void
    {
        $owner = $this->user();
        $detail = $this->repairDetail('REQ-NCR-RCMB-2026-0001');
        $ticket = $this->happenedAt(
            $this->ticket('REQ-NCR-RCMB-2026-0001', $owner, ['detail_id' => $detail->id]),
            '2026-01-05 09:00:00'
        );

        $this->artisan('requests:renumber --force')->assertExitCode(0);

        $new = 'ICT-NCR-RCMB-2026-01-05-0001';
        $this->assertSame($new, $this->number($ticket->id));
        $this->assertSame($new, $detail->fresh()->service_request_no, 'The ICT form mirror must follow its ticket.');
    }

    public function test_pm_form_no_follows_but_a_foreign_service_request_no_is_left_alone(): void
    {
        $owner = $this->user(['office' => 'ADMINISTRATIVE DIVISION']);
        // form_no mirrors the ticket; service_request_no was filled in by hand
        // with a different reference — that is not ours to overwrite.
        $detail = $this->pmDetail([
            'form_no'            => 'PM-NCR-RCMB-2026-0007',
            'service_request_no' => 'REF-EXTERNAL-2026-0042',
        ]);
        $ticket = $this->happenedAt(
            $this->ticket('PM-NCR-RCMB-2026-0007', $owner, [
                'type'      => 'Preventive Maintenance',
                'detail_id' => $detail->id,
            ]),
            '2026-01-05 09:00:00'
        );

        $this->artisan('requests:renumber --force')->assertExitCode(0);

        $new = 'PM-NCR-RCMB-2026-01-05-0001';
        $this->assertSame($new, $this->number($ticket->id));

        $detail->refresh();
        $this->assertSame($new, $detail->form_no, 'The PM form mirror must follow its ticket.');
        $this->assertSame(
            'REF-EXTERNAL-2026-0042',
            $detail->service_request_no,
            'A foreign service_request_no must never be overwritten.'
        );
    }

    public function test_running_the_command_twice_is_idempotent(): void
    {
        $owner = $this->user();
        $first = $this->happenedAt($this->ticket('REQ-NCR-RCMB-2026-0001', $owner), '2026-01-05 09:00:00');
        $second = $this->happenedAt($this->ticket('REQ-NCR-RCMB-2026-0002', $owner), '2026-01-05 10:00:00');

        $this->artisan('requests:renumber --force')->assertExitCode(0);
        $afterFirst = [$first->id => $this->number($first->id), $second->id => $this->number($second->id)];

        // Second run: everything is already in format, so nothing moves.
        $this->artisan('requests:renumber --force')->assertExitCode(0);

        $this->assertSame($afterFirst[$first->id], $this->number($first->id));
        $this->assertSame($afterFirst[$second->id], $this->number($second->id));
    }

    public function test_a_number_held_by_an_out_of_scope_row_aborts_the_whole_write(): void
    {
        $owner = $this->user();

        // Row A (2026-01-05) wants ICT-NCR-RCMB-2026-01-05-0001 …
        $legacy = $this->happenedAt($this->ticket('REQ-NCR-RCMB-2026-0001', $owner), '2026-01-05 09:00:00');

        // … but row B (already in the new format, on another day) holds it.
        $holder = $this->happenedAt(
            $this->ticket('ICT-NCR-RCMB-2026-01-05-0001', $owner),
            '2026-01-06 09:00:00'
        );

        $this->artisan('requests:renumber --force')->assertExitCode(1);

        $this->assertSame('REQ-NCR-RCMB-2026-0001', $this->number($legacy->id), 'The colliding row must not be touched.');
        $this->assertSame(
            'ICT-NCR-RCMB-2026-01-05-0001',
            $this->number($holder->id),
            'Nobody may steal a number that is already taken.'
        );
    }

    public function test_region_filter_limits_the_scope(): void
    {
        $ncr = $this->user();
        $rii = $this->user(['region' => 'REGION II', 'branch' => 'Batuen']);

        $ncrTicket = $this->happenedAt($this->ticket('REQ-NCR-RCMB-2026-0001', $ncr), '2026-01-05 09:00:00');
        $riiTicket = $this->happenedAt($this->ticket('REQ-RII-BATU-2026-0001', $rii), '2026-01-05 09:00:00');

        $this->artisan('requests:renumber --force --region=RII')->assertExitCode(0);

        $this->assertSame('ICT-RII-BATU-2026-01-05-0001', $this->number($riiTicket->id));
        $this->assertSame(
            'REQ-NCR-RCMB-2026-0001',
            $this->number($ncrTicket->id),
            'A region filter must leave every other region alone.'
        );
    }

    public function test_soft_deleted_rows_are_renumbered_too(): void
    {
        $owner = $this->user();
        $ticket = $this->happenedAt($this->ticket('REQ-NCR-RCMB-2026-0001', $owner), '2026-01-05 09:00:00');
        $ticket->delete();

        $this->artisan('requests:renumber --force')->assertExitCode(0);

        $this->assertSame('ICT-NCR-RCMB-2026-01-05-0001', $this->number($ticket->id));
        $this->assertNotNull(
            RequestModel::withTrashed()->whereKey($ticket->id)->value('deleted_at'),
            'Renumbering must not resurrect a soft-deleted row.'
        );
    }

    public function test_skip_orphans_leaves_rows_without_a_detail_id(): void
    {
        $owner = $this->user();
        $orphan = $this->happenedAt($this->ticket('ZZTMP-CSM-CARD-001', $owner), '2026-01-05 09:00:00');
        $real = $this->happenedAt(
            $this->ticket('REQ-NCR-RCMB-2026-0002', $owner, ['detail_id' => $this->repairDetail()->id]),
            '2026-01-05 10:00:00'
        );

        $this->artisan('requests:renumber --force --skip-orphans')->assertExitCode(0);

        $this->assertSame('ZZTMP-CSM-CARD-001', $this->number($orphan->id), 'Placeholder rows keep their number.');
        $this->assertSame(
            'ICT-NCR-RCMB-2026-01-05-0001',
            $this->number($real->id),
            'A skipped row releases its slot, so the real ticket takes 0001 and the day has no gap.'
        );
    }

    public function test_without_the_flag_orphan_rows_are_renumbered_too(): void
    {
        $owner = $this->user();
        $orphan = $this->happenedAt($this->ticket('ZZTMP-CSM-CARD-001', $owner), '2026-01-05 09:00:00');
        $real = $this->happenedAt(
            $this->ticket('REQ-NCR-RCMB-2026-0002', $owner, ['detail_id' => $this->repairDetail()->id]),
            '2026-01-05 10:00:00'
        );

        $this->artisan('requests:renumber --force')->assertExitCode(0);

        $this->assertSame('ICT-NCR-RCMB-2026-01-05-0001', $this->number($orphan->id));
        $this->assertSame('ICT-NCR-RCMB-2026-01-05-0002', $this->number($real->id));
    }

    public function test_the_live_generator_continues_after_the_backfill(): void
    {
        $owner = $this->user();
        $ticket = $this->happenedAt(
            $this->ticket('REQ-2026-09-17-0001', $owner),
            now()->format('Y-m-d') . ' 09:00:00'
        );

        $this->artisan('requests:renumber --force')->assertExitCode(0);

        $this->assertSame(
            'ICT-NCR-RCMB-' . now()->format('Y-m-d') . '-0001',
            $this->number($ticket->id)
        );

        $next = \App\Support\RequestHelpers::generateRequestNumber('ICT', $owner);

        $this->assertSame(
            'ICT-NCR-RCMB-' . now()->format('Y-m-d') . '-0002',
            $next,
            'New tickets must continue the backfilled sequence, not restart it.'
        );
    }
}
