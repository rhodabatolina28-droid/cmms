@extends('layouts.app')

@section('title', 'Purchase Requests | NCMB CMMS')
@section('page-title', 'Purchase Requests')

@section('styles')
<style nonce="{{ $cspNonce }}">
    .prx-container { width: 100%; margin-top: -10px; animation: fadeInSlide 0.4s ease-out; }
    .prx-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
    .prx-head { background: #f8fafc; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; }
    .prx-filters { display: flex; flex-wrap: wrap; gap: 6px; }
    .prx-filter { padding: 7px 13px; border-radius: 20px; font-size: 12.5px; font-weight: 700; color: #475569; text-decoration: none; border: 1px solid #e2e8f0; background: #fff; }
    .prx-filter.active { background: #0038A8; color: #fff; border-color: #0038A8; }
    .prx-table { width: 100%; border-collapse: collapse; }
    .prx-table th { text-align: left; padding: 12px 16px; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #64748b; background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
    .prx-table td { padding: 13px 16px; font-size: 13.5px; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
    .prx-table tr:hover td { background: #f8fafc; }
    .p-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 800; }
    .p-pending { background: #fef3c7; color: #92400e; }
    .p-approved { background: #dbeafe; color: #1e40af; }
    .p-received { background: #dcfce7; color: #166534; }
    .p-cancelled { background: #fee2e2; color: #991b1b; }
    .empty-state { text-align: center; padding: 60px 20px; color: #64748b; }
    .empty-state .big { font-size: 40px; margin-bottom: 8px; }
    @media (max-width: 768px) {
        .prx-head { flex-direction: column; align-items: flex-start; }
        .prx-table { min-width: 600px; }
    }
</style>
@endsection

@section('content')
<div class="prx-container">
    @if(!empty($isSuperAdminView))
        <div class="p-badge p-approved" style="margin-bottom:12px;">👁 Read-only oversight — Super Admin</div>
    @endif

    <div class="prx-card">
        <div class="prx-head">
            <div class="prx-filters">
                <a href="{{ route($isSuperAdminView ? 'super_admin.purchase_requests' : 'purchase_requests.index', ['status' => 'pending']) }}" class="prx-filter {{ $filter === 'pending' ? 'active' : '' }}">Pending ({{ $counts['pending'] }})</a>
                <a href="{{ route($isSuperAdminView ? 'super_admin.purchase_requests' : 'purchase_requests.index', ['status' => 'approved']) }}" class="prx-filter {{ $filter === 'approved' ? 'active' : '' }}">Approved ({{ $counts['approved'] }})</a>
                <a href="{{ route($isSuperAdminView ? 'super_admin.purchase_requests' : 'purchase_requests.index', ['status' => 'received']) }}" class="prx-filter {{ $filter === 'received' ? 'active' : '' }}">Received ({{ $counts['received'] }})</a>
                <a href="{{ route($isSuperAdminView ? 'super_admin.purchase_requests' : 'purchase_requests.index', ['status' => 'cancelled']) }}" class="prx-filter {{ $filter === 'cancelled' ? 'active' : '' }}">Cancelled ({{ $counts['cancelled'] }})</a>
                <a href="{{ route($isSuperAdminView ? 'super_admin.purchase_requests' : 'purchase_requests.index', ['status' => 'all']) }}" class="prx-filter {{ $filter === 'all' ? 'active' : '' }}">All</a>
            </div>
            <span style="font-size:12px;color:#64748b;">Purchase Request · RA 9184</span>
        </div>

        @if($requests->count() === 0)
            <div class="empty-state">
                <div class="big">📄</div>
                <div>Wala pang purchase request dito.</div>
            </div>
        @else
            <div style="overflow-x:auto;">
                <table class="prx-table">
                    <thead>
                        <tr>
                            <th>PR Number</th>
                            <th>Status</th>
                            <th>Source</th>
                            <th>Items</th>
                            <th>Requested by</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $pr)
                        <tr>
                            <td style="font-weight:800;color:#0038A8;">{{ $pr->pr_number }}</td>
                            <td>
                                @php $cls = 'p-' . $pr->status; @endphp
                                <span class="p-badge {{ in_array($pr->status, ['pending','approved','received','cancelled']) ? $cls : 'p-pending' }}">{{ ucfirst($pr->status) }}</span>
                            </td>
                            <td>{{ $pr->requisition?->ticket?->display_number ?? ($pr->requisition?->ticket?->request_number ?? '—') }}</td>
                            <td>{{ count($pr->items ?? []) }}</td>
                            <td>{{ $pr->requester?->full_name ?? '—' }}</td>
                            <td>{{ $pr->created_at->format('M d, Y') }}</td>
                            <td><a href="{{ route(($isSuperAdminView ? 'super_admin.purchase_requests' : 'purchase_requests.show'), $pr->id) }}" class="prx-filter" style="text-decoration:none;border-color:#0038A8;color:#0038A8;">View</a></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="display:flex;justify-content:flex-end;padding:14px 20px;">{{ $requests->links() }}</div>
        @endif
    </div>
</div>
@endsection