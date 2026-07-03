@php
    $pageTitle = $pmSchedule->schedule_name;
@endphp

@extends('layouts.app')

@section('title', $pageTitle)
@section('page-title', $pageTitle)

@section('styles')
<style nonce="{{ $cspNonce }}">
    .premium-card { background: white; border-radius: 14px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 20px; }
    .page-container { max-width:1400px; margin:0 auto; padding:0 24px; }
    .action-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:2px; flex-wrap:wrap; gap:12px; }
    .back-link { display:inline-flex; align-items:center; gap:8px; color:#64748b; text-decoration:none; font-size:13px; font-weight:600; padding:10px 16px; border-radius:8px; transition:all 0.2s; min-height:44px; }
    .back-link:hover { background:#f1f5f9; color:#1e293b; }
    .action-group { display:flex; gap:8px; }
    .btn-edit { padding:10px 20px; background:white; color:#0038A8; border:2px solid #0038A8; border-radius:8px; font-size:13px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:8px; transition:all 0.2s; min-height:44px; }
    .btn-edit:hover { background:#0038A8; color:white; transform:translateY(-1px); box-shadow:0 4px 10px rgba(0,56,168,0.2); }
    .detail-header { margin-bottom:20px; }
    .detail-header > div:first-child { flex:1; }
    .detail-title { font-size:24px; font-weight:800; color:#1e293b; margin:0 0 8px; }
    .detail-subtitle { font-size:13px; color:#64748b; margin:0; }
    .info-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
    .info-item { padding:0; background:transparent; border-radius:0; border:none; }
    .info-item:hover { background:transparent; }
    .info-label { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.06em; font-weight:700; margin-bottom:6px; }
    .info-value { font-size:15px; color:#1e293b; font-weight:600; }
    .badge-freq { display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.03em; }
    .badge-monthly { background:#dbeafe; color:#1e40af; box-shadow:0 2px 4px rgba(30,64,175,0.15); }
    .badge-quarterly { background:#fef3c7; color:#92400e; box-shadow:0 2px 4px rgba(146,64,14,0.15); }
    .badge-semi { background:#e0e7ff; color:#3730a3; box-shadow:0 2px 4px rgba(55,48,163,0.15); }
    .badge-annual { background:#dcfce7; color:#166534; box-shadow:0 2px 4px rgba(22,101,52,0.15); }
    .badge-inactive { background:#fee2e2; color:#991b1b; box-shadow:0 2px 4px rgba(153,27,27,0.15); }
    .section-title { font-size:16px; font-weight:800; color:#1e293b; margin:0 0 20px; display:flex; align-items:center; gap:10px; }
    .section-title i { color:#0038A8; }
    .monitor-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
    .monitor-title { margin:0; font-size:18px; color:#1e293b; font-weight:800; display:flex; align-items:center; gap:8px; }
    .monitor-title i { color:#0038A8; }
    .monitor-status { font-size:12px; color:#64748b; font-weight:600; padding:6px 12px; background:#f1f5f9; border-radius:20px; }
    .summary-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-bottom:16px; }
    .stat-card { background:#f8fafc; border-radius:8px; padding:12px 8px; text-align:center; border:1px solid #e2e8f0; display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:70px; }
    .stat-value { font-size:24px; font-weight:800; line-height:1; margin-bottom:4px; }
    .stat-value.blue { color:#0038A8; }
    .stat-value.green { color:#10b981; }
    .stat-value.amber { color:#f59e0b; }
    .stat-value.slate { font-size:10px; font-weight:600; color:#1e293b; }
    .stat-label { font-size:9px; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:0.03em; }
    
    .division-card { border:1px solid #e2e8f0; border-radius:8px; padding:8px; background:white; transition:border-color 0.2s; }
    .division-card:hover { border-color:#94a3b8; }
    .division-card.active { border-color:#0038A8; background:#eff6ff; }
    .division-card.done { border-color:#86efac; background:#f0fdf4; }
    .div-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:6px; }
    .div-name { font-size:12px; color:#1e293b; font-weight:700; word-break:break-word; overflow-wrap:break-word; hyphens:auto; }
    .div-badges { display:flex; gap:4px; flex-wrap:wrap; }
    .div-badge { padding:1px 6px; border-radius:8px; font-size:8px; font-weight:700; }
    .div-badge-done { background:#dcfce7; color:#166534; }
    .div-badge-pending { background:#fef3c7; color:#92400e; }
    .div-badge-focus { background:#dbeafe; color:#1e40af; }
    .progress-bar { height:8px; border-radius:4px; background:#e2e8f0; overflow:hidden; margin:8px 0; }
    .progress-fill { height:100%; border-radius:3px; transition:width 0.5s ease; }
    .div-footer { font-size:10px; color:#64748b; font-weight:600; }
    .next-user-box { background:#f0fdf4; border:1px solid #86efac; border-radius:10px; padding:16px; margin-top:16px; display:none; align-items:center; gap:12px; }
    .next-user-box.show { display:flex; }
    .next-user-icon { color:#16a34a; font-size:20px; flex-shrink:0; }
    .next-user-content { flex:1; }
    .next-user-title { font-size:14px; font-weight:700; color:#1e293b; margin-bottom:4px; }
    .next-user-detail { font-size:12px; color:#64748b; line-height:1.5; }
    .history-card { }
    .history-title { font-size:18px; font-weight:800; color:#1e293b; margin:0 0 20px; display:flex; align-items:center; gap:10px; }
    .history-title i { color:#0038A8; }
    .history-list { }
    .history-item { padding:16px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; transition:background 0.2s; }
    .history-item:last-child { border-bottom:none; }
    .history-item:hover { background:#f8fafc; }
    .history-info { display:flex; flex-direction:column; gap:4px; }
    .history-label { font-size:13px; font-weight:700; color:#0038A8; }
    .history-date { font-size:12px; color:#64748b; }
    .history-badge { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; background:#f1f5f9; color:#64748b; }
    .empty-state { text-align:center; padding:40px 20px; color:#94a3b8; }
    .empty-icon { font-size:40px; margin-bottom:12px; opacity:0.5; }
    .empty-text { font-size:13px; margin:0; }
    @media (max-width: 768px) {
        .page-container { padding:0 12px !important; }
        .premium-card { padding:16px !important; border-radius:12px !important; }
        .action-bar { flex-direction:column; align-items:stretch !important; gap:8px !important; }
        .back-link { justify-content:center; width:100% !important; }
        .detail-header { flex-direction:column !important; align-items:stretch !important; }
        .detail-header > div:first-child { margin-bottom:8px; }
        .detail-title { font-size:20px !important; }
        .detail-subtitle { font-size:12px !important; }
        .btn-edit { width:100% !important; justify-content:center !important; min-height:44px !important; }
        .info-grid { grid-template-columns:1fr !important; gap:12px !important; }
        .info-label { font-size:10px !important; }
        .info-value { font-size:14px !important; }
        .summary-grid { grid-template-columns:repeat(2,1fr) !important; gap:10px !important; margin-bottom:20px !important; }
        .stat-card { padding:16px !important; border-radius:12px !important; }
        .stat-value { font-size:24px !important; }
        .stat-value.slate { font-size:12px !important; }
        .stat-label { font-size:10px !important; }
        .monitor-header { flex-direction:column !important; align-items:stretch !important; gap:8px !important; }
        .monitor-title { font-size:15px !important; }
        .monitor-status { align-self:flex-start !important; }
        .division-card { padding:14px !important; border-radius:10px !important; }
        .div-header { flex-direction:column !important; align-items:stretch !important; gap:8px !important; }
        .div-name { font-size:14px !important; }
        .div-badges { width:100% !important; }
        .div-badge { font-size:10px !important; padding:3px 10px !important; }
        .progress-bar { height:8px !important; margin:12px 0 !important; }
        .div-footer { font-size:11px !important; }
        .next-user-box { flex-direction:column !important; text-align:center !important; padding:14px !important; }
        .next-user-title { font-size:13px !important; }
        .next-user-detail { font-size:11px !important; }
        .history-title { font-size:15px !important; }
        .history-item { flex-direction:column !important; align-items:flex-start !important; gap:8px !important; padding:14px !important; }
        .history-label { font-size:12px !important; }
        .history-date { font-size:11px !important; }
        .history-badge { align-self:flex-start !important; }
        .empty-state { padding:30px 16px !important; }
        .empty-icon { font-size:32px !important; }
        .empty-text { font-size:12px !important; }
    }
    @media (max-width: 480px) {
        .premium-card { padding:14px !important; border-radius:10px !important; }
        .summary-grid { grid-template-columns:1fr !important; gap:8px !important; }
        .stat-card { padding:14px !important; }
        .stat-value { font-size:22px !important; }
        .division-card { padding:12px !important; }
        .div-name { font-size:13px !important; }
        .detail-title { font-size:18px !important; }
        .page-container { padding:0 8px !important; }
    }
</style>
@endsection

@section('content')
<div class="page-container">

    {{-- Action Bar --}}
    <div class="action-bar">
        <a href="{{ route('pm-schedules.index') }}" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Schedules
        </a>
    </div>

    {{-- Schedule Details --}}
    <div class="premium-card">
        <div class="detail-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
            <div>
                <h1 class="detail-title">{{ $pmSchedule->schedule_name }}</h1>
                <p class="detail-subtitle">Schedule details and configuration</p>
            </div>
            <a href="{{ route('pm-schedules.edit', $pmSchedule->id) }}" class="btn-edit">
                <i class="fa-solid fa-pen"></i> Edit Schedule
            </a>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Status</div>
                <div class="info-value">
                    <span class="badge-freq {{ $pmSchedule->is_active ? 'badge-monthly' : 'badge-inactive' }}">
                        {{ $pmSchedule->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Frequency</div>
                <div class="info-value">
                    <span class="badge-freq
                        {{ $pmSchedule->frequency === 'Monthly' ? 'badge-monthly' : '' }}
                        {{ $pmSchedule->frequency === 'Quarterly' ? 'badge-quarterly' : '' }}
                        {{ $pmSchedule->frequency === 'Semi-annual' ? 'badge-semi' : '' }}
                        {{ $pmSchedule->frequency === 'Annual' ? 'badge-annual' : '' }}">
                        {{ $pmSchedule->frequency }}
                    </span>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Division Filter</div>
                <div class="info-value">{{ $pmSchedule->division_filter ?: 'All Divisions' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Created By</div>
                <div class="info-value">{{ $pmSchedule->creator?->full_name ?? 'Unknown' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Last Generated</div>
                <div class="info-value">
                    @php
                        $lastCycle = $pmSchedule->cycles()->latest('started_at')->first();
                    @endphp
                    {{ $lastCycle ? $lastCycle->started_at->format('M d, Y') : 'Not yet generated' }}
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Next Scheduled</div>
                <div class="info-value">
                    @php
                        $soonestNext = \App\Models\PMDivisionSchedule::where('pm_schedule_id', $pmSchedule->id)
                            ->whereNotNull('next_scheduled_at')
                            ->min('next_scheduled_at');
                    @endphp
                    {{ $soonestNext ? \Carbon\Carbon::parse($soonestNext)->format('M d, Y') : '—' }}
                </div>
            </div>
        </div>
    </div>

    {{-- Queue Monitor --}}
    <div class="premium-card" style="padding: 28px; margin-bottom: 40px;">
        @if($pmSchedule->current_focus_division)
            <div class="monitor-header">
                <h3 class="monitor-title">
                    <i class="fa-solid fa-chart-line"></i> Queue Status
                </h3>
                <span id="queueLoading" class="monitor-status">Loading...</span>
            </div>
            <div id="queueContent">
                <div id="queueSummary" class="summary-grid"></div>
                <div id="queueDivisions"></div>
                <div id="queueNextUser" class="next-user-box"></div>
            </div>
        @else
            <div class="monitor-header">
                <h3 class="monitor-title">
                    Division PM Status
                </h3>
                <span class="monitor-status">
                    {{ $pmTotalCompleted }} Completed Divisions
                </span>
            </div>
            <div style="overflow-x:auto; margin-top: 16px;">
                <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
                    <thead>
                        <tr style="border-bottom:2px solid #e2e8f0; color:#64748b;">
                            <th style="padding:12px; font-weight:700;">Division</th>
                            <th style="padding:12px; font-weight:700;">Status</th>
                            <th style="padding:12px; font-weight:700;">Progress</th>
                            <th style="padding:12px; font-weight:700;">Next Scheduled Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pmDivisions as $div => $data)
                            @php
                                $isDone = $data['status'] === 'Completed';
                                $isFocus = $data['status'] === 'In Progress';
                                $statusColor = $isDone ? '#166534' : ($isFocus ? '#1e40af' : '#64748b');
                                $statusBg = $isDone ? '#dcfce7' : ($isFocus ? '#dbeafe' : '#f1f5f9');
                            @endphp
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:12px; font-weight:600; color:#1e293b;">
                                    {{ $div }}
                                </td>
                                <td style="padding:12px;">
                                    <span style="background:{{ $statusBg }}; color:{{ $statusColor }}; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700;">
                                        {{ $data['status'] }}
                                    </span>
                                </td>
                                <td style="padding:12px; font-weight:600; color:#475569;">
                                    {{ $data['done'] }} / {{ $data['total'] }} Users
                                </td>
                                <td style="padding:12px; font-weight:600; color:{{ $isDone ? '#059669' : '#94a3b8' }};">
                                    {{ $data['next_date'] ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align:center; padding:20px; color:#64748b;">No divisions found with active users.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Generation History --}}
    <div class="premium-card history-card" style="padding: 28px;">
        <h3 class="history-title">
            <i class="fa-solid fa-clock-rotate-left"></i> Generation History
        </h3>
        @if($pmSchedule->history->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-inbox"></i></div>
                <p class="empty-text">No runs recorded yet.</p>
            </div>
        @else
            <div class="history-list">
                @foreach($pmSchedule->history as $entry)
                    <div class="history-item">
                        <div class="history-info">
                            <span class="history-label">Generated {{ $entry->generated_count }} ticket(s)</span>
                            <span class="history-date">
                                {{ $entry->generated_at->format('M d, Y \a\t h:i A') }}
                            </span>
                        </div>
                        <span class="history-badge">{{ $entry->action }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function() {
    @if($pmSchedule->current_focus_division)
    loadQueueStatus();
    @endif
});

@if($pmSchedule->current_focus_division)
function loadQueueStatus() {
    const loading = document.getElementById('queueLoading');
    const summary = document.getElementById('queueSummary');
    const divisions = document.getElementById('queueDivisions');
    const nextUser = document.getElementById('queueNextUser');

    fetch('{{ route("pm-schedules.queue", $pmSchedule->id) }}', {
        headers: { 'Accept': 'application/json' }
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) { 
            loading.textContent = 'Error loading queue'; 
            loading.style.background = '#fee2e2';
            loading.style.color = '#dc2626';
            return; 
        }
        loading.textContent = 'Live';
        loading.style.background = '#dcfce7';
        loading.style.color = '#166534';

        const divMap = {'RESEARCH AND INFORMATION DIVISION':'RID','ADMINISTRATIVE DIVISION':'AD','FINANCIAL AND MANAGEMENT DIVISION':'FMD','COMMISSION ON AUDIT':'COA','CONCILIATION AND MEDIATION DIVISION':'CMD','VOLUNTARY ARBITRATION DIVISION':'VAD','WORKPLACE RELATIONS ENHANCEMENT DIVISION':'WRED','OFFICE OF THE EXECUTIVE DIRECTOR':'OED'};
        function shortDiv(name) { return divMap[name] || name; }
        summary.innerHTML = [
            '<div class="stat-card"><div class="stat-value blue">' + data.total_users + '</div><div class="stat-label">Total Users</div></div>',
            '<div class="stat-card"><div class="stat-value green">' + data.total_done + '</div><div class="stat-label">Completed</div></div>',
            '<div class="stat-card"><div class="stat-value amber">' + data.total_pending + '</div><div class="stat-label">Pending</div></div>',
            '<div class="stat-card"><div class="stat-value slate">' + shortDiv(data.focus_division || '—') + '</div><div class="stat-label">Current Focus</div></div>'
        ].join('');

        let divHtml = '';
        for (const [div, status] of Object.entries(data.divisions)) {
            const isActive = div === data.focus_division;
            const pct = status.total > 0 ? Math.round((status.done / status.total) * 100) : 0;
            const barColor = isActive ? '#0038A8' : (pct === 100 ? '#10b981' : '#64748b');
            divHtml += [
                '<div class="division-card ' + (isActive ? 'active' : '') + ' ' + (pct === 100 ? 'done' : '') + '">',
                    '<div class="div-header">',
                        '<div class="div-name">' + div + '</div>',
                        '<div class="div-badges">',
                            isActive ? '<span class="div-badge div-badge-focus">Current Focus</span>' : '',
                            '<span class="div-badge div-badge-done">' + status.done + ' done</span>',
                            '<span class="div-badge div-badge-pending">' + status.pending + ' pending</span>',
                        '</div>',
                    '</div>',
                    '<div class="progress-bar"><div class="progress-fill" data-pct="' + pct + '" style="background:' + barColor + ';width:0%"></div></div>',
                    '<div class="div-footer">' + pct + '% complete (' + status.total + ' users)</div>',
                '</div>'
            ].join('');
        }
        divisions.innerHTML = divHtml;
        
        divisions.querySelectorAll('.progress-fill').forEach(function(el) {
            const pct = el.getAttribute('data-pct');
            setTimeout(() => { el.style.width = pct + '%'; }, 50);
        });

        if (data.next_user) {
            nextUser.classList.add('show');
            nextUser.innerHTML = [
                '<i class="fa-solid fa-arrow-right next-user-icon"></i>',
                '<div class="next-user-content">',
                    '<div class="next-user-title">Next in Queue: ' + data.next_user.name + '</div>',
                    '<div class="next-user-detail">',
                        'Oldest asset: ' + data.next_user.oldest_date + 
                        ' | Division: ' + data.focus_division + 
                        (data.next_division ? ' | Next division: ' + data.next_division : ''),
                    '</div>',
                '</div>'
            ].join('');
        } else {
            nextUser.classList.remove('show');
        }
    })
    .catch(() => { 
        loading.textContent = 'Failed to load'; 
        loading.style.background = '#fee2e2';
        loading.style.color = '#dc2626';
    });
}
@endif
</script>
@endsection
