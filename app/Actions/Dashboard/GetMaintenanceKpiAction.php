<?php

namespace App\Actions\Dashboard;

use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\Auth;

class GetMaintenanceKpiAction
{
    /**
     * D9 (rev) - Maintenance KPI: MTTR + MTBF (2 cards).
     * Failure definition (naka-lock): completed ICT WITH a downtime window
     * (downtime_duration NOT NULL) - breakdowns only, request-only tickets excluded.
     * MTTR = avg(downtime_duration)/1440 of failures in the month (ISO 55000 time-to-restore;
     *        kabaligtod sa asset profile downtime numbers - isang data source)
     * MTBF = days in month / failure count (null when no failures)
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

            // Failures = completed ICT WITH a downtime window (breakdowns only)
            $failures = (clone $base)
                ->where("status", "Completed")
                ->whereNotNull("downtime_duration")
                ->whereYear("completed_at", $y)
                ->whereMonth("completed_at", $m)
                ->get(["downtime_duration"]);

            $count = $failures->count();
            $mttrDays = null;
            $mtbfDays = null;
            if ($count > 0) {
                $totalMinutes = $failures->sum(fn ($r) => $r->downtime_duration);
                $mttrDays = round($totalMinutes / $count / 1440, 1);
                $daysInMonth = \Carbon\Carbon::parse($key . "-01")->daysInMonth();
                $mtbfDays = round($daysInMonth / $count, 1);
            }

            $perMonth[$key] = [
                "label" => $label,
                "mttr_days" => $mttrDays,
                "mtbf_days" => $mtbfDays,
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
            "mtbf_days" => $cur["mtbf_days"],
            "mtbf_prev" => $prev["mtbf_days"] ?? null,
        ];
    }
}
