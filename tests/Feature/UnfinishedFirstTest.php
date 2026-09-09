<?php

namespace Tests\Feature;

use App\Actions\SuperAdmin\GetRequestsDataAction;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * D2/D4 follow-up — UNFINISHED-FIRST ordering (locked rule):
 * Completed/Cancelled/Rejected tickets SINK to the bottom of every list and
 * dashboard "Recent" widget; Pending/Ongoing/waiting tickets FLOAT to the top
 * so unfinished work is never buried. High officials keep their queue priority
 * WITHIN the unfinished group.
 */
class UnfinishedFirstTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(string $role = 'user', ?string $position = null): User
    {
        $this->counter++;

        return User::create([
            'full_name' => 'UF User ' . $this->counter,
            'email' => 'uf-user-' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => $role,
            'is_active' => true,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'RESEARCH AND INFORMATION DIVISION',
            'position' => $position,
        ]);
    }

    private function ticket(User $requestor, array $attrs = []): RequestModel
    {
        $this->counter++;

        return RequestModel::create(array_merge([
            'user_id' => $requestor->id,
            'request_number' => 'REQ-NCR-RCMB-2026-' . str_pad((string) $this->counter, 4, '0', STR_PAD_LEFT),
            'type' => 'ICT',
            'requestor_name' => $requestor->full_name,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'RESEARCH AND INFORMATION DIVISION',
            'status' => 'Pending',
            'is_deleted' => false,
            'division_admin_review_status' => 'Approved',
            'description' => 'Unfinished-first test ticket',
        ], $attrs));
    }

    private function backdate(RequestModel $ticket, string $age): void
    {
        $ticket->created_at = now()->sub($age);
        $ticket->save();
    }

    public function test_unfinished_first_scope_orders_active_above_terminal(): void
    {
        $u = $this->user();

        // NEWEST ticket is Completed — created_at-desc alone would bury the
        // older Ongoing one. The scope must flip that.
        $finished = $this->ticket($u, ['status' => 'Completed']);
        $this->backdate($finished, '5 minutes');

        $active = $this->ticket($u, ['status' => 'Ongoing']);
        $this->backdate($active, '3 days');

        $order = RequestModel::query()
            ->unfinishedFirst()
            ->orderBy('created_at', 'desc')
            ->pluck('id')
            ->all();

        $this->assertSame([$active->id, $finished->id], $order);
    }

    public function test_ict_list_sinks_completed_tickets(): void
    {
        $sa = $this->user('super_admin');
        $requestor = $this->user();

        $finished = $this->ticket($requestor, ['status' => 'Completed']);
        $this->backdate($finished, '1 hour');

        $active = $this->ticket($requestor, ['status' => 'Ongoing']);
        $this->backdate($active, '4 days');

        $statuses = collect($this->actingAs($sa)
            ->getJson(route('ict.index'))
            ->assertOk()
            ->json('requests'))
            ->pluck('status')
            ->all();

        $this->assertSame('Ongoing', $statuses[0], 'Unfinished ticket must float above Completed');
        $this->assertContains('Completed', $statuses, 'Completed ticket must still be listed (below)');
    }

    public function test_master_list_sinks_completed_tickets(): void
    {
        $sa = $this->user('super_admin');
        $requestor = $this->user();

        $finished = $this->ticket($requestor, ['status' => 'Completed']);
        $this->backdate($finished, '1 hour');

        $active = $this->ticket($requestor, ['status' => 'Ongoing']);
        $this->backdate($active, '2 days');

        $this->actingAs($sa);
        $json = app(GetRequestsDataAction::class)
            ->execute(Request::create('/requests/data', 'GET'))
            ->getData(true);

        $statuses = collect($json['requests'])->pluck('status')->all();
        $this->assertSame('Ongoing', $statuses[0], 'Master List must show unfinished first');
        $this->assertSame('Completed', $statuses[1]);
    }

    public function test_sa_dashboard_recent_office_requests_floats_unfinished(): void
    {
        $sa = $this->user('super_admin');
        $requestor = $this->user();

        // Newest = Completed; older = Ongoing. The widget must NOT bury the
        // unfinished ticket under the fresh Completed one.
        $finished = $this->ticket($requestor, ['status' => 'Completed']);
        $this->backdate($finished, '10 minutes');

        $active = $this->ticket($requestor, ['status' => 'Ongoing']);
        $this->backdate($active, '5 days');

        $response = $this->actingAs($sa)->get(route('dashboard.super-admin'));
        $response->assertOk();

        $recent = $response->viewData('recentRequests');
        $this->assertSame($active->id, $recent->first()->id, 'Recent Office Requests must lead with unfinished tickets');
        $this->assertSame('Completed', $recent->last()->status, 'Completed must sink to the bottom');
    }

    public function test_admin_dashboard_recent_floats_unfinished(): void
    {
        $admin = $this->user('admin');
        $requestor = $this->user(); // same default branch + office as the admin

        $finished = $this->ticket($requestor, ['status' => 'Completed']);
        $this->backdate($finished, '10 minutes');

        $active = $this->ticket($requestor, ['status' => 'Pending']);
        $this->backdate($active, '2 days');

        $response = $this->actingAs($admin)->get(route('dashboard.admin'));
        $response->assertOk();

        $recent = $response->viewData('requests');
        $this->assertSame($active->id, $recent->first()->id, 'Division Admin Recent must lead with unfinished tickets');
    }

    public function test_overdue_stat_card_counts_both_pm_and_ict(): void
    {
        $sa = $this->user('super_admin');
        $requestor = $this->user();

        // Overdue PM: auto-generated Scheduled sitting 8 days.
        $pm = $this->ticket($requestor, [
            'type' => 'Preventive Maintenance',
            'status' => 'Scheduled',
            'is_auto_generated' => true,
        ]);
        $this->backdate($pm, '8 days');

        // Overdue ICT: Ongoing for 9 days — previously NEVER counted.
        $ict = $this->ticket($requestor, ['status' => 'Ongoing']);
        $this->backdate($ict, '9 days');

        // Fresh ICT — must NOT inflate the overdue count.
        $this->ticket($requestor, ['status' => 'Ongoing']);

        $response = $this->actingAs($sa)->get(route('dashboard.super-admin'));
        $response->assertOk();

        $stats = $response->viewData('stats');
        $this->assertSame(
            2,
            $stats['overdue_tickets'],
            'Overdue card must count BOTH the aging PM and the aging ICT (fresh excluded)'
        );
    }

    public function test_master_list_puts_urgent_on_top(): void
    {
        $sa = $this->user('super_admin');
        $official = $this->user('user', 'Chief, RID');
        $regular = $this->user();

        // The regular ticket is NEWEST — created_at-desc alone would bury the
        // official's. Urgent (high official) must lead the unfinished group.
        $regularActive = $this->ticket($regular, ['status' => 'Ongoing']);
        $this->backdate($regularActive, '1 hour');

        $urgent = $this->ticket($official, ['status' => 'Ongoing']);
        $this->backdate($urgent, '3 days');

        $this->actingAs($sa);
        $json = app(GetRequestsDataAction::class)
            ->execute(Request::create('/requests/data', 'GET'))
            ->getData(true);

        $this->assertSame(
            $urgent->id,
            $json['requests'][0]['id'],
            'URGENT (high official) ticket must float above regular unfinished in Master List'
        );
    }

    public function test_sa_dashboard_recent_puts_urgent_on_top(): void
    {
        $sa = $this->user('super_admin');
        $official = $this->user('user', 'OIC-Executive Director IV');
        $regular = $this->user();

        $regularActive = $this->ticket($regular, ['status' => 'Ongoing']);
        $this->backdate($regularActive, '1 hour');

        $urgent = $this->ticket($official, ['status' => 'Pending']);
        $this->backdate($urgent, '2 days');

        $response = $this->actingAs($sa)->get(route('dashboard.super-admin'));
        $response->assertOk();

        $recent = $response->viewData('recentRequests');
        $this->assertSame(
            $urgent->id,
            $recent->first()->id,
            'URGENT ticket must lead the Recent Office Requests widget'
        );
    }

    public function test_admin_dashboard_recent_puts_urgent_on_top(): void
    {
        $admin = $this->user('admin');
        $official = $this->user('user', 'Director II, Technical Services');
        $regular = $this->user();

        $regularActive = $this->ticket($regular, ['status' => 'Ongoing']);
        $this->backdate($regularActive, '1 hour');

        $urgent = $this->ticket($official, ['status' => 'Ongoing']);
        $this->backdate($urgent, '2 days');

        $response = $this->actingAs($admin)->get(route('dashboard.admin'));
        $response->assertOk();

        $recent = $response->viewData('requests');
        $this->assertSame(
            $urgent->id,
            $recent->first()->id,
            'URGENT ticket must lead the Division Admin Recent widget'
        );
    }

    public function test_pm_work_orders_data_carries_age_fields(): void
    {
        $sa = $this->user('super_admin');
        $requestor = $this->user();

        // Aging Scheduled order → red bucket.
        $old = $this->ticket($requestor, [
            'type' => 'Preventive Maintenance',
            'status' => 'Scheduled',
            'is_auto_generated' => true,
        ]);
        $this->backdate($old, '8 days');

        // Completed order → age must be null (history, not alarm).
        $done = $this->ticket($requestor, [
            'type' => 'Preventive Maintenance',
            'status' => 'Completed',
            'is_auto_generated' => true,
        ]);
        $this->backdate($done, '12 days');

        $this->actingAs($sa);
        $json = app(\App\Actions\PMSchedule\GetOrdersDataAction::class)
            ->execute(Request::create('/pm-schedules/orders/data', 'GET'))
            ->getData(true);

        $orders = collect($json['orders']);
        $oldOrder = $orders->firstWhere('id', $old->id);
        $doneOrder = $orders->firstWhere('id', $done->id);

        $this->assertNotNull($oldOrder, 'Aging order must be in the payload');
        $this->assertSame('red', $oldOrder['age_bucket']);
        $this->assertNotNull($oldOrder['age_display']);

        $this->assertNotNull($doneOrder, 'Completed order must be in the payload');
        $this->assertNull($doneOrder['age_bucket'], 'Completed order must NOT carry age_bucket');
        $this->assertNull($doneOrder['age_display'], 'Completed order must NOT carry age_display');
    }
}
