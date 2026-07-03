@php
    $pageTitle = 'PM Schedules';
@endphp

@extends('layouts.app')

@section('title', $pageTitle)
@section('page-title', $pageTitle)

@section('styles')
<style nonce="{{ $cspNonce }}">
    .premium-card { background: white; border-radius: 14px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 24px; }
    .schedule-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; transition: all 0.25s; box-shadow: 0 2px 4px rgba(0,0,0,0.03); }
    .schedule-card:hover { border-color: #0038A8; box-shadow: 0 4px 15px rgba(0,56,168,0.1); transform: translateY(-2px); }
    .badge-freq { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em; }
    .badge-monthly { background: #dbeafe; color: #1e40af; box-shadow: 0 2px 4px rgba(30, 64, 175, 0.15); }
    .badge-quarterly { background: #fef3c7; color: #92400e; box-shadow: 0 2px 4px rgba(146, 64, 14, 0.15); }
    .badge-semi { background: #e0e7ff; color: #3730a3; box-shadow: 0 2px 4px rgba(55, 48, 163, 0.15); }
    .badge-annual { background: #dcfce7; color: #166534; box-shadow: 0 2px 4px rgba(22, 101, 52, 0.15); }
    .badge-active { background: #dcfce7; color: #166534; box-shadow: 0 2px 4px rgba(22, 101, 52, 0.15); }
    .badge-inactive { background: #fee2e2; color: #991b1b; box-shadow: 0 2px 4px rgba(153, 27, 27, 0.15); }
    .stat-card { text-align:center; padding:14px; }
    .stat-count { font-size: 24px; font-weight: 800; color: #0038A8; line-height: 1; }
    .stat-label { font-size: 10px; color: #64748b; margin-top: 4px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
    .pm-section-title { font-size: 12px; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
    .pm-section-title i { color: #0038A8; }
    .status-scheduled { background: #fef3c7; color: #92400e; border: 1px solid rgba(245, 158, 11, 0.2); box-shadow: 0 2px 4px rgba(245, 158, 11, 0.15); }
    .status-ongoing { background: #dbeafe; color: #1e40af; border: 1px solid rgba(59, 130, 246, 0.2); box-shadow: 0 2px 4px rgba(59, 130, 246, 0.15); }
    .status-completed { background: #dcfce7; color: #166534; border: 1px solid rgba(16, 185, 129, 0.2); box-shadow: 0 2px 4px rgba(16, 185, 129, 0.15); }
    .cycle-box { background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; padding: 18px; margin-bottom: 20px; }
    .cycle-title { margin:0; font-size:16px; font-weight:800; color:#1e293b; }
    .cycle-status-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700; text-transform:uppercase; }
    .cycle-badge-active { background:#dcfce7; color:#166534; }
    .cycle-badge-paused { background:#fef3c7; color:#92400e; }
    .cycle-badge-idle { background:#f1f5f9; color:#64748b; }
    .cycle-badge-completed { background:#dbeafe; color:#1e40af; }
    .div-card { border-radius: 8px; padding: 10px 12px; border: 1px solid #e2e8f0; transition: all 0.2s; background: white; }
    .div-card:hover { border-color: #0038A8; }
    .div-card.completed { border-color: #86efac; background: #f0fdf4; }
    .div-card.active { border-color: #93c5fd; background: #eff6ff; }
    .div-card.queued { border-color: #e2e8f0; background: #f8fafc; }
    .btn-control { padding: 7px 14px; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
    .btn-control:disabled { opacity: 0.5; cursor: not-allowed; }
    .btn-pause { background:#fef3c7; color:#92400e; }
    .btn-resume { background:#dcfce7; color:#166534; }
    .btn-stop { background:#fee2e2; color:#dc2626; }
    .btn-generate { background:#059669; color:white; }
    .btn-new-schedule { background:#0038A8; color:white; }
    .div-bar-track { width:100%; height:4px; background:#e2e8f0; border-radius:3px; margin-top:6px; overflow:hidden; }
    .div-bar-fill { height:100%; border-radius:3px; transition:width 0.3s; }
    .div-card.completed .div-bar-fill { background:#10b981; }
    .div-card:not(.completed) .div-bar-fill { background:#0038A8; }
    .table-wrap-wo { overflow-x:auto; border-radius:10px; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.04); }
    .table-wo { width:100%; border-collapse:collapse; background:white; min-width:750px; }
    .tr-header-wo { background:linear-gradient(135deg,#f8fafc 0%,#f1f5f9 100%); }
    .th-wo { color:#475569; font-size:10px; font-weight:700; text-transform:uppercase; padding:12px 14px; text-align:left; border-bottom:2px solid #0038A8; }
    .th-wo-center { color:#475569; font-size:10px; font-weight:700; text-transform:uppercase; padding:12px 14px; text-align:center; border-bottom:2px solid #0038A8; }
    .tr-wo { border-bottom:1px solid #f1f5f9; transition:all 0.2s; }
    .tr-wo:hover { background:#f8fafc !important; }
    .td-wo-link { padding:12px 14px; font-weight:700; font-size:12px; }
    .a-wo { color:#0038A8; text-decoration:none; font-weight:700; font-size:12px; }
    .td-wo-name { padding:12px 14px; font-weight:600; color:#1e293b; font-size:13px; }
    .td-wo-div { padding:12px 14px; color:#475569; font-size:12px; }
    .td-wo-assign { padding:12px 14px; font-weight:600; font-size:13px; color:#1e293b; }
    .td-wo-status { padding:12px 14px; }
    .td-wo-action { padding:12px 14px; text-align:center; }
    .status-pill { display:inline-block; padding:3px 10px; border-radius:20px; font-size:9px; font-weight:800; text-transform:uppercase; }
    .btn-wo-action { display:inline-flex; align-items:center; gap:6px; padding:7px 12px; background:#0038A8; color:white; border-radius:6px; font-size:10px; font-weight:700; text-decoration:none; transition:all 0.2s; }
    .btn-wo-action:hover { background:#002d8c; box-shadow:0 4px 10px rgba(0,56,168,0.2); }
    .empty-schedules { text-align:center; padding:60px 20px; color:#94a3b8; }
    .empty-icon { font-size:48px; margin-bottom:16px; opacity:0.5; }
    .empty-title-sched { color:#64748b; }
    .btn-create-sched { display:inline-block; margin-top:16px; padding:10px 24px; background:#0038A8; color:white; border-radius:8px; text-decoration:none; font-weight:700; }
    .sched-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px,1fr)); gap:16px; }
    .sched-card-head { display:flex; justify-content:space-between; align-items:start; margin-bottom:12px; }
    .sched-card-title { margin:0; font-size:15px; color:#1e293b; font-weight:700; }
    .sched-card-title a { color:#0038A8; text-decoration:none; }
    .sched-card-meta { margin:4px 0 0; font-size:10px; color:#64748b; }
    .sched-card-body { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:12px; align-items:center; }
    .sched-div-tag { display:inline-block; padding:2px 8px; background:#fef3c7; color:#92400e; border-radius:12px; font-size:10px; font-weight:600; }
    .btn-toggle-sched { padding:4px 10px; border:none; border-radius:5px; font-size:9px; font-weight:700; cursor:pointer; text-transform:uppercase; transition:all 0.2s; }
    .btn-toggle-active { background:#fef3c7; color:#92400e; }
    .btn-toggle-inactive { background:#dcfce7; color:#166534; }
    .btn-delete-sched { padding:4px 10px; background:#fee2e2; color:#dc2626; border:none; border-radius:5px; font-size:9px; font-weight:700; cursor:pointer; }
    .sched-progress { margin-top:12px; padding-top:12px; border-top:1px solid #f1f5f9; }
    .sched-progress-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; }
    .sched-progress-label { font-size:10px; font-weight:600; color:#64748b; }
    .sched-progress-pct { font-size:10px; font-weight:700; color:#0038A8; }
    .sched-bar-track { width:100%; height:6px; background:#f1f5f9; border-radius:3px; overflow:hidden; }
    .sched-bar-fill { height:100%; border-radius:3px; transition:width 0.3s; }
    .sched-bar-fill.bar-done { background:#10b981; }
    .sched-bar-fill.bar-pending { background:#0038A8; }
    .sched-progress-users { font-size:9px; color:#64748b; margin-top:3px; }
    .sched-dates { display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:11px; color:#475569; margin-top:10px; padding-top:10px; border-top:1px solid #f1f5f9; }
    .sched-paginator { margin-top:20px; }
    .delete-all-wrap { margin-top:20px; padding-top:14px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; }
    .btn-delete-all { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; background:white; color:#dc2626; border:2px solid #dc2626; border-radius:8px; font-size:11px; font-weight:700; cursor:pointer; transition:all 0.2s; }
    .btn-delete-all:hover { background:#dc2626; color:white; }
    .empty-state-wo { text-align:center; padding:30px 20px; color:#94a3b8; border:1px dashed #e2e8f0; border-radius:10px; }
    .page-head-flex { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; }
    .page-title-text { font-size:20px; font-weight:800; color:#1e293b; margin:0; }
    .page-subtitle-text { font-size:12px; color:#64748b; margin:4px 0 0; }
    .stats-grid-custom { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-top:16px; padding-top:16px; border-top:1px solid #e2e8f0; }
    .cycle-row-flex { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; }
    .cycle-title-group { display:flex; align-items:center; gap:8px; }
    .cycle-actions-flex { display:flex; gap:6px; flex-wrap:wrap; }
    .div-grid-custom { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:8px; margin-top:12px; padding-top:12px; border-top:1px solid #e2e8f0; }
    .div-header { display:flex; justify-content:space-between; align-items:center; }
    .div-name { font-size:12px; color:#1e293b; }
    .div-count { font-size:11px; font-weight:700; }
    .div-card.completed .div-count { color:#166534; }
    .div-card.active .div-count { color:#1e40af; }
    .div-card.queued .div-count { color:#64748b; }
    .div-footer-custom { display:flex; justify-content:space-between; align-items:center; margin-top:8px; font-size:10px; color:#64748b; }
    .btn-refresh { background:#f1f5f9; color:#475569; }
    @media (max-width: 768px) {
        .premium-card { padding: 16px !important; }
        .stat-count { font-size: 20px !important; }
        .schedule-card { padding: 14px !important; }
        .stats-grid-custom { grid-template-columns: repeat(2,1fr) !important; gap: 8px !important; }
        .cycle-row-flex { flex-direction: column !important; align-items: stretch !important; }
        .cycle-actions-flex { flex-direction: column !important; width: 100% !important; }
        .cycle-actions-flex .btn-control { width: 100% !important; justify-content: center !important; min-height: 40px !important; }
        .page-head-flex { flex-direction: column !important; }
        .div-grid-custom { grid-template-columns: repeat(2,1fr) !important; gap: 6px !important; }
        .sched-grid { grid-template-columns: 1fr !important; }
        .sched-dates { grid-template-columns: 1fr !important; gap: 4px !important; }
        .btn-new-schedule { width: 100% !important; justify-content: center !important; }
        .delete-all-wrap { justify-content: stretch !important; }
        .btn-delete-all { width: 100% !important; justify-content: center !important; }
        .div-footer-custom { flex-direction: column !important; gap: 6px !important; align-items: stretch !important; }
        .btn-refresh { width: 100% !important; justify-content: center !important; }
        .empty-state-wo { padding: 20px 16px !important; }
        .empty-schedules { padding: 40px 16px !important; }
        .empty-icon { font-size: 36px !important; }
        .btn-create-sched { width: 100% !important; text-align: center !important; }
    }
    @media (max-width: 480px) {
        .stats-grid-custom { grid-template-columns: 1fr !important; }
        .div-grid-custom { grid-template-columns: 1fr !important; }
        .th-wo, .th-wo-center { padding: 8px 6px !important; font-size: 9px !important; }
        .td-wo-link, .td-wo-name, .td-wo-div, .td-wo-assign, .td-wo-status, .td-wo-action { padding: 8px 6px !important; font-size: 10px !important; }
        .btn-wo-action { padding: 6px 10px !important; font-size: 9px !important; min-height: 36px !important; }
    }
</style>
@endsection

@section('content')
<div class="page-wrap">

    @php
        $totalSchedules = $schedules->count();
        $activeSchedules = $schedules->where('is_active', true)->count();
        $totalWorkOrders = $workOrders ? $workOrders->count() : 0;
        $completedWorkOrders = $workOrders ? $workOrders->where('status', 'Completed')->count() : 0;
        $activeSchedule = \App\Models\PMSchedule::active()->first();
        $cycleStatus = 'idle';
        $cycleDivision = null;
        $cyclePaused = false;
        if ($activeSchedule) {
            $cycleDivision = $activeSchedule->current_focus_division;
            $cyclePaused = $activeSchedule->is_paused;
            if ($cycleDivision) {
                $cycleStatus = $cyclePaused ? 'paused' : 'active';
            } elseif ($activeSchedule->current_cycle_id === null && $activeSchedule->cycle_count > 0) {
                // No active cycle but has completed at least one — last cycle finished
                $lastCycle = \App\Models\PMCycle::where('pm_schedule_id', $activeSchedule->id)
                    ->whereNotNull('completed_at')
                    ->latest('completed_at')
                    ->first();
                $cycleStatus = $lastCycle ? 'completed' : 'idle';
            }
        }
    @endphp

    {{-- HEADER + STATS + PM CYCLE + DIVISIONS --}}
    <div class="premium-card">
        <div class="page-head-flex">
            <div>
                <h1 class="page-title-text">PM Schedules</h1>
                <p class="page-subtitle-text">Manage automated preventive maintenance schedules. Batch generation per division, oldest asset first.</p>
            </div>
            <a href="{{ route('pm-schedules.create') }}" class="btn-control btn-new-schedule" style="text-decoration:none;">
                <i class="fa-solid fa-plus"></i> New Schedule
            </a>
        </div>

        <div class="stats-grid-custom">
            <div class="schedule-card stat-card">
                <div class="stat-count">{{ $statTotalSchedules }}</div>
                <div class="stat-label">Total PM Schedules</div>
            </div>
            <div class="schedule-card stat-card">
                <div class="stat-count" style="color:#059669;">{{ $statActiveSchedules }}</div>
                <div class="stat-label">Active Schedules</div>
            </div>
            <div class="schedule-card stat-card">
                <div class="stat-count" style="color:#d97706;">{{ $statActiveWorkOrders }}</div>
                <div class="stat-label">Active Work Orders</div>
            </div>
            <div class="schedule-card stat-card">
                <div class="stat-count" style="color:#0038A8;">{{ $statCompletedThisMonth }}</div>
                <div class="stat-label">Completed WOs (This Month)</div>
            </div>
        </div>

        <div class="cycle-box" style="margin-top:16px;">
            <div class="cycle-row-flex">
                <div>
                    <div class="cycle-title-group">
                        <h2 class="cycle-title">PM Cycle Control</h2>
                        <span class="cycle-status-badge cycle-badge-{{ $cycleStatus }}">
                            <i class="fa-solid fa-circle" style="font-size:6px;"></i>
                            {{ $cycleStatus === 'active' ? 'Active' : ($cycleStatus === 'paused' ? 'Paused' : ($cycleStatus === 'completed' ? 'Cycle Complete' : 'Idle')) }}
                        </span>
                    </div>
                    <p style="margin:4px 0 0;font-size:11px;color:#64748b;">
                        @if($cycleDivision)
                            Currently processing: <strong>{{ $cycleDivision }}</strong>
                        @elseif($cycleStatus === 'completed')
                            All divisions done. Ready to start a new cycle.
                        @else
                            No active PM cycle. Click "Generate PM" to start.
                        @endif
                    </p>
                </div>
                <div class="cycle-actions-flex">
                    @if($cycleStatus === 'active' || $cycleStatus === 'paused')
                        @if($cycleStatus === 'active')
                            <button type="button" id="pauseCycleBtn" class="btn-control btn-pause"><i class="fa-solid fa-pause"></i> Pause</button>
                        @else
                            <button type="button" id="resumeCycleBtn" class="btn-control btn-resume"><i class="fa-solid fa-play"></i> Resume</button>
                        @endif
                        <button type="button" id="stopCycleBtn" class="btn-control btn-stop"><i class="fa-solid fa-stop"></i> Stop</button>
                    @endif
                    <button type="button" id="forceRunBtn" class="btn-control btn-generate" {{ $cycleStatus === 'active' ? 'disabled' : '' }}>
                        <i class="fa-solid fa-{{ $cycleStatus === 'active' ? 'spinner fa-spin' : 'play' }}"></i>
                        @if($cycleStatus === 'active') Processing...
                        @elseif($cycleStatus === 'paused') Paused
                        @elseif($cycleStatus === 'completed') Start New Cycle
                        @else Generate PM
                        @endif
                    </button>
                </div>
            </div>


        </div>
    </div>

    {{-- WORK ORDERS CARD --}}
    <div class="premium-card">
        <div class="pm-section-title" style="justify-content:space-between;">
            <span><i class="fa-solid fa-clipboard-list"></i> Work Orders
                @if(isset($totalActiveWorkOrderCount) && $totalActiveWorkOrderCount > 0)
                    <span style="background:#0038A8;color:white;border-radius:20px;padding:2px 8px;font-size:10px;margin-left:6px;">{{ $totalActiveWorkOrderCount }}</span>
                @endif
                @if($focusDivision)
                    <span style="color:#64748b;font-size:10px;font-weight:500;margin-left:6px;">— {{ $focusDivision }}</span>
                @endif
            </span>
            <a href="{{ route('pm-schedules.orders') }}" style="font-size:11px;font-weight:700;color:#0038A8;text-decoration:none;">
                <i class="fa-solid fa-list"></i> View All Work Orders
            </a>
        </div>
        @php
            $displayOrders = $focusDivision 
                ? ($workOrders ?? collect())->filter(function($order) use ($focusDivision) {
                    return str_contains(strtoupper($order->office ?? ''), strtoupper($focusDivision));
                })
                : ($workOrders ?? collect());
        @endphp
        @if($displayOrders->isEmpty())
            <div class="empty-state-wo">
                <p style="font-size:13px;color:#64748b;margin:0;">No PM Work Orders yet. Generated tickets appear here once the scheduler runs.</p>
            </div>
        @else
            <div class="table-wrap-wo">
                <table class="table-wo">
                    <thead>
                        <tr class="tr-header-wo">
                            <th class="th-wo">PM #</th>
                            <th class="th-wo">Employee</th>
                            <th class="th-wo">Division</th>
                            <th class="th-wo">Assignee</th>
                            <th class="th-wo">Status</th>
                            <th class="th-wo-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($displayOrders as $order)
                        <tr class="tr-wo">
                            <td class="td-wo-link">
                                <a href="{{ route('maintenance.edit', $order->id) }}" class="a-wo">{{ $order->display_number ?? $order->request_number }}</a>
                            </td>
                            <td class="td-wo-name">{{ $order->requestor_name ?? '—' }}</td>
                            <td class="td-wo-div">{{ $order->office ?? '—' }}</td>
                            <td class="td-wo-assign">{{ $order->assignedTo?->full_name ?? '—' }}</td>
                            <td class="td-wo-status">
                                @php
                                    $statusLabel = $order->status;
                                    $statusClass = 'scheduled';
                                    if ($order->status === 'Completed') { $statusLabel = 'Completed'; $statusClass = 'completed'; }
                                    elseif ($order->status === 'Ongoing') { $statusLabel = 'In Progress'; $statusClass = 'ongoing'; }
                                    else { $statusLabel = 'To Do'; $statusClass = 'scheduled'; }
                                @endphp
                                <span class="status-pill status-{{ $statusClass }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="td-wo-action">
                                <a href="{{ route('maintenance.edit', $order->id) }}" class="btn-wo-action">
                                    <i class="fa-solid fa-arrow-right"></i> {{ $order->status === 'Scheduled' ? 'Start' : 'Update' }}
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- SCHEDULES CARD --}}
    <div class="premium-card">
        <div class="pm-section-title"><i class="fa-solid fa-calendar-days"></i> Schedules</div>
        @if($schedules->isEmpty())
            <div class="empty-schedules">
                <i class="fa-solid fa-calendar-circle-plus empty-icon"></i>
                <h3 class="empty-title-sched">No PM Schedules Yet</h3>
                <p class="page-subtitle-text">Create your first schedule to start automating preventive maintenance.</p>
                <a href="{{ route('pm-schedules.create') }}" class="btn-create-sched">Create Schedule</a>
            </div>
        @else
            <div class="sched-grid">
                @foreach($schedules as $schedule)
                    <div class="schedule-card">
                        <div class="sched-card-head">
                            <div>
                                <h3 class="sched-card-title">
                                    <a href="{{ route('pm-schedules.show', $schedule->id) }}">{{ $schedule->schedule_name }}</a>
                                </h3>
                                <p class="sched-card-meta">Created {{ $schedule->created_at->format('M d, Y') }} by {{ $schedule->creator?->full_name ?? 'Unknown' }}</p>
                            </div>
                            <span class="badge-freq {{ $schedule->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $schedule->is_active ? 'Active' : 'Inactive' }}</span>
                        </div>
                        <div class="sched-card-body">
                            <span class="badge-freq
                                {{ $schedule->frequency === 'Monthly' ? 'badge-monthly' : '' }}
                                {{ $schedule->frequency === 'Quarterly' ? 'badge-quarterly' : '' }}
                                {{ $schedule->frequency === 'Semi-annual' ? 'badge-semi' : '' }}
                                {{ $schedule->frequency === 'Annual' ? 'badge-annual' : '' }}">{{ $schedule->frequency }}
                            </span>
                            @if($schedule->division_filter)<span class="sched-div-tag">{{ $schedule->division_filter }}</span>@endif
                            <button type="button" data-action="toggle-schedule" data-id="{{ $schedule->id }}" class="btn-toggle-sched {{ $schedule->is_active ? 'btn-toggle-active' : 'btn-toggle-inactive' }}" style="margin-left:auto;">
                                <i class="fa-solid fa-{{ $schedule->is_active ? 'pause' : 'play' }}"></i> {{ $schedule->is_active ? 'Pause' : 'Activate' }}
                            </button>
                            <button type="button" data-action="delete-schedule" data-id="{{ $schedule->id }}" data-name="{{ $schedule->schedule_name }}" class="btn-delete-sched"><i class="fa-solid fa-trash"></i></button>
                        </div>
                        <div class="sched-progress">
                            <div class="sched-progress-head">
                                <span class="sched-progress-label">Progress</span>
                                <span class="sched-progress-pct">{{ $schedule->progress_percentage ?? 0 }}%</span>
                            </div>
                            <div class="sched-bar-track">
                                <div class="sched-bar-fill {{ ($schedule->progress_percentage ?? 0) >= 100 ? 'bar-done' : 'bar-pending' }}" data-pct="{{ $schedule->progress_percentage ?? 0 }}"></div>
                            </div>
                            <div class="sched-progress-users">{{ $schedule->completed_divisions ?? 0 }} / {{ $schedule->total_divisions ?? 0 }} divisions completed</div>
                        </div>
                        <div style="font-size:11px; color:#475569; margin-top:10px; padding-top:10px; border-top:1px solid #f1f5f9;">
                            @if($schedule->current_focus_division)
                                <div><strong>Status:</strong> <span style="color:#0038A8;"><i class="fa-solid fa-spinner fa-spin" style="font-size:9px;"></i> Processing {{ $schedule->current_focus_division }}</span></div>
                            @elseif(($schedule->completed_divisions ?? 0) >= ($schedule->total_divisions ?? 1) && ($schedule->total_divisions ?? 0) > 0)
                                <div><strong>Status:</strong> <span style="color:#059669;"><i class="fa-solid fa-check-circle"></i> Cycle Complete</span></div>
                            @elseif(($schedule->completed_divisions ?? 0) > 0)
                                <div><strong>Status:</strong> <span style="color:#0038A8;"><i class="fa-solid fa-hourglass-half"></i> In Progress</span></div>
                            @else
                                <div><strong>Status:</strong> <span style="color:#64748b;">Idle / Not Started</span></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="sched-paginator">{{ $schedules->links() }}</div>
        @endif
    </div>

    {{-- Delete All Button --}}
    <div class="delete-all-wrap">
        <button type="button" id="deleteAllBtn" class="btn-delete-all"><i class="fa-solid fa-trash"></i> Delete All Schedules</button>
    </div>
</div>

@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
const PM_SCHEDULES_BASE_URL = '{{ route('pm-schedules.index') }}';

// Set progress bar widths from data-pct attribute
document.querySelectorAll('.sched-bar-fill[data-pct]').forEach(function(el) {
    el.style.width = (el.getAttribute('data-pct') || 0) + '%';
});

// Force Run / Generate PM
document.getElementById('forceRunBtn')?.addEventListener('click', function () {
    if (this.disabled) return;
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';
    btn.style.opacity = '0.6';
    btn.style.cursor = 'not-allowed';
    
    Swal.fire({
        title: 'Generate PM Now?',
        text: 'This will generate PM tickets for the next division in queue.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#059669',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Generate',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return fetch('{{ route("pm-schedules.force-run") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                }
            }).then(res => {
                if (!res.ok) throw new Error('Request failed');
                return res.json();
            }).catch(err => {
                Swal.showValidationMessage('Failed: ' + err.message);
            });
        }
    }).then(result => {
        if (result.isConfirmed && result.value?.success) {
            Swal.fire({ title: 'Done!', text: result.value.message, icon: 'success', confirmButtonColor: '#0038A8' })
                .then(() => location.reload());
        } else if (result.value && !result.value?.success) {
            Swal.fire('Error', result.value.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-play"></i> Generate PM';
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
        }
    });
});

// Pause Cycle
document.getElementById('pauseCycleBtn')?.addEventListener('click', function () {
    const scheduleId = {{ $activeSchedule?->id ?? 0 }};
    if (!scheduleId) return;
    
    Swal.fire({
        title: 'Pause PM Cycle?',
        text: 'Auto-advance will halt. IT can still conduct PMs.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d97706',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Pause',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return fetch('{{ route("pm-schedules.pause", ":id") }}'.replace(':id', scheduleId), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
            }).then(res => res.json()).catch(err => Swal.showValidationMessage('Failed'));
        }
    }).then(result => {
        if (result.isConfirmed && result.value?.success) {
            Swal.fire('Paused', result.value.message, 'success').then(() => location.reload());
        }
    });
});

// Resume Cycle
document.getElementById('resumeCycleBtn')?.addEventListener('click', function () {
    const scheduleId = {{ $activeSchedule?->id ?? 0 }};
    if (!scheduleId) return;
    
    Swal.fire({
        title: 'Resume PM Cycle?',
        text: 'Auto-advance will continue.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#059669',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Resume',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return fetch('{{ route("pm-schedules.resume", ":id") }}'.replace(':id', scheduleId), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
            }).then(res => res.json()).catch(err => Swal.showValidationMessage('Failed'));
        }
    }).then(result => {
        if (result.isConfirmed && result.value?.success) {
            Swal.fire('Resumed', result.value.message, 'success').then(() => location.reload());
        }
    });
});

// Stop Cycle
document.getElementById('stopCycleBtn')?.addEventListener('click', function () {
    const scheduleId = {{ $activeSchedule?->id ?? 0 }};
    if (!scheduleId) return;
    
    Swal.fire({
        title: 'Stop PM Cycle?',
        text: 'This will stop the current cycle. The schedule stays active — you can generate a new cycle anytime.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Stop',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return fetch('{{ route("pm-schedules.stop", ":id") }}'.replace(':id', scheduleId), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
            }).then(res => res.json()).catch(err => Swal.showValidationMessage('Failed'));
        }
    }).then(result => {
        if (result.isConfirmed && result.value?.success) {
            Swal.fire('Stopped', result.value.message, 'success').then(() => location.reload());
        }
    });
});

// Refresh Status
document.getElementById('refreshStatusBtn')?.addEventListener('click', function () {
    location.reload();
});

function toggleScheduleStatus(id) {
    Swal.fire({
        title: 'Toggle Schedule Status?',
        showCancelButton: true,
        confirmButtonColor: '#0038A8',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return fetch('{{ route("pm-schedules.toggle", ":id") }}'.replace(':id', id), {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
            }).then(res => res.json()).catch(err => Swal.showValidationMessage('Failed'));
        }
    }).then(result => {
        if (result.isConfirmed && result.value?.success) {
            Swal.fire('Updated', result.value.message, 'success').then(() => location.reload());
        }
    });
}

function deleteSchedule(id, name) {
    Swal.fire({
        title: 'Delete Schedule?',
        text: `Are you sure you want to delete "${name}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return fetch('{{ route("pm-schedules.destroy", ":id") }}'.replace(':id', id), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
            }).then(res => res.json()).catch(err => Swal.showValidationMessage('Failed'));
        }
    }).then(result => {
        if (result.isConfirmed && result.value?.success) {
            Swal.fire('Deleted', result.value.message, 'success').then(() => location.reload());
        }
    });
}

document.getElementById('deleteAllBtn')?.addEventListener('click', function () {
    Swal.fire({
        title: 'Delete All Schedules?',
        text: 'This will permanently delete all PM schedules.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete All',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return fetch('{{ route("pm-schedules.destroy-all") }}', {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
            }).then(res => res.json()).catch(err => Swal.showValidationMessage('Failed'));
        }
    }).then(result => {
        if (result.isConfirmed && result.value?.success) {
            Swal.fire('Deleted', result.value.message, 'success').then(() => location.reload());
        }
    });
});
document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-action="toggle-schedule"]');
    if (btn) { toggleScheduleStatus(parseInt(btn.dataset.id)); }
    var delBtn = e.target.closest('[data-action="delete-schedule"]');
    if (delBtn) { deleteSchedule(parseInt(delBtn.dataset.id), delBtn.dataset.name); }
});
document.querySelectorAll('.div-bar-fill, .sched-bar-fill').forEach(function(el) {
    if (el.hasAttribute('data-pct')) {
        el.style.width = el.getAttribute('data-pct') + '%';
    }
});
</script>
@endsection