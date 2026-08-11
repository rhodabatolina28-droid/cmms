@extends('layouts.app')

@section('title', 'Maintenance Requests')
@section('page-title', 'Maintenance Requests')

@section('styles')
<style nonce="{{ $cspNonce }}">
    .badge-auto { display: inline-block; padding: 2px 8px; background: #e0e7ff; color: #3730a3; border-radius: 20px; font-size: 10px; font-weight: 700; margin-left: 6px; vertical-align: middle; text-transform: uppercase; }
    .maint-card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .maint-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .maint-header-left { display: flex; align-items: center; gap: 12px; }
    .maint-header-icon { font-size: 24px; color: #0038A8; }
    .maint-header-title { font-size: 20px; color: #111827; font-weight: 700; margin: 0; }
    .maint-pm-link { text-decoration: none; padding: 12px 20px; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; min-height: 44px; background: #0038A8; color: white; border-radius: 8px; transition: all 0.2s; }
    .maint-pm-link:hover { background: #002d8c; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0, 56, 168, 0.2); }
    .maint-table-wrap { overflow-x: auto; }
    .maint-table { width: 100%; border-collapse: collapse; min-width: 800px; text-align: left; }
    .maint-th { background-color: #f1f5f9; border-bottom: 2px solid #e2e8f0; }
    .maint-th-cell { padding: 12px; font-weight: 800; color: #475569; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
    .maint-row { border-bottom: 1px solid #f1f5f9; transition: all 0.2s; position: relative; }
    .maint-row:hover { background: #f8fafc !important; }
    .maint-row:hover td:first-child { box-shadow: inset 4px 0 0 #0038A8; border-top-left-radius: 4px; border-bottom-left-radius: 4px; }
    .maint-td-id { padding: 12px; font-weight: 700; color: #0038A8; }
    .maint-td-name { padding: 12px; color: #1e293b; font-weight: 600; }
    .maint-td-office { padding: 12px; color: #64748b; }
    .maint-td-date { padding: 12px; color: #64748b; font-size: 12px; }
    .maint-td-status { padding: 12px; text-align: center; }
    .maint-td-assigned { padding: 12px; color: #374151; font-size: 13px; }
    .maint-td-actions { padding: 12px; text-align: center; }
    .maint-status { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; border: 1px solid transparent; }
    .maint-status-pending { background: #fffbeb; color: #92400e; border-color: rgba(245, 158, 11, 0.2); box-shadow: 0 2px 4px rgba(245, 158, 11, 0.15); }
    .maint-status-ongoing { background: #eff6ff; color: #1e40af; border-color: rgba(59, 130, 246, 0.2); box-shadow: 0 2px 4px rgba(59, 130, 246, 0.15); }
    .maint-status-completed { background: #ecfdf5; color: #065f46; border-color: rgba(16, 185, 129, 0.2); box-shadow: 0 2px 4px rgba(16, 185, 129, 0.15); }
    .maint-status-awaiting-signature { background: #faf5ff; color: #7e22ce; border-color: rgba(126, 34, 206, 0.2); box-shadow: 0 2px 4px rgba(126, 34, 206, 0.15); }
    .maint-status-cancelled { background: #f3f4f6; color: #6b7280; border-color: rgba(107, 114, 128, 0.2); }
    .maint-assigned-name { font-weight: 700; color: #0038A8; }
    .maint-assigned-none { color: #f59e0b; font-weight: 700; font-size: 11px; background: #fffbeb; padding: 2px 8px; border-radius: 20px; }
    .maint-btn-view { padding: 8px 16px; font-size: 12px; text-decoration: none; border-radius: 8px; min-height: 44px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; transition: all 0.2s; }
    .maint-btn-super { background-color: #0038A8; color: white; }
    .maint-btn-super:hover { background: #002d8c; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0, 56, 168, 0.2); }
    .maint-btn-admin { padding: 8px 16px; font-size: 12px; text-decoration: none; border: 1px solid #cbd5e1; border-radius: 8px; color: #475569; min-height: 44px; display: inline-flex; align-items: center; gap: 6px; font-weight: 700; transition: all 0.2s; }
    .maint-btn-admin:hover { background: #0038A8; border-color: #0038A8; color: white; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0, 56, 168, 0.2); }
    .maint-btn-it { background-color: #6366f1; color: white; }
    .maint-btn-it:hover { background: #4f46e5; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2); }
    .maint-empty { text-align: center; padding: 50px 20px; color: #94a3b8; }
    .maint-empty-icon { font-size: 40px; display: block; margin-bottom: 12px; opacity: 0.3; }
    .maint-empty-text { font-weight: 700; font-size: 15px; color: #64748b; }
    .maint-empty-sub { font-size: 13px; margin-top: 4px; }
    .maint-pagination { margin-top: 20px; }
    @media (max-width: 767px) {
        .maint-header { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; }
        .maint-header .btn-primary { width: 100% !important; justify-content: center !important; }
        .maint-table td a { padding: 10px 14px !important; font-size: 13px !important; width: 100% !important; justify-content: center !important; box-sizing: border-box !important; }
        .maint-table td { white-space: nowrap; }
        .card-header-accent { flex-direction: column !important; gap: 10px !important; }
        .filter-ribbon { flex-direction: column !important; gap: 10px !important; }
        .table-wrap { overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; }
        input, select, textarea { min-height: 48px !important; font-size: 15px !important; }
        .maint-btn-view, .maint-btn-super, .maint-btn-admin, .maint-btn-it { width: 100% !important; min-height: 48px !important; font-size: 14px !important; justify-content: center !important; }
    }
</style>
@endsection

@section('content')
<section class="content-section">
    <div class="maint-card">
        <div class="maint-header">
            <div class="maint-header-left">
                <i class="fa-solid fa-wrench maint-header-icon"></i>
                <h3 class="maint-header-title">Maintenance Requests</h3>
            </div>
            <a href="{{ route('pm-schedules.index') }}" class="btn-primary maint-pm-link">
                <i class="fa-solid fa-calendar-days"></i> PM Schedules
            </a>
        </div>

        <div class="maint-table-wrap">
            <table class="maint-table">
                <thead>
                    <tr class="maint-th">
                        <th class="maint-th-cell">Request #</th>
                        <th class="maint-th-cell">Requestor</th>
                        <th class="maint-th-cell">Office</th>
                        <th class="maint-th-cell">Date</th>
                        <th class="maint-th-cell">Completed At</th>
                        <th class="maint-th-cell" style="text-align: center;">Status</th>
                        <th class="maint-th-cell">Assigned To</th>
                        <th class="maint-th-cell" style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                    <tr class="maint-row">
                        <td class="maint-td-id">
                            {{ $req->display_number ?? $req->request_number }}
                            @if(!empty($req->is_auto_generated))
                                <span class="badge-auto">Auto</span>
                            @endif
                        </td>
                        <td class="maint-td-name">{{ $req->requestor_name }}</td>
                        <td class="maint-td-office">{{ $req->office }}</td>
                        <td class="maint-td-date">{{ $req->created_at->format('M d, Y | h:i A') }}</td>
                        <td class="maint-td-date">{{ $req->completed_at ? $req->completed_at->format('M d, Y | h:i A') : '—' }}</td>
                        <td class="maint-td-status">
                            <span class="maint-status maint-status-{{ strtolower(str_replace(' ', '-', $req->status)) }}">
                                {{ $req->status }}
                            </span>
                        </td>
                        <td class="maint-td-assigned">
                            @if($req->assignedTo)
                                <span class="maint-assigned-name">{{ $req->assignedTo->full_name }}</span>
                            @else
                                <span class="maint-assigned-none">⚠ Unassigned</span>
                            @endif
                        </td>
                        <td class="maint-td-actions">
                            @if(Auth::user()->role === 'super_admin')
                            <a href="{{ route('maintenance.edit', $req->id) }}" class="maint-btn-view maint-btn-super">
                                <i class="fa-solid fa-screwdriver-wrench"></i> View / Assign
                            </a>
                            @elseif(Auth::user()->role === 'admin')
                            <a href="{{ route('maintenance.show', $req->id) }}" class="maint-btn-admin">View</a>
                            @elseif(Auth::user()->role === 'it')
                            <a href="{{ route('maintenance.edit', $req->id) }}" class="maint-btn-view maint-btn-it">Open</a>
                            @else
                            <a href="{{ route('maintenance.edit', $req->id) }}" class="maint-btn-admin">View</a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="maint-empty">
                            <i class="fa-solid fa-toolbox maint-empty-icon"></i>
                            <div class="maint-empty-text">No maintenance records found</div>
                            <div class="maint-empty-sub">Scheduled PM tasks will appear here once created.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="maint-pagination">
                {{ $requests->links() }}
            </div>
        </div>
    </div>
</section>
@endsection