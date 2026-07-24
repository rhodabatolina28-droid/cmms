@php
    $pageTitle = 'My PM Tasks';
@endphp

@extends('layouts.app')

@section('title', $pageTitle)
@section('page-title', $pageTitle)

@section('styles')
<style nonce="{{ $cspNonce }}">
    .polish-card { background: white; border-radius: 15px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
    .card-header-accent { background: #f8fafc; padding: 20px 30px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .card-body-content { padding: 25px 30px; }
    .h3-title { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; }
    .p-subtitle { margin: 2px 0 0; font-size: 12px; color: #64748b; }
    .task-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .task-card:hover { border-color: #0038A8; box-shadow: 0 4px 12px rgba(0,56,168,0.1); transform: translateY(-3px); }
    .badge-pm { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; border: 1px solid transparent; }
    .status-pending { background: #fffbeb; color: #92400e; border-color: rgba(245, 158, 11, 0.2); box-shadow: 0 2px 4px rgba(245, 158, 11, 0.15); }
    .status-ongoing { background: #eff6ff; color: #1e40af; border-color: rgba(59, 130, 246, 0.2); box-shadow: 0 2px 4px rgba(59, 130, 246, 0.15); }
    .status-completed { background: #ecfdf5; color: #065f46; border-color: rgba(16, 185, 129, 0.2); box-shadow: 0 2px 4px rgba(16, 185, 129, 0.15); }
    .filter-btn { padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; border: 1px solid #e2e8f0; background: white; color: #475569; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .filter-btn:hover { border-color: #0038A8; color: #0038A8; }
    .filter-btn.active { background: #0038A8; color: white; border-color: #0038A8; }
    .tr-hover-row { transition: all 0.2s; position: relative; }
    .tr-hover-row:hover { background: #f8fafc !important; }
    .tr-hover-row:hover td:first-child { box-shadow: inset 4px 0 0 #0038A8; border-top-left-radius: 4px; border-bottom-left-radius: 4px; }
    .pm-page-wrap { width: 100%; }
    .pm-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
    .pm-stat-card { text-align: center; padding: 14px; }
    .pm-stat-num { font-size: 24px; font-weight: 800; }
    .pm-stat-num-total { color: #0038A8; }
    .pm-stat-num-scheduled { color: #92400e; }
    .pm-stat-num-ongoing { color: #1e40af; }
    .pm-stat-num-completed { color: #047857; }
    .pm-stat-label { font-size: 10px; color: #64748b; font-weight: 700; text-transform: uppercase; margin-top: 4px; }
    .pm-filters { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
    .pm-table-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
    .pm-empty { text-align: center; padding: 60px 20px; color: #94a3b8; }
    .pm-empty-icon { font-size: 48px; margin-bottom: 16px; opacity: 0.4; }
    .pm-empty-title { color: #64748b; font-size: 16px; }
    .pm-empty-text { font-size: 13px; }
    .pm-table { width: 100%; border-collapse: collapse; }
    .pm-th { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
    .pm-th-cell { padding: 14px 16px; text-align: left; font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 700; }
    .pm-th-cell-center { padding: 14px 16px; text-align: center; font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 700; }
    .pm-row { border-bottom: 1px solid #f1f5f9; transition: background 0.2s; }
    .pm-td { padding: 14px 16px; }
    .pm-td-link { color: #0038A8; font-weight: 700; text-decoration: none; font-size: 13px; }
    .pm-td-name { padding: 14px 16px; color: #1e293b; font-weight: 600; font-size: 13px; }
    .pm-td-office { padding: 14px 16px; color: #475569; font-size: 12px; }
    .pm-td-status { padding: 14px 16px; }
    .pm-td-action { padding: 14px 16px; text-align: center; }
    .pm-btn-action { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none; transition: all 0.2s; }
    .pm-btn-start { background: #0038A8; color: white; }
    .pm-btn-start:hover { background: #002366; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0, 56, 168, 0.2); }
    .pm-btn-view-task { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .pm-btn-view-task:hover { background: #e2e8f0; transform: translateY(-2px); }
    .pm-pagination { margin-top: 20px; }
    @media (max-width: 767px) {
        .pm-page-wrap { padding: 0 0 !important; }
        .pm-page-title { font-size: 18px !important; flex-wrap: wrap !important; }
        .pm-page-sub { font-size: 12px !important; }
        .pm-stats-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 10px !important; }
        .pm-stat-card { padding: 12px 10px !important; }
        .pm-stat-num { font-size: 20px !important; }
        .pm-stat-label { font-size: 9px !important; }
        .pm-filters { gap: 8px !important; flex-wrap: wrap !important; }
        .filter-btn { 
            padding: 10px 16px !important; 
            font-size: 12px !important; 
            min-height: 44px !important;
            white-space: nowrap !important;
        }
        .pm-table-card { border-radius: 8px !important; overflow-x: auto !important; }
        .pm-table { min-width: 500px !important; }
        .pm-th-cell { padding: 10px 12px !important; font-size: 10px !important; }
        .pm-td { padding: 10px 12px !important; }
        .pm-td-name { padding: 10px 12px !important; font-size: 12px !important; }
        .pm-td-office { padding: 10px 12px !important; font-size: 11px !important; }
        .pm-td-link { font-size: 12px !important; }
        .pm-btn-action { 
            padding: 10px 16px !important; 
            font-size: 12px !important; 
            min-height: 44px !important;
            justify-content: center !important;
        }
        .pm-empty { padding: 40px 20px !important; }
        .pm-empty-icon { font-size: 36px !important; }
        .pm-empty-title { font-size: 14px !important; }
        .pm-pagination { margin-top: 16px !important; }
    }
</style>
@endsection

@section('content')
<div class="pm-page-wrap">

    <div class="polish-card">
        {{-- HEADER STRIP --}}
        <div class="card-header-accent">
            <div>
                <h3 class="h3-title">My PM Tasks</h3>
                <p class="p-subtitle">Preventive Maintenance work orders assigned to you.</p>
            </div>
        </div>

        <div class="card-body-content">
            {{-- Stats Summary --}}
            @php
                $totalCount = $pmTasks->total();
                $scheduledCount = $pmTasks->filter(fn($t) => $t->status === 'Scheduled')->count();
                $ongoingCount = $pmTasks->filter(fn($t) => $t->status === 'Ongoing')->count();
                $completedCount = $pmTasks->filter(fn($t) => $t->status === 'Completed')->count();
            @endphp

            <div class="pm-stats-grid">
                <div class="task-card pm-stat-card">
                    <div class="pm-stat-num pm-stat-num-total">{{ $totalCount }}</div>
                    <div class="pm-stat-label">Total PM Tasks</div>
                </div>
                <div class="task-card pm-stat-card">
                    <div class="pm-stat-num pm-stat-num-scheduled">{{ $scheduledCount }}</div>
                    <div class="pm-stat-label">To Do</div>
                </div>
                <div class="task-card pm-stat-card">
                    <div class="pm-stat-num pm-stat-num-ongoing">{{ $ongoingCount }}</div>
                    <div class="pm-stat-label">In Progress</div>
                </div>
                <div class="task-card pm-stat-card">
                    <div class="pm-stat-num pm-stat-num-completed">{{ $completedCount }}</div>
                    <div class="pm-stat-label">Completed</div>
                </div>
            </div>

            {{-- Filters --}}
            <div class="pm-filters">
                <a href="{{ route('pm.tasks') }}" class="filter-btn {{ !request('status') ? 'active' : '' }}"><i class="fa-solid fa-list"></i> All</a>
                <a href="{{ route('pm.tasks', ['status' => 'Scheduled']) }}" class="filter-btn {{ request('status') === 'Scheduled' ? 'active' : '' }}"><i class="fa-solid fa-clock"></i> To Do</a>
                <a href="{{ route('pm.tasks', ['status' => 'Ongoing']) }}" class="filter-btn {{ request('status') === 'Ongoing' ? 'active' : '' }}"><i class="fa-solid fa-play"></i> In Progress</a>
                <a href="{{ route('pm.tasks', ['status' => 'Completed']) }}" class="filter-btn {{ request('status') === 'Completed' ? 'active' : '' }}"><i class="fa-solid fa-check"></i> Completed</a>
            </div>

            {{-- PM Tasks Table --}}
            <div class="pm-table-card">
                @if($pmTasks->isEmpty())
                    <div class="pm-empty">
                        <i class="fa-solid fa-clipboard-check pm-empty-icon"></i>
                        <h3 class="pm-empty-title">No PM Tasks Assigned</h3>
                        <p class="pm-empty-text">PM tasks will appear here once assigned to you by the Super Admin.</p>
                    </div>
                @else
                    <table class="pm-table">
                        <thead>
                            <tr class="pm-th">
                                <th class="pm-th-cell">PM ID</th>
                                <th class="pm-th-cell">End User</th>
                                <th class="pm-th-cell">Division</th>
                                <th class="pm-th-cell">Status</th>
                                <th class="pm-th-cell-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pmTasks as $task)
                            <tr class="tr-hover-row pm-row">
                                <td class="pm-td">
                                    <a href="{{ route('maintenance.edit', $task->id) }}" class="pm-td-link">
                                        {{ $task->display_number ?? $task->request_number }}
                                    </a>
                                </td>
                                <td class="pm-td-name">{{ $task->requestor_name }}</td>
                                <td class="pm-td-office">{{ $task->office ?? '—' }}</td>
                                <td class="pm-td-status">
                                    @php
                                        $badgeClass = match($task->status) {
                                            'Scheduled' => 'status-pending',
                                            'Ongoing' => 'status-ongoing',
                                            'Completed' => 'status-completed',
                                            default => 'status-pending'
                                        };
                                        $statusLabel = match($task->status) {
                                            'Scheduled' => 'To Do',
                                            'Ongoing' => 'In Progress',
                                            'Completed' => 'Completed',
                                            default => $task->status
                                        };
                                    @endphp
                                    <span class="badge-pm {{ $badgeClass }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="pm-td-action">
                                    <a href="{{ $task->status === 'Scheduled' ? route('maintenance.start', $task->id) : route('maintenance.edit', $task->id) }}" class="pm-btn-action {{ $task->status === 'Completed' ? 'pm-btn-view-task' : 'pm-btn-start' }}">
                                        <i class="fa-solid fa-{{ $task->status === 'Completed' ? 'eye' : 'arrow-right' }}"></i>
                                        {{ $task->status === 'Scheduled' ? 'Start' : ($task->status === 'Completed' ? 'View' : 'Update') }}
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Pagination --}}
            @if($pmTasks->hasPages())
            <div class="pm-pagination">
                {{ $pmTasks->links() }}
            </div>
            @endif
        </div>
    </div>

</div>
@endsection