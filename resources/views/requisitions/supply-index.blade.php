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
    .cmms-contents-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        margin: 0 0 14px;
        padding: 10px 14px;
        background: #f8fafc;
        border: 1px solid var(--cmms-border);
        border-radius: 8px;
        font-size: 13px;
        color: var(--cmms-muted);
        position: sticky;
        top: 78px;
        z-index: 20;
    }
    .cmms-contents-bar-label strong { color: var(--cmms-ink); }
    .cmms-req-card--needs-action { border-left:4px solid transparent; }
    .cmms-req-card--needs-review { border-left:4px solid #d97706; }
    .cmms-req-card--awaiting-issue { border-left:4px solid #16a34a; }
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

    /* Government Queue Summary */
    .cmms-gov-summary {
        background: #f8fafc;
        border: 1px solid #c5c9d0;
        border-radius: 6px;
        padding: 12px 18px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 24px;
    }
    .gov-summary-title {
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #475569;
        min-width: 120px;
        padding-right: 20px;
        border-right: 1px solid #cbd5e1;
    }
    .gov-summary-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 32px;
        flex: 1;
    }
    .gov-summary-item {
        text-decoration: none;
        display: flex;
        align-items: baseline;
        gap: 8px;
        opacity: 0.5;
        transition: opacity 0.2s;
    }
    .gov-summary-item:hover { opacity: 0.8; }
    .gov-summary-item.active { opacity: 1; border-bottom: 2px solid transparent; }
    .gov-summary-item .gov-val {
        font-size: 16px;
        font-weight: 800;
        color: #0f172a;
    }
    .gov-summary-item .gov-lbl {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
    }
    .gov-summary-item--pending.active .gov-val { color: #b45309; }
    .gov-summary-item--approved.active .gov-val { color: #0038A8; }
    .gov-summary-item--issued.active .gov-val { color: #15803d; }
    .gov-summary-item--rejected.active .gov-val { color: #b91c1c; }
    
    /* Refined Action Callout for Gov UX */
    .cmms-action-callout {
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin: 0 0 16px; padding: 12px 16px;
        background: #fff; border-left: 4px solid #0038A8; border-top: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;
        border-radius: 4px; font-size: 13px; color: #0f172a; box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    }
    .cmms-action-callout.is-clear { border-left-color: #15803d; }
    .cmms-action-callout i { font-size: 16px; color: #0038A8; }
    .cmms-action-callout.is-clear i { color: #15803d; }
    .cmms-action-callout strong { font-weight: 700; color: #0038A8; }
    .cmms-action-callout.is-clear strong { color: #15803d; }
    .cmms-callout-chip::before { content: " | "; color: #cbd5e1; margin: 0 4px; }
    
    @media (max-width: 768px) {
        .cmms-gov-summary { flex-direction: column; align-items: flex-start; gap: 12px; }
        .gov-summary-title { border-right: none; border-bottom: 1px solid #cbd5e1; padding-bottom: 8px; width: 100%; }
        .gov-summary-grid { gap: 16px; }
    }
</style>
@endsection

@section('content')
@php
    $supplyView = $supplyView ?? 'queue';
    $filter = $filter ?? 'pending';
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
                <a href="{{ route('requisitions.index', ['view' => 'queue', 'status' => $filter]) }}" class="{{ $supplyView === 'queue' ? 'active' : '' }}">
                    REQUISITION QUEUE
                    @if($supplyView === 'queue' && isset($counts['pending']) && $counts['pending'] > 0)
                        <span class="gov-badge">{{ $counts['pending'] }}</span>
                    @endif
                </a>
                <a href="{{ route('requisitions.index', ['view' => 'tickets']) }}" class="{{ $supplyView === 'tickets' ? 'active' : '' }}">
                    ICT JOB ORDERS
                </a>
            </div>

    @if($supplyView === 'queue')
        @php
            $toReview = (int) ($counts['pending'] ?? 0);
            $toIssue  = (int) ($counts['approved'] ?? 0);
            $needsAction = $toReview + $toIssue;
        @endphp
        <div class="cmms-action-callout {{ $needsAction > 0 ? '' : 'is-clear' }}">
            @if($needsAction > 0)
                <i class="fa-solid fa-bell"></i>
                <span>
                    You have <strong>{{ $needsAction }}</strong> requisition{{ $needsAction > 1 ? 's' : '' }} awaiting your action.
                    @if($toReview > 0)
                        <span class="cmms-callout-chip"><strong>{{ $toReview }}</strong> to review</span>
                    @endif
                    @if($toIssue > 0)
                        <span class="cmms-callout-chip"><strong>{{ $toIssue }}</strong> ready to issue</span>
                    @endif
                </span>
            @else
                <i class="fa-solid fa-circle-check"></i>
                <span>All caught up &mdash; no requisitions awaiting your action.</span>
            @endif
        </div>
        <div class="cmms-gov-summary">
            <div class="gov-summary-title">QUEUE SUMMARY</div>
            <div class="gov-summary-grid">
                @foreach([
                    'pending' => ['To review', $counts['pending'] ?? 0],
                    'approved' => ['Ready to issue', $counts['approved'] ?? 0],
                    'issued' => ['Issued', $counts['issued'] ?? 0],
                    'rejected' => ['Rejected', $counts['rejected'] ?? 0],
                ] as $key => [$label, $num])
                <a href="{{ route('requisitions.index', ['view' => 'queue', 'status' => $key]) }}"
                   class="gov-summary-item gov-summary-item--{{ $key }} {{ $filter === $key ? 'active' : '' }}">
                    <div class="gov-val">{{ $num }}</div>
                    <div class="gov-lbl">{{ $label }}</div>
                </a>
                @endforeach
            </div>
        </div>

        <div class="cmms-contents-bar">
            <div class="cmms-contents-bar-label">
                @if($filter === 'all')
                    Showing all requisition records
                @else
                    Showing: <strong>{{ $filterLabels[$filter] ?? ucfirst($filter) }}</strong> &middot; {{ $requisitions->total() }} record(s)
                @endif
            </div>
            @if($filter !== 'all')
            <a href="{{ route('requisitions.index', ['view' => 'queue', 'status' => 'all']) }}" class="cmms-filter-pill">View all records</a>
            @endif
        </div>

        <div class="cmms-panel">
            <div class="cmms-panel-head">
                <h2>Parts requisition records</h2>
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
                    <div class="cmms-req-list">
                        @foreach($requisitions as $req)
                            @include('requisitions.partials.req-card', [
                                'req' => $req,
                                'showRequester' => true,
                                'actionLabel' => 'Review',
                                'quickActions' => true,
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
