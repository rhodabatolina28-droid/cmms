<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\Request;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * X1 — Downtime overhaul (Gov-Option-B):
 * - B1: durations must be POSITIVE (Carbon 3 signed diffInMinutes bug).
 * - B2: bundled (auto-generated) PM must credit EVERY asset of the user.
 * - Split: ICT/repair → total_downtime · PM → total_pm_downtime (never mixed).
 */
class DowntimeTrackingTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'Downtime User ' . $this->counter,
            'email' => 'downtime-user-' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'can_supply' => false,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
        ], $attributes));
    }

    private function asset(User $custodian, array $attributes = []): InventoryAsset
    {
        $this->counter++;

        return InventoryAsset::create(array_merge([
            'category' => 'Desktop',
            'item_name' => 'Downtime Asset ' . $this->counter,
            'serial_number' => 'DT-' . $this->counter,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
            'status' => 'Active',
            'assigned_to_user' => $custodian->id,
        ], $attributes));
    }

    private function ticket(User $requestor, array $attributes = []): Request
    {
        $this->counter++;

        return Request::create(array_merge([
            'user_id' => $requestor->id,
            'request_number' => 'REQ-NCR-RCMB-2026-' . str_pad((string) $this->counter, 4, '0', STR_PAD_LEFT),
            'type' => 'ICT',
            'requestor_name' => $requestor->full_name,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
            'status' => 'Pending',
            'is_deleted' => false,
            'description' => 'Downtime tracking test ticket',
        ], $attributes));
    }

    /** Move the open window into the past so minute-granular durations are deterministic. */
    private function backdateWindow(Request $ticket, int $minutes): void
    {
        $ticket->update(['downtime_start' => now()->subMinutes($minutes)]);
        $ticket->refresh();
    }

    public function test_ict_ticket_completion_records_positive_downtime_in_repair_bucket_only(): void
    {
        $requestor = $this->user();
        $asset = $this->asset($requestor);
        $ticket = $this->ticket($requestor, ['linked_asset_id' => $asset->asset_id]);

        $ticket->update(['status' => 'Ongoing']);
        $this->assertNotNull($ticket->fresh()->downtime_start, 'Ongoing must open the downtime window');
        $this->backdateWindow($ticket, 90);

        $ticket->update(['status' => 'Completed']);
        $ticket->refresh();
        $asset->refresh();

        // B1: duration must be positive and ~90 minutes (never negative, never 0).
        $this->assertNotNull($ticket->downtime_end);
        $this->assertGreaterThanOrEqual(90, $ticket->downtime_duration);
        $this->assertLessThanOrEqual(91, $ticket->downtime_duration);

        // Gov-Option-B split: ICT breakdown → total_downtime ONLY.
        $this->assertGreaterThanOrEqual(90, $asset->total_downtime);
        $this->assertSame(0, (int) $asset->total_pm_downtime);
    }

    public function test_pm_ticket_completion_credits_pm_bucket_not_repair_bucket(): void
    {
        $requestor = $this->user();
        $asset = $this->asset($requestor);
        $ticket = $this->ticket($requestor, [
            'linked_asset_id' => $asset->asset_id,
            'type' => 'Preventive Maintenance',
        ]);

        $ticket->update(['status' => 'Ongoing']);
        $this->backdateWindow($ticket, 30);

        $ticket->update(['status' => 'Completed']);
        $asset->refresh();

        // PM servicing → total_pm_downtime ONLY; total_downtime (failure) untouched.
        $this->assertGreaterThanOrEqual(30, (int) $asset->total_pm_downtime);
        $this->assertSame(0, (int) $asset->total_downtime);
        $this->assertGreaterThanOrEqual(30, $ticket->fresh()->downtime_duration);
    }

    public function test_bundled_auto_pm_credits_every_asset_of_the_user(): void
    {
        // B2: a bundled PM previously credited only the FIRST asset because the
        // window-close sat inside the asset loop (downtime_end written on iter 1).
        $requestor = $this->user();
        $assetA = $this->asset($requestor);
        $assetB = $this->asset($requestor);
        $assetC = $this->asset($requestor);

        $ticket = $this->ticket($requestor, [
            'type' => 'Preventive Maintenance',
            'is_auto_generated' => true,
            'linked_asset_id' => null,
        ]);

        $ticket->update(['status' => 'Ongoing']);
        $this->backdateWindow($ticket, 45);

        $ticket->update(['status' => 'Completed']);

        foreach ([$assetA, $assetB, $assetC] as $index => $asset) {
            $asset->refresh();
            $this->assertGreaterThanOrEqual(
                45,
                (int) $asset->total_pm_downtime,
                'Bundled PM must credit asset #' . ($index + 1) . " ({$asset->serial_number})"
            );
            $this->assertSame(0, (int) $asset->total_downtime);
        }
    }

    public function test_pm_ticket_with_linked_asset_still_credits_linked_asset_once(): void
    {
        // Linked + bundled branch combined: the linked asset must be credited
        // exactly once (no double-count via unique('asset_id')).
        $requestor = $this->user();
        $asset = $this->asset($requestor);
        $ticket = $this->ticket($requestor, [
            'type' => 'Preventive Maintenance',
            'is_auto_generated' => true,
            'linked_asset_id' => $asset->asset_id,
        ]);

        $ticket->update(['status' => 'Ongoing']);
        $this->backdateWindow($ticket, 20);
        $ticket->update(['status' => 'Completed']);

        $asset->refresh();
        $this->assertGreaterThanOrEqual(20, (int) $asset->total_pm_downtime);
        $this->assertLessThanOrEqual(21, (int) $asset->total_pm_downtime, 'Linked asset must be credited exactly once');
    }

    public function test_ticket_that_never_went_ongoing_leaves_downtime_untouched(): void
    {
        $requestor = $this->user();
        $asset = $this->asset($requestor);
        $ticket = $this->ticket($requestor, ['linked_asset_id' => $asset->asset_id]);

        $ticket->update(['status' => 'Completed']);

        $ticket->refresh();
        $asset->refresh();
        $this->assertNull($ticket->downtime_start);
        $this->assertNull($ticket->downtime_end);
        $this->assertNull($ticket->downtime_duration);
        $this->assertSame(0, (int) $asset->total_downtime);
        $this->assertSame(0, (int) $asset->total_pm_downtime);
    }

    public function test_cancelled_ongoing_ict_ticket_closes_window_and_credits_asset(): void
    {
        // X3 (G1): previously only Completed closed the window — a Cancelled
        // Ongoing ticket left an open window (145h "open" on live data).
        $requestor = $this->user();
        $asset = $this->asset($requestor);
        $ticket = $this->ticket($requestor, ['linked_asset_id' => $asset->asset_id]);

        $ticket->update(['status' => 'Ongoing']);
        $this->backdateWindow($ticket, 60);
        $ticket->update(['status' => 'Cancelled']);

        $ticket->refresh();
        $asset->refresh();
        $this->assertNotNull($ticket->downtime_end, 'Cancelled must close the downtime window');
        $this->assertGreaterThanOrEqual(60, $ticket->downtime_duration);
        $this->assertGreaterThanOrEqual(60, (int) $asset->total_downtime);
        $this->assertSame(0, (int) $asset->total_pm_downtime);
        $this->assertFalse($ticket->is_downtime, 'Closed window must not report is_downtime');
    }

    public function test_rejected_and_referred_external_tickets_close_the_window(): void
    {
        $requestor = $this->user();

        $assetRejected = $this->asset($requestor);
        $rejected = $this->ticket($requestor, ['linked_asset_id' => $assetRejected->asset_id]);
        $rejected->update(['status' => 'Ongoing']);
        $this->backdateWindow($rejected, 15);
        $rejected->update(['status' => 'Rejected']);
        $rejected->refresh();
        $assetRejected->refresh();
        $this->assertNotNull($rejected->downtime_end);
        $this->assertGreaterThanOrEqual(15, (int) $assetRejected->total_downtime);

        $assetReferred = $this->asset($requestor);
        $referred = $this->ticket($requestor, ['linked_asset_id' => $assetReferred->asset_id]);
        $referred->update(['status' => 'Ongoing']);
        $this->backdateWindow($referred, 25);
        $referred->update(['status' => 'Referred - External']);
        $referred->refresh();
        $assetReferred->refresh();
        $this->assertNotNull($referred->downtime_end);
        $this->assertGreaterThanOrEqual(25, (int) $assetReferred->total_downtime);
    }

    public function test_cancelled_pm_ticket_credits_pm_bucket(): void
    {
        // Credit follows the ticket TYPE, not the terminal status.
        $requestor = $this->user();
        $asset = $this->asset($requestor);
        $ticket = $this->ticket($requestor, [
            'linked_asset_id' => $asset->asset_id,
            'type' => 'Preventive Maintenance',
        ]);

        $ticket->update(['status' => 'Ongoing']);
        $this->backdateWindow($ticket, 40);
        $ticket->update(['status' => 'Cancelled']);

        $asset->refresh();
        $this->assertGreaterThanOrEqual(40, (int) $asset->total_pm_downtime);
        $this->assertSame(0, (int) $asset->total_downtime);
    }

    public function test_awaiting_parts_keeps_window_open_and_reports_is_downtime(): void
    {
        // X3 (G3): the asset is still down while awaiting parts — is_downtime
        // must not depend on status === 'Ongoing'.
        $requestor = $this->user();
        $asset = $this->asset($requestor);
        $ticket = $this->ticket($requestor, ['linked_asset_id' => $asset->asset_id]);

        $ticket->update(['status' => 'Ongoing']);
        $ticket->update(['status' => 'Awaiting Parts']);

        $ticket->refresh();
        $this->assertNull($ticket->downtime_end, 'Awaiting Parts must keep the window open');
        $this->assertTrue($ticket->is_downtime, 'Window open = asset is down, even while Awaiting Parts');
    }
}