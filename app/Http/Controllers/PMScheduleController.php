<?php

namespace App\Http\Controllers;

use App\Models\PMSchedule;
use App\Models\PMScheduleHistory;
use App\Models\AuditLog;
use App\Services\GeneratePMScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PMScheduleController extends Controller
{
    private GeneratePMScheduleService $pmService;

    public function __construct(GeneratePMScheduleService $pmService)
    {
        $this->pmService = $pmService;
    }

    public function index()
    {
        $schedules = PMSchedule::with('creator')
            ->orderByDesc('created_at')
            ->paginate(20);
        
        // Calculate progress for each schedule
        $schedules->each(function ($schedule) {
            $yearMonth = now()->format('Y-m');
            $totalUsers = \App\Models\InventoryAsset::whereIn('status', ['Active', 'Spare'])
                ->whereNotNull('assigned_to_user')
                ->when($schedule->division_filter, function ($q) use ($schedule) {
                    $divisionMappings = [
                        'RID' => ['RESEARCH AND INFORMATION', 'RID'],
                        'AD' => ['ADMINISTRATIVE', 'AD'],
                        'FMD' => ['FINANCIAL AND MANAGEMENT', 'FMD'],
                        'COA' => ['COMMISSION ON AUDIT', 'COA'],
                        'CMD' => ['CONCILIATION AND MEDIATION', 'CMD'],
                        'VAD' => ['VOLUNTARY ARBITRATION', 'VAD'],
                        'WRED' => ['WORKPLACE RELATIONS', 'WRED'],
                        'OED' => ['EXECUTIVE DIRECTOR', 'OED'],
                    ];
                    $keywords = $divisionMappings[$schedule->division_filter] ?? [$schedule->division_filter];
                    $q->where(function ($q2) use ($keywords) {
                        foreach ($keywords as $kw) {
                            $q2->orWhere('department', 'LIKE', "%{$kw}%")
                               ->orWhere('office', 'LIKE', "%{$kw}%");
                        }
                    });
                })
                ->distinct('assigned_to_user')
                ->count('assigned_to_user');
            
            $completedUsers = \App\Models\Request::where('pm_schedule_id', $schedule->id)
                ->where('is_auto_generated', true)
                ->where('status', 'Completed')
                ->distinct('user_id')
                ->count('user_id');
            
            $schedule->total_users = $totalUsers;
            $schedule->completed_users = $completedUsers;
            $schedule->progress_percentage = $totalUsers > 0 ? round(($completedUsers / $totalUsers) * 100) : 0;
        });

        $user = Auth::user();
        $yearMonth = now()->format('Y-m');

        $generatedQuery = \App\Models\Request::where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true)
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$yearMonth]);

        $completedQuery = \App\Models\Request::where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true)
            ->where('status', 'Completed')
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$yearMonth]);

        if ($user && $user->branch) {
            $generatedQuery->where('branch', $user->branch);
            $completedQuery->where('branch', $user->branch);
        }

        $generatedThisCycle = $generatedQuery->count();
        $completedThisCycle = $completedQuery->count();

        $pendingQuery = \App\Models\Request::where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true)
            ->whereIn('status', ['Scheduled', 'Ongoing', 'Awaiting Signature']);

        if ($user && $user->branch) {
            $pendingQuery->where('branch', $user->branch);
        }

        $pendingCompletion = $pendingQuery->count();

        $workOrderQuery = \App\Models\Request::with(['user', 'assignedTo', 'maintenanceRequest'])
            ->where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true)
            ->whereIn('status', ['Scheduled', 'Ongoing', 'Awaiting Signature']); // Only show active orders

        if ($user && $user->branch) {
            $workOrderQuery->where('branch', $user->branch);
        }

        $workOrders = $workOrderQuery->limit(15)->get();
        
        // Get oldest asset date for each work order's end user
        $userIds = $workOrders->pluck('user_id')->unique()->filter();
        $oldestAssets = \App\Models\InventoryAsset::whereIn('assigned_to_user', $userIds)
            ->whereIn('status', ['Active', 'Spare'])
            ->get()
            ->groupBy('assigned_to_user')
            ->map(function ($assets) {
                $oldest = $assets->min('date_acquired');
                return $oldest;
            });

        foreach ($workOrders as $order) {
            $order->schedule_date = $order->maintenanceRequest?->maintenance_date ?? $order->created_at->toDateString();
            $order->oldest_asset_date = $oldestAssets[$order->user_id] ?? null;
        }
        
        // Sort by oldest asset date (oldest first)
        $workOrders = $workOrders->sortBy(function ($order) {
            return $order->oldest_asset_date ? \Carbon\Carbon::parse($order->oldest_asset_date)->timestamp : PHP_INT_MAX;
        })->take(10)->values();
        
        // Dynamic priority: assign based on position in sorted list (no hardcoded months)
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

        // Get current focus division from the first active schedule
        $focusDivision = null;
        $activeSchedule = PMSchedule::active()->first();
        if ($activeSchedule) {
            $queueStatus = $this->pmService->getQueueStatus($activeSchedule);
            $focusDivision = $queueStatus['focus_division'] ?? null;
        }

        return view('pm-schedules.index', compact('schedules', 'generatedThisCycle', 'completedThisCycle', 'pendingCompletion', 'workOrders', 'focusDivision'));
    }

    public function create()
    {
        return view('pm-schedules.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'schedule_name'   => 'required|string|max:255|unique:pm_schedules',
            'division_filter' => 'nullable|string|max:50',
            'frequency'       => 'required|in:Monthly,Quarterly,Semi-annual,Annual',
        ]);

        $schedule = PMSchedule::create([
            'schedule_name'      => $validated['schedule_name'],
            'asset_categories'   => [],
            'division_filter'    => $validated['division_filter'] ?? null,
            'frequency'          => $validated['frequency'],
            'next_scheduled_date' => now()->toDateString(),
            'created_by'         => Auth::id(),
        ]);

        return redirect()->route('pm-schedules.show', $schedule->id)
            ->with('success', 'PM Schedule created successfully.');
    }

    public function show(PMSchedule $pmSchedule)
    {
        $pmSchedule->load(['creator', 'history']);
        return view('pm-schedules.show', compact('pmSchedule'));
    }

    public function edit(PMSchedule $pmSchedule)
    {
        return view('pm-schedules.edit', compact('pmSchedule'));
    }

    public function update(Request $request, PMSchedule $pmSchedule)
    {
        $validated = $request->validate([
            'schedule_name' => 'required|string|max:255|unique:pm_schedules,schedule_name,' . $pmSchedule->id,
            'division_filter' => 'nullable|string|max:50',
            'frequency' => 'required|in:Monthly,Quarterly,Semi-annual,Annual',
        ]);

        $pmSchedule->update([
            'schedule_name' => $validated['schedule_name'],
            'division_filter' => $validated['division_filter'] ?? null,
            'frequency' => $validated['frequency'],
            'is_active' => $validated['is_active'] ?? $pmSchedule->is_active,
        ]);

        return redirect()->route('pm-schedules.show', $pmSchedule->id)
            ->with('success', 'PM Schedule updated successfully.');
    }

    public function destroy(PMSchedule $pmSchedule)
    {
        $pmSchedule->delete();
        return redirect()->route('pm-schedules.index')
            ->with('success', 'PM Schedule deleted.');
    }

    public function destroyAll(Request $request)
    {
        if (!Auth::user() || Auth::user()->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }
        
        $deleted = PMSchedule::whereNotNull('id')->delete();
        
        return response()->json([
            'success' => true,
            'message' => "Deleted {$deleted} schedule(s) successfully.",
        ]);
    }

    public function toggleStatus(PMSchedule $pmSchedule)
    {
        if (!Auth::user() || Auth::user()->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $pmSchedule->update([
            'is_active' => !$pmSchedule->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => $pmSchedule->is_active ? 'Schedule activated.' : 'Schedule paused.',
        ]);
    }

    public function preview(PMSchedule $pmSchedule)
    {
        $preview = $this->pmService->preview($pmSchedule);
        return response()->json($preview);
    }

    public function generate(PMSchedule $pmSchedule)
    {
        if (!$pmSchedule->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot generate: schedule is inactive.',
            ], 422);
        }

        try {
            $created = $this->pmService->generate($pmSchedule);
            $count = count($created);

            return response()->json([
                'success' => true,
                'message' => "Generated {$count} PM request(s) successfully.",
                'request_numbers' => $created,
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function history(PMSchedule $pmSchedule)
    {
        $history = $pmSchedule->history()->orderByDesc('generated_at')->get();
        return view('pm-schedules.history', compact('pmSchedule', 'history'));
    }

    public function queueStatus(PMSchedule $pmSchedule)
    {
        $status = $this->pmService->getQueueStatus($pmSchedule);
        return response()->json($status);
    }

    public function orders()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'super_admin') {
            abort(403, 'Only Super Admins can view PM Work Orders.');
        }

        $status = request('status', 'all');

        $query = \App\Models\Request::with(['user', 'assignedTo', 'pmSchedule', 'maintenanceRequest'])
            ->where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true);

        // Super Admin sees all within branch
        if ($user->branch) {
            $query->where('branch', $user->branch);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('pm-schedules.orders', compact('orders', 'status'));
    }

    /**
     * One-click repair: fix all broken auto-generated PM records.
     * - Creates missing preventive_maintenance rows for requests with null detail_id
     * - Links them properly via detail_id
     * - Backfills branch and division_admin_review_status
     * Safe to run multiple times.
     */
    public function repairBrokenRecords()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }
        try {
            $fixed = 0;

            // Step 1: Soft-delete orphaned PM rows with empty/null form_no
            // (these are leftover failed attempts that block the unique constraint)
            \App\Models\PreventiveMaintenance::where(function ($q) {
                    $q->whereNull('form_no')->orWhere('form_no', '');
                })
                ->each(function ($pm) {
                    $linked = \App\Models\Request::where('detail_id', $pm->id)->exists();
                    if (!$linked) {
                        $pm->delete(); // soft delete
                    }
                });

            // Step 2: Fix auto-generated PM requests that have null detail_id
            $broken = \App\Models\Request::where('type', 'Preventive Maintenance')
                ->where('is_auto_generated', true)
                ->whereNull('detail_id')
                ->get();

            foreach ($broken as $req) {
                // Try to find an existing PM by matching form_no = request_number
                $pm = \App\Models\PreventiveMaintenance::withTrashed()
                    ->where('form_no', $req->request_number)
                    ->first();

                if ($pm) {
                    if ($pm->trashed()) {
                        $pm->restore();
                    }
                } else {
                    // Create a new PM form for this request
                    $pm = \App\Models\PreventiveMaintenance::create([
                        'form_no'           => $req->request_number,
                        'end_user_name'     => $req->requestor_name ?: 'Auto-generated',
                        'end_user_division' => $req->office ?: '',
                        'maintenance_date'  => $req->created_at?->toDateString() ?? now()->toDateString(),
                    ]);
                }

                $req->update(['detail_id' => $pm->id]);
                $fixed++;
            }

            // Step 3: Backfill missing branch from the user who created the request
            \App\Models\Request::where('type', 'Preventive Maintenance')
                ->where('is_auto_generated', true)
                ->whereNull('branch')
                ->with('user')
                ->get()
                ->each(function ($req) {
                    if ($req->user && $req->user->branch) {
                        $req->update(['branch' => $req->user->branch]);
                    }
                });

            // Step 4: Mark all auto-generated PMs as Approved so super_admin can see them
            \App\Models\Request::where('type', 'Preventive Maintenance')
                ->where('is_auto_generated', true)
                ->where(function ($q) {
                    $q->whereNull('division_admin_review_status')
                      ->orWhere('division_admin_review_status', '');
                })
                ->update(['division_admin_review_status' => 'Approved']);

            return response()->json([
                'success' => true,
                'message' => "Repair complete! Fixed {$fixed} broken PM record(s). All auto-generated PMs are now fully accessible.",
                'fixed'   => $fixed,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Repair failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate PM for the next division in queue (batch generation).
     * Includes Anti-Spam check - will not run if a cycle is already active.
     */
    public function forceRun()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }
        
        try {
            // Get active schedules
            $schedules = PMSchedule::active()->get();

            if ($schedules->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No active schedules found. Please create a schedule first.',
                    'total_generated' => 0,
                ]);
            }

            $totalGenerated = 0;
            $results = [];

            foreach ($schedules as $schedule) {
                // Anti-Spam: Check if there's already an IN PROGRESS cycle
                if ($schedule->current_focus_division) {
                    $results[] = [
                        'schedule_name' => $schedule->schedule_name,
                        'message' => "Skipped - already processing {$schedule->current_focus_division}",
                        'generated' => 0,
                    ];
                    continue;
                }

                $created = $this->pmService->generate($schedule);
                $count = count($created);
                $totalGenerated += $count;
                $results[] = [
                    'schedule_name' => $schedule->schedule_name,
                    'generated' => $count,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => $totalGenerated > 0 
                    ? "Generated {$totalGenerated} PM request(s) for the next division."
                    : "No eligible users found. All users may already have PM tickets this cycle.",
                'total_generated' => $totalGenerated,
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pause the current PM cycle - halts auto-advance.
     * IT can still conduct PMs while paused.
     */
    public function pauseCycle(PMSchedule $schedule)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if (!$schedule->is_active) {
            return response()->json(['success' => false, 'message' => 'Schedule is inactive.'], 422);
        }

        $schedule->update([
            'is_paused' => true,
            'paused_at' => now(),
        ]);

        AuditLog::log("Paused PM Cycle", "PM Schedule", 
            "Paused PM cycle for {$schedule->schedule_name}", "System");

        return response()->json(['success' => true, 'message' => 'PM cycle paused. IT can still conduct PMs.']);
    }

    /**
     * Resume the current PM cycle - continues auto-advance.
     */
    public function resumeCycle(PMSchedule $schedule)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $schedule->update([
            'is_paused' => false,
            'paused_at' => null,
        ]);

        AuditLog::log("Resumed PM Cycle", "PM Schedule",
            "Resumed PM cycle for {$schedule->schedule_name}", "System");

        return response()->json(['success' => true, 'message' => 'PM cycle resumed.']);
    }

    /**
     * Stop the current PM cycle entirely.
     */
    public function stopCycle(PMSchedule $schedule)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $schedule->update([
            'is_active' => false,
            'cycle_stopped_at' => now(),
            'current_focus_division' => null,
        ]);

        AuditLog::log("Stopped PM Cycle", "PM Schedule",
            "Stopped PM cycle for {$schedule->schedule_name}", "System");

        return response()->json(['success' => true, 'message' => 'PM cycle stopped. Create a new schedule to start again.']);
    }

    /**
     * Check and auto-advance to next division if current division is complete.
     */
    public function advanceCycle()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        try {
            $nextDivision = $this->pmService->checkAndAdvance();
            
            if ($nextDivision) {
                return response()->json([
                    'success' => true,
                    'message' => "Advanced to next division: {$nextDivision}",
                    'next_division' => $nextDivision,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'No advance needed. Current division still in progress or all divisions complete.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Advance check failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}

