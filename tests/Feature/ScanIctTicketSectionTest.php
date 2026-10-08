<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scan hub (/r/{id}) — "ICT Repair Ticket" section lifecycle (2026-10-08 decision).
 *
 *  - Only an ACTIVE (unfinished) ticket renders the "ICT Repair Ticket" panel —
 *    mirrors Request::isActiveTicketAttribute: terminal statuses (Completed /
 *    Cancelled / Rejected) are history, not live alarms;
 *  - terminal tickets stay visible in "Recent Service History" (that history
 *    query has no status filter);
 *  - a NEWER terminal ticket must not bury an older still-open one (the panel
 *    keeps showing the latest ACTIVE ticket);
 *  - supply/admin never see the panel (ScanController passes ictTicket=null).
 */
class ScanIctTicketSectionTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    public function test_open_ict_ticket_shows_section_and_wins_over_newer_terminal_one(): void
    {
        $it = $this->user(['role' => 'it']);
        $owner = $this->user(['full_name' => 'Juan Dela Cruz']);
        $asset = $this->asset(['assigned_to_user' => $owner->id]);
        $open = $this->ticket($owner, $asset, 'Ongoing');

        $html = $this->actingAs($it)
            ->get(route('qr.redirect', $asset->asset_id))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('ICT Repair Ticket', $html);
        $this->assertStringContainsString('#' . $open->request_number, $html);

        // A NEWER completed ticket must not bury the still-open one.
        $this->ticket($owner, $asset, 'Completed');
        $html = $this->actingAs($it)
            ->get(route('qr.redirect', $asset->asset_id))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('ICT Repair Ticket', $html);
        $this->assertStringContainsString('#' . $open->request_number, $html);
    }

    public function test_completed_ict_ticket_is_hidden_and_lives_in_recent_history(): void
    {
        $it = $this->user(['role' => 'it']);
        $owner = $this->user(['full_name' => 'Juan Dela Cruz']);
        $asset = $this->asset(['assigned_to_user' => $owner->id]);
        $done = $this->ticket($owner, $asset, 'Completed');

        $html = $this->actingAs($it)
            ->get(route('qr.redirect', $asset->asset_id))
            ->assertOk()
            ->getContent();

        // Section gone (and its #number no longer rendered anywhere)...
        $this->assertStringNotContainsString('ICT Repair Ticket', $html);
        $this->assertStringNotContainsString('#' . $done->request_number, $html);

        // ...but the ticket lives in Recent Service History (label + status badge).
        $this->assertStringContainsString('Recent Service History', $html);
        $this->assertStringContainsString('ICT Repair', $html);
        $this->assertStringContainsString('Completed', $html);

        // Cancelled is terminal too — same rule.
        $done->update(['status' => 'Cancelled']);
        $html = $this->actingAs($it)
            ->get(route('qr.redirect', $asset->asset_id))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('ICT Repair Ticket', $html);
    }

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'ICT Scan User ' . $this->counter,
            'email' => 'ict-scan-user-' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'can_supply' => false,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
        ], $attributes));
    }

    private function asset(array $attributes = []): InventoryAsset
    {
        $this->counter++;

        return InventoryAsset::create(array_merge([
            'category' => 'Desktop',
            'item_name' => 'ICT Scan Asset',
            'serial_number' => 'ICTSCAN-SN-' . $this->counter,
            'property_number' => 'PROP-ICTSCAN-' . $this->counter,
            'par_number' => 'PAR-ICTSCAN-' . $this->counter,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
            'status' => 'Active',
        ], $attributes));
    }

    private function ticket(User $owner, InventoryAsset $asset, string $status): RequestModel
    {
        $this->counter++;

        return RequestModel::create([
            'user_id' => $owner->id,
            'request_number' => 'ICT-SCAN-' . str_pad((string) $this->counter, 4, '0', STR_PAD_LEFT),
            'type' => 'ICT',
            'status' => $status,
            'requestor_name' => $owner->full_name,
            'region' => 'NCR',
            'branch' => 'RCMB',
            'office' => 'Administrative Division',
            'is_deleted' => false,
            'description' => 'Scan ICT ticket lifecycle test #' . $this->counter,
            'linked_asset_id' => $asset->asset_id,
        ]);
    }
}