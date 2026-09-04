@php
    $pageTitle = 'My PM Tasks';
@endphp

@extends('layouts.app')

@section('title', $pageTitle)
@section('page-title', $pageTitle)

@section('styles')
<style nonce="{{ $cspNonce }}">
    .polish-card { background: white; border-radius: 15px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
    .card-header-accent { background: #f8fafc; padding: 20px 30px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
    .card-body-content { padding: 25px 30px; }
    .h3-title { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; }
    .p-subtitle { margin: 2px 0 0; font-size: 12px; color: #64748b; }

    /* Stats */
    .pm-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
    .task-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .task-card:hover { border-color: #0038A8; box-shadow: 0 4px 12px rgba(0,56,168,0.1); transform: translateY(-3px); }
    .pm-stat-card { text-align: center; padding: 14px; }
    .pm-stat-num { font-size: 24px; font-weight: 800; }
    .pm-stat-num-total { color: #0038A8; }
    .pm-stat-num-scheduled { color: #92400e; }
    .pm-stat-num-ongoing { color: #1e40af; }
    .pm-stat-num-completed { color: #047857; }
    .pm-stat-label { font-size: 10px; color: #64748b; font-weight: 700; text-transform: uppercase; margin-top: 4px; }

    /* Filters */
    .pm-filters { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
    .filter-btn { padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; border: 1px solid #e2e8f0; background: white; color: #475569; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .filter-btn:hover { border-color: #0038A8; color: #0038A8; }
    .filter-btn.active { background: #0038A8; color: white; border-color: #0038A8; }

    /* Table */
    .pm-table-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
    .pm-empty { text-align: center; padding: 60px 20px; color: #94a3b8; }
    .pm-empty-icon { font-size: 48px; margin-bottom: 16px; opacity: 0.4; }
    .pm-empty-title { color: #64748b; font-size: 16px; }
    .pm-empty-text { font-size: 13px; }
    .pm-table { width: 100%; border-collapse: collapse; }
    .pm-th { background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-bottom: 2px solid #0038A8; }
    .pm-th-cell { padding: 12px 16px; text-align: left; font-size: 10px; color: #475569; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
    .pm-th-cell-center { padding: 12px 16px; text-align: center; font-size: 10px; color: #475569; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
    .pm-row { border-bottom: 1px solid #f1f5f9; transition: background 0.2s; }
    .pm-row:hover { background: #f8fafc; }
    .pm-row-overdue { border-left: 3px solid #f59e0b !important; background: #fffbeb; }
    .pm-row-overdue:hover { background: #fef3c7 !important; }
    .pm-td { padding: 14px 16px; }
    .pm-td-link { color: #0038A8; font-weight: 700; text-decoration: none; font-size: 13px; }
    .pm-td-link:hover { text-decoration: underline; }
    .pm-td-name { padding: 14px 16px; color: #1e293b; font-weight: 600; font-size: 13px; }
    .pm-td-office { padding: 14px 16px; color: #475569; font-size: 12px; }
    .pm-td-status { padding: 14px 16px; }
    .pm-td-action { padding: 10px 16px; text-align: center; }

    /* Status Badges */
    .badge-pm { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; border: 1px solid transparent; }
    .status-pending { background: #fffbeb; color: #92400e; border-color: rgba(245, 158, 11, 0.2); }
    .status-ongoing { background: #eff6ff; color: #1e40af; border-color: rgba(59, 130, 246, 0.2); }
    .status-completed { background: #ecfdf5; color: #065f46; border-color: rgba(16, 185, 129, 0.2); }
    .badge-overdue { display: inline-block; padding: 3px 8px; border-radius: 20px; font-size: 9px; font-weight: 800; text-transform: uppercase; background: #fee2e2; color: #991b1b; margin-left: 5px; }

    /* Progress Bar */
    .progress-wrap { margin-top: 6px; }
    .progress-track { height: 4px; background: #e2e8f0; border-radius: 3px; overflow: hidden; width: 100%; }
    .progress-fill { height: 100%; border-radius: 3px; transition: width 0.4s ease; }
    .progress-fill-todo { background: #fbbf24; width: 0%; }
    .progress-fill-ongoing { background: #3b82f6; width: 50%; }
    .progress-fill-done { background: #10b981; width: 100%; }
    .progress-label { font-size: 9px; color: #94a3b8; font-weight: 600; margin-top: 2px; }

    /* Asset Tag */
    .asset-tag { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; color: #475569; font-weight: 600; background: #f1f5f9; border-radius: 4px; padding: 2px 7px; margin-top: 3px; }
    .asset-tag i { color: #0038A8; font-size: 10px; }

    /* Action Buttons */
    .pm-btn-action { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; transition: all 0.2s; cursor: pointer; border: none; }
    .pm-btn-start { background: #0038A8; color: white; }
    .pm-btn-start:hover { background: #002366; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0, 56, 168, 0.2); color: white; }
    .pm-btn-update { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
    .pm-btn-update:hover { background: #dbeafe; transform: translateY(-2px); color: #1e40af; }
    .pm-btn-view { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .pm-btn-view:hover { background: #e2e8f0; transform: translateY(-2px); color: #475569; }
    .pm-pagination { margin-top: 20px; }

    /* Base: hide swipe hint on desktop (mobile re-shows below) */
    .mobile-table-hint { display: none; }

    @media screen and (max-width: 767px) {
        .card-header-accent { padding: 15px !important; }
        .card-body-content { padding: 15px !important; }
        .pm-stats-grid { grid-template-columns: repeat(2, 1fr) !important; }

        /* PM table - Supply Workspace scroll pattern */
        .mobile-table-hint {
            display: flex !important;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 9px 12px;
            background: #eff6ff;
            color: #1e40af;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.03em;
            border: 1px solid #dbeafe;
            border-bottom: none;
            border-radius: 10px 10px 0 0;
        }
        .pm-table-card {
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch !important;
            border-radius: 0 0 10px 10px !important;
        }
        .pm-table {
            min-width: 1000px !important;
            width: 1000px !important;
            table-layout: auto !important;
        }
        .pm-table .pm-td,
        .pm-table .pm-td-name,
        .pm-table .pm-td-office,
        .pm-table .pm-td-status,
        .pm-table .pm-td-action,
        .pm-table .pm-th-cell,
        .pm-table .pm-th-cell-center {
            white-space: nowrap !important;
            padding: 12px 12px !important;
            font-size: 12px !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
        }
        /* Action buttons stay inline-compact (global full-width rule override) */
        .pm-btn-action { width: auto !important; }
    }
</style>
@endsection

@section('content')
<div style="width:100%;">

    <div class="polish-card">
        {{-- HEADER --}}
        <div class="card-header-accent">
            <div>
                <h3 class="h3-title"><i class="fa-solid fa-clipboard-check" style="color:#0038A8;margin-right:8px;"></i>My PM Tasks</h3>
                <p class="p-subtitle">Preventive Maintenance work orders assigned to you.</p>
            </div>
        </div>

        <div class="card-body-content">
            {{-- Stats Summary --}}
            @php
                $totalCount     = $pmTasks->total();
                $scheduledCount = $pmTasks->filter(fn($t) => $t->status === 'Scheduled')->count();
                $ongoingCount   = $pmTasks->filter(fn($t) => $t->status === 'Ongoing')->count();
                $completedCount = $pmTasks->filter(fn($t) => $t->status === 'Completed')->count();
                $overdueCount   = $pmTasks->filter(fn($t) => $t->status === 'Scheduled' && $t->created_at->diffInDays(now()) > 7)->count();
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
                @if($overdueCount > 0)
                <div class="task-card pm-stat-card" style="border-color:#fde68a;background:#fffbeb;">
                    <div class="pm-stat-num" style="color:#92400e;">{{ $overdueCount }}</div>
                    <div class="pm-stat-label" style="color:#b45309;">Overdue</div>
                </div>
                @endif
            </div>

            {{-- Filters --}}
            <div class="pm-filters">
                <a href="{{ route('pm.tasks') }}"                               class="filter-btn {{ !request('status') ? 'active' : '' }}"><i class="fa-solid fa-list"></i> All</a>
                <a href="{{ route('pm.tasks', ['status' => 'Scheduled']) }}"   class="filter-btn {{ request('status') === 'Scheduled' ? 'active' : '' }}"><i class="fa-solid fa-clock"></i> To Do</a>
                <a href="{{ route('pm.tasks', ['status' => 'Ongoing']) }}"     class="filter-btn {{ request('status') === 'Ongoing' ? 'active' : '' }}"><i class="fa-solid fa-play"></i> In Progress</a>
                <a href="{{ route('pm.tasks', ['status' => 'Completed']) }}"   class="filter-btn {{ request('status') === 'Completed' ? 'active' : '' }}"><i class="fa-solid fa-check"></i> Completed</a>
            </div>

            {{-- PM Tasks Table --}}
            <div class="mobile-table-hint"><i class="fa-solid fa-arrows-left-right"></i> Swipe table horizontally to view all columns</div>
            <div class="pm-table-card">
                @if($pmTasks->isEmpty())
                    <div class="pm-empty">
                        <div class="pm-empty-icon"><i class="fa-solid fa-clipboard-check"></i></div>
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
                                <th class="pm-th-cell">Asset</th>
                                <th class="pm-th-cell">Progress</th>
                                <th class="pm-th-cell">Completed At</th>
                                <th class="pm-th-cell">Status</th>
                                <th class="pm-th-cell-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pmTasks as $task)
                            @php
                                $isOverdue = $task->status === 'Scheduled' && $task->created_at->diffInDays(now()) > 7;
                                $rowClass  = $isOverdue ? 'pm-row pm-row-overdue' : 'pm-row';
                                $progressFill = match($task->status) {
                                    'Scheduled'        => 'progress-fill-todo',
                                    'Ongoing'          => 'progress-fill-ongoing',
                                    'Completed'        => 'progress-fill-done',
                                    default            => 'progress-fill-todo',
                                };
                                $progressPct = match($task->status) {
                                    'Scheduled'        => '0%',
                                    'Ongoing'          => '50%',
                                    'Completed'        => '100%',
                                    default            => '0%',
                                };
                                $assetName = $task->linkedAsset?->asset_name ?? $task->linkedAsset?->description ?? null;
                                $assetTag  = $task->linkedAsset?->asset_tag ?? $task->linkedAsset?->par_number ?? null;
                                $badgeClass  = match($task->status) {
                                    'Scheduled' => 'status-pending',
                                    'Ongoing'   => 'status-ongoing',
                                    'Completed' => 'status-completed',
                                    default     => 'status-pending',
                                };
                                $statusLabel = match($task->status) {
                                    'Scheduled' => 'To Do',
                                    'Ongoing'   => 'In Progress',
                                    'Completed' => 'Completed',
                                    default     => $task->status,
                                };
                                $startRoute = route('maintenance.start', $task->id);
                                $editRoute  = route('maintenance.edit', $task->id);
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td class="pm-td">
                                    <a href="{{ $editRoute }}" class="pm-td-link">
                                        {{ $task->display_number ?? $task->request_number }}
                                    </a>
                                </td>
                                <td class="pm-td-name">{{ $task->requestor_name }}</td>
                                <td class="pm-td-office">{{ $task->office ?? '—' }}</td>

                                {{-- Asset Column --}}
                                <td class="pm-td">
                                    @if($assetName || $assetTag)
                                        <div style="font-size:12px;font-weight:600;color:#1e293b;">{{ $assetName ?? '—' }}</div>
                                        @if($assetTag)
                                            <span class="asset-tag"><i class="fa-solid fa-tag"></i>{{ $assetTag }}</span>
                                        @endif
                                    @else
                                        <span style="color:#94a3b8;font-size:12px;">—</span>
                                    @endif
                                </td>

                                {{-- Progress Bar --}}
                                <td class="pm-td" style="min-width:100px;">
                                    <div class="progress-wrap">
                                        <div class="progress-track">
                                            <div class="progress-fill {{ $progressFill }}" style="width:{{ $progressPct }};"></div>
                                        </div>
                                        <div class="progress-label">{{ $progressPct }} done</div>
                                    </div>
                                </td>

                                <td class="pm-td-office">
                                    {{ $task->completed_at ? $task->completed_at->format('M d, Y | h:i A') : '—' }}
                                </td>

                                <td class="pm-td-status">
                                    <span class="badge-pm {{ $badgeClass }}">{{ $statusLabel }}</span>
                                    @if($isOverdue)
                                        <span class="badge-overdue"><i class="fa-solid fa-clock"></i> Overdue</span>
                                    @endif
                                </td>

                                <td class="pm-td-action">
                                    @if($task->status === 'Scheduled')
                                        {{-- Start button triggers SweetAlert confirmation --}}
                                        <button
                                            class="pm-btn-action pm-btn-start js-start-pm"
                                            data-id="{{ $task->id }}"
                                            data-url="{{ $startRoute }}"
                                            data-name="{{ $task->requestor_name }}"
                                            data-number="{{ $task->display_number ?? $task->request_number }}">
                                            <i class="fa-solid fa-play"></i> Start
                                        </button>
                                    @elseif($task->status === 'Ongoing')
                                        <a href="{{ $editRoute }}" class="pm-btn-action pm-btn-update">
                                            <i class="fa-solid fa-pen-to-square"></i> Update
                                        </a>
                                    @else
                                        <a href="{{ $editRoute }}" class="pm-btn-action pm-btn-view">
                                            <i class="fa-solid fa-eye"></i> View
                                        </a>
                                    @endif
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

@section('scripts')
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.js-start-pm').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const url    = this.dataset.url;
            const name   = this.dataset.name;
            const number = this.dataset.number;

            Swal.fire({
                title: 'Start PM Task?',
                html: `<p style="color:#475569;font-size:14px;">You are about to start <strong>${number}</strong> for <strong>${name}</strong>.</p><p style="color:#94a3b8;font-size:12px;margin-top:8px;">This will mark the work order as <span style="color:#1e40af;font-weight:700;">In Progress</span>.</p>`,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#0038A8',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fa-solid fa-play"></i> Start Now',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then(function(result) {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        });
    });
});
</script>
@endsection