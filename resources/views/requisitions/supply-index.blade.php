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
            <div class="cmms-view-switch">
                <a href="{{ route('requisitions.index', ['view' => 'queue', 'status' => $filter]) }}" class="{{ $supplyView === 'queue' ? 'active' : '' }}">Requisition Queue</a>
                <a href="{{ route('requisitions.index', ['view' => 'tickets']) }}" class="{{ $supplyView === 'tickets' ? 'active' : '' }}">ICT Job Orders</a>
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
        <div class="cmms-stat-strip">
            @foreach([
                'pending' => ['To review', $counts['pending'] ?? 0],
                'approved' => ['Ready to issue', $counts['approved'] ?? 0],
                'issued' => ['Issued', $counts['issued'] ?? 0],
                'rejected' => ['Rejected', $counts['rejected'] ?? 0],
            ] as $key => [$label, $num])
            <a href="{{ route('requisitions.index', ['view' => 'queue', 'status' => $key]) }}"
               class="cmms-stat-chip cmms-stat-chip--{{ $key }} {{ $filter === $key ? 'is-active' : '' }}">
                <div class="n">{{ $num }}</div>
                <div class="l">{{ $label }}</div>
            </a>
            @endforeach
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
            const labels = { approve: 'Approve', reject: 'Disapprove', issue: 'Issue parts' };
            const { value: remarks, isConfirmed } = await Swal.fire({
                title: labels[action] + ' ' + pr,
                text: isReject ? 'Provide a reason for disapproval.' : 'Add an optional note, then confirm.',
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
