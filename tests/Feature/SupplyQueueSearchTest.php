<?php

namespace Tests\Feature;

use App\Models\Requisition;
use App\Models\Request as Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplyQueueSearchTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function makeUser(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'Queue Search User ' . $this->counter,
            'email' => 'queue-search-' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'can_supply' => false,
            'region' => 'NCR',
            'branch' => 'RCMB',
        ], $attributes));
    }

    private function makeSupplyAdmin(): User
    {
        return $this->makeUser([
            'role' => 'admin',
            'can_supply' => true,
            'full_name' => 'Supply Officer',
        ]);
    }

    private function makeRequisition(User $requester, array $attributes = []): Requisition
    {
        $ticket = Ticket::create([
            'user_id' => $this->makeUser()->id,
            'assigned_to' => $requester->id,
            'request_number' => 'JO-NCR-2026-' . str_pad((string) (++$this->counter), 4, '0', STR_PAD_LEFT),
            'type' => 'ICT',
            'requestor_name' => 'Queue Requestor',
            'description' => 'Test repair',
            'status' => Ticket::STATUS_ONGOING,
            'region' => 'NCR',
        ]);

        return Requisition::create(array_merge([
            'request_id' => $ticket->id,
            'requested_by' => $requester->id,
            'status' => Requisition::STATUS_PENDING,
            'items' => [['description' => 'Generic item', 'quantity' => 1]],
            'remarks' => null,
        ], $attributes));
    }

    public function test_queue_search_filters_by_requester_name(): void
    {
        $supply = $this->makeSupplyAdmin();
        $alice = $this->makeUser(['full_name' => 'Alice Wonderland']);
        $bob = $this->makeUser(['full_name' => 'Bob Builder']);

        $this->makeRequisition($alice, ['items' => [['description' => 'RAM 8GB', 'quantity' => 1]]]);
        $this->makeRequisition($bob, ['items' => [['description' => 'Keyboard', 'quantity' => 2]]]);

        $response = $this->actingAs($supply)->get(route('requisitions.index', ['view' => 'queue', 'q' => 'Alice']));

        $response->assertOk();
        $response->assertSee('Alice Wonderland');
        $response->assertDontSee('Bob Builder');
    }

    public function test_queue_search_matches_item_description_and_req_alias(): void
    {
        $supply = $this->makeSupplyAdmin();
        $requester = $this->makeUser(['full_name' => 'Plain Requester']);

        $ram = $this->makeRequisition($requester, ['items' => [['description' => 'SSD 512GB NVMe', 'quantity' => 1]]]);
        $this->makeRequisition($requester, ['items' => [['description' => 'Mouse pad', 'quantity' => 1]]]);

        // Item description search
        $byItem = $this->actingAs($supply)->get(route('requisitions.index', ['view' => 'queue', 'q' => 'NVMe']));
        $byItem->assertOk();
        $byItem->assertSee('SSD 512GB NVMe');
        $byItem->assertDontSee('Mouse pad');

        // REQ-##### alias resolves to the requisition id
        $alias = 'REQ-' . str_pad((string) $ram->id, 5, '0', STR_PAD_LEFT);
        $byAlias = $this->actingAs($supply)->get(route('requisitions.index', ['view' => 'queue', 'q' => $alias]));
        $byAlias->assertOk();
        $byAlias->assertSee($alias);
    }

    public function test_queue_sort_oldest_first_orders_cards(): void
    {
        $supply = $this->makeSupplyAdmin();
        $requester = $this->makeUser(['full_name' => 'Sorted Requester']);

        $older = $this->makeRequisition($requester);
        $newer = $this->makeRequisition($requester);

        Requisition::whereKey($older->id)->update(['created_at' => now()->subDays(5)]);
        Requisition::whereKey($newer->id)->update(['created_at' => now()->subDay()]);

        $asc = $this->actingAs($supply)->get(route('requisitions.index', [
            'view' => 'queue',
            'status' => 'all',
            'sort' => 'oldest',
        ]));
        $asc->assertOk();
        $oldNo = 'REQ-' . str_pad((string) $older->id, 5, '0', STR_PAD_LEFT);
        $newNo = 'REQ-' . str_pad((string) $newer->id, 5, '0', STR_PAD_LEFT);
        $asc->assertSeeInOrder([$oldNo, $newNo]);

        $desc = $this->actingAs($supply)->get(route('requisitions.index', [
            'view' => 'queue',
            'status' => 'all',
        ]));
        $desc->assertOk();
        $desc->assertSeeInOrder([$newNo, $oldNo]);
    }

    public function test_default_listing_shows_all_records(): void
    {
        $supply = $this->makeSupplyAdmin();
        $requester = $this->makeUser();

        $pending = $this->makeRequisition($requester);
        $issued = $this->makeRequisition($requester, ['status' => Requisition::STATUS_ISSUED]);

        // No params → defaults to the All records queue so everything is visible.
        $response = $this->actingAs($supply)->get(route('requisitions.index'));
        $response->assertOk();
        $response->assertSee('REQ-' . str_pad((string) $pending->id, 5, '0', STR_PAD_LEFT));
        $response->assertSee('REQ-' . str_pad((string) $issued->id, 5, '0', STR_PAD_LEFT));
        $response->assertSee('All records');
    }

    public function test_pending_filter_still_narrows_to_pending_only(): void
    {
        $supply = $this->makeSupplyAdmin();
        $requester = $this->makeUser();

        $pending = $this->makeRequisition($requester);
        $issued = $this->makeRequisition($requester, ['status' => Requisition::STATUS_ISSUED]);

        $response = $this->actingAs($supply)->get(route('requisitions.index', [
            'view' => 'queue',
            'status' => 'pending',
        ]));
        $response->assertOk();
        $response->assertSee('REQ-' . str_pad((string) $pending->id, 5, '0', STR_PAD_LEFT));
        $response->assertDontSee('REQ-' . str_pad((string) $issued->id, 5, '0', STR_PAD_LEFT));
    }

    public function test_tickets_tab_includes_pm_generated_job_orders(): void
    {
        $supply = $this->makeSupplyAdmin();
        $it = $this->makeUser(['role' => 'it']);

        // PM-generated ticket carrying a parts request
        $pmTicket = Ticket::create([
            'user_id' => $this->makeUser()->id,
            'assigned_to' => $it->id,
            'request_number' => 'JO-NCR-RCMB-2026-' . str_pad((string) (++$this->counter), 4, '0', STR_PAD_LEFT),
            'type' => 'Preventive Maintenance',
            'linked_asset_id' => null,
            'requestor_name' => 'PM Requestor',
            'description' => 'Quarterly maintenance',
            'status' => Ticket::STATUS_ONGOING,
            'region' => 'NCR',
        ]);
        Requisition::create([
            'request_id' => $pmTicket->id,
            'requested_by' => $it->id,
            'status' => Requisition::STATUS_PENDING,
            'items' => [['description' => 'Thermal paste', 'quantity' => 1]],
        ]);

        $response = $this->actingAs($supply)->get(route('requisitions.index', ['view' => 'tickets']));

        // Table renders the SHORT display form (region/branch stripped by accessor).
        $parts = explode('-', $pmTicket->request_number);
        $shortDisplay = $parts[0] . '-' . $parts[3] . '-' . $parts[4];

        $response->assertOk();
        $response->assertSee($shortDisplay);
    }

    public function test_it_history_defaults_to_all_and_status_filter_narrows(): void
    {
        $it = $this->makeUser(['role' => 'it']);

        $pending = $this->makeRequisition($it);
        $issued = $this->makeRequisition($it, ['status' => Requisition::STATUS_ISSUED]);

        $pendingNo = 'REQ-' . str_pad((string) $pending->id, 5, '0', STR_PAD_LEFT);
        $issuedNo = 'REQ-' . str_pad((string) $issued->id, 5, '0', STR_PAD_LEFT);

        $default = $this->actingAs($it)->get(route('requisitions.index'));
        $default->assertOk();
        $default->assertSee($pendingNo);
        $default->assertSee($issuedNo);

        $filtered = $this->actingAs($it)->get(route('requisitions.index', ['history_status' => 'issued']));
        $filtered->assertOk();
        $filtered->assertSee($issuedNo);
        $filtered->assertDontSee($pendingNo);
    }

    public function test_history_filter_keeps_the_history_tab_active(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $this->makeRequisition($it);

        // Clicking a filter carries tab=history so the History panel stays open
        $response = $this->actingAs($it)->get(route('requisitions.index', [
            'tab' => 'history',
            'history_status' => 'all',
        ]));

        $response->assertOk();
        // History tab is marked active; Request Parts is not
        $response->assertSee('class="cmms-tab active" data-target="tab-history"', false);
    }

    public function test_it_history_search_matches_item_description(): void
    {
        $it = $this->makeUser(['role' => 'it']);
        $hit = $this->makeRequisition($it, ['items' => [['description' => 'NVMe SSD 1TB', 'quantity' => 1]]]);
        $miss = $this->makeRequisition($it, ['items' => [['description' => 'Mouse pad', 'quantity' => 1]]]);

        $response = $this->actingAs($it)->get(route('requisitions.index', ['history_q' => 'NVMe']));

        $response->assertOk();
        $response->assertSee('NVMe SSD 1TB');
        $hitNo = 'REQ-' . str_pad((string) $hit->id, 5, '0', STR_PAD_LEFT);
        $missNo = 'REQ-' . str_pad((string) $miss->id, 5, '0', STR_PAD_LEFT);
        $response->assertSee($hitNo);
        $response->assertDontSee($missNo);
    }
}
