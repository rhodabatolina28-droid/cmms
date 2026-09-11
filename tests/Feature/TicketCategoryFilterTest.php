<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D8.3 — Master List "Type" column + category filter.
 * - SA JSON endpoint: server-side `category` filter (linked asset type) + hasFilters hook
 * - IT list: linked_asset category serialized for the client-side filter
 * Registry convention (COA/DICT): labeled column, text only — no icons.
 */
class TicketCategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'Category User ' . $this->counter,
            'email' => 'category-user-' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'RESEARCH AND INFORMATION DIVISION',
        ], $attributes));
    }

    private function asset(string $category): InventoryAsset
    {
        $this->counter++;

        return InventoryAsset::create([
            'category' => $category,
            'item_name' => 'Category Asset ' . $this->counter,
            'serial_number' => 'CAT-' . $this->counter,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'RESEARCH AND INFORMATION DIVISION',
            'status' => 'Spare',
        ]);
    }

    private function ictTicket(User $requestor, string $number, ?InventoryAsset $asset = null, ?User $assignee = null): RequestModel
    {
        return RequestModel::create([
            'user_id' => $requestor->id,
            'request_number' => $number,
            'type' => 'ICT',
            'requestor_name' => $requestor->full_name,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'RESEARCH AND INFORMATION DIVISION',
            'status' => 'Pending',
            'is_deleted' => false,
            'description' => 'Category filter test ticket ' . $number,
            'division_admin_review_status' => 'Approved',
            'linked_asset_id' => $asset?->asset_id,
            'assigned_to' => $assignee?->id,
        ]);
    }

    public function test_master_list_json_filters_by_linked_asset_category(): void
    {
        $sa = $this->user(['role' => 'super_admin']);
        $requestor = $this->user();

        $laptop = $this->asset('Laptop');
        $printer = $this->asset('Printer/Scanner');

        $laptopTicket = $this->ictTicket($requestor, 'REQ-NCR-RCMB-2026-0001', $laptop);
        $printerTicket = $this->ictTicket($requestor, 'REQ-NCR-RCMB-2026-0002', $printer);

        $response = $this->actingAs($sa)
            ->getJson(route('super_admin.requests.data', ['category' => 'Laptop']))
            ->assertOk();

        $ids = collect($response->json('requests'))->pluck('id')->all();
        $this->assertContains($laptopTicket->id, $ids, 'Category filter must keep the matching ticket');
        $this->assertNotContains($printerTicket->id, $ids, 'Category filter must drop the non-matching ticket');

        // Eager-loaded category must be serialized for the JS column (text only).
        $first = collect($response->json('requests'))->firstWhere('id', $laptopTicket->id);
        $this->assertSame('Laptop', $first['linked_asset']['category'] ?? null);

        // hasFilters hook: category alone must activate filtered_stats.
        $this->assertSame(1, $response->json('filtered_stats.total'));
        $this->assertSame(2, $response->json('stats.total'), 'Unfiltered stats must stay at 2');
    }

    public function test_ict_list_serializes_linked_asset_category(): void
    {
        $it = $this->user(['role' => 'it']);
        $requestor = $this->user();

        $laptop = $this->asset('Laptop');
        $monitor = $this->asset('Monitor');

        // IT branch lists only tickets ASSIGNED to the IT user.
        $laptopTicket = $this->ictTicket($requestor, 'REQ-NCR-RCMB-2026-0011', $laptop, $it);
        $monitorTicket = $this->ictTicket($requestor, 'REQ-NCR-RCMB-2026-0012', $monitor, $it);

        $response = $this->actingAs($it)
            ->getJson(route('ict.index'))
            ->assertOk();

        $requests = collect($response->json('requests'));
        $this->assertSame('Laptop', $requests->firstWhere('id', $laptopTicket->id)['linked_asset']['category'] ?? null);
        $this->assertSame('Monitor', $requests->firstWhere('id', $monitorTicket->id)['linked_asset']['category'] ?? null);
    }

    public function test_ticket_without_linked_asset_keeps_null_category(): void
    {
        $sa = $this->user(['role' => 'super_admin']);
        $requestor = $this->user();

        $ticket = $this->ictTicket($requestor, 'REQ-NCR-RCMB-2026-0021', null);

        $response = $this->actingAs($sa)
            ->getJson(route('super_admin.requests.data'))
            ->assertOk();

        $first = collect($response->json('requests'))->firstWhere('id', $ticket->id);
        $this->assertNull($first['linked_asset'] ?? null, 'Unlinked tickets serialize linked_asset as null — the front end falls back to —');
    }

    public function test_master_list_views_render_type_column_and_category_filter(): void
    {
        $requestor = $this->user();
        $asset = $this->asset('Laptop');
        $ticket = $this->ictTicket($requestor, 'REQ-NCR-RCMB-2026-0031', $asset);

        // IT view (server-rendered rows + client-side filter)
        $it = $this->user(['role' => 'it']);
        $ticket->update(['assigned_to' => $it->id]);
        $this->actingAs($it)->get(route('ict.index'))
            ->assertOk()
            ->assertSee('All Categories')
            ->assertSee('<th>Type</th>', false)
            ->assertSee('td-type', false)
            ->assertSee('Laptop');

        // Admin view (server-rendered rows + client-side filter)
        $admin = $this->user(['role' => 'admin']);
        $this->actingAs($admin)->get(route('ict.index'))
            ->assertOk()
            ->assertSee('All Categories')
            ->assertSee('<th>Type</th>', false)
            ->assertSee('ad-td-type', false)
            ->assertSee('Laptop');

        // SA view (JS-rendered rows — static thead + ribbon must carry the new column/filter)
        $sa = $this->user(['role' => 'super_admin']);
        $this->actingAs($sa)->get(route('ict.index'))
            ->assertOk()
            ->assertSee('All Categories')
            ->assertSee('<th>Type</th>', false)
            ->assertSee('sa-td-type', false);
    }
}
