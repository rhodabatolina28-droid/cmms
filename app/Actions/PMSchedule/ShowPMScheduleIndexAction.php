<?php

namespace App\Actions\PMSchedule;

use App\Models\PMSchedule;
use App\Models\PMCycle;
use App\Models\PMDivisionSchedule;
use App\Models\InventoryAsset;
use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\Auth;

class ShowPMScheduleIndexAction
{
    /**
     * Display the PM schedules index with dashboard stats and work orders.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function execute()
    {
        $schedules = PMSchedule::with('creator')
            ->orderByDesc('created_at')
            ->paginate(20);

        // Calculate progress for each schedule
        $schedules->each(function ($schedule) {
            $completedDivisions = 0;

            if ($schedule->current_cycle_id) {
                $activeCycle = PMCycle::find($schedule->current_cycle_id);
                $cycleStart  = $activeCycle?->started_at ?? now();

                $totalDivisions = RequestModel::where('pm_schedule_id', $schedule->id)
                    ->where('is_auto_generated', true)
                    ->where('created_at', '>=', $cycleStart)
                    ->distinct('office')
                    ->count('office');

                $completedDivisions = PMDivisionSchedule::where('pm_cycle_id', $schedule->current_cycle_id)
                    ->whereNotNull('last_completed_at')
                    ->count();

                if ($totalDivisions === 0) {
                    $totalDivisions = InventoryAsset::where('status', 'Active')
                        ->whereNotNull('assigned_to_user')
                        ->distinct('office')
                        ->count('office');
                }
            } else {
                $totalDivisions = InventoryAsset::where('status', 'Active')
                    ->whereNotNull('assigned_to_user')
                    ->distinct('office')
                    ->count('office');
            }

            if ($totalDivisions === 0) {
                $totalDivisions = 1;
            }

            $schedule->total_divisions     = $totalDivisions;
            $schedule->completed_divisions = min($completedDivisions, $totalDivisions);

            if (is_null($schedule->current_focus_division) && is_null($schedule->current_cycle_id) && $completedDivisions > 0) {
                $schedule->progress_percentage = 100;
            } elseif (is_null($schedule->current_focus_division) && $schedule->current_cycle_id === null) {
                $schedule->progress_percentage = 0;
            } else {
                $schedule->progress_percentage = $totalDivisions > 0
                    ? round(($completedDivisions / $totalDivisions) * 100)
                    : 0;
            }
        });

        $user = Auth::user();
        $statTotalSchedules = PMSchedule::count();
        $statActiveSchedules = PMSchedule::where('is_active', true)->count();

        $statActiveWorkOrders = RequestModel::where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true)
            ->whereIn('status', ['Scheduled', 'Ongoing', 'Awaiting Signature'])
            ->count();

        $statCompletedThisMonth = RequestModel::where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true)
            ->where('status', 'Completed')
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [now()->format('Y-m')])
            ->count();

        $workOrderQuery = RequestModel::with(['user', 'assignedTo', 'maintenanceRequest'])
            ->where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true)
            ->whereIn('status', ['Scheduled', 'Ongoing', 'Awaiting Signature']);

        if ($user && $user->branch) {
            $workOrderQuery->where('branch', $user->branch);
        }

        $activeSchedule = PMSchedule::active()->first();
        $currentFocus = $activeSchedule?->current_focus_division;
        if ($currentFocus) {
            $workOrderQuery->where('office', $currentFocus);
        }

        $totalActiveWorkOrderCount = (clone $workOrderQuery)->count();

        $workOrders = $workOrderQuery->limit(20)->get();

        $userIds = $workOrders->pluck('user_id')->unique()->filter();
        $oldestAssets = InventoryAsset::whereIn('assigned_to_user', $userIds)
            ->whereIn('status', ['Active', 'Spare'])
            ->get()
            ->groupBy('assigned_to_user')
            ->map(function ($assets) {
                return $assets->min('date_acquired');
            });

        foreach ($workOrders as $order) {
            $order->schedule_date = $order->maintenanceRequest?->maintenance_date ?? $order->created_at->toDateString();
            $order->oldest_asset_date = $oldestAssets[$order->user_id] ?? null;
        }

        $workOrders = $workOrders->sortBy(function ($order) {
            return $order->oldest_asset_date ? \Carbon\Carbon::parse($order->oldest_asset_date)->timestamp : PHP_INT_MAX;
        })->values();

        $total = $workOrders->count();
        $workOrders->each(function ($order, $index) use ($total) {
            $percentile = $total > 1 ? ($index / ($total - 1)) : 0;
            if ($percentile <= 0.33) {
                $order->priority = 'High';
            } elseif ($percentile <= 0.66) {
                $order->priority = 'Medium';
            } else {
                $order->priority = 'Low';
            }
        });

        $focusDivision = $activeSchedule?->current_focus_division;

        return view('pm-schedules.index', compact(
            'schedules', 'workOrders', 'focusDivision', 'activeSchedule',
            'statTotalSchedules', 'statActiveSchedules', 'statActiveWorkOrders', 'statCompletedThisMonth',
            'totalActiveWorkOrderCount'
        ));
    }
}
