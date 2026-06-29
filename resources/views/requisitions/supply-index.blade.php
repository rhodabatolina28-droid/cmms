@extends('layouts.app')

@section('title', 'Supply Workspace')
@section('page-title', 'Supply Workspace')

@section('styles')
@include('requisitions.partials.official-assets')
<style nonce="{{ $cspNonce }}">
    .cmms-count-badge { font-size:0.75rem; color:#64748b; font-weight:600; }
    .cmms-pagination-bar { padding:14px 20px; border-top:1px solid #e2e8f0; }
    .table-wrap { overflow-x:auto; }
    .text-muted-none { color:#94a3b8; font-size:0.8rem; }
    .td-nowrap { white-space:nowrap; }
    @media (max-width: 767px) {
        .card-header-accent { flex-direction: column !important; gap: 10px !important; }
        .filter-ribbon { flex-direction: column !important; gap: 10px !important; }
        .table-wrap { overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; }
        input, select, textarea { min-height: 48px !important; font-size: 15px !important; }
        .btn, button:not(#sidebarToggle):not(#notifBell):not(.swal2-confirm):not(.swal2-cancel) { min-height: 48px !important; width: 100% !important; font-size: 14px !important; }
        .cmms-pagination-bar { padding: 10px 12px !important; }
    }
</style>
@endsection

@section('content')
@php
    $supplyView = $supplyView ?? 'queue';
    $filter = $filter ?? 'pending';
@endphp
<div class="cmms-official cmms-official-page">

    <div class="cmms-official-hero">
        <div class="ref">National Conciliation and Mediation Board · Property and Supply</div>
        <h1>Supply Workspace</h1>
        <p class="sub">{{ Auth::user()->region }}@if(Auth::user()->office) · {{ Auth::user()->office }}@endif</p>
    </div>

    <div class="cmms-page-intro">
        <p>
            @if($supplyView === 'tickets')
                ICT job orders within your scope. Open the latest requisition when Supply action is needed.
            @else
                Parts requisitions submitted by IT personnel. Review, approve, reject, or issue from this queue.
            @endif
        </p>
        <a href="{{ route(Auth::user()->role === 'super_admin' ? 'dashboard.super-admin' : 'dashboard.admin') }}" class="cmms-btn-secondary">Return to dashboard</a>
    </div>

    <div class="cmms-view-switch">
        <a href="{{ route('requisitions.index', ['view' => 'queue', 'status' => $filter]) }}" class="{{ $supplyView === 'queue' ? 'active' : '' }}">Requisition Queue</a>
        <a href="{{ route('requisitions.index', ['view' => 'tickets']) }}" class="{{ $supplyView === 'tickets' ? 'active' : '' }}">ICT Job Orders</a>
    </div>

    @if($supplyView === 'queue')
        <div class="cmms-stat-strip">
            @foreach([
                'pending' => ['To review', $counts['pending'] ?? 0],
                'approved' => ['Ready to issue', $counts['approved'] ?? 0],
                'issued' => ['Issued', $counts['issued'] ?? 0],
                'rejected' => ['Rejected', $counts['rejected'] ?? 0],
            ] as $key => [$label, $num])
            <a href="{{ route('requisitions.index', ['view' => 'queue', 'status' => $key]) }}"
               class="cmms-stat-chip {{ $filter === $key ? 'is-active' : '' }}">
                <div class="n">{{ $num }}</div>
                <div class="l">{{ $label }}</div>
            </a>
            @endforeach
        </div>

        @if($filter !== 'all')
        <div class="cmms-filter-row">
            <a href="{{ route('requisitions.index', ['view' => 'queue', 'status' => 'all']) }}" class="cmms-filter-pill">View all records</a>
        </div>
        @endif

        <div class="cmms-panel">
            <div class="cmms-panel-head">
                <h2>Parts requisition records</h2>
                <span class="cmms-count-badge">{{ $requisitions->total() }} record(s)</span>
            </div>
            <div class="cmms-panel-body flush">
                @if($requisitions->isEmpty())
                    <div class="cmms-empty">
                        <i class="fa-solid fa-inbox" style="font-size:32px;color:#cbd5e1;display:block;margin-bottom:12px;"></i>
                        <h3 style="margin:0 0 6px;color:#475569;">No requisitions found</h3>
                        <p>There are no
                            @if($filter === 'pending') pending requisitions to review.
                            @elseif($filter === 'approved') approved requisitions awaiting issue.
                            @elseif($filter === 'issued') issued requisitions yet.
                            @elseif($filter === 'rejected') rejected requisitions.
                            @else requisitions under this classification.
                            @endif
                        </p>
                    </div>
                @else
                    <div class="cmms-req-list">
                        @foreach($requisitions as $req)
                            @include('requisitions.partials.req-card', [
                                'req' => $req,
                                'showRequester' => true,
                                'actionLabel' => 'Review',
                            ])
                        @endforeach
                    </div>
                @endif
            </div>
            @if($requisitions->hasPages())
            <div class="cmms-pagination-bar">{{ $requisitions->links() }}</div>
            @endif
        </div>
    @else
        <div class="cmms-panel">
            <div class="cmms-panel-head">
                <h2>ICT job orders</h2>
                <span class="cmms-count-badge">Read-only · {{ $ictTickets->total() }} ticket(s)</span>
            </div>
            <div class="cmms-panel-body flush">
                @if($ictTickets->isEmpty())
                    <div class="cmms-empty">
                        <i class="fa-solid fa-clipboard-list" style="font-size:32px;color:#cbd5e1;display:block;margin-bottom:12px;"></i>
                        <h3 style="margin:0 0 6px;color:#475569;">No ICT job orders</h3>
                        <p>No ICT assignments fall within your office scope.</p>
                    </div>
                @else
                    <div class="table-wrap">
                        <table class="cmms-official-table cmms-ticket-table">
                            <thead>
                                <tr>
                                    <th>Job order</th>
                                    <th>Status</th>
                                    <th>Assigned IT</th>
                                    <th>End user</th>
                                    <th>Parts requests</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ictTickets as $t)
                                <tr>
                                    <td><strong>{{ $t->display_number ?? $t->request_number }}</strong></td>
                                    <td><span class="cmms-ticket-status">{{ $t->status }}</span></td>
                                    <td>{{ $t->assignedTo?->full_name ?? '-' }}</td>
                                    <td>{{ $t->user?->full_name ?? '-' }}</td>
                                    <td>
                                        @php
                                            $byStatus = $t->requisitions->groupBy('status');
                                        @endphp
                                        @forelse($byStatus as $st => $group)
                                            <span class="req-pill cmms-status-{{ strtolower($st) }}">{{ ucfirst($st) }}: {{ $group->count() }}</span>
                                        @empty
                                            <span class="text-muted-none">None</span>
                                        @endforelse
                                    </td>
                                    <td class="td-nowrap">
                                        @if($t->requisitions->isNotEmpty())
                                            @php $latest = $t->requisitions->sortByDesc('created_at')->first(); @endphp
                                            <a href="{{ route('requisitions.show', $latest->id) }}" class="cmms-btn-secondary">Latest PR</a>
                                        @endif
                                        <a href="{{ route('ict.show', $t->id) }}" class="cmms-btn-secondary" target="_blank">Job order</a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            @if($ictTickets->hasPages())
            <div class="cmms-pagination-bar">{{ $ictTickets->links() }}</div>
            @endif
        </div>
    @endif
</div>
@endsection
