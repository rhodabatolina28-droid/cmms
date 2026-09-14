<?php

namespace Tests\Feature;

use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D9 (rev) - Maintenance KPI dashboard: MTTR + MTBF (2 cards).
 * - MTTR = avg(downtime_duration)/1440 of completed ICT breakdowns, in days
 * - MTBF = days in month / count of failures (completed ICT WITH downtime)
 * Both use the same failure set: completed ICT with downtime_duration NOT NULL
 * (aligned with the locked downtime = breakdown decision; request-only tickets
 * are NOT failures). SLA% / P1% / Parts Usage are REMOVED (deferred D3).
 */
class KpiDashboardTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

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

    private function ictTicket(User $requestor, string $number, ?int $downtimeMinutes = null, ?string $createdAt = null, ?string $completedAt = null): RequestModel
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
        if ($completedAt) {
            $t->completed_at = $completedAt; $t->save();
            if ($downtimeMinutes !== null) { $t->downtime_duration = $downtimeMinutes; $t->save(); }
        }
        return $t->refresh();
    }


    public function test_kpi_action_computes_mttr_and_mtbf(): void
    {
        $sa = $this->user(["role" => "super_admin"]);
        $requestor = $this->user();

        $cur = now()->startOfMonth()->addHours(10);
        $curDays = now()->startOfMonth()->daysInMonth();
        $prev = now()->subMonth()->startOfMonth();
        $prevDays = $prev->daysInMonth();

        // current month: 2 breakdowns with downtime (1440 min = 1 day, 2880 min = 2 days)
        $this->ictTicket($requestor, "REQ-NCR-RCMB-2026-0001", 1440, $cur->format("Y-m-d H:i:s"), $cur->copy()->addHours(24)->format("Y-m-d H:i:s"));
        $this->ictTicket($requestor, "REQ-NCR-RCMB-2026-0002", 2880, $cur->copy()->addHours(2)->format("Y-m-d H:i:s"), $cur->copy()->addHours(2)->addHours(48)->format("Y-m-d H:i:s"));

        // request-only ticket (completed WITHOUT downtime) - must NOT count as failure
        $this->ictTicket($requestor, "REQ-NCR-RCMB-2026-0003", null, $cur->copy()->addHours(4)->format("Y-m-d H:i:s"), $cur->copy()->addHours(6)->format("Y-m-d H:i:s"));

        // previous month: 1 breakdown, 4320 min = 3 days
        $this->ictTicket($requestor, "REQ-NCR-RCMB-2026-0011", 4320, $prev->copy()->addHours(10)->format("Y-m-d H:i:s"), $prev->copy()->addHours(82)->format("Y-m-d H:i:s"));

        $this->actingAs($sa);
        $kpi = (new \App\Actions\Dashboard\GetMaintenanceKpiAction)->execute();

        $this->assertSame(now()->format("Y-m"), $kpi["selected"]);
        $this->assertCount(6, $kpi["months"]);
        // MTTR = avg downtime (1440+2880)/2 /1440 = 1.5 days; prev month: 4320/1440 = 3.0 days
        $this->assertSame(1.5, $kpi["mttr_days"]);
        $this->assertSame(3.0, $kpi["mttr_prev"]);
        // MTBF = month days / 2 failures (request-only ticket excluded)
        $this->assertSame(round($curDays / 2, 1), $kpi["mtbf_days"]);
        $this->assertSame(round($prevDays / 1, 1), $kpi["mtbf_prev"]);
    }

    public function test_zero_failure_month_reports_no_failures(): void
    {
        $sa = $this->user(["role" => "super_admin"]);
        $requestor = $this->user();

        $this->actingAs($sa);
        $kpi = (new \App\Actions\Dashboard\GetMaintenanceKpiAction)->execute();

        $this->assertNull($kpi["mtbf_days"]);
        $this->assertNull($kpi["mttr_days"]);
    }

    public function test_sa_dashboard_renders_mttr_and_mtbf_cards(): void
    {
        $sa = $this->user(["role" => "super_admin"]);
        $requestor = $this->user();

        $cur = now()->startOfMonth()->addHours(10);
        $this->ictTicket($requestor, "REQ-NCR-RCMB-2026-0021", 1440, $cur->format("Y-m-d H:i:s"), $cur->copy()->addHours(24)->format("Y-m-d H:i:s"));

        $this->actingAs($sa)
            ->get(route("dashboard.super-admin"))
            ->assertOk()
            ->assertSee("Maintenance KPI")
            ->assertSee("MTTR")
            ->assertSee("MTBF")
            ->assertSee("1.0")
            ->assertSee("Avg. time to restore");
    }
}
