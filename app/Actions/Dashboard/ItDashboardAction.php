<?php

namespace App\Actions\Dashboard;

use App\Models\Request as RequestModel;
use App\Models\PMSchedule;
use App\Models\InventoryAsset;
use App\Services\GeneratePMScheduleService;
use Illuminate\Support\Facades\Auth;

class ItDashboardAction
{
    /**
     * Build the IT dashboard view.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function execute()
    {
        $user = Auth::user();

        // Get ALL tickets assigned to IT (ICT + PM)
        $assignedQuery = RequestModel::where('assigned_to', $user->id);

        // Stats - count all non-completed tickets assigned to IT
        $statsRow = (clone $assignedQuery)
            ->where('status', '!=', RequestModel::STATUS_COMPLETED)
            ->selectRaw("COUNT(*) as assigned")
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending", [RequestModel::STATUS_PENDING])
            ->selectRaw("SUM(CASE WHEN status = 'Ongoing' THEN 1 ELSE 0 END) as ongoing")
            ->selectRaw("SUM(CASE WHEN status = 'Scheduled' THEN 1 ELSE 0 END) as scheduled")
            ->selectRaw("SUM(CASE WHEN status = 'Awaiting Parts' THEN 1 ELSE 0 END) as awaiting_parts")
            ->selectRaw("SUM(CASE WHEN status = '" . RequestModel::STATUS_REFERRED_EXTERNAL . "' THEN 1 ELSE 0 END) as referred_external")
            ->first();

        $stats = [
            'assigned' => $statsRow->assigned ?? 0,
            'pending' => $statsRow->pending ?? 0,
            'ongoing' => $statsRow->ongoing ?? 0,
            'scheduled' => $statsRow->scheduled ?? 0,
            'awaiting_parts' => $statsRow->awaiting_parts ?? 0,
            'referred_external' => $statsRow->referred_external ?? 0,
        ];

        // Show ALL assigned tickets including Scheduled PMs
        $requests = (clone $assignedQuery)
            ->with(['user', 'repairRequest', 'maintenanceRequest', 'requisitions'])
            ->whereNotIn('status', [RequestModel::STATUS_COMPLETED, RequestModel::STATUS_CANCELLED])
            ->orderByRaw("CASE
                WHEN status = ? THEN 0
                WHEN status = 'Scheduled' THEN 1
                WHEN status = ? THEN 2
                WHEN status = ? THEN 3
                ELSE 4 END", [
                RequestModel::STATUS_PENDING,
                RequestModel::STATUS_ONGOING,
                RequestModel::STATUS_AWAITING_PARTS,
            ])
            ->orderBy('updated_at', 'desc')
            ->limit(6)
            ->get();

        $needsCompletion = (clone $assignedQuery)
            ->with(['repairRequest', 'maintenanceRequest'])
            ->where(function ($query) {
                $query->where(function ($ict) {
                    $ict->where('type', 'ICT')
                        ->whereHas('repairRequest', function ($repair) {
                            $repair->where(function ($missing) {
                                $missing->whereNull('after_repair_status')
                                    ->orWhere('after_repair_status', '')
                                    ->orWhereNull('it_personnel_signature')
                                    ->orWhere('it_personnel_signature', '');
                            });
                        });
                })->orWhere(function ($pm) {
                    $pm->where('type', 'Preventive Maintenance')
                        ->whereHas('maintenanceRequest', function ($maintenance) {
                            $maintenance->whereNull('technician_signature')
                                ->orWhere('technician_signature', '');
                        });
                });
            })
            ->whereNotIn('status', [RequestModel::STATUS_PENDING, RequestModel::STATUS_AWAITING_PARTS])
            ->orderBy('updated_at', 'desc')
            ->limit(4)
            ->get();

        // PM Schedule Data for IT Dashboard
        $pmFocusDivision = null;
        $pmDivisions = [];
        $pmWorkOrders = collect();
        $pmTotalPending = 0;
        $pmTotalCompleted = 0;

        $activeSchedule = PMSchedule::active()->first();
        if ($activeSchedule) {
            $pmService = app(GeneratePMScheduleService::class);
            $queueStatus = $pmService->getQueueStatus($activeSchedule);
            $pmFocusDivision = $queueStatus['focus_division'] ?? null;
            $pmDivisions = $queueStatus['divisions'] ?? [];
            $pmTotalPending = $queueStatus['total_pending'] ?? 0;
            $pmTotalCompleted = $queueStatus['total_done'] ?? 0;

            // Get PM work orders for IT's branch
            $pmWorkOrders = RequestModel::with(['user', 'maintenanceRequest'])
                ->where('type', 'Preventive Maintenance')
                ->where('is_auto_generated', true)
                ->whereIn('status', ['Scheduled', 'Ongoing', 'Awaiting Signature'])
                ->when($user->branch, fn ($q) => $q->where('branch', $user->branch))
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        }

        // Warranty alerts — handle missing column gracefully
        try {
            $itWarrantyQuery = InventoryAsset::query()
                ->when($user->region, fn ($q) => $q->where('region', $user->region))
                ->when($user->branch, fn ($q) => $q->where('branch', $user->branch));
            $warrantyExpiring = (clone $itWarrantyQuery)->whereNotNull('warranty_expiration')
                ->where('warranty_expiration', '>=', now())
                ->where('warranty_expiration', '<=', now()->addDays(30))->limit(5)->get();
            $warrantyExpired = (clone $itWarrantyQuery)->whereNotNull('warranty_expiration')
                ->where('warranty_expiration', '<', now())->limit(5)->get();
        } catch (\Exception $e) {
            $warrantyExpiring = collect();
            $warrantyExpired = collect();
        }

        return view('dashboard.it', compact(
            'requests', 'stats', 'needsCompletion',
            'pmFocusDivision', 'pmDivisions', 'pmWorkOrders',
            'pmTotalPending', 'pmTotalCompleted',
            'warrantyExpiring', 'warrantyExpired'
        ));
    }
}
