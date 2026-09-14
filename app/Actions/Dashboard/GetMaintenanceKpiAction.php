<?php

namespace App\Actions\Dashboard;

use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GetMaintenanceKpiAction
{
    /**
     * D9: Maintenance KPI - buwanang (6-month window).
     * MTTR       = avg hours(created_at to completed_at) of completed ICT tickets, in days
     * P1 share   = high-official ICT tickets (User::is_high_official, D4) / total ICT in month
     * PartsUsage = count of OUT movements (qty_change < 0) in parts_stock_movements
     * Selected month via kpi_month GET param (default: current month).
     */
    public function execute(): array
    {
        $user = Auth::user();

        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[now()->subMonths($i)->format("Y-m")] = now()->subMonths($i)->format("F Y");
        }

        $selected = request()->input("kpi_month");
        if (! isset($months[$selected])) {
            $selected = array_key_last($months);
        }

        // Same scope as the dashboard: approved ICT tickets, branch-scoped
        $base = RequestModel::query()
            ->where("type", "ICT")
            ->where("division_admin_review_status", "Approved")
            ->whereHas("user", function ($q) use ($user) {
                if ($user && $user->branch) {
                    $q->where("branch", $user->branch);
                }
            });

        $perMonth = [];
        foreach ($months as $key => $label) {
            [$y, $m] = explode("-", $key);

            // MTTR - completed in this month (abs: Carbon 3 signed diffs, same trap as the B1 fix)
            $completed = (clone $base)
                ->where("status", "Completed")
                ->whereYear("completed_at", $y)
                ->whereMonth("completed_at", $m)
                ->get(["created_at", "completed_at"]);
            $mttrDays = null;
            if ($completed->isNotEmpty()) {
                $totalHours = $completed->sum(fn ($r) => abs($r->completed_at->diffInHours($r->created_at)));
                $mttrDays = round($totalHours / $completed->count() / 24, 1);
            }

            // P1 share - created in this month
            $monthTickets = (clone $base)
                ->whereYear("created_at", $y)
                ->whereMonth("created_at", $m)
                ->with("user:id,position")
                ->get(["id", "user_id"]);
            $p1Count = $monthTickets->filter(fn ($t) => $t->user && $t->user->is_high_official)->count();
            $p1Total = $monthTickets->count();

            // Parts usage - OUT movements in this month
            $partsUsage = DB::table("parts_stock_movements")
                ->where("qty_change", "<", 0)
                ->whereYear("created_at", $y)
                ->whereMonth("created_at", $m)
                ->count();

            $perMonth[$key] = [
                "label" => $label,
                "mttr_days" => $mttrDays,
                "p1_share" => $p1Total > 0 ? (int) round($p1Count / $p1Total * 100) : null,
                "p1_count" => $p1Count,
                "p1_total" => $p1Total,
                "parts_usage" => $partsUsage,
            ];
        }

        $keys = array_keys($perMonth);
        $selIdx = array_search($selected, $keys, true);
        $prevKey = $selIdx > 0 ? $keys[$selIdx - 1] : null;
        $cur = $perMonth[$selected];
        $prev = $prevKey !== null ? $perMonth[$prevKey] : null;

        return [
            "months" => $months,
            "selected" => $selected,
            "selected_label" => $cur["label"],
            "mttr_days" => $cur["mttr_days"],
            "mttr_prev" => $prev["mttr_days"] ?? null,
            "p1_share" => $cur["p1_share"],
            "p1_count" => $cur["p1_count"],
            "p1_total" => $cur["p1_total"],
            "parts_usage" => $cur["parts_usage"],
            "parts_prev" => $prev["parts_usage"] ?? null,
        ];
    }
}
