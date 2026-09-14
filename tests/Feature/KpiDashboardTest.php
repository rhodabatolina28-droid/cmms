<?php

namespace Tests\Feature;

use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * D9 - Maintenance KPI dashboard (3 cards: MTTR / P1 share / Parts Usage).
 * - MTTR: avg hours(created_at to completed_at) of completed ICT tickets, in days
 * - P1 share: high-official ICT tickets (D4 position accessor) vs total ICT in month
 * - Parts Usage: count of OUT movements (qty_change < 0) in parts_stock_movements
 */
class KpiDashboardTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;
    private ?int $partId = null;

    private function user(array $attributes = []): User
    {
        $this->counter++;
        return User::create(array_merge([
            "full_name" => "KPI User " . $this->counter,
            "email" => "kpi-user-" . $this->counter . "@test.com",
            "password" => bcrypt("password"),
            "role" => "user",
            "is_active" => true,
            "region" => "NCR",
            "branch" => "Main Office",
            "office" => "RESEARCH AND INFORMATION DIVISION",
        ], $attributes));
    }

    private function ictTicket(User $requestor, string $number, ?string $createdAt = null, ?string $completedAt = null): RequestModel
    {
        $t = RequestModel::create([
            "user_id" => $requestor->id,
            "request_number" => $number,
            "type" => "ICT",
            "requestor_name" => $requestor->full_name,
            "region" => "NCR",
            "branch" => "Main Office",
            "office" => "RESEARCH AND INFORMATION DIVISION",
            "status" => $completedAt ? "Completed" : "Pending",
            "is_deleted" => false,
            "description" => "KPI test ticket " . $number,
            "division_admin_review_status" => "Approved",
        ]);
        if ($createdAt) { $t->created_at = $createdAt; $t->save(); }
        if ($completedAt) { $t->completed_at = $completedAt; $t->save(); }
        return $t->refresh();
    }

    /** FK-safe: parts_stock_movements.part_id has a foreign key to parts_stock. */
    private function outMovement(string $when, int $qty = -1): void
    {
        if ($this->partId === null) {
            $this->partId = (int) DB::table("parts_stock")->insertGetId([
                "item_name" => "KPI Test Part",
                "unit" => "pcs",
                "created_at" => now(),
                "updated_at" => now(),
            ]);
        }
        DB::table("parts_stock_movements")->insert([
            "part_id" => $this->partId,
            "qty_change" => $qty,
            "reason" => "KPI test issue",
            "created_at" => $when,
        ]);
    }


    public function test_kpi_action_computes_mttr_p1_share_and_parts_usage(): void
    {
        $sa = $this->user(["role" => "super_admin"]);
        $official = $this->user(["position" => "OIC-Executive Director IV"]);
        $requestor = $this->user();

        $cur = now()->startOfMonth()->addHours(10);

        // current month: 2 completed regular tickets, each 24h -> MTTR = 1.0 day
        $this->ictTicket($requestor, "REQ-NCR-RCMB-2026-0001", $cur->format("Y-m-d H:i:s"), $cur->copy()->addHours(24)->format("Y-m-d H:i:s"));
        $this->ictTicket($requestor, "REQ-NCR-RCMB-2026-0002", $cur->copy()->addHours(2)->format("Y-m-d H:i:s"), $cur->copy()->addHours(2)->addHours(24)->format("Y-m-d H:i:s"));

        // current month: 1 high-official ticket (Pending) -> P1 = 1 of 3
        $this->ictTicket($official, "REQ-NCR-RCMB-2026-0003", $cur->copy()->addHours(4)->format("Y-m-d H:i:s"));

        // previous month: 1 completed ticket, 48h -> MTTR prev = 2.0 days
        $prev = now()->subMonth()->startOfMonth()->addHours(10);
        $this->ictTicket($requestor, "REQ-NCR-RCMB-2026-0011", $prev->format("Y-m-d H:i:s"), $prev->copy()->addHours(48)->format("Y-m-d H:i:s"));

        // parts movements: 2 OUT this month, 1 OUT previous month
        $this->outMovement(now()->format("Y-m-d H:i:s"), -1);
        $this->outMovement(now()->format("Y-m-d H:i:s"), -2);
        $this->outMovement(now()->subMonth()->format("Y-m-d H:i:s"), -1);

        $this->actingAs($sa);
        $kpi = (new \App\Actions\Dashboard\GetMaintenanceKpiAction)->execute();

        $this->assertSame(now()->format("Y-m"), $kpi["selected"]);
        $this->assertCount(6, $kpi["months"]);
        $this->assertSame(1.0, $kpi["mttr_days"]);
        $this->assertSame(2.0, $kpi["mttr_prev"]);
        $this->assertSame(1, $kpi["p1_count"]);
        $this->assertSame(3, $kpi["p1_total"]);
        $this->assertSame(33, $kpi["p1_share"]);
        $this->assertSame(2, $kpi["parts_usage"]);
        $this->assertSame(1, $kpi["parts_prev"]);
    }

    public function test_sa_dashboard_renders_kpi_section(): void
    {
        $sa = $this->user(["role" => "super_admin"]);
        $requestor = $this->user();

        $cur = now()->startOfMonth()->addHours(10);
        $this->ictTicket($requestor, "REQ-NCR-RCMB-2026-0021", $cur->format("Y-m-d H:i:s"), $cur->copy()->addHours(24)->format("Y-m-d H:i:s"));
        $this->outMovement(now()->format("Y-m-d H:i:s"), -1);

        $this->actingAs($sa)
            ->get(route("dashboard.super-admin"))
            ->assertOk()
            ->assertSee("Maintenance KPI")
            ->assertSee("MTTR")
            ->assertSee("P1")
            ->assertSee("Parts Usage")
            ->assertSee("1.0");
    }
}
