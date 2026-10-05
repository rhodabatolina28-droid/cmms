<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\PhysicalCountSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scan-rate-limit defect (Oct 2026, reported live: "pag nakarami na ko ng scan
 * hindi ako makapag scan").
 *
 * Root cause: ThrottleRequests keys authenticated requests by sha1(user_id)
 * ONLY (no route component, empty prefix for `throttle:30,1`), so every
 * `throttle:*` route shared ONE 30/min bucket per user. Each QR scan = 1 POST
 * /search + 1 POST /mark, and "Mark all Present" bursts +10 sequentially ->
 * 429 in seconds, silently swallowed by the JS (search returned nothing,
 * markMany reported "already counted", buttons stayed disabled).
 *
 * Fix under test: route-scoped throttle prefixes (pc-search / pc-mark / ...)
 * with burst-safe limits, so scanning is isolated from the rest of the app.
 */
class PhysicalCountScanThrottleTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'PC Throttle User ' . $this->counter,
            'email' => 'pc-throttle-' . $this->counter . '@test.com',
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
            'serial_number' => 'THR-SN-' . $this->counter,
            'property_number' => 'THR-PROP-' . $this->counter,
            'par_number' => 'THR-PAR-' . $this->counter,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
            'status' => 'Active',
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

    /**
     * Cross-route isolation proof: exhaust the /search route with 35 hits,
     * then hit two OTHER throttled routes. If the counters were shared (the
     * old sha1(user_id) key), they would 429 despite having zero attempts
     * of their own.
     */
    public function test_search_burst_does_not_block_other_throttled_routes(): void
    {
        $supply = $this->user(['role' => 'supply_officer', 'full_name' => 'Supply Throttle']);
        $session = $this->startCountSession($supply);

        $searchStatuses = [];
        for ($i = 1; $i <= 35; $i++) {
            $searchStatuses[$i] = $this->actingAs($supply)
                ->postJson(route('physical-count.search', $session->id), ['q' => 'x'])
                ->status();
        }

        // (1) A different route with its own bucket must not be affected by
        //     the 35 /search hits (shared counter = 429 here).
        $this->assertNotSame(
            429,
            $this->actingAs($supply)->postJson(route('notifications.read-all'))->status(),
            'notifications/read-all was throttled by another route\'s traffic (shared counter)'
        );

        // (2) /mark must stay reachable after the /search burst — validation
        //     422 (missing asset_id) proves the request reached the action.
        $mark = $this->actingAs($supply)
            ->postJson(route('physical-count.mark', $session->id), []);
        $this->assertNotSame(429, $mark->status(), 'mark was throttled by the search route (shared counter)');
        $mark->assertStatus(422);

        // (3) /search itself must survive a 35-hit typing/scan burst
        //     (was capped at 30 by the shared bucket).
        foreach ($searchStatuses as $i => $status) {
            $this->assertNotSame(429, $status, "/search hit 429 at request #{$i} of 35");
        }
    }

    /**
     * Burst safety for /mark: a scan session must be able to fire at least
     * 40 sequential marks (custodian "Mark all" bursts) without hitting the
     * throttle (was 30/min shared with every other throttle:* route).
     */
    public function test_mark_route_survives_bulk_marking_burst(): void
    {
        $supply = $this->user(['role' => 'supply_officer', 'full_name' => 'Bulk Supply']);
        $session = $this->startCountSession($supply);

        for ($i = 1; $i <= 40; $i++) {
            $status = $this->actingAs($supply)
                ->postJson(route('physical-count.mark', $session->id), [])
                ->status();
            $this->assertNotSame(429, $status, "mark hit 429 at request #{$i} of 40");
            $this->assertSame(422, $status, "mark returned {$status} at request #{$i} (expected validation 422)");
        }
    }

    /**
     * The physical-count scan routes must carry route-scoped throttle prefixes
     * so their counters cannot collide with each other or with the ~60 other
     * throttle:* routes in the app.
     */
    public function test_scan_routes_use_scoped_throttle_prefixes(): void
    {
        $expected = [
            'physical-count.store'    => 'throttle:30,1,pc-store',
            'physical-count.search'   => 'throttle:120,1,pc-search',
            'physical-count.mark'     => 'throttle:300,1,pc-mark',
            'physical-count.complete' => 'throttle:30,1,pc-complete',
        ];

        foreach ($expected as $name => $middleware) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route {$name} not found");
            $this->assertContains(
                $middleware,
                $route->gatherMiddleware(),
                "Route {$name} must declare scoped middleware {$middleware}"
            );
        }
    }
}

