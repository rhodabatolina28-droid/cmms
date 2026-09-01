@extends('layouts.app')

@section('title', 'Supply Workspace')
@section('page-title', 'Supply Workspace')

@section('styles')
@include('requisitions.partials.official-assets')
<style nonce="{{ $cspNonce }}">
    .cmms-count-badge { font-size:0.75rem; color:#64748b; font-weight:600; }
    .cmms-pagination-bar { padding:14px 20px; border-top:1px solid #e2e8f0; }
    .table-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; }
    /* Containment: horizontal scroll stays inside the table area */
    .cmms-panel { max-width:100%; min-width:0; }
    .cmms-page-card, .cmms-page-card-body { max-width:100%; min-width:0; }
    @media (max-width:768px) {
        .cmms-req-table th, .cmms-req-table td { padding:10px 12px; font-size:12.5px; }
    }
    .text-muted-none { color:#94a3b8; font-size:0.8rem; }
    .td-nowrap { white-space:nowrap; }
    .cmms-req-actions--quick { display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-end; align-items:center; }
    .cmms-req-actions--quick .cmms-btn-primary,
    .cmms-req-actions--quick .cmms-btn-success,
    .cmms-req-actions--quick .cmms-btn-danger-ghost { margin:0; }
    /* Queue table row status accent */
    .cmms-req-table td:first-child { border-left:3px solid transparent; }
    .cmms-req-table tr.cmms-req-row--needs-review td:first-child { border-left-color:#d97706; }
    .cmms-req-table tr.cmms-req-row--awaiting-issue td:first-child { border-left-color:#16a34a; }
    .cmms-req-table tr.cmms-req-row { transition:background .12s ease; }
    .cmms-req-table tr.cmms-req-row:hover td { background:#f8fafc; }
    .cmms-req-table tr.cmms-req-details-row > td { background:#f8fafc; border-bottom:1px solid #e2e8f0; }
    .cmms-req-table .right { text-align:right; }

    /* PR queue — waiting visuals (Phase 2) */
    .pr-wait-dot { display:inline-block; width:6px; height:6px; border-radius:50%; background:#d97706; margin-right:5px; vertical-align:middle; animation:prQueuePulse 1.6s ease-in-out infinite; }
    @keyframes prQueuePulse { 0%,100% { opacity:1; } 50% { opacity:.25; } }
    .pr-wait-badge { display:inline-block; margin-top:4px; padding:1px 8px; border-radius:10px; font-size:10px; font-weight:700; }
    .pr-wait-badge.warn { background:#fef3c7; color:#92400e; }
    .pr-wait-badge.urgent { background:#fee2e2; color:#b91c1c; }

    /* PR records table — natural column sizing, horizontal scroll fallback */
    .cmms-pr-table { min-width:900px; width:100%; }
    .cmms-pr-table th, .cmms-pr-table td { vertical-align:middle; }
    .cmms-pr-table .nowrap { white-space:nowrap; }
    /* Icon action buttons - same look as Parts & Consumables rows */
    .act-btn { width:30px; height:30px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; border:1px solid #e2e8f0; background:#fff; color:#475569; cursor:pointer; font-size:12px; transition:all .15s; text-decoration:none; }
    .act-btn:hover { border-color:#0038A8; color:#0038A8; background:#eff6ff; }
    .act-btn.in { border-color:#bbf7d0; color:#15803d; }
    .act-btn.in:hover { background:#f0fdf4; border-color:#86efac; color:#166534; }
    .act-btn.out { border-color:#fecaca; color:#b91c1c; }
    .act-btn.out:hover { background:#fef2f2; border-color:#fca5a5; }
    .act-btn:disabled { opacity:.4; cursor:not-allowed; }
    .act-btn:focus-visible { outline:2px solid #0038A8; outline-offset:2px; }
    .cmms-req-details-chevron { transition:transform .15s; }
    .cmms-req-details-btn.is-open .cmms-req-details-chevron { transform:rotate(180deg); }
    /* Column alignment: fixed layout - ITEMS is the wide middle column; rest compact */
    table.cmms-ticket-table { min-width:820px; }
    .cmms-req-table { table-layout:fixed; min-width:860px; }
    .cmms-req-table th, .cmms-req-table td { vertical-align:middle; }
    .cmms-req-table .cell-trim { overflow-wrap:anywhere; word-break:break-word; }
    .cmms-req-table th {
        padding:12px 16px; font-size:11px; text-transform:uppercase; letter-spacing:.5px;
        color:#64748b; background:#f8fafc; border-bottom:2px solid #e2e8f0; text-align:center;
    }
    .cmms-req-table th:first-child { text-align:left; }
    .cmms-req-table td {
        padding:13px 16px; font-size:13.5px;
        border-bottom:1px solid #f1f5f9; color:#1e293b;
        text-align:center;
    }
    .cmms-req-table td:first-child { text-align:left; }
    .cmms-req-table tr.cmms-req-details-row > td { text-align:left; }
    .cmms-req-table td.td-nowrap { white-space:normal; }
    .cmms-req-table .cmms-qbtn-group, .cmms-req-table .row-actions { justify-content:center; flex-wrap:wrap; }
    @media (max-width: 767px) {
        .card-header-accent { flex-direction: column !important; gap: 10px !important; }
        .filter-ribbon { flex-direction: column !important; gap: 10px !important; }
        .table-wrap { overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; }
        input, select, textarea { min-height: 48px !important; font-size: 15px !important; }
        .btn, button:not(#sidebarToggle):not(#notifBell):not(.swal2-confirm):not(.swal2-cancel):not(.act-btn) { min-height: 48px !important; width: 100% !important; font-size: 14px !important; }
        .cmms-pagination-bar { padding: 10px 12px !important; }
    }

    /* Government Formal Tabs */
    .cmms-gov-tabs {
        display: flex;
        gap: 24px;
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 20px;
    }
    .cmms-gov-tabs a {
        padding: 10px 4px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        text-decoration: none;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .cmms-gov-tabs a:hover { color: #0f172a; }
    .cmms-gov-tabs a.active { color: #0038A8; border-bottom-color: #0038A8; }
    .gov-badge {
        background: #fee2e2;
        color: #b91c1c;
        padding: 2px 6px;
        border-radius: 12px;
        font-size: 10px;
        line-height: 1;
    }

    /* QUEUE SUMMARY stat cards */
    .queue-summary-cards { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:10px; margin-bottom:14px; }
    .qstat { display:block; background:#fff; border:1px solid var(--cmms-border); border-radius:10px; padding:12px 14px; text-decoration:none; transition:all .15s; }
    .qstat:hover { transform:translateY(-2px); box-shadow:0 10px 22px rgba(15,23,42,.08); }
    .qstat:focus-visible { outline:2px solid #0038A8; outline-offset:2px; }
    .qstat.active { border-color:#0038A8; box-shadow:inset 0 0 0 1px #0038A8, 0 6px 16px rgba(0,56,168,.08); }
    .qstat-val { display:block; font-size:22px; font-weight:900; color:#0f172a; line-height:1.2; }
    .qstat-lbl { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
    .qstat--pending.active { border-color:#d97706; box-shadow:inset 0 0 0 1px #d97706; }
    .qstat--pending.active .qstat-val { color:#b45309; }
    .qstat--issued.active { border-color:#16a34a; box-shadow:inset 0 0 0 1px #16a34a; }
    .qstat--issued.active .qstat-val { color:#15803d; }
    .qstat--rejected.active { border-color:#dc2626; box-shadow:inset 0 0 0 1px #dc2626; }
    .qstat--rejected.active .qstat-val { color:#b91c1c; }
    
    
    /* Queue toolbar: search + sort */
    .cmms-queue-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; margin-bottom:14px; }
    .cmms-queue-toolbar form { display:flex; align-items:center; gap:9px; flex:1; min-width:230px; background:#fff; border:1px solid var(--cmms-border); border-radius:8px; padding:8px 12px; }
    .cmms-queue-toolbar form > i { color:#94a3b8; font-size:13px; }
    .cmms-queue-toolbar form input[type="text"] { border:none !important; outline:none; box-shadow:none; flex:1; min-width:120px; font-size:13px; background:transparent; padding:0; margin:0; }
    .cmms-queue-toolbar form input[type="text"]:focus { border:none !important; }
    .cmms-queue-toolbar .cmms-filter-pill { white-space:nowrap; }
    .cmms-filter-pill .chip-cnt { display:inline-block; margin-left:6px; padding:0 7px; border-radius:10px; background:#e2e8f0; color:#475569; font-size:10.5px; font-weight:800; }
    .cmms-filter-pill.active .chip-cnt { background:#0038A8; color:#fff; }
    .cmms-queue-toolbar.pr-chips { justify-content:flex-start; gap:8px; }
    .cmms-sort-toggle { display:inline-flex; border:1px solid var(--cmms-border); border-radius:8px; overflow:hidden; background:#fff; }
    .cmms-sort-toggle a { display:inline-flex; align-items:center; gap:6px; padding:8px 13px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#64748b; text-decoration:none; transition:all .15s; }
    .cmms-sort-toggle a + a { border-left:1px solid var(--cmms-border); }
    .cmms-sort-toggle a:hover { color:#0038A8; background:#f8fafc; }
    .cmms-sort-toggle a.active { background:#0038A8; color:#fff; }

    @media (max-width: 768px) {
        .cmms-queue-toolbar { flex-direction: column; align-items: stretch; }
        .cmms-sort-toggle { justify-content: stretch; }
        .cmms-sort-toggle a { flex: 1; justify-content: center; }
    }
</style>
@endsection

@section('content')
@php
    $supplyView = $supplyView ?? 'queue';
    $filter = $filter ?? 'all';
    $q = isset($q) ? trim((string) $q) : '';
    $sort = $sort ?? 'newest';
    // Carry current search/sort context across tab & filter links (drop empties
    // so default URLs stay clean and unchanged).
    $carryParams = function (array $base) use ($q, $sort) {
        if ($q !== '') { $base['q'] = $q; }
        if (in_array(($base['view'] ?? ''), ['queue'], true) && $sort !== 'newest') { $base['sort'] = $sort; }
        return array_filter($base, fn ($v) => $v !== '' && $v !== null);
    };
    $filterLabels = [
        'pending' => 'Pending review',
        'approved' => 'Ready to issue',
        'issued' => 'Issued',
        'rejected' => 'Rejected',
        'all' => 'All records',
    ];
@endphp
<div class="cmms-official cmms-official-page">
    <div class="cmms-page-card">
        <div class="cmms-page-card-head">
            <div>
                <h2>Supply Workspace</h2>
                <div class="sub">{{ Auth::user()->region }}@if(Auth::user()->office) &middot; {{ Auth::user()->office }}@endif</div>
            </div>
            <a href="{{ route(Auth::user()->role === 'super_admin' ? 'dashboard.super-admin' : 'dashboard.admin') }}" class="cmms-btn-secondary">Return to dashboard</a>
        </div>
        <div class="cmms-page-card-body">
            <div class="cmms-gov-tabs">
                <a href="{{ route('requisitions.index', $carryParams(['view' => 'queue'])) }}" class="{{ $supplyView === 'queue' ? 'active' : '' }}" @if($supplyView === 'queue') aria-current="page" @endif>
                    REQUISITION QUEUE
                    @if($supplyView === 'queue' && isset($counts['pending']) && $counts['pending'] > 0)
                        <span class="gov-badge">{{ $counts['pending'] }}</span>
                    @endif
                </a>
                <a href="{{ route('requisitions.index', $carryParams(['view' => 'tickets'])) }}" class="{{ $supplyView === 'tickets' ? 'active' : '' }}" @if($supplyView === 'tickets') aria-current="page" @endif>
                    JOB ORDERS
                </a>
                <a href="{{ route('requisitions.index', $carryParams(['view' => 'purchase-requests'])) }}" class="{{ $supplyView === 'purchase-requests' ? 'active' : '' }}" @if($supplyView === 'purchase-requests') aria-current="page" @endif>
                    PURCHASE REQUESTS
                    @if($supplyView !== 'purchase-requests' && isset($prCounts['submitted']) && $prCounts['submitted'] > 0)
                        <span class="gov-badge">{{ $prCounts['submitted'] }}</span>
                    @endif
                </a>
            </div>

    @if($supplyView === 'queue')
        @php
            $baseCounts = [
                'pending' => ['To review', $counts['pending'] ?? 0],
                'approved' => ['Ready to issue', $counts['approved'] ?? 0],
                'issued' => ['Issued', $counts['issued'] ?? 0],
                'rejected' => ['Rejected', $counts['rejected'] ?? 0],
            ];
            // "All records" leads the row so the default view is first.
            $queueCounts = ['all' => ['All records', array_sum(array_column($baseCounts, 1))]] + $baseCounts;
        @endphp
        <div class="queue-summary-cards">
            @foreach($queueCounts as $key => [$label, $num])
            <a href="{{ route('requisitions.index', array_filter(['view' => 'queue', 'status' => $key, 'q' => $q], fn ($v) => $v !== '' && $v !== null)) }}"
               class="qstat qstat--{{ $key }} {{ $filter === $key ? 'active' : '' }}" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}">
                <span class="qstat-val">{{ $num }}</span>
                <span class="qstat-lbl">{{ $label }}</span>
            </a>
            @endforeach
        </div>

        <div class="cmms-queue-toolbar">
            <form method="GET" action="{{ route('requisitions.index') }}" role="search">
                <input type="hidden" name="view" value="queue">
                <input type="hidden" name="status" value="{{ $filter }}">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="q" value="{{ $q }}" placeholder="Search REQ no / requester / JO no / item..." maxlength="100" aria-label="Search requisitions">
                @if($q !== '')
                    <a href="{{ route('requisitions.index', ['view' => 'queue', 'status' => $filter]) }}" class="cmms-filter-pill" title="Clear search">&times; Clear</a>
                @endif
            </form>
            <div class="cmms-sort-toggle" role="group" aria-label="Sort order">
                <a href="{{ route('requisitions.index', array_filter(['view' => 'queue', 'status' => $filter, 'q' => $q], fn ($v) => $v !== '' && $v !== null)) }}" class="{{ $sort !== 'oldest' ? 'active' : '' }}">Newest</a>
                <a href="{{ route('requisitions.index', array_filter(['view' => 'queue', 'status' => $filter, 'q' => $q, 'sort' => 'oldest'], fn ($v) => $v !== '' && $v !== null)) }}" class="{{ $sort === 'oldest' ? 'active' : '' }}"><i class="fa-solid fa-arrow-down-short-wide"></i> Oldest first</a>
            </div>
        </div>

        <div class="cmms-panel">
            <div class="cmms-panel-head">
                <h2>Parts requisition records <span style="font-size:12px;font-weight:700;color:#64748b;">&mdash; <span id="queueFilterLabel">{{ $filterLabels[$filter] ?? ucfirst($filter) }}</span></span></h2>
                <span class="cmms-count-badge"><span id="queueTotalCount">{{ $requisitions->total() }}</span> record(s)</span>
            </div>
            <div class="cmms-panel-body flush" id="queuePanelBody">
                <div class="table-wrap">
                    <table class="cmms-official-table cmms-ticket-table cmms-req-table">
                        <colgroup>
                            <col style="width:10%">
                            <col style="width:13%">
                            <col style="width:13%">
                            <col style="width:20%">
                            <col style="width:12%">
                            <col style="width:13%">
                            <col style="width:7%">
                            <col>
                        </colgroup>
                        <thead>
                            <tr>
                                <th>REQ #</th>
                                <th>Requester</th>
                                <th>Job order</th>
                                <th>Items</th>
                                <th>Date</th>
                                <th>Completed</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="queueTableBody">
                            @forelse($requisitions as $req)
                                @include('requisitions.partials.req-table-row', [
                                    'req' => $req,
                                    'showRequester' => true,
                                    'quickActions' => true,
                                ])
                            @empty
                                <tr><td colspan="8" style="text-align:center;padding:30px 16px;color:#64748b;">No requisitions under this classification.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="queuePagination" class="cmms-pagination-bar">
                @if($requisitions->hasPages())
                    {{ $requisitions->links('vendor.pagination.parts') }}
                @endif
            </div>
        </div>
    @elseif($supplyView === 'tickets')
        <div class="cmms-queue-toolbar">
            <form method="GET" action="{{ route('requisitions.index') }}" role="search">
                <input type="hidden" name="view" value="tickets">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="q" value="{{ $q }}" placeholder="Search job order no / end user / assigned IT..." maxlength="100" aria-label="Search job orders">
                @if($q !== '')
                    <a href="{{ route('requisitions.index', ['view' => 'tickets']) }}" class="cmms-filter-pill" title="Clear search">&times; Clear</a>
                @endif
            </form>
        </div>
        <div class="cmms-panel">
            <div class="cmms-panel-head">
                <h2>Job orders</h2>
                    <span class="cmms-count-badge">Read-only &middot; {{ $ictTickets->total() }} ticket(s)</span>
            </div>
            <div class="cmms-panel-body flush">
                @if($ictTickets->isEmpty())
                    <div class="cmms-empty">
                        <i class="fa-solid fa-clipboard-list cmms-empty-icon"></i>
                        <h3 style="margin:0 0 6px;color:#475569;">No job orders</h3>
                        <p>No assigned job orders fall within your office scope.</p>
                    </div>
                @else
                    <div class="table-wrap">
                        <table class="cmms-official-table cmms-ticket-table">
                            <thead>
                                <tr>
                                    <th>Job order</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Assigned IT</th>
                                    <th>End user</th>
                                    <th>Parts requests</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="ticketsTableBody">
                                @foreach($ictTickets as $t)
                                <tr>
                                    <td><strong>{{ $t->display_number ?? $t->request_number }}</strong></td>
                                    <td><span class="cmms-req-tag {{ $t->type === 'Preventive Maintenance' ? 'cmms-req-tag--issue' : 'cmms-req-tag--review' }}">{{ $t->type === 'Preventive Maintenance' ? 'PM' : 'ICT' }}</span></td>
                                    <td><span class="cmms-ticket-status cmms-ticket-status--{{ strtolower(str_replace(' ', '-', $t->status)) }}">{{ $t->status }}</span></td>
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
                                            <a href="{{ route('requisitions.show', $latest->id) }}" class="cmms-btn-secondary">Latest REQ</a>
                                        @endif
                                        <a href="{{ $t->type === 'Preventive Maintenance' ? route('maintenance.show', $t->id) : route('ict.show', $t->id) }}" class="cmms-btn-secondary" target="_blank">Job order</a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            <div id="ticketsPagination" class="cmms-pagination-bar">
                @if($ictTickets->hasPages())
                    {{ $ictTickets->links('vendor.pagination.parts') }}
                @endif
            </div>
        </div>
    @elseif($supplyView === 'purchase-requests')
        {{-- ===== TAB 3: PURCHASE REQUESTS (PR document flow) ===== --}}
        <div class="cmms-queue-toolbar pr-chips">
            @php
                $prFilter = request()->query('status', 'all');
                $allPrCount = ($prCounts['submitted'] ?? 0) + ($prCounts['finalized'] ?? 0) + ($prCounts['delivered'] ?? 0);
                $prChips = [
                    'all' => ['All', $allPrCount],
                    'submitted' => ['Submitted', $prCounts['submitted'] ?? 0],
                    'finalized' => ['Finalized', $prCounts['finalized'] ?? 0],
                    'delivered' => ['Delivered', $prCounts['delivered'] ?? 0],
                ];
            @endphp
            @foreach($prChips as $key => [$label, $cnt])
                <a href="{{ route('requisitions.index', $carryParams(['view' => 'purchase-requests', 'status' => $key])) }}"
                   data-pr-status="{{ $key }}"
                   class="cmms-filter-pill {{ ($prFilter === $key || ($key === 'all' && !in_array($prFilter, ['submitted','finalized','delivered'], true))) ? 'active' : '' }}"
                   aria-current="{{ ($prFilter === $key || ($key === 'all' && !in_array($prFilter, ['submitted','finalized','delivered'], true))) ? 'page' : 'false' }}">
                    {{ $label }}<span class="chip-cnt">{{ $cnt }}</span>
                </a>
            @endforeach
        </div>

        <div class="cmms-panel">
            <div class="cmms-panel-head">
                <h2>Purchase Requests</h2>
                <span class="cmms-count-badge"><span id="prTotalCount">{{ $requests->total() }}</span> record(s)</span>
            </div>
            <div class="cmms-panel-body flush">
                @if($requests->isEmpty())
                    <div class="cmms-empty">
                        <i class="fa-solid fa-folder-open cmms-empty-icon"></i>
                        <p>
                            @if($prFilter === 'finalized')
                                No finalized purchase requests yet.
                            @elseif($prFilter === 'submitted')
                                No submitted purchase requests awaiting review.
                            @else
                                No purchase request records yet.
                            @endif
                        </p>
                    </div>
                @else
                    <div class="table-wrap">
                        <table class="cmms-official-table cmms-req-table cmms-pr-table">
                            <thead>
                                <tr>
                                    <th>PR #</th><th>Requested by</th>
                                    <th>Items</th><th>Total</th>
                                    <th>Date submitted</th><th>Status</th><th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="prTableBody">
                                @include('purchase-requests.partials.pr-table-rows')
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            <div id="prPagination" class="cmms-pagination-bar">
                @if($requests->hasPages())
                    {{ $requests->links('vendor.pagination.parts') }}
                @endif
            </div>
        </div>
    @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
(function () {
    const baseUrl = @json(url('requisitions'));
    const queueDataUrl = @json(route('requisitions.queue.data'));

    // ── Queue AJAX loading (no full page reload — same UX as Inventory/Parts) ──
    const queueFilterLabel = document.getElementById('queueFilterLabel');
    const queueTotalCount = document.getElementById('queueTotalCount');
    const queueTableBody = document.getElementById('queueTableBody');
    const queuePagination = document.getElementById('queuePagination');

    const FILTER_LABELS = { all: 'All records', pending: 'Pending review', approved: 'Ready to issue', issued: 'Issued', rejected: 'Rejected' };

    function loadQueue(params) {
        const qs = new URLSearchParams(params || {});
        if (!qs.has('view')) qs.set('view', 'queue');
        const sInput = document.querySelector('.cmms-queue-toolbar input[name="q"]');
        const srt = document.querySelector('.cmms-sort-toggle a.active');
        if (!qs.get('status')) qs.set('status', 'all');
        if (sInput && sInput.value.trim()) qs.set('q', sInput.value.trim());

        fetch(queueDataUrl + '?' + qs.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            if (queueTableBody) queueTableBody.innerHTML = data.rows;
            if (queuePagination) queuePagination.innerHTML = data.pagination;
            if (queueTotalCount) queueTotalCount.textContent = data.total;
            if (queueFilterLabel) queueFilterLabel.textContent = FILTER_LABELS[data.filter] || data.filter;
            bindQuickActions();
        })
        .catch(() => {});
    }

    function bindQuickActions() {
        document.querySelectorAll('.cmms-req-details-btn').forEach(btn => {
            if (btn._bound) return;
            btn._bound = true;
            btn.addEventListener('click', () => {
                const panel = document.getElementById('req-details-' + btn.dataset.rid);
                if (!panel) return;
                const open = panel.hasAttribute('hidden');
                btn.classList.toggle('is-open', open);
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) panel.removeAttribute('hidden');
                else panel.setAttribute('hidden', '');
            });
        });

        document.querySelectorAll('.supply-quick-btn').forEach(btn => {
            if (btn._bound) return;
            btn._bound = true;
            btn.addEventListener('click', async () => {
                await runQuickAction(btn);
            });
        });
    }

    async function runQuickAction(btn) {
        if (!window.Swal) return;
        const action = btn.dataset.action;
        const id = btn.dataset.id;
        const pr = btn.dataset.pr;
        const isReject = action === 'reject';
        const issueDestination = btn.dataset.issueDestination;
        const labels = { approve: 'Approve', reject: 'Disapprove', issue: 'Issue to asset custodian' };
        const { value: remarks, isConfirmed } = await window.Swal.fire({
            title: labels[action] + ' ' + pr,
            text: isReject
                ? 'Provide a reason for disapproval.'
                : action === 'issue' && issueDestination
                    ? 'Issued units will be assigned to ' + issueDestination + '.'
                    : 'Add an optional note, then confirm.',
            input: isReject ? 'textarea' : 'text',
            inputPlaceholder: isReject ? 'Reason for disapproval' : 'Optional note',
            inputAttributes: { maxlength: '500' },
            showCancelButton: true,
            confirmButtonColor: '#0038A8',
            confirmButtonText: 'Confirm',
            inputValidator: (v) => {
                if (isReject && (!v || !v.trim())) return 'Please provide a reason for disapproval.';
            },
        });
        if (!isConfirmed) return;
        btn.disabled = true;
        try {
            const res = await fetch(baseUrl + '/' + id + '/review', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ action, remarks: remarks || '' }),
            });
            const data = await res.json();
            if (data.success) {
                if (window.Swal) await window.Swal.fire({ icon: 'success', title: 'Recorded', text: data.message, confirmButtonColor: '#0038A8' });
                loadQueue();
                return;
            }
            if (window.Swal) window.Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#0038A8' });
        } catch (e) {
            if (window.Swal) window.Swal.fire({ icon: 'error', title: 'Network error', confirmButtonColor: '#0038A8' });
        }
        btn.disabled = false;
    }
    // ── Pagination links → AJAX (no reload) ──
    function bindQueuePagination() {
        if (!queuePagination) return;
        queuePagination.querySelectorAll('a').forEach(a => {
            if (a._bound) return;
            a._bound = true;
            a.addEventListener('click', (e) => {
                e.preventDefault();
                const href = a.getAttribute('href');
                if (href) {
                    const params = Object.fromEntries(new URLSearchParams(href.split('?')[1] || ''));
                    loadQueue(params);
                }
            });
        });
    }

    // ── Queue search / sort / stat-card filters → AJAX (no reload) ──
    const queueSearchForm = document.querySelector('.cmms-queue-toolbar form');
    if (queueSearchForm) {
        queueSearchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const fd = new FormData(queueSearchForm);
            loadQueue({ status: fd.get('status') || 'all', q: fd.get('q') || '' });
        });
    }
    document.querySelectorAll('.cmms-sort-toggle a').forEach(a => {
        a.addEventListener('click', (e) => {
            e.preventDefault();
            const isOldest = a.textContent.includes('Oldest');
            const fd = queueSearchForm ? new FormData(queueSearchForm) : new FormData();
            loadQueue({ status: fd.get('status') || 'all', q: fd.get('q') || '', sort: isOldest ? 'oldest' : 'newest' });
        });
    });


    // Initial bindings for server-rendered queue
    bindQuickActions();
    bindQueuePagination();
    // ── Job Orders tab AJAX (no reload) ──
    const ticketsTableBody = document.getElementById('ticketsTableBody');
    const ticketsPagination = document.getElementById('ticketsPagination');
    const ticketsSearchForm = document.querySelector('.cmms-queue-toolbar form input[name="view"][value="tickets"]')?.closest('form');

    function loadTickets(page) {
        const params = new URLSearchParams();
        params.set('view', 'tickets');
        if (ticketsSearchForm) {
            const qVal = ticketsSearchForm.querySelector('input[name="q"]').value.trim();
            if (qVal) params.set('q', qVal);
        }
        if (page) params.set('page', page);

        fetch(@json(route('requisitions.tickets.data')) + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            if (ticketsTableBody) ticketsTableBody.innerHTML = data.rows;
            if (ticketsPagination) ticketsPagination.innerHTML = data.pagination;
            bindTicketsPagination();
        })
        .catch(() => {});
    }

    function bindTicketsPagination() {
        if (!ticketsPagination) return;
        ticketsPagination.querySelectorAll('a').forEach(a => {
            if (a._bound) return;
            a._bound = true;
            a.addEventListener('click', (e) => {
                e.preventDefault();
                const page = new URLSearchParams(a.getAttribute('href').split('?')[1] || '').get('page');
                loadTickets(page);
            });
        });
    }

    if (ticketsTableBody && ticketsSearchForm) {
        ticketsSearchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            loadTickets(1);
        });
        bindTicketsPagination();
    }

    // ── Purchase Requests tab AJAX (no reload) ──
    const prTableBody = document.getElementById('prTableBody');
    const prPagination = document.getElementById('prPagination');
    const prTotalCount = document.getElementById('prTotalCount');

    function loadPr(status, page) {
        const params = new URLSearchParams();
        params.set('status', status || 'all');
        if (page) params.set('page', page);

        fetch(@json(route('requisitions.pr.data')) + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            if (prTableBody) prTableBody.innerHTML = data.rows;
            if (prPagination) prPagination.innerHTML = data.pagination;
            if (prTotalCount) prTotalCount.textContent = data.total;
            bindPrPagination();
            bindPrFinalize();
        })
        .catch(() => {});
    }

    function bindPrPagination() {
        if (!prPagination) return;
        prPagination.querySelectorAll('a').forEach(a => {
            if (a._bound) return;
            a._bound = true;
            a.addEventListener('click', (e) => {
                e.preventDefault();
                const page = new URLSearchParams(a.getAttribute('href').split('?')[1] || '').get('page');
                loadPr(null, page);
            });
        });
    }

    function bindPrFinalize() {
        document.querySelectorAll('.pr-finalize-btn').forEach(btn => {
            if (btn._bound) return;
            btn._bound = true;
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const form = btn.closest('form');
                if (!window.Swal) { form.submit(); return; }
                Swal.fire({
                    icon: 'question',
                    title: 'Finalize this Purchase Request?',
                    text: (btn.dataset.pr || '') + ' will be marked finalized and ready to print for physical submission to Procurement.',
                    showCancelButton: true,
                    confirmButtonText: 'Finalize',
                    confirmButtonColor: '#0038A8',
                    cancelButtonColor: '#64748b',
                }).then((r) => { if (r.isConfirmed) form.submit(); });
            });
        });
    }

    // PR filter chips → AJAX
    document.querySelectorAll('.cmms-filter-pill[data-pr-status]').forEach(chip => {
        chip.addEventListener('click', (e) => {
            e.preventDefault();
            loadPr(chip.dataset.prStatus, 1);
        });
    });

    bindPrPagination();
    bindPrFinalize();
})();
</script>
@endsection
