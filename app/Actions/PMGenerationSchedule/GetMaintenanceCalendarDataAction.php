<?php

namespace App\Actions\PMGenerationSchedule;

use App\Models\Request as RequestModel;
use App\Models\PMGenerationSchedule;
use App\Models\PMDivisionSchedule;
use App\Models\PMSchedule;
use App\Models\User;
use App\Policies\RequestPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class GetMaintenanceCalendarDataAction
{
    private RequestPolicy $policy;

    public function __construct()
    {
        $this->policy = new RequestPolicy();
    }

    /**
     * Map a full division name to its short code for compact calendar display.
     */
    private function shortDivisionName(?string $divisionName): string
    {
        $map = [
            'RESEARCH AND INFORMATION DIVISION' => 'RID',
            'ADMINISTRATIVE DIVISION'           => 'AD',
            'FINANCIAL AND MANAGEMENT DIVISION' => 'FMD',
            'COMMISSION ON AUDIT'               => 'COA',
            'CONCILIATION AND MEDIATION DIVISION' => 'CMD',
            'VOLUNTARY ARBITRATION DIVISION'    => 'VAD',
            'WORKPLACE RELATIONS ENHANCEMENT DIVISION' => 'WRED',
            'OFFICE OF THE EXECUTIVE DIRECTOR'  => 'OED',
        ];

        $upper = strtoupper(trim($divisionName ?? ''));
        return $map[$upper] ?? $divisionName ?? 'N/A';
    }

    public function execute(Request $request): array
    {
        $user = Auth::user();
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $filter = $request->get('filter', 'all'); // all, pm, ict

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $events = [];

        // 0. Check if there's an active PM schedule for the user's branch
        $actorBranch = $user?->branch;
        $hasActiveSchedule = PMSchedule::active()
            ->when($actorBranch, function ($q) use ($actorBranch) {
                $q->whereHas('creator', fn($u) => $u->where('branch', $actorBranch));
            })
            ->exists();

        // 1. PM manual queue rows (future scheduled PM generations)
        if ($filter === 'all' || $filter === 'pm') {
            $queueRows = PMGenerationSchedule::with(['schedule', 'generator'])
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('scheduled_date', [$startDate->toDateString(), $endDate->toDateString()])
                      ->orWhere('status', PMGenerationSchedule::STATUS_PENDING);
                })
                ->get();

            foreach ($queueRows as $queue) {
                $events[] = [
                    'id'           => "pm-queue-{$queue->id}",
                    'event_type'   => 'pm',
                    'source'       => 'pm_generation_schedule',
                    'source_id'    => $queue->id,
                    'date'         => $queue->scheduled_date->toDateString(),
                    'title'        => $queue->schedule?->schedule_name ?? "PM Schedule #{$queue->pm_schedule_id}",
                    'status'       => $queue->calendar_status_label,
                    'display_number' => null,
                    'office'       => $queue->generated_division ?? $queue->division_filter_snapshot ?? 'N/A',
                    'assignee'     => $queue->generator?->full_name ?? 'N/A',
                    'priority'     => null,
                    'details_url'  => route('pm-schedules.show', $queue->pm_schedule_id),
                    'is_editable'  => $queue->isPending() && $user->role === 'super_admin',
                ];
            }
        }

        // 1.2 PM Schedules — show active schedules on their next_scheduled_date
        if ($filter === 'all' || $filter === 'pm') {
            $pmSchedules = PMSchedule::where('is_active', true)
                ->whereNotNull('next_scheduled_date')
                ->whereBetween('next_scheduled_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get();

            foreach ($pmSchedules as $sched) {
                // Build divisions list — ALL eligible divisions in the branch, not just generated ones
                $scheduleDivisions = [];
                $scheduleRequests = RequestModel::where('pm_schedule_id', $sched->id)
                    ->where('is_auto_generated', true)
                    ->get();

                // Get all eligible divisions from active assets (respecting branch + schedule filter)
                $assetQuery = \App\Models\InventoryAsset::where('status', 'Active')
                    ->whereNotNull('assigned_to_user');

                $creator = $sched->creator;
                if ($creator && $creator->branch) {
                    $assetQuery->where('branch', $creator->branch);
                }

                // Apply division filter if set
                if ($sched->division_filter) {
                    $divisionMappings = [
                        'RID'  => ['RESEARCH AND INFORMATION', 'RID'],
                        'AD'   => ['ADMINISTRATIVE', 'AD'],
                        'FMD'  => ['FINANCIAL AND MANAGEMENT', 'FMD'],
                        'COA'  => ['COMMISSION ON AUDIT', 'COA'],
                        'CMD'  => ['CONCILIATION AND MEDIATION', 'CMD'],
                        'VAD'  => ['VOLUNTARY ARBITRATION', 'VAD'],
                        'WRED' => ['WORKPLACE RELATIONS', 'WRED'],
                        'OED'  => ['EXECUTIVE DIRECTOR', 'OED'],
                    ];
                    $keywords = $divisionMappings[$sched->division_filter] ?? [$sched->division_filter];
                    $assetQuery->where(function ($q) use ($keywords) {
                        foreach ($keywords as $kw) {
                            $q->orWhere('office', 'LIKE', "%{$kw}%")
                              ->orWhere('department', 'LIKE', "%{$kw}%");
                        }
                    });
                }

                // Group assets by division → all divisions that exist in the branch
                $divGroups = [];
                foreach ($assetQuery->get() as $asset) {
                    $officeKey = strtoupper(trim($asset->office ?? $asset->department ?? ''));
                    if ($officeKey === '') continue;
                    if (!isset($divGroups[$officeKey])) {
                        $divGroups[$officeKey] = [
                            'name' => $asset->office ?? $asset->department ?? 'N/A',
                            'ticket_count' => 0,
                            'assigned_to' => 'Unassigned',
                        ];
                    }
                }

                // Count actual tickets per division (from generated requests)
                foreach ($scheduleRequests as $req) {
                    $officeKey = strtoupper(trim($req->office ?? ''));
                    if (!isset($divGroups[$officeKey])) {
                        $divGroups[$officeKey] = [
                            'name' => $req->office ?? 'N/A',
                            'ticket_count' => 0,
                            'assigned_to' => 'Unassigned',
                        ];
                    }
                    $divGroups[$officeKey]['ticket_count']++;
                    if ($req->assignedTo) {
                        $divGroups[$officeKey]['assigned_to'] = $req->assignedTo->full_name;
                    }
                }

                foreach ($divGroups as $officeKey => $div) {
                    // Determine status: complete if in pm_division_schedules with last_completed_at
                    $divSched = PMDivisionSchedule::where('pm_schedule_id', $sched->id)
                        ->where('division_name', 'LIKE', "%{$officeKey}%")
                        ->latest('last_completed_at')
                        ->first();

                    $status = $divSched && $divSched->last_completed_at
                        ? 'Complete'
                        : ($sched->current_focus_division && strtoupper(trim($sched->current_focus_division)) === $officeKey
                            ? 'Ongoing'
                            : 'Next');

                    $scheduleDivisions[] = [
                        'name' => $div['name'], // FULL division name in the divisions list
                        'full_name' => $div['name'],
                        'short_name' => $this->shortDivisionName($div['name']), // short code for calendar cells
                        'ticket_count' => $div['ticket_count'],
                        'status' => $status,
                        'assigned_to' => $div['assigned_to'],
                    ];
                }

                // Sort divisions by status priority: Ongoing (0) → Next (1) → Complete (2)
                usort($scheduleDivisions, function ($a, $b) {
                    $priority = ['Ongoing' => 0, 'Next' => 1, 'Complete' => 2];
                    $pa = $priority[$a['status']] ?? 3;
                    $pb = $priority[$b['status']] ?? 3;
                    return $pa <=> $pb;
                });

                $events[] = [
                    'id'           => "pm-sched-{$sched->id}",
                    'event_type'   => 'pm',
                    'source'       => 'pm_schedule',
                    'source_id'    => $sched->id,
                    'date'         => $sched->next_scheduled_date->toDateString(),
                    'title'        => $sched->schedule_name,
                    'status'       => 'Scheduled',
                    'display_number' => null,
                    'office'       => $sched->division_filter ?? 'All Divisions',
                    'assignee'     => $sched->assignedIt?->full_name ?? 'Unassigned',
                    'assigned_it_id' => $sched->assigned_it_id,
                    'can_assign_it' => false, // Assign IT is on the division event, not schedule event
                    'divisions'    => $scheduleDivisions,
                    'priority'     => null,
                    'details_url'  => route('pm-schedules.show', $sched->id),
                    'is_editable'  => false,
                ];
            }
        }

        // 1.5 PM Division Schedule — next_scheduled_at dates (from completed cycles)
        if ($filter === 'all' || $filter === 'pm') {
            $divisionSchedules = PMDivisionSchedule::with('schedule')
                ->whereNotNull('next_scheduled_at')
                ->whereBetween('next_scheduled_at', [$startDate->toDateString(), $endDate->toDateString()])
                ->get();

            foreach ($divisionSchedules as $divSched) {
                $shortDiv = $this->shortDivisionName($divSched->division_name);
                $events[] = [
                    'id'           => "pm-division-{$divSched->id}",
                    'event_type'   => 'pm',
                    'source'       => 'pm_division_schedule',
                    'source_id'    => $divSched->id,
                    'date'         => $divSched->next_scheduled_at->toDateString(),
                    'title'        => $shortDiv,
                    'status'       => 'Scheduled',
                    'display_number' => null,
                    'office'       => $divSched->division_name ?? 'N/A',
                    'assignee'     => 'Auto-scheduled',
                    'priority'     => null,
                    'details_url'  => $divSched->pm_schedule_id ? route('pm-schedules.show', $divSched->pm_schedule_id) : '#',
                    'is_editable'  => false,
                ];
            }
        }

        // 2. PM work orders (existing requests where type = Preventive Maintenance)
        // Grouped by division + date — one event per division with ticket_count
        if ($filter === 'all' || $filter === 'pm') {
            $pmRequests = RequestModel::with(['user', 'assignedTo', 'maintenanceRequest'])
                ->where('type', 'Preventive Maintenance')
                ->get();

            // Build division → completion status map (per schedule + division)
            $divisionStatusMap = [];
            $divisionRows = PMDivisionSchedule::whereIn('pm_schedule_id', $pmRequests->pluck('pm_schedule_id')->filter()->unique())
                ->get(['pm_schedule_id', 'division_name', 'last_completed_at', 'next_scheduled_at']);
            foreach ($divisionRows as $divRow) {
                $officeKey = strtoupper(trim($divRow->division_name));
                $divisionStatusMap[$divRow->pm_schedule_id][$officeKey] = $divRow->last_completed_at
                    ? 'complete'
                    : ($divRow->next_scheduled_at ? 'scheduled' : 'in_progress');
            }

            // Group requests by division + date
            $groupedPm = [];
            foreach ($pmRequests as $req) {
                if (!$this->policy->viewMaintenance($user, $req)) {
                    continue;
                }

                $pmDetail = $req->maintenanceRequest;
                $eventDate = $pmDetail?->service_schedule_date
                    ?? $pmDetail?->maintenance_date
                    ?? $req->created_at->toDateString();

                if (!Carbon::parse($eventDate)->between($startDate, $endDate)) {
                    continue;
                }

                $officeKey = strtoupper(trim($req->office ?? ''));
                $divisionStatus = $req->pm_schedule_id && isset($divisionStatusMap[$req->pm_schedule_id][$officeKey])
                    ? $divisionStatusMap[$req->pm_schedule_id][$officeKey]
                    : 'in_progress';

                $groupKey = $eventDate . '|' . $officeKey;
                if (!isset($groupedPm[$groupKey])) {
                    $groupedPm[$groupKey] = [
                        'date' => Carbon::parse($eventDate)->toDateString(),
                        'division' => $req->office ?? 'N/A',
                        'division_status' => $divisionStatus,
                        'ticket_count' => 0,
                        'first_request_id' => $req->id,
                        'assignee' => $req->assignedTo?->full_name ?? 'Unassigned',
                        'pm_schedule_id' => $req->pm_schedule_id,
                        'tickets' => [],
                    ];
                }
                $groupedPm[$groupKey]['ticket_count']++;
                $groupedPm[$groupKey]['tickets'][] = [
                    'id' => $req->id,
                    'display_number' => $req->display_number,
                    'status' => $req->status ?? 'Pending',
                    'assignee' => $req->assignedTo?->full_name ?? 'Unassigned',
                    'details_url' => route('maintenance.show', $req->id),
                ];
            }

            foreach ($groupedPm as $group) {
                $shortDiv = $this->shortDivisionName($group['division']);

                // can_assign_it is true only when PM generated AND no IT assigned yet
                $schedule = $group['pm_schedule_id'] ? PMSchedule::find($group['pm_schedule_id']) : null;
                $canAssignIt = $schedule
                    && $schedule->assigned_it_id === null
                    && ($schedule->current_focus_division !== null
                        || RequestModel::where('pm_schedule_id', $schedule->id)
                            ->where('is_auto_generated', true)
                            ->exists());

                $events[] = [
                    'id'             => "pm-div-{$group['first_request_id']}",
                    'event_type'     => 'pm',
                    'source'         => 'request',
                    'source_id'      => $group['first_request_id'],
                    'pm_schedule_id' => $group['pm_schedule_id'],
                    'date'           => $group['date'],
                    'title'          => $shortDiv,
                    'status'         => 'Scheduled',
                    'division_status' => $group['division_status'],
                    'ticket_count'   => $group['ticket_count'],
                    'tickets'        => $group['tickets'],
                    'display_number' => null,
                    'office'         => $group['division'],
                    'assignee'       => $group['assignee'],
                    'assigned_it_id' => $schedule?->assigned_it_id,
                    'can_assign_it'  => $canAssignIt,
                    'priority'       => null,
                    'details_url'    => route('maintenance.show', $group['first_request_id']),
                    'is_editable'    => false,
                ];
            }
        }

        // 3. ICT requests (existing requests where type = ICT with repair_requests)
        if ($filter === 'all' || $filter === 'ict') {
            $ictRequests = RequestModel::with(['user', 'assignedTo', 'repairRequest'])
                ->where('type', 'ICT')
                ->get();

            foreach ($ictRequests as $req) {
                if (!$this->policy->viewIct($user, $req)) {
                    continue;
                }

                $repair = $req->repairRequest;
                $eventDate = $repair?->service_schedule_date
                    ?? $repair?->date_received
                    ?? $req->created_at->toDateString();

                if (!Carbon::parse($eventDate)->between($startDate, $endDate)) {
                    continue;
                }

                $events[] = [
                    'id'             => "ict-{$req->id}",
                    'event_type'     => 'ict',
                    'source'         => 'request',
                    'source_id'      => $req->id,
                    'date'           => Carbon::parse($eventDate)->toDateString(),
                    'title'          => $req->requestor_name ?? 'ICT Request',
                    'status'         => $req->status ?? 'Pending',
                    'display_number' => $req->display_number,
                    'office'         => $req->office ?? 'N/A',
                    'assignee'       => $req->assignedTo?->full_name ?? 'Unassigned',
                    'priority'       => $req->priority,
                    'details_url'    => route('ict.show', $req->id),
                    'is_editable'    => false,
                ];
            }
        }

        // Sort events by date
        usort($events, fn($a, $b) => strcmp($a['date'], $b['date']));

        // Summary counts
        $pmCount = count(array_filter($events, fn($e) => $e['event_type'] === 'pm'));
        $ictCount = count(array_filter($events, fn($e) => $e['event_type'] === 'ict'));
        $doneCount = count(array_filter($events, fn($e) => $e['status'] === 'Completed'));
        $overdueCount = count(array_filter($events, fn($e) => $e['status'] === 'Overdue'));

        return [
            'events' => $events,
            'summary' => [
                'pm'      => $pmCount,
                'ict'     => $ictCount,
                'done'    => $doneCount,
                'overdue' => $overdueCount,
            ],
            'has_active_schedule' => $hasActiveSchedule,
            'month' => $month,
            'year' => $year,
            'filter' => $filter,
        ];
    }
}