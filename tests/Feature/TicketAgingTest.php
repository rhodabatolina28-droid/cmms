<?php

namespace Tests\Feature;

use App\Models\Request;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D2-a — Ticket Aging accessors (test-first):
 * - age_in_minutes:  (int) max(0, ...) — Carbon-3-proof clamp (F3)
 * - aging_bucket:    🟢 ≤24h · 🟡 ≤72h · 🟠 ≤7d · 🔴 >7d (exact boundaries)
 * - age_display:     "2h" / "1d 4h" / "10d 5h"
 * - is_aging_overdue: Scheduled + red only (F6 single source of truth)
 * - should_show_age: active statuses only — terminal = no chip (F5)
 */
class TicketAgingTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function ticket(array $attributes = []): Request
    {
        $this->counter++;

        $user = User::create([
            'full_name' => 'Aging User ' . $this->counter,
            'email' => 'aging-user-' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'can_supply' => false,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
        ]);

        return Request::create(array_merge([
            'user_id' => $user->id,
            'request_number' => 'REQ-NCR-RCMB-2026-' . str_pad((string) $this->counter, 4, '0', STR_PAD_LEFT),
            'type' => 'ICT',
            'requestor_name' => $user->full_name,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
            'status' => 'Pending',
            'is_deleted' => false,
            'description' => 'Aging test ticket',
        ], $attributes));
    }

    /** Backdate created_at so the age is deterministic at minute granularity. */
    private function age(Request $ticket, int $minutes): Request
    {
        // Freeze the clock: boundary tests need EXACT minute diffs — a live
        // clock adds elapsed seconds (14760 became 14761 → "10d 6h").
        Carbon::setTestNow(now());

        $ticket->created_at = Carbon::now()->subMinutes($minutes);
        $ticket->save();
        $ticket->refresh();

        return $ticket;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // unfreeze for the next test
        parent::tearDown();
    }

    public function test_bucket_boundaries_are_exact(): void
    {
        $cases = [
            [1, 'green', '1 minute old is green'],
            [1439, 'green', 'just under 24h is green'],
            [1440, 'green', 'exactly 24h closes the green bucket'],
            [1441, 'yellow', 'past 24h opens yellow'],
            [4320, 'yellow', 'exactly 72h closes the yellow bucket'],
            [4321, 'orange', 'past 72h opens orange'],
            [10080, 'orange', 'exactly 7d closes the orange bucket'],
            [10081, 'red', 'past 7d opens red'],
            [11520, 'red', '8 days is red'],
        ];

        foreach ($cases as [$minutes, $expected, $message]) {
            $ticket = $this->age($this->ticket(), $minutes);
            $this->assertSame($expected, $ticket->aging_bucket, " {$message} ({$minutes}min)");
        }
    }

    public function test_age_in_minutes_is_clamped_for_future_created_at(): void
    {
        $ticket = $this->ticket();
        $ticket->created_at = now()->addMinutes(30); // future-dated (test edge)
        $ticket->save();
        $ticket->refresh();

        // F3: max(0, ...) — never negative, never counted as "old".
        $this->assertSame(0, $ticket->age_in_minutes);
        $this->assertSame('green', $ticket->aging_bucket);
    }

    public function test_age_display_formats(): void
    {
        $this->assertSame('0h', $this->age($this->ticket(), 0)->age_display);
        $this->assertSame('45m', $this->age($this->ticket(), 45)->age_display);
        $this->assertSame('2h', $this->age($this->ticket(), 120)->age_display);
        $this->assertSame('2h 30m', $this->age($this->ticket(), 150)->age_display);
        $this->assertSame('1d 2h', $this->age($this->ticket(), 1560)->age_display);
        $this->assertSame('10d 5h', $this->age($this->ticket(), 14700)->age_display);
        $this->assertSame('10d', $this->age($this->ticket(), 14400)->age_display);
    }

    public function test_is_aging_overdue_is_scheduled_plus_red_only(): void
    {
        // F6: single source of truth replacing the duplicate 7-day rules.
        $scheduled8d = $this->age($this->ticket(['status' => 'Scheduled']), 11520);
        $this->assertTrue($scheduled8d->is_aging_overdue);

        $ongoing8d = $this->age($this->ticket(['status' => 'Ongoing']), 11520);
        $this->assertFalse($ongoing8d->is_aging_overdue, 'Ongoing tickets age but are never "overdue"');

        $scheduled1d = $this->age($this->ticket(['status' => 'Scheduled']), 1440);
        $this->assertFalse($scheduled1d->is_aging_overdue, 'Young Scheduled ticket is not overdue');
    }

    public function test_should_show_age_for_active_statuses_only(): void
    {
        // F5: terminal statuses must NOT show an age chip — finished tickets
        // are not alarms.
        $active = ['Pending', 'Ongoing', 'Scheduled', 'Awaiting Parts', 'Awaiting Signature', 'Referred - External'];
        foreach ($active as $status) {
            $this->assertTrue($this->ticket(['status' => $status])->should_show_age, "{$status} shows age");
        }

        $terminal = ['Completed', 'Cancelled', 'Rejected'];
        foreach ($terminal as $status) {
            $this->assertFalse($this->ticket(['status' => $status])->should_show_age, "{$status} hides age");
        }
    }
}
