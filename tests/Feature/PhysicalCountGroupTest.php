<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\PhysicalCount;
use App\Models\PhysicalCountSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhysicalCountGroupTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'Test User ' . $this->counter,
            'email' => 'pc-user-' . $this->counter . '@test.com',
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
            'item_name' => 'Desktop ' . $this->counter,
            'serial_number' => 'PC-SN-' . $this->counter,
            'property_number' => 'PC-PROP-' . $this->counter,
            'par_number' => 'PC-PAR-' . $this->counter,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
            'status' => 'Spare',
        ], $attributes));
    }

    private function startCountSession(User $actor): PhysicalCountSession
    {
        return PhysicalCountSession::create([
            'started_by' => $actor->id,
            'started_at' => now(),
            'status' => 'Ongoing',
            'scope_region' => $actor->region,
            'scope_branch' => $actor->branch,
        ]);
    }

    public function test_unique_custodian_name_match_returns_group(): void
    {
        $supply = $this->user(['role' => 'supply_officer', 'email' => 'supply1@test.com', 'full_name' => 'Supply Officer One']);
        $custodian = $this->user(['full_name' => 'Maria Santos']);

        $a1 = $this->asset(['assigned_to_user' => $custodian->id, 'status' => 'Active']);
        $a2 = $this->asset(['assigned_to_user' => $custodian->id, 'status' => 'Active']);
        $a3 = $this->asset(['assigned_to_user' => $custodian->id, 'status' => 'Active']);
        // Excluded from group: unassigned spare + For Disposal
        $this->asset(['status' => 'Spare']);
        $this->asset(['assigned_to_user' => $custodian->id, 'status' => 'For Disposal']);

        $session = $this->startCountSession($supply);

        $data = $this->actingAs($supply)
            ->postJson(route('physical-count.search', $session->id), ['q' => 'Maria'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $this->assertNotNull($data['custodian_group']);
        $this->assertSame('Maria Santos', $data['custodian_group']['full_name']);
        $this->assertSame(3, $data['custodian_group']['total']);

        $groupIds = collect($data['custodian_group']['assets'])->pluck('asset_id')->all();
        $this->assertEqualsCanonicalizing([$a1->asset_id, $a2->asset_id, $a3->asset_id], $groupIds);
    }

    public function test_multiple_name_matches_do_not_return_group(): void
    {
        $supply = $this->user(['role' => 'supply_officer', 'email' => 'supply2@test.com', 'full_name' => 'Supply Officer Two']);
        $maria1 = $this->user(['full_name' => 'Maria Santos']);
        $maria2 = $this->user(['full_name' => 'Maria Clara']);
        $this->asset(['assigned_to_user' => $maria1->id, 'status' => 'Active']);
        $this->asset(['assigned_to_user' => $maria2->id, 'status' => 'Active']);

        $session = $this->startCountSession($supply);

        $data = $this->actingAs($supply)
            ->postJson(route('physical-count.search', $session->id), ['q' => 'Maria'])
            ->assertOk()
            ->json();

        $this->assertNull($data['custodian_group']);
        // Flat list still finds both custodians' assets via the name match
        $this->assertGreaterThanOrEqual(2, count($data['assets']));
    }

    public function test_group_is_scoped_to_actor_branch(): void
    {
        $supply = $this->user(['role' => 'supply_officer', 'email' => 'supply3@test.com', 'full_name' => 'Supply Officer Three']);
        $custodian = $this->user(['full_name' => 'Juan Dela Cruz']);

        $inBranch = $this->asset(['assigned_to_user' => $custodian->id, 'status' => 'Active', 'branch' => 'Main Office']);
        $this->asset(['assigned_to_user' => $custodian->id, 'status' => 'Active', 'branch' => 'Baguio Branch']);

        $session = $this->startCountSession($supply);

        $data = $this->actingAs($supply)
            ->postJson(route('physical-count.search', $session->id), ['q' => 'Juan Dela Cruz'])
            ->assertOk()
            ->json();

        $this->assertNotNull($data['custodian_group']);
        $this->assertSame(1, $data['custodian_group']['total']);
        $this->assertSame($inBranch->asset_id, $data['custodian_group']['assets'][0]['asset_id']);
    }

    public function test_search_without_custodian_returns_no_group(): void
    {
        $supply = $this->user(['role' => 'supply_officer', 'email' => 'supply4@test.com', 'full_name' => 'Supply Officer Four']);
        $this->asset(['item_name' => 'Unique Projector', 'status' => 'Spare']);

        $session = $this->startCountSession($supply);

        $data = $this->actingAs($supply)
            ->postJson(route('physical-count.search', $session->id), ['q' => 'Unique Projector'])
            ->assertOk()
            ->json();

        $this->assertNull($data['custodian_group']);
        $this->assertCount(1, $data['assets']);
    }

    public function test_search_requires_supply_access(): void
    {
        $endUser = $this->user(['role' => 'user']);
        $supply = $this->user(['role' => 'supply_officer', 'email' => 'supply5@test.com', 'full_name' => 'Supply Officer Five']);
        $session = $this->startCountSession($supply);

        $this->actingAs($endUser)
            ->postJson(route('physical-count.search', $session->id), ['q' => 'x'])
            ->assertStatus(403);
    }

    public function test_mark_then_remark_is_rejected_for_bulk_safety(): void
    {
        $supply = $this->user(['role' => 'supply_officer', 'email' => 'supply6@test.com', 'full_name' => 'Supply Officer Six']);
        $custodian = $this->user(['full_name' => 'Maria Reyes']);
        $asset = $this->asset(['assigned_to_user' => $custodian->id, 'status' => 'Active']);
        $session = $this->startCountSession($supply);

        $this->actingAs($supply)
            ->postJson(route('physical-count.mark', $session->id), [
                'asset_id' => $asset->asset_id,
                'status' => 'Present',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        // Immutability: bulk "Mark all Present" must treat this 422 as skip
        $this->actingAs($supply)
            ->postJson(route('physical-count.mark', $session->id), [
                'asset_id' => $asset->asset_id,
                'status' => 'Present',
            ])
            ->assertStatus(422);

        $this->assertSame(1, PhysicalCount::where('session_id', $session->id)->count());
    }

    public function test_completed_session_rejects_search(): void
    {
        $supply = $this->user(['role' => 'supply_officer', 'email' => 'supply7@test.com', 'full_name' => 'Supply Officer Seven']);
        $session = $this->startCountSession($supply);
        $session->update(['status' => 'Completed', 'completed_at' => now()]);

        $this->actingAs($supply)
            ->postJson(route('physical-count.search', $session->id), ['q' => 'x'])
            ->assertStatus(422);
    }

    public function test_qr_batch_page_loads_with_paged_loader_for_supply_role(): void
    {
        $supply = $this->user(['role' => 'supply_officer', 'email' => 'supply8@test.com', 'full_name' => 'Supply Officer Eight']);

        $this->actingAs($supply)
            ->get(route('inventory.qr-batch'))
            ->assertOk()
            ->assertSee('loadAllAssets', false)
            ->assertSee('per_page=100', false);
    }
}
