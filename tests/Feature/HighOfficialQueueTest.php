<?php

namespace Tests\Feature;

use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D4b — High-Official queue-jump (⚡).
 * Locked rule: officials muna (newest first), tapos ang regular tickets sa
 * kanilang status flow. Sa IT dashboard widget + ICT list views.
 */
class HighOfficialQueueTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(?string $position, string $role = 'user'): User
    {
        $this->counter++;

        return User::create([
            'full_name' => 'Queue User ' . $this->counter,
            'email' => 'queue-user-' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => $role,
            'is_active' => true,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'RESEARCH AND INFORMATION DIVISION',
            'position' => $position,
        ]);
    }

    private function ictTicket(User $requestor, string $number, string $createdAt, ?User $assignee = null): RequestModel
    {
        $ticket = RequestModel::create([
            'user_id' => $requestor->id,
            'request_number' => $number,
            'type' => 'ICT',
            'requestor_name' => $requestor->full_name,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'RESEARCH AND INFORMATION DIVISION',
            'status' => 'Pending',
            'is_deleted' => false,
            'description' => 'Queue-jump test ticket ' . $number,
            'assigned_to' => $assignee?->id,
        ]);
        $ticket->created_at = $createdAt;
        $ticket->save();

        return $ticket;
    }

    public function test_officials_first_scope_orders_official_tickets_above_regular(): void
    {
        $official = $this->user('Chief, RID');
        $regular = $this->user(null);

        // Official filed EARLIER, regular filed LATER — created_at-desc alone
        // would put the regular ticket on top. The lead CASE must flip that.
        $olderOfficial = $this->ictTicket($official, 'REQ-NCR-RCMB-2026-0001', now()->subDays(2));
        $newerRegular = $this->ictTicket($regular, 'REQ-NCR-RCMB-2026-0002', now()->subHour());

        $order = RequestModel::query()
            ->officialsFirst()
            ->orderBy('created_at', 'desc')
            ->pluck('id')
            ->all();

        $this->assertSame([$olderOfficial->id, $newerRegular->id], $order);
    }

    public function test_it_ict_list_puts_official_ticket_first(): void
    {
        $it = $this->user(null, 'it');
        $official = $this->user('OIC-Executive Director IV');
        $regular = $this->user(null);

        // Tickets must be ASSIGNED to the IT user — the IT branch lists only
        // tickets assigned to them.
        $olderOfficial = $this->ictTicket($official, 'REQ-NCR-RCMB-2026-0011', now()->subDays(2), $it);
        $newerRegular = $this->ictTicket($regular, 'REQ-NCR-RCMB-2026-0012', now()->subHour(), $it);

        $response = $this->actingAs($it)
            ->getJson(route('ict.index'))
            ->assertOk();

        $ids = collect($response->json('requests'))->pluck('id')->all();
        $this->assertSame($olderOfficial->id, $ids[0], 'Official ticket must jump the queue despite being older');
        $this->assertContains($newerRegular->id, $ids, 'Regular ticket must still be visible (not dropped)');

        // Badge payload: serialized user must expose the flag for the JS views.
        $first = collect($response->json('requests'))->firstWhere('id', $olderOfficial->id);
        $this->assertTrue($first['user']['is_high_official']);
    }

    public function test_it_dashboard_shows_official_badge_on_widget(): void
    {
        $it = $this->user(null, 'it');
        $official = $this->user('Chief, FMD');

        $this->ictTicket($official, 'REQ-NCR-RCMB-2026-0021', now()->subDay(), $it);

        $this->actingAs($it)
            ->get(route('dashboard.it'))
            ->assertOk()
            ->assertSee('ICT-2026-0021')
            ->assertSee('Urgent');
    }

    public function test_urgent_badge_hides_once_ticket_reaches_terminal_status(): void
    {
        // D4b + F5: the URGENT badge is an alarm — a Completed/Cancelled/Rejected
        // ticket is history and must not keep flashing red on the lists.
        $official = $this->user('Director II, Technical Services');
        $ticket = $this->ictTicket($official, 'REQ-NCR-RCMB-2026-0031', now()->subDays(8));

        $this->assertTrue($ticket->is_urgent_visible, 'Active ticket must show the badge');
        $this->assertTrue($ticket->should_show_age);

        foreach (['Completed', 'Cancelled', 'Rejected'] as $terminal) {
            $ticket->update(['status' => $terminal]);
            $ticket->refresh();
            $this->assertFalse($ticket->is_urgent_visible, "URGENT badge must hide on {$terminal}");
            $this->assertFalse($ticket->should_show_age, "Age chip must hide on {$terminal}");
        }

        // Back to an active status (e.g. reopened flow) — the badge returns.
        $ticket->update(['status' => 'Awaiting Parts']);
        $ticket->refresh();
        $this->assertTrue($ticket->is_urgent_visible);
    }
}
