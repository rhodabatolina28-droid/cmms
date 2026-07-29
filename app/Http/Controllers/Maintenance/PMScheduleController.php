<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;

use App\Models\PMSchedule;
use App\Services\GeneratePMScheduleService;
use App\Http\Requests\StorePMScheduleRequest;
use App\Http\Requests\UpdatePMScheduleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Actions\PMSchedule\ShowPMScheduleIndexAction;
use App\Actions\PMSchedule\ShowPMScheduleAction;
use App\Actions\PMSchedule\StorePMScheduleAction;
use App\Actions\PMSchedule\GeneratePMScheduleAction;
use App\Actions\PMSchedule\ForceRunPMAction;
use App\Actions\PMSchedule\RepairBrokenPMRecordsAction;
use App\Actions\PMSchedule\GetOrdersDataAction;
use App\Actions\PMSchedule\AdvancePMCycleAction;
use App\Actions\PMSchedule\ManagePMCycleAction;

class PMScheduleController extends Controller
{
    private GeneratePMScheduleService $pmService;

    public function __construct(GeneratePMScheduleService $pmService)
    {
        $this->pmService = $pmService;
    }

    public function index()
    {
        return (new ShowPMScheduleIndexAction)->execute();
    }

    public function create()
    {
        return view('pm-schedules.create');
    }

    public function store(StorePMScheduleRequest $request)
    {
        return (new StorePMScheduleAction)->execute($request->validated());
    }

    public function show(PMSchedule $pmSchedule)
    {
        return (new ShowPMScheduleAction)->execute($pmSchedule);
    }

    public function edit(PMSchedule $pmSchedule)
    {
        return view('pm-schedules.edit', compact('pmSchedule'));
    }

    public function update(UpdatePMScheduleRequest $request, PMSchedule $pmSchedule)
    {
        $validated = $request->validated();

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
        return (new GeneratePMScheduleAction)->execute($pmSchedule, $this->pmService);
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

        return view('pm-schedules.orders');
    }

    /**
     * AJAX endpoint — returns paginated work orders with status filter.
     */
    public function ordersData(Request $request)
    {
        return (new GetOrdersDataAction)->execute($request);
    }

    /**
     * One-click repair: fix all broken auto-generated PM records.
     */
    public function repairBrokenRecords()
    {
        return (new RepairBrokenPMRecordsAction)->execute();
    }

    /**
     * Generate PM for the next division in queue (batch generation).
     * Includes Anti-Spam check - will not run if a cycle is already active.
     */
    public function forceRun()
    {
        return (new ForceRunPMAction)->execute($this->pmService);
    }

    /**
     * Pause the current PM cycle - halts auto-advance.
     * IT can still conduct PMs while paused.
     */
    public function pauseCycle(PMSchedule $schedule)
    {
        return (new ManagePMCycleAction)->execute('pause', $schedule);
    }

    /**
     * Resume the current PM cycle - continues auto-advance.
     */
    public function resumeCycle(PMSchedule $schedule)
    {
        return (new ManagePMCycleAction)->execute('resume', $schedule);
    }

    /**
     * Stop the current PM cycle entirely.
     */
    public function stopCycle(PMSchedule $schedule)
    {
        return (new ManagePMCycleAction)->execute('stop', $schedule);
    }

    /**
     * Check and auto-advance to next division if current division is complete.
     */
    public function advanceCycle()
    {
        return (new AdvancePMCycleAction)->execute($this->pmService);
    }
}
