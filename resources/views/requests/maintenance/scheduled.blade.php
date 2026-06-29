@php
    $pageTitle = 'Scheduled PM Tasks';
@endphp

@extends('layouts.app')

@section('title', $pageTitle)
@section('page-title', $pageTitle)

@section('styles')
<style nonce="{{ $cspNonce }}">
    .sched-table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
    .sched-table th { background: #f8fafc; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; text-align: left; border-bottom: 2px solid #e2e8f0; }
    .sched-table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
    .sched-table tr:hover td { background: #f8fafc; }
    .badge-scheduled { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; background: #fef3c7; color: #92400e; }
    .btn-conduct { display: inline-flex; align-items: center; gap: 4px; padding: 11px 16px; background: #0038A8; color: white; border: none; border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; min-height: 44px; }
    .btn-conduct:hover { background: #002d8a; }
    .stat-count { font-size: 28px; font-weight: 800; color: #0038A8; line-height: 1; }
    .stat-label { font-size: 12px; color: #64748b; margin-top: 4px; }
    @media (max-width: 767px) {
        .sched-table { min-width: 650px; }
        .sched-table th, .sched-table td { padding: 8px 10px; }
        .sched-table td { font-size: 12px; }
        .stat-count { font-size: 22px; }
        .stats-grid { grid-template-columns: 1fr !important; }
    }
    .sched-wrap { border-radius: 12px; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
    .sched-container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
    .stat-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; text-align: center; }
    .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
    .empty-icon { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
    .empty-title { color: #64748b; }
    .empty-text { font-size: 14px; }
    .sched-id { font-weight: 700; color: #0038A8; }
    .sched-sn { font-size: 11px; color: #64748b; }
    .sched-date { font-size: 12px; color: #64748b; }
    .sched-pagination { margin-top: 24px; }
</style>
@endsection

@section('content')
<div class="sched-container">

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-count">{{ $requests->total() }}</div>
            <div class="stat-label">Total Scheduled PMs</div>
        </div>
        <div class="stat-card">
            <div class="stat-count">{{ $requests->whereNull('assigned_to')->count() }}</div>
            <div class="stat-label">Unassigned</div>
        </div>
        <div class="stat-card">
            <div class="stat-count">{{ $requests->where('assigned_to', '!=', null)->count() }}</div>
            <div class="stat-label">Assigned</div>
        </div>
    </div>

    @if($requests->isEmpty())
        <div class="empty-state">
            <i class="fa-solid fa-calendar-check empty-icon"></i>
            <h3 class="empty-title">No Scheduled PM Tasks</h3>
            <p class="empty-text">All preventive maintenance tasks have been completed or no schedules are due.</p>
        </div>
    @else
        <div class="sched-wrap"><table class="sched-table">
            <thead>
                <tr>
                    <th>Request No.</th>
                    <th>Asset</th>
                    <th>End User</th>
                    <th>Schedule</th>
                    <th>Created</th>
                    <th>Assigned To</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($requests as $req)
                    <tr>
                        <td class="sched-id">{{ $req->display_number ?? $req->request_number }}</td>
                        <td>
                            {{ $req->linkedAsset?->item_name ?? 'N/A' }}
                            <div class="sched-sn">SN: {{ $req->linkedAsset?->serial_number ?? 'N/A' }}</div>
                        </td>
                        <td>{{ $req->requestor_name ?? 'N/A' }}</td>
                        <td><span class="badge-scheduled">Scheduled</span></td>
                        <td class="sched-date">{{ $req->created_at->format('M d, Y') }}</td>
                        <td>{{ $req->assignedTo?->full_name ?? '—' }}</td>
                        <td>
                            <a href="{{ route('maintenance.edit', $req->id) }}" class="btn-conduct">
                                <i class="fa-solid fa-play"></i> Conduct PM
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>

        <div class="sched-pagination">
            {{ $requests->links() }}
        </div>
    @endif
</div>
@endsection
