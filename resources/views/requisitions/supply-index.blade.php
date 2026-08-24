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
    .cmms-req-actions--quick { display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-end; align-items:center; }
    .cmms-req-actions--quick .cmms-btn-primary,
    .cmms-req-actions--quick .cmms-btn-success,
    .cmms-req-actions--quick .cmms-btn-danger-ghost { margin:0; }
    /* Queue table row status accent */
    .cmms-req-table td:first-child { border-left:3px solid transparent; }
    .cmms-req-table tr.cmms-req-row--needs-review td:first-child { border-left-color:#d97706; }
    .cmms-req-table tr.cmms-req-row--awaiting-issue td:first-child { border-left-color:#16a34a; }
    .cmms-req-table tr.cmms-req-row:hover td { background:#f8fafc; }
    .cmms-req-table tr.cmms-req-details-row > td { background:#f8fafc; border-bottom:1px solid #e2e8f0; }
    .cmms-req-table .right { text-align:right; }
    .cmms-qbtn-group { display:inline-flex; gap:6px; align-items:center; justify-content:flex-end; flex-wrap:wrap; }
    .cmms-qbtn { display:inline-flex; align-items:center; gap:5px; padding:6px 10px; border-radius:6px; font-size:11.5px; font-weight:700; border:none; cursor:pointer; text-decoration:none; transition:all .15s; }
    .cmms-qbtn--primary { background:#0038A8; color:#fff; }
    .cmms-qbtn--primary:hover { background:#002d8a; }
    .cmms-qbtn--success { background:#15803d; color:#fff; }
    .cmms-qbtn--success:hover { background:#116632; }
    .cmms-qbtn--danger-ghost { background:#fff; color:#b91c1c; border:1px solid #fecaca; }
    .cmms-qbtn--danger-ghost:hover { background:#fef2f2; }
    .cmms-qbtn--ghost { background:#fff; color:#475569; border:1px solid #cbd5e1; }
    .cmms-qbtn--ghost:hover { color:#0038A8; border-color:#0038A8; }
    .cmms-req-details-chevron { transition:transform .15s; }
    .cmms-req-details-btn.is-open .cmms-req-details-chevron { transform:rotate(180deg); }
    @media (max-width: 767px) {
        .card-header-accent { flex-direction: column !important; gap: 10px !important; }
        .filter-ribbon { flex-direction: column !important; gap: 10px !important; }
        .table-wrap { overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; }
        input, select, textarea { min-height: 48px !important; font-size: 15px !important; }
        .btn, button:not(#sidebarToggle):not(#notifBell):not(.swal2-confirm):not(.swal2-cancel) { min-height: 48px !important; width: 100% !important; font-size: 14px !important; }
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

    /* QUEUE SUMMARY Ã¢â‚¬â€ stat cards */
    .queue-summary-cards { display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin-bottom:14px; }
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
    .cmms-sort-toggle { display:inline-flex; border:1px solid var(--cmms-border); border-radius:8px; overflow:hidden; background:#fff; }
    .cmms-sort-toggle a { display:inline-flex; align-items:center; gap:6px; padding:8px 13px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#64748b; text-decoration:none; transition:all .15s; }
    .cmms-sort-toggle a + a { border-left:1px solid var(--cmms-border); }
    .cmms-sort-toggle a:hover { color:#0038A8; background:#f8fafc; }
    .cmms-sort-toggle a.active { background:#0038A8; color:#fff; }

    @media (max-width: 768px) {
        .queue-summary-cards { grid-template-columns: repeat(2, 1fr); }
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
                    ICT JOB ORDERS
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
                <h2>Parts requisition records <span style="font-size:12px;font-weight:700;color:#64748b;">&mdash; {{ $filterLabels[$filter] ?? ucfirst($filter) }}</span></h2>
                <span class="cmms-count-badge">{{ $requisitions->total() }} record(s)</span>
            </div>
            <div class="cmms-panel-body flush">
                @if($requisitions->isEmpty())
                    <div class="cmms-empty">
                        <i class="fa-solid fa-inbox cmms-empty-icon"></i>
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
                    <div class="table-wrap">
                        <table class="cmms-official-table cmms-ticket-table cmms-req-table">
                            <thead>
                                <tr>
                                    <th>REQ #</th>
                                    <th>Requester</th>
                                    <th>Job order</th>
                                    <th>Items</th>
                                    <th>Date filed</th>
                                    <th>Status</th>
                                    <th class="right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($requisitions as $req)
                                    @include('requisitions.partials.req-table-row', [
                                        'req' => $req,
                                        'showRequester' => true,
                                        'quickActions' => true,
                                    ])
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            @if($requisitions->hasPages())
            <div class="cmms-pagination-bar">{{ $requisitions->links() }}</div>
            @endif
        </div>
    @else
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
                <h2>ICT job orders</h2>
                <span class="cmms-count-badge">Read-only Ã‚Â· {{ $ictTickets->total() }} ticket(s)</span>
            </div>
            <div class="cmms-panel-body flush">
                @if($ictTickets->isEmpty())
                    <div class="cmms-empty">
                        <i class="fa-solid fa-clipboard-list cmms-empty-icon"></i>
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
    </div>
</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
(function () {
    if (typeof Swal === 'undefined') return;
    const baseUrl = @json(url('requisitions'));
    document.querySelectorAll('.supply-quick-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const action = btn.dataset.action;
            const id = btn.dataset.id;
            const pr = btn.dataset.pr;
            const isReject = action === 'reject';
            const issueDestination = btn.dataset.issueDestination;
            const labels = { approve: 'Approve', reject: 'Disapprove', issue: 'Issue to asset custodian' };
            const { value: remarks, isConfirmed } = await Swal.fire({
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
                    await Swal.fire({ icon: 'success', title: 'Recorded', text: data.message, confirmButtonColor: '#0038A8' });
                    window.location.reload();
                    return;
                }
                Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#0038A8' });
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Network error', confirmButtonColor: '#0038A8' });
            }
            btn.disabled = false;
        });
    });

    document.querySelectorAll('.cmms-req-details-btn').forEach(btn => {
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
})();
</script>
@endsection
