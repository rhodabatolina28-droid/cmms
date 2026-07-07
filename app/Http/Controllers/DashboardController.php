<?php

namespace App\Http\Controllers;

use App\Models\Request as RequestModel;
use App\Models\User;
use App\Models\PMSchedule;
use App\Services\GeneratePMScheduleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function adminDashboard()
    {
        $user = Auth::user();

        // Role-based request visibility
        if ($user->role === 'user' || $user->role === 'admin' || $user->role === 'supply_officer') {
            // Regular users and Admin/Supply Officer: ICT only
            $requestsQuery = RequestModel::where('type', 'ICT')->whereHas('user', function($q) use ($user) {
                if ($user->branch) {
                    $q->where('branch', $user->branch);
                }
                if ($user->office) {
                    $q->where('office', $user->office);
                }
                // Department filter removed - office (division) is sufficient
            });
        } elseif ($user->role === 'it') {
            // IT: ICT + PM assigned to them
            $requestsQuery = RequestModel::where(function ($q) use ($user) {
                $q->where('type', 'ICT')
                  ->orWhere(function ($sub) use ($user) {
                      $sub->where('type', 'Preventive Maintenance')
                          ->where('assigned_to', $user->id);
                  });
            })->whereHas('user', function($q) use ($user) {
                if ($user->branch) {
                    $q->where('branch', $user->branch);
                }
                if ($user->office) {
                    $q->where('office', $user->office);
                }
            });
        } else {
            // Super Admin: All requests (ICT + PM)
            $requestsQuery = RequestModel::whereHas('user', function($q) use ($user) {
                if ($user->branch) {
                    $q->where('branch', $user->branch);
                }
                if ($user->office) {
                    $q->where('office', $user->office);
                }
            });
        }
        
        $requests = $requestsQuery->with('user')->orderBy('created_at', 'desc')->limit(10)->get();

        // Fetch scoped users - division level
        $usersQuery = User::query();
        if ($user->branch) {
            $usersQuery->where('branch', $user->branch);
        }
        if ($user->office) {
            $usersQuery->where('office', $user->office);
        }
        $users = $usersQuery->limit(10)->get();

        // Fetch scoped assets - division level
        $assetsQuery = \App\Models\InventoryAsset::whereHas('assignedUser', function($q) use ($user) {
            if ($user->branch) {
                $q->where('branch', $user->branch);
            }
            if ($user->office) {
                $q->where('office', $user->office);
            }
        });
        $assets = $assetsQuery->with('assignedUser')->limit(10)->get();

        // Stats calculation — consolidated into single query
        $statsRow = (clone $requestsQuery)
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending", [RequestModel::STATUS_PENDING])
            ->selectRaw("SUM(CASE WHEN status = 'Ongoing' THEN 1 ELSE 0 END) as ongoing")
            ->selectRaw("SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN type = 'ICT' AND assigned_to IS NULL AND status IN (?, ?) THEN 1 ELSE 0 END) as unassigned_jobs", [RequestModel::STATUS_PENDING, RequestModel::STATUS_ONGOING])
            ->first();

        $stats = [
            'total' => $statsRow->total ?? 0,
            'pending' => $statsRow->pending ?? 0,
            'ongoing' => $statsRow->ongoing ?? 0,
            'completed' => $statsRow->completed ?? 0,
            'unassigned_jobs' => $statsRow->unassigned_jobs ?? 0,
        ];

        $unassignedRequests = (clone $requestsQuery)
            ->with(['user', 'assignedTo'])
            ->where('type', 'ICT')
            ->whereNull('assigned_to')
            ->whereIn('status', [RequestModel::STATUS_PENDING, RequestModel::STATUS_ONGOING])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Supply logic for authorized supply admins
        $supplyStats = [];
        $pendingRequisitions = collect();
        if ($user->canProcessSupply()) {
            // Supply admin sees assets office-wide (entire branch), not just their division
            $assetsQuerySupply = \App\Models\InventoryAsset::query();
            if ($user->region) {
                $assetsQuerySupply->where('region', $user->region);
            }
            if ($user->branch) {
                $assetsQuerySupply->where('branch', $user->branch);
            }
            // Supply admin manages entire branch - no division filter

            $reqQuery = \App\Models\Requisition::query();
            \App\Support\RequestAuthorization::scopeRequisitionsForSupplyOfficer($user, $reqQuery);

            $assetStatsRow = (clone $assetsQuerySupply)
                ->selectRaw("COUNT(*) as total_assets")
                ->selectRaw("SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active")
                ->selectRaw("SUM(CASE WHEN status = 'For Repair' THEN 1 ELSE 0 END) as under_repair")
                ->first();

            $reqStatusRow = (clone $reqQuery)
                ->selectRaw("SUM(CASE WHEN status = '" . \App\Models\Requisition::STATUS_PENDING . "' THEN 1 ELSE 0 END) as pending_reqs")
                ->selectRaw("SUM(CASE WHEN status = '" . \App\Models\Requisition::STATUS_APPROVED . "' THEN 1 ELSE 0 END) as approved_reqs")
                ->first();

            $supplyStats = [
                'total_assets' => $assetStatsRow->total_assets ?? 0,
                'active' => $assetStatsRow->active ?? 0,
                'under_repair' => $assetStatsRow->under_repair ?? 0,
                'pending_reqs' => $reqStatusRow->pending_reqs ?? 0,
                'approved_reqs' => $reqStatusRow->approved_reqs ?? 0,
            ];

            $pendingRequisitions = (clone $reqQuery)
                ->where('status', \App\Models\Requisition::STATUS_PENDING)
                ->with(['ticket', 'requester'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
        }

        // Warranty alerts — handle missing column gracefully
        try {
            $warrantyQuery = \App\Models\InventoryAsset::whereHas('assignedUser', function($q) use ($user) {
                if ($user->branch) $q->where('branch', $user->branch);
                if ($user->office) $q->where('office', $user->office);
            });
            $warrantyExpiring = (clone $warrantyQuery)->whereNotNull('warranty_expiration')
                ->where('warranty_expiration', '>=', now())
                ->where('warranty_expiration', '<=', now()->addDays(30))->limit(5)->get();
            $warrantyExpired = (clone $warrantyQuery)->whereNotNull('warranty_expiration')
                ->where('warranty_expiration', '<', now())->limit(5)->get();
        } catch (\Exception $e) {
            $warrantyExpiring = collect();
            $warrantyExpired = collect();
        }

        return view('dashboard.admin', compact('requests', 'stats', 'users', 'assets', 'unassignedRequests', 'supplyStats', 'pendingRequisitions', 'warrantyExpiring', 'warrantyExpired'));
    }

    public function superAdminDashboard()
    {
        $user = Auth::user();
        
        // Super Admin is office-scoped (branch level only)
        // They should see ALL requests/users in their branch, not filtered by division
        $approvedRequests = RequestModel::query()
            ->where('division_admin_review_status', 'Approved')
            ->where('status', '!=', RequestModel::STATUS_SCHEDULED)
            ->whereHas('user', function ($query) use ($user) {
                if ($user->branch) {
                    $query->where('branch', $user->branch);
                }
                // DO NOT filter by office/division - Super Admin manages entire branch
            });

        $recentRequests = (clone $approvedRequests)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $statsRow = (clone $approvedRequests)
            ->where('status', '!=', RequestModel::STATUS_SCHEDULED)
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending", [RequestModel::STATUS_PENDING])
            ->selectRaw("SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status = 'Ongoing' THEN 1 ELSE 0 END) as ongoing")
            ->selectRaw("SUM(CASE WHEN type = 'ICT' THEN 1 ELSE 0 END) as ict")
            ->selectRaw("SUM(CASE WHEN type = 'Preventive Maintenance' THEN 1 ELSE 0 END) as maintenance")
            ->first();

        $departmentStats = (clone $approvedRequests)
            ->join('users', 'requests.user_id', '=', 'users.id')
            ->select('users.branch', \Illuminate\Support\Facades\DB::raw('count(requests.id) as total'))
            ->groupBy('users.branch')
            ->pluck('total', 'branch')
            ->toArray();

        $stats = [
            'total'       => $statsRow->total ?? 0,
            'pending'     => $statsRow->pending ?? 0,
            'completed'   => $statsRow->completed ?? 0,
            'ongoing'     => $statsRow->ongoing ?? 0,
            'ict'         => $statsRow->ict ?? 0,
            'maintenance' => $statsRow->maintenance ?? 0,
            'total_users' => User::query()
                ->when($user->region, fn ($query) => $query->where('region', $user->region))
                ->when($user->branch, fn ($query) => $query->where('branch', $user->branch))
                ->count(),
        ];

        // Warranty alerts — handle missing column gracefully
        try {
            $warrantyQuery = \App\Models\InventoryAsset::query()
                ->when($user->region, fn ($q) => $q->where('region', $user->region))
                ->when($user->branch, fn ($q) => $q->where('branch', $user->branch));
            $warrantyExpiring = (clone $warrantyQuery)->whereNotNull('warranty_expiration')
                ->where('warranty_expiration', '>=', now())
                ->where('warranty_expiration', '<=', now()->addDays(30))->limit(5)->get();
            $warrantyExpired = (clone $warrantyQuery)->whereNotNull('warranty_expiration')
                ->where('warranty_expiration', '<', now())->limit(5)->get();
        } catch (\Exception $e) {
            $warrantyExpiring = collect();
            $warrantyExpired = collect();
        }

        return view('dashboard.super-admin', compact('recentRequests', 'stats', 'departmentStats', 'warrantyExpiring', 'warrantyExpired'));
    }

    public function userDashboard()
    {
        $user = Auth::user();
        
        // Users see ICT + PM requests in dashboard (PM needs CSM survey completion)
        $requests = RequestModel::where('user_id', $user->id)
            ->whereIn('type', ['ICT', 'Preventive Maintenance'])
            ->where('status', '!=', RequestModel::STATUS_SCHEDULED)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Optimized: Combined stats query
        $statsRow = RequestModel::where('user_id', $user->id)
            ->where('status', '!=', RequestModel::STATUS_SCHEDULED)
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending", [RequestModel::STATUS_PENDING])
            ->selectRaw("SUM(CASE WHEN status = 'Ongoing' THEN 1 ELSE 0 END) as ongoing")
            ->selectRaw("SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed")
            ->first();

        // Optimized: Single query for asset count
        $assetCount = \App\Models\InventoryAsset::where('assigned_to_user', $user->id)->count();

        $stats = [
            'total' => $statsRow->total ?? 0,
            'pending' => $statsRow->pending ?? 0,
            'ongoing' => $statsRow->ongoing ?? 0,
            'completed' => $statsRow->completed ?? 0,
            'assets' => $assetCount,
        ];

        $hasAssignedAssets = $assetCount > 0;

        // Optimized: Skip warranty alerts for regular users to speed up dashboard load
        // Warranty alerts are more relevant for admin/IT users
        $warrantyExpiring = collect();
        $warrantyExpired = collect();

        return view('dashboard.user', compact('requests', 'stats', 'hasAssignedAssets', 'warrantyExpiring', 'warrantyExpired'));
    }

    public function itDashboard()
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
            $itWarrantyQuery = \App\Models\InventoryAsset::query()
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
