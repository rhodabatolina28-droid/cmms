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
        // trend series (6 months ascending; index 5 = current, 4 = prev, 0 = oldest)
        $this->assertSame(1.5, $kpi["trend"]["mttr"][5]);
        $this->assertSame(round($curDays / 2, 1), $kpi["trend"]["mtbf"][5]);
        $this->assertFalse($kpi["trend"]["censored"][5]);
        $this->assertSame(round($prevDays / 1, 1), $kpi["trend"]["mtbf"][4]);
        $this->assertNull($kpi["trend"]["mttr"][0]);
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
            // renamed plain-language labels (D9.7): acronyms nananatili sa subtitles
            ->assertSee("Avg. Downtime")
            ->assertSee("Days Between Failures")
            ->assertSee("1.0")
            ->assertSee("Mean time to repair")
            ->assertSee("Avg. Downtime")
            ->assertSee("Days Between Failures");
    }

    public function test_trend_marks_censored_months_for_no_breakdowns(): void
    {
        $sa = $this->user(["role" => "super_admin"]);
        $requestor = $this->user();

        $cur = now()->startOfMonth()->addHours(10);
        $curDays = now()->startOfMonth()->daysInMonth();
        $prev2 = now()->subMonths(2)->startOfMonth();

        // current month: only a request-only completed ticket (no downtime) -> censored
        $this->ictTicket($requestor, "REQ-NCR-RCMB-2026-0031", null, $cur->format("Y-m-d H:i:s"), $cur->copy()->addHours(6)->format("Y-m-d H:i:s"));

        // two months back: 1 breakdown, 2880 min = 2 days (index 3 -> plus the 2 empty months)
        $this->ictTicket($requestor, "REQ-NCR-RCMB-2026-0051", 2880, $prev2->copy()->addHours(10)->format("Y-m-d H:i:s"), $prev2->copy()->addHours(10)->addHours(48)->format("Y-m-d H:i:s"));

        $this->actingAs($sa);
        $kpi = (new \App\Actions\Dashboard\GetMaintenanceKpiAction)->execute();

        // index 5 = current month: censored (completed but no breakdown)
        $this->assertSame(round($curDays, 1), $kpi["trend"]["mtbf"][5]);
        $this->assertTrue($kpi["trend"]["censored"][5]);
        $this->assertNull($kpi["trend"]["mttr"][5]);
        // index 4 = prev month: no observation -> gap
        $this->assertNull($kpi["trend"]["mtbf"][4]);
        $this->assertFalse($kpi["trend"]["censored"][4]);
        // index 3 = two months back: solid breakdown, MTTR 2.0
        $this->assertSame(round($prev2->daysInMonth(), 1), $kpi["trend"]["mtbf"][3]);
        $this->assertSame(2.0, $kpi["trend"]["mttr"][3]);
        $this->assertFalse($kpi["trend"]["censored"][3]);
    }

    public function test_overdue_counts_only_open_pending_ongoing_tickets(): void
    {
        // Fix 1: ang Overdue card ay dapat hindi lalampas sa Pending + Ongoing.
        // Dati: raw query kasama ang Scheduled PM / Awaiting Parts / Awaiting
        // Signature / Referred-External (8) habang ang stat cards ay user-
        // submitted Pending+Ongoing lang (4) — mukhang double counting.
        $sa = $this->user(["role" => "super_admin"]);
        $requestor = $this->user();
        $old = now()->subDays(10)->format("Y-m-d H:i:s");

        $make = function (string $number, string $type, string $status) use ($requestor): RequestModel {
            return RequestModel::create([
                "user_id" => $requestor->id,
                "request_number" => $number,
                "type" => $type,
                "requestor_name" => $requestor->full_name,
                "region" => "NCR",
                "branch" => "Main Office",
                "office" => "RESEARCH AND INFORMATION DIVISION",
                "status" => $status,
                "is_deleted" => false,
                "description" => "Overdue test " . $number,
                "division_admin_review_status" => "Approved",
            ]);
        };

        // Overdue: Pending, 10 days old
        $pending = $make("REQ-NCR-RCMB-2026-0601", "ICT", "Pending");
        $pending->created_at = $old; $pending->save();

        // Excluded: Scheduled PM, 10 days old (mananatili sa D2 aging chips, hindi sa card)
        $scheduled = $make("REQ-NCR-RCMB-2026-0602", "Preventive Maintenance", "Scheduled");
        $scheduled->created_at = $old; $scheduled->save();

        // Excluded: Awaiting Parts, 10 days old
        $awaiting = $make("REQ-NCR-RCMB-2026-0603", "ICT", "Awaiting Parts");
        $awaiting->created_at = $old; $awaiting->save();

        // Hindi pa overdue: Pending, 3 days old
        $make("REQ-NCR-RCMB-2026-0604", "ICT", "Pending");

        $this->actingAs($sa);
        $view = (new \App\Actions\Dashboard\SuperAdminDashboardAction)->execute();
        $stats = $view->getData()["stats"];

        $this->assertSame(1, $stats["overdue_tickets"]);
        $this->assertLessThanOrEqual(
            $stats["pending"] + $stats["ongoing"],
            $stats["overdue_tickets"],
            "Overdue must never exceed Pending + Ongoing"
        );
    }

    public function test_active_assets_card_omits_under_repair_subnote(): void
    {
        // Fix 2: ang "4 under repair" subtext sa loob ng Active Assets card ay
        // nagmumukhang kasama sa Active count. Ang Under Repair ay HIWALAY na
        // enum states (For Repair / Under Maintenance) — may sariling slice sa
        // doughnut, kaya hindi na kailangan ang subnote (option b).
        $sa = $this->user(["role" => "super_admin"]);
        $this->actingAs($sa)
            ->get(route("dashboard.super-admin"))
            ->assertOk()
            ->assertSee("Active Assets")
            // lowercase phrase — HINDI tatamaan ang doughnut label na 'Under Repair'
            ->assertDontSee("under repair");
    }

    public function test_mtbf_decline_shows_warning_indicator(): void
    {
        // Fix 3: pagbaba ng MTBF = mas madalas na breakdown (BAD) — dapat red/
        // warning: red line + red chip + ▼ red delta text. Dati laging green
        // ang line/chip at baligtad ang ▲/▼ arrows.
        $sa = $this->user(["role" => "super_admin"]);
        $requestor = $this->user();

        $cur = now()->startOfMonth()->addHours(10);
        $prev = now()->subMonth()->startOfMonth();

        // prev month: 1 breakdown -> mataas na MTBF (~daysInMonth)
        $this->ictTicket($requestor, "REQ-NCR-RCMB-2026-0711", 1440, $prev->format("Y-m-d H:i:s"), $prev->copy()->addHours(24)->format("Y-m-d H:i:s"));

        // current month: 3 breakdowns -> MTBF = curDays/3, MAS MABABA kaysa prev
        $this->ictTicket($requestor, "REQ-NCR-RCMB-2026-0712", 1440, $cur->format("Y-m-d H:i:s"), $cur->copy()->addHours(24)->format("Y-m-d H:i:s"));
        $this->ictTicket($requestor, "REQ-NCR-RCMB-2026-0713", 1440, $cur->copy()->addHours(2)->format("Y-m-d H:i:s"), $cur->copy()->addHours(2)->addHours(24)->format("Y-m-d H:i:s"));
        $this->ictTicket($requestor, "REQ-NCR-RCMB-2026-0714", 1440, $cur->copy()->addHours(4)->format("Y-m-d H:i:s"), $cur->copy()->addHours(4)->addHours(24)->format("Y-m-d H:i:s"));

        $this->actingAs($sa)
            ->get(route("dashboard.super-admin"))
            ->assertOk()
            // worsening delta text (red) — dati baligtad ang arrow nito
            ->assertSee("days shorter")
            // red indicator color sa line graph + chip (kapag worsened)
            ->assertSee("dc2626")
            // ▼ arrow (value down) sa worsening — dati ▲ ang naka-render
            // (escape=false: literal na HTML entity ang hinahanap sa output)
            ->assertSee("&#9660;", false);
    }

    public function test_overdue_pms_counts_only_late_scheduled_pm(): void
    {
        // D9.7: ang Overdue PMs ay Scheduled PM lang na lampas sa 3-working-day
        // service window (D2-e is_aging_overdue rule). PM never uses
        // Pending/Ongoing, kaya walang double-count sa ICT overdue card.
        $sa = $this->user(["role" => "super_admin"]);
        $requestor = $this->user();

        $mkPm = function (string $number, string $status, string $createdAt) use ($requestor): RequestModel {
            $t = RequestModel::create([
                "user_id" => $requestor->id,
                "request_number" => $number,
                "type" => "Preventive Maintenance",
                "requestor_name" => $requestor->full_name,
                "region" => "NCR",
                "branch" => "Main Office",
                "office" => "RESEARCH AND INFORMATION DIVISION",
                "status" => $status,
                "is_deleted" => false,
                "description" => "Overdue PM test " . $number,
                "division_admin_review_status" => "Approved",
            ]);
            $t->created_at = $createdAt;
            $t->save();
            return $t->refresh();
        };

        // 8 days old = min. 4 working days > 3 -> OVERDUE
        $late = $mkPm("REQ-NCR-RCMB-2026-0801", "Scheduled", now()->subDays(8)->format("Y-m-d H:i:s"));
        // 1 day old = 0-1 working days -> hindi pa overdue
        $fresh = $mkPm("REQ-NCR-RCMB-2026-0802", "Scheduled", now()->subDay()->format("Y-m-d H:i:s"));
        // Completed = excluded (status filter)
        $mkPm("REQ-NCR-RCMB-2026-0803", "Completed", now()->subDays(10)->format("Y-m-d H:i:s"));

        $this->assertTrue($late->is_aging_overdue);
        $this->assertFalse($fresh->is_aging_overdue);

        $this->actingAs($sa);
        $view = (new \App\Actions\Dashboard\SuperAdminDashboardAction)->execute();
        $stats = $view->getData()["stats"];

        $this->assertSame(1, $stats["overdue_pms"]);
    }

    public function test_dashboard_renders_new_cards_and_renamed_kpis(): void
    {
        // D9.7: bagong CSM Satisfaction card + amber PM-overdue subtext sa
        // Overdue card + plain-language KPI labels. Laihat ng lumang acronym
        // trend titles ay dapat mawala.
        $sa = $this->user(["role" => "super_admin"]);
        $requestor = $this->user();

        $pm = RequestModel::create([
            "user_id" => $requestor->id,
            "request_number" => "REQ-NCR-RCMB-2026-0811",
            "type" => "Preventive Maintenance",
            "requestor_name" => $requestor->full_name,
            "region" => "NCR",
            "branch" => "Main Office",
            "office" => "RESEARCH AND INFORMATION DIVISION",
            "status" => "Scheduled",
            "is_deleted" => false,
            "description" => "Render test late PM",
            "division_admin_review_status" => "Approved",
        ]);
        $pm->created_at = now()->subDays(8)->format("Y-m-d H:i:s");
        $pm->save();

        $this->actingAs($sa)
            ->get(route("dashboard.super-admin"))
            ->assertOk()
            // bagong cards
            ->assertSee("CSM Satisfaction")
            ->assertSee("PM overdue")
            // renamed KPI labels
            ->assertSee("Avg. Downtime")
            ->assertSee("Days Between Failures")
            ->assertSee("Avg. Downtime")
            ->assertSee("Days Between Failures")
            // lumang acronym trend titles at ang redundant Service Quality widget ay dapat wala na
            ->assertDontSee("MTTR Trend")
            ->assertDontSee("MTBF Trend")
            ->assertDontSee("Service Quality");
    }
}
