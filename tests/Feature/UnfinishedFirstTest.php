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
}
