<?php

namespace App\Http\Controllers;

use App\Models\PMSchedule;
use App\Models\PMScheduleHistory;
use App\Models\PMCycle;
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
            $completedDivisions = 0;

            if ($schedule->current_cycle_id) {
                // Total = distinct divisions that have ANY auto-generated PM request in this cycle
                $activeCycle = \App\Models\PMCycle::find($schedule->current_cycle_id);
                $cycleStart  = $activeCycle?->started_at ?? now();

                $totalDivisions = \App\Models\Request::where('pm_schedule_id', $schedule->id)
                    ->where('is_auto_generated', true)
                    ->where('created_at', '>=', $cycleStart)
                    ->distinct('office')
                    ->count('office');

                // Completed = divisions recorded in pm_division_schedules for this cycle
                $completedDivisions = \App\Models\PMDivisionSchedule::where('pm_cycle_id', $schedule->current_cycle_id)
                    ->whereNotNull('last_completed_at')
                    ->count();

                // If no requests yet, fall back to counting active-asset divisions
                if ($totalDivisions === 0) {
                    $totalDivisions = \App\Models\InventoryAsset::where('status', 'Active')
                        ->whereNotNull('assigned_to_user')
                        ->distinct('office')
                        ->count('office');
                }
            } else {
                // Idle — count all active-asset divisions as baseline
                $totalDivisions = \App\Models\InventoryAsset::where('status', 'Active')
                    ->whereNotNull('assigned_to_user')
                    ->distinct('office')
                    ->count('office');
            }

            // Fallback
            if ($totalDivisions === 0) {
                $totalDivisions = 1;
            }

            $schedule->total_divisions     = $totalDivisions;
            $schedule->completed_divisions = min($completedDivisions, $totalDivisions);

            if (is_null($schedule->current_focus_division) && is_null($schedule->current_cycle_id) && $completedDivisions > 0) {
                // Cycle just finished
                $schedule->progress_percentage = 100;
            } elseif (is_null($schedule->current_focus_division) && $schedule->current_cycle_id === null) {
                $schedule->progress_percentage = 0; // idle/not started
            } else {
                $schedule->progress_percentage = $totalDivisions > 0
                    ? round(($completedDivisions / $totalDivisions) * 100)
                    : 0;
            }
        });

        $user = \Illuminate\Support\Facades\Auth::user();
        // Top Dashboard Stats
        $statTotalSchedules = \App\Models\PMSchedule::count();
        $statActiveSchedules = \App\Models\PMSchedule::where('is_active', true)->count();
        
        $statActiveWorkOrders = \App\Models\Request::where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true)
            ->whereIn('status', ['Scheduled', 'Ongoing', 'Awaiting Signature'])
            ->count();
            
        $statCompletedThisMonth = \App\Models\Request::where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true)
            ->where('status', 'Completed')
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [now()->format('Y-m')])
            ->count();

        $workOrderQuery = \App\Models\Request::with(['user', 'assignedTo', 'maintenanceRequest'])
            ->where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true)
            ->whereIn('status', ['Scheduled', 'Ongoing', 'Awaiting Signature']);

        if ($user && $user->branch) {
            $workOrderQuery->where('branch', $user->branch);
        }

        // Index page shows CURRENT FOCUS DIVISION only (dashboard widget).
        // For full paginated list → /pm-schedules/orders
        $activeSchedule = PMSchedule::active()->first();
        $currentFocus = $activeSchedule?->current_focus_division;
        if ($currentFocus) {
            $workOrderQuery->where('office', $currentFocus);
        }

        // Total count for "View All" badge
        $totalActiveWorkOrderCount = (clone $workOrderQuery)->count();

        $workOrders = $workOrderQuery->limit(20)->get();

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
        })->values();

        // Dynamic priority
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

        // Get current focus division directly from DB (most reliable source of truth)
        $focusDivision = null;
        if ($activeSchedule) {
            $focusDivision = $activeSchedule->current_focus_division;
        }
        return view('pm-schedules.index', compact(
            'schedules', 'workOrders', 'focusDivision', 'activeSchedule',
            'statTotalSchedules', 'statActiveSchedules', 'statActiveWorkOrders', 'statCompletedThisMonth',
            'totalActiveWorkOrderCount'
        ));
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

        // Enforce one active PM schedule per branch.
        // Government CMMS only needs one standardized schedule per branch/office.
        // Prevent accidental duplicate active schedules which would cause
        // the cron to silently skip the second one (active()->first() issue).
        $actorBranch = Auth::user()?->branch;
        $existingActive = PMSchedule::active()
            ->when($actorBranch, function ($q) use ($actorBranch) {
                // Check schedules created by users in the same branch
                $q->whereHas('creator', fn($u) => $u->where('branch', $actorBranch));
            })
            ->first();

        if ($existingActive) {
            return redirect()->back()
                ->withInput()
                ->with('error', "An active PM Schedule already exists for your branch: \"{$existingActive->schedule_name}\". Please deactivate or delete it before creating a new one.");
        }

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
        
        $pmDivisions = [];
        $pmTotalPending = 0;
        $pmTotalCompleted = 0;

        $focusDivision = $pmSchedule->current_focus_division;
        
        // Calculate PM Divisions progress
        $assets = \App\Models\InventoryAsset::where('status', 'Active')
            ->whereNotNull('assigned_to_user')
            ->get();
        
        $usersByDivision = [];
        foreach ($assets as $asset) {
            $div = $asset->office ?? $asset->department ?? 'Unassigned';
            $usersByDivision[$div][$asset->assigned_to_user] = true;
        }

        $completedDivisions = collect();

        // If a cycle is currently active, show its division progress
        // If idle (no active cycle), show the last completed cycle's results
        $displayCycle = $pmSchedule->current_cycle_id
            ? \App\Models\PMCycle::find($pmSchedule->current_cycle_id)
            : \App\Models\PMCycle::where('pm_schedule_id', $pmSchedule->id)
                ->whereNotNull('completed_at')
                ->latest('completed_at')
                ->first();

        if ($displayCycle) {
            $completedDivisions = \App\Models\PMDivisionSchedule::where('pm_cycle_id', $displayCycle->id)
                ->whereNotNull('last_completed_at')
                ->get()
                ->keyBy('division_name');
        }
            
        $completedUsersQuery = \App\Models\Request::where('pm_schedule_id', $pmSchedule->id)
            ->where('is_auto_generated', true)
            ->where('status', 'Completed');

        if ($displayCycle) {
            $completedUsersQuery->where('created_at', '>=', $displayCycle->started_at);
        }

        $completedUsersThisWave = $completedUsersQuery->get()
            ->groupBy('office')
            ->map(fn($reqs) => $reqs->unique('user_id')->count());

        foreach ($usersByDivision as $div => $users) {
            $total = count($users);
            $isCompleted = $completedDivisions->has($div);
            
            if ($isCompleted) {
                $done = $total;
                $nextScheduleDate = $completedDivisions[$div]->next_scheduled_at ? \Carbon\Carbon::parse($completedDivisions[$div]->next_scheduled_at)->format('M d, Y') : null;
            } else {
                $done = $completedUsersThisWave[$div] ?? 0;
                $nextScheduleDate = null;
            }
            
            $done = min($done, $total);
            
            $pmDivisions[$div] = [
                'total' => $total,
                'done'  => $done,
                'next_date' => $nextScheduleDate,
                'status' => $isCompleted ? 'Completed' : ($focusDivision === $div ? 'In Progress' : 'Pending')
            ];
            
            if ($isCompleted) {
                $pmTotalCompleted++;
            } else {
                $pmTotalPending++;
            }
        }

        // Include any divisions that completed this cycle but no longer have active assets
        foreach ($completedDivisions as $divName => $divRecord) {
            if (!isset($pmDivisions[$divName])) {
                $pmDivisions[$divName] = [
                    'total' => 0,
                    'done'  => 0,
                    'next_date' => $divRecord->next_scheduled_at ? \Carbon\Carbon::parse($divRecord->next_scheduled_at)->format('M d, Y') : null,
                    'status' => 'Completed'
                ];
                $pmTotalCompleted++;
            }
        }
        
        ksort($pmDivisions);
        
        return view('pm-schedules.show', compact(
            'pmSchedule', 'pmDivisions', 'pmTotalPending', 'pmTotalCompleted', 'displayCycle'
        ));
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

            // Cooldown signal — cycle just completed, not yet due for new cycle
            if (isset($created['__cooldown__'])) {
                $nextDate = \Carbon\Carbon::parse($created['__cooldown__'])->format('F d, Y');
                return response()->json([
                    'success' => false,
                    'message' => "PM cycle is on cooldown. Next generation is allowed on or after {$nextDate}.",
                    'cooldown_until' => $created['__cooldown__'],
                ], 422);
            }

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

        $query = \App\Models\Request::with(['user', 'assignedTo', 'maintenanceRequest'])
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
                
                if (isset($created['__cooldown__'])) {
                    $nextDate = \Carbon\Carbon::parse($created['__cooldown__'])->format('F d, Y');
                    $results[] = [
                        'schedule_name' => $schedule->schedule_name,
                        'message' => "Skipped - on cooldown until {$nextDate}",
                        'generated' => 0,
                    ];
                    AuditLog::log(
                        'Manual PM Generation Skipped',
                        'PM Schedule',
                        "Super admin attempted to generate PM for '{$schedule->schedule_name}' but it is on cooldown until {$nextDate}",
                        $user->branch ?? 'System'
                    );
                    continue;
                }

                $count = count($created);
                $totalGenerated += $count;
                $results[] = [
                    'schedule_name' => $schedule->schedule_name,
                    'generated' => $count,
                ];

                // Audit log — track manual generation by super admin
                $division = $schedule->fresh()->current_focus_division ?? 'N/A';
                AuditLog::log(
                    'Manual PM Generation',
                    'PM Schedule',
                    "Super admin manually generated {$count} PM work order(s) for '{$schedule->schedule_name}' — Division: {$division}",
                    $user->branch ?? 'System'
                );
            }

            return response()->json([
                'success' => true,
                'message' => $totalGenerated > 0 
                    ? "Generated {$totalGenerated} PM request(s) for the next division."
                    : (isset($results[0]) && str_contains($results[0]['message'], 'cooldown') 
                        ? $results[0]['message'] 
                        : "No eligible users found or cycle is already complete."),
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
            'is_active'              => true,  // Keep schedule active — only stop the current cycle
            'is_paused'              => false,
            'current_focus_division' => null,
            'current_cycle_id'       => null,
            'cycle_stopped_at'       => now(),
        ]);

        // Mark the current cycle as completed if one exists
        if ($schedule->current_cycle_id) {
            \App\Models\PMCycle::where('id', $schedule->current_cycle_id)
                ->update(['completed_at' => now()]);
        }

        AuditLog::log("Stopped PM Cycle", "PM Schedule",
            "Stopped PM cycle for {$schedule->schedule_name}. Schedule remains active for next generation.", "System");

        return response()->json(['success' => true, 'message' => 'PM cycle stopped. The schedule is still active — click "Generate PM" to start a new cycle.']);
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
            $schedule = PMSchedule::active()->first();
            if (!$schedule) {
                return response()->json(['success' => false, 'message' => 'No active schedule found.'], 404);
            }

            [$nextDivision, $cycleComplete] = $this->pmService->checkAndAdvance($schedule);
            
            if ($nextDivision) {
                return response()->json([
                    'success' => true,
                    'message' => "Advanced to next division: {$nextDivision}",
                    'next_division' => $nextDivision,
                ]);
            }

            if ($cycleComplete) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cycle complete! Next generation will start a new cycle.',
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

