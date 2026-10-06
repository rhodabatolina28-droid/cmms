<?php

namespace Tests\Feature;

use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PM Tasks page (IT side) — Oct 2026 pivot fixes:
 *
 *  (A) an IT user must see ONLY the work orders assigned to them. The old
 *      `orWhereNull('assigned_to') AND branch = my branch` clause leaked
 *      UNASSIGNED branch tickets (and the queue relied on luck) into the
 *      IT's personal PM Tasks list — user rule: "dapat ang makikita ay yung
 *      na-assign lang sa kanya".
 *
 *  (B) the table must order like the System Admin's PM Work Orders —
 *      Scheduled → Ongoing → Awaiting Signature → Completed (created_at
 *      desc tiebreak), so a task completed TODAY sinks below the still-
 *      active ones instead of staying on top (the old plain
 *      `created_at desc` kept it there because it was just created).
 */
class PmTasksItScopeTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'PmTasks User ' . $this->counter,
            'name' => 'PmTasks User ' . $this->counter,
            'email' => 'pmtasks-' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'region' => 'NCR',
            'branch' => 'RCMB',
            'office' => 'RESEARCH AND INFORMATION DIVISION',
        ], $attributes));
    }

    private function pmWorkOrder(User $requestor, ?User $assignee, string $status): RequestModel
    {
        $this->counter++;

        return RequestModel::create([
            'user_id' => $requestor->id,
            'request_number' => 'PM-NCR-RCMB-2026-99' . str_pad((string) $this->counter, 2, '0', STR_PAD_LEFT),
            'type' => 'Preventive Maintenance',
            'requestor_name' => $requestor->full_name,
            'region' => 'NCR',
            'branch' => 'RCMB',
            'office' => 'RESEARCH AND INFORMATION DIVISION',
            'status' => $status,
            'is_deleted' => false,
            'assigned_to' => $assignee?->id,
            'is_auto_generated' => true,
            'division_admin_review_status' => 'Approved',
            'asset_id' => 0,
        ]);
    }

    /** Pull the view data (stats + paginator) straight off the response. */
    private function viewData($response): array
    {
        $response->assertOk();

        return $response->original->getData();
    }

    /**
     * The table prints display_number (D9.42 short form) OR request_number —
     * the unique 4-digit tail ('99xx') is present either way, so assertions
     * stay independent of which one the view chose.
     */
    private function tail(RequestModel $r): string
    {
        return substr($r->request_number, -4);
    }

    public function test_it_sees_only_work_orders_assigned_to_them(): void
    {
        $it = $this->user(['role' => 'it']);
        $otherIt = $this->user(['role' => 'it']);
        $requestor = $this->user();

        $mine = $this->pmWorkOrder($requestor, $it, 'Scheduled');
        $unassignedSameBranch = $this->pmWorkOrder($requestor, null, 'Scheduled');
        $otherIts = $this->pmWorkOrder($requestor, $otherIt, 'Scheduled');

        $res = $this->actingAs($it)->get(route('pm.tasks'));

        $res->assertOk();
        $res->assertSee($this->tail($mine));
        $res->assertDontSee(
            $this->tail($unassignedSameBranch),
            false,
            'IT must NOT see unassigned branch work orders — assigned-to-me only (fix A)'
        );
        $res->assertDontSee(
            $this->tail($otherIts),
            false,
            "IT must NOT see another IT's work orders"
        );
    }

    public function test_completed_tasks_sink_below_active_tasks(): void
    {
        $it = $this->user(['role' => 'it']);
        $requestor = $this->user();

        // Active task created 2 days ago; the COMPLETED one created just now
        // (the "I just finished it" scenario — it must NOT stay on top).
        $active = $this->pmWorkOrder($requestor, $it, 'Ongoing');
        RequestModel::whereKey($active->id)->update(['created_at' => now()->subDays(2)]);
        $done = $this->pmWorkOrder($requestor, $it, 'Completed');

        $html = (string) $this->actingAs($it)->get(route('pm.tasks'))->getContent();

        $posActive = strpos($html, $this->tail($active));
        $posDone = strpos($html, $this->tail($done));
        $this->assertNotFalse($posActive, 'Active row missing from page');
        $this->assertNotFalse($posDone, 'Completed row missing from page');
        $this->assertLessThan(
            $posDone,
            $posActive,
            'Active task must render ABOVE the newer completed task (completed sinks — fix B)'
        );
    }

    public function test_stats_count_only_assigned_tasks(): void
    {
        $it = $this->user(['role' => 'it']);
        $otherIt = $this->user(['role' => 'it']);
        $requestor = $this->user();

        $this->pmWorkOrder($requestor, $it, 'Scheduled');
        $this->pmWorkOrder($requestor, $it, 'Ongoing');
        $this->pmWorkOrder($requestor, $it, 'Completed');
        $this->pmWorkOrder($requestor, null, 'Scheduled');      // unassigned leak (fix A)
        $this->pmWorkOrder($requestor, $otherIt, 'Scheduled');  // other IT — never counted

        $data = $this->viewData($this->actingAs($it)->get(route('pm.tasks')));

        $this->assertSame(3, $data['stats']['total'], 'Stats must cover assigned-to-me tasks only');
        $this->assertSame(1, $data['stats']['scheduled']);
        $this->assertSame(1, $data['stats']['ongoing']);
        $this->assertSame(1, $data['stats']['completed']);
        $this->assertSame(0, $data['stats']['overdue']);
        $this->assertSame(3, $data['pmTasks']->count(), 'List and stats must agree');
    }
}