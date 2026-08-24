@extends('layouts.app')

@php
    $isIT = Auth::user()->role === 'it';
    $ticket = $requisition->ticket;
    $status = $requisition->status;
@endphp

@section('title', 'REQ-' . str_pad($requisition->id, 5, '0', STR_PAD_LEFT))
@section('page-title', $isIT ? 'IT Department — Requisition Details' : 'Supply Office — Requisition Review')

@section('styles')
@include('requisitions.partials.official-assets')
<style nonce="{{ $cspNonce }}">
    .mb-18 { margin-bottom:18px; }
    .mt-18 { margin-top:18px; }
    .panel-inventory { border-left:3px solid #10b981; }
    .panel-head-green { background:#f0fdf4; }
    .text-green-700 { color:#065f46; }
    .icon-green { margin-right:6px; color:#10b981; }
    .text-green-800 { color:#047857; }
    .badge-sm { font-size:0.75rem; font-weight:600; }
    .panel-body-flush { padding:0; }
    .info-bar { padding:10px 16px 6px; background:#f0fdf4; font-size:12px; color:#065f46; border-bottom:1px solid #d1fae5; }
    .icon-mr-4 { margin-right:4px; }
    .item-block { padding:14px 16px; border-bottom:1px solid #e2e8f0; }
    .item-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px; }
    .item-label { font-size:10px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.05em; }
    .item-title { font-weight:700; font-size:14px; color:#1e293b; }
    .badge-spare { background:#dcfce7; color:#166534; font-size:11px; font-weight:800; padding:3px 10px; border-radius:99px; }
    .asset-list { display:flex; flex-direction:column; gap:6px; }
    .asset-row { display:flex; justify-content:space-between; align-items:center; border-radius:6px; padding:8px 12px; flex-wrap:wrap; gap:8px; }
    .asset-row-spare { background:#f0fdf4; border:1px solid #bbf7d0; }
    .asset-row-other { background:#f8fafc; border:1px solid #e2e8f0; }
    .asset-name { font-weight:700; font-size:13px; color:#1e293b; }
    .asset-model { font-size:11px; color:#64748b; margin-left:6px; }
    .asset-sn { font-size:11px; color:#64748b; margin-top:2px; font-family:monospace; }
    .asset-actions { display:flex; align-items:center; gap:8px; }
    .badge-available { background:#dcfce7; color:#166534; font-size:10px; font-weight:800; padding:2px 8px; border-radius:99px; text-transform:uppercase; }
    .badge-status { background:#f1f5f9; color:#475569; font-size:10px; font-weight:800; padding:2px 8px; border-radius:99px; text-transform:uppercase; }
    .asset-link { font-size:11px; font-weight:700; color:#0038A8; text-decoration:none; white-space:nowrap; }
    .icon-xs { font-size:9px; }
    .icon-gray { margin-right:6px; color:#94a3b8; }
    .info-text { margin:0; font-size:13px; color:#64748b; }
    .info-text-sm { margin:0 0 14px; font-size:13px; color:#5c6573; line-height:1.5; }
    .action-bar-flush { border-top:none; padding-top:0; margin-top:12px; }
    .panel-body-compact { padding:14px 16px; }
    .mr-6 { margin-right:6px; }
    .text-gray-400 { color:#94a3b8; }
    .badge-short { background:#fee2e2; color:#991b1b; font-size:11px; font-weight:800; padding:3px 10px; border-radius:99px; white-space:nowrap; }
    .badge-stock-ok { background:#dcfce7; color:#166534; font-size:11px; font-weight:800; padding:3px 10px; border-radius:99px; white-space:nowrap; }
    .rq-banner { border-radius:8px; padding:10px 14px; margin-bottom:14px; display:flex; align-items:center; gap:8px; border:1px solid; }
    .rq-banner-icon { font-size:15px; }
    .rq-banner-title { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; margin-bottom:2px; }
    .rq-banner-text { font-size:12px; }
    .rq-banner--pending { background:#fff7ed; border-color:#fed7aa; border-left:3px solid #d97706; }
    .rq-banner--pending .rq-banner-icon { color:#d97706; }
    .rq-banner--pending .rq-banner-title { color:#92400e; }
    .rq-banner--pending .rq-banner-text { color:#78350f; }
    .rq-banner--approved { background:#eff6ff; border-color:#bfdbfe; border-left:3px solid #0038A8; }
    .rq-banner--approved .rq-banner-icon { color:#0038A8; }
    .rq-banner--approved .rq-banner-title { color:#1e40af; }
    .rq-banner--approved .rq-banner-text { color:#1e40af; }
    @media (max-width: 767px) {
        .card-header-accent { flex-direction: column !important; gap: 10px !important; }
        .table-wrap, .scroll-x { overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; }
        .action-bar-flush { flex-direction: column !important; gap: 10px !important; }
        input, select, textarea { min-height: 48px !important; font-size: 15px !important; }
        .btn, button:not(#sidebarToggle):not(#notifBell):not(.swal2-confirm):not(.swal2-cancel) { min-height: 48px !important; width: 100% !important; font-size: 14px !important; }
        .panel-body-compact { padding: 12px !important; }
        .info-text, .info-text-sm { font-size: 13px !important; }
    }
</style>
@endsection

@section('content')
<div class="cmms-official cmms-official-page">
    <div class="cmms-page-card">
        <div class="cmms-page-card-head">
            <div>
                <h2>Parts Requisition REQ-{{ str_pad($requisition->id, 5, '0', STR_PAD_LEFT) }}</h2>
                <div class="sub">Job order {{ $ticket?->display_number ?? $ticket?->request_number ?? '&mdash;' }} &middot; {{ ucfirst($status) }}</div>
            </div>
            <a href="{{ route('requisitions.index') }}" class="cmms-btn-secondary">Back to requisitions</a>
        </div>
        <div class="cmms-page-card-body">


    <div class="cmms-layout-split">
        <div>
            @if($ticket && $ticket->linkedAsset)
            <div class="cmms-panel mb-18">
                <div class="cmms-panel-head"><h2>Accountable property</h2></div>
                <div class="cmms-panel-body">
                    <div class="cmms-meta-grid">
                        <div class="cmms-meta-card">
                            <div class="label">Article</div>
                            <div class="value">{{ $ticket->linkedAsset->item_name }}</div>
                        </div>
                        <div class="cmms-meta-card">
                            <div class="label">Serial number</div>
                            <div class="value">{{ $ticket->linkedAsset->serial_number ?? 'N/A' }}</div>
                        </div>
                        <div class="cmms-meta-card">
                            <div class="label">Property status</div>
                            <div class="value">{{ $ticket->linkedAsset->status }}</div>
                        </div>
                        <div class="cmms-meta-card">
                            <div class="label">Asset custodian</div>
                            <div class="value">{{ $issueContext['custodian']?->full_name ?? 'Not assigned' }}</div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @include('requisitions.partials.status-tracker', ['requisition' => $requisition, 'variant' => 'full'])

            @if($canReview && $status === 'approved' && ! $issueContext['valid'])
            <div class="cmms-panel mb-18" style="border-left:3px solid #dc2626;">
                <div class="cmms-panel-head"><h2 style="color:#991b1b;">Issue blocked</h2></div>
                <div class="cmms-panel-body">
                    <p class="info-text" style="color:#991b1b;">{{ $issueContext['message'] }}</p>
                </div>
            </div>
            @endif

            @include('requisitions.partials.pr-readonly', ['requisition' => $requisition])

    @if((Auth::user()->canProcessSupply() || Auth::user()->role === 'super_admin') && $inventoryMatches->isNotEmpty())
    {{-- ══ SUPPLY: Inventory Availability Check ══ --}}
    <div class="cmms-panel mt-18 panel-inventory">
        <div class="cmms-panel-head panel-head-green">
            <h2 class="text-green-700"><i class="fa-solid fa-warehouse icon-green"></i>Inventory Availability Check</h2>
            <span class="badge-sm text-green-800">
                {{ $inventoryMatches->count() }} of {{ count($requisition->items ?? []) }} item(s) may have matching stock
            </span>
        </div>
        <div class="cmms-panel-body panel-body-flush">
            <div class="info-bar">
                <i class="fa-solid fa-circle-info icon-mr-4"></i>
                These are possible matches from the current inventory. Verify physically before deciding to issue from stock or procure new.
            </div>
            @foreach($inventoryMatches as $lineIndex => $match)
            @php $reqLine = $match['requested']; $assets = $match['assets']; @endphp
            <div class="item-block">
                <div class="item-header">
                    <div>
                        <span class="item-label">Requested item #{{ $lineIndex + 1 }}</span>
                        <div class="item-title">
                            {{ $reqLine['quantity'] ?? 1 }} × {{ $reqLine['description'] ?? '' }}
                        </div>
                    </div>
                    <span class="badge-spare">
                        {{ $assets->where('status','Spare')->count() }} spare · {{ $assets->where('status','Active')->count() }} in-use
                    </span>
                </div>
                <div class="asset-list">
                    @foreach($assets as $asset)
                    <div class="asset-row {{ $asset->status === 'Spare' ? 'asset-row-spare' : 'asset-row-other' }}">
                        <div>
                            <span class="asset-name">{{ $asset->item_name }}</span>
                            @if($asset->brand || $asset->model)
                                <span class="asset-model">{{ trim(($asset->brand ?? '').' '.($asset->model ?? '')) }}</span>
                            @endif
                            <div class="asset-sn">
                                SN: {{ $asset->serial_number ?? 'N/A' }}
                                @if($asset->branch) · {{ $asset->branch }} @endif
                            </div>
                        </div>
                        <div class="asset-actions">
                            @if($asset->status === 'Spare')
                                <span class="badge-available">Available (Spare)</span>
                            @else
                                <span class="badge-status">
                                    {{ $asset->status }}
                                    @if($asset->assignedUser) — {{ $asset->assignedUser->full_name }} @endif
                                </span>
                            @endif
                            @php
                                $detailRoute = Auth::user()->role === 'super_admin'
                                    ? route('super_admin.inventory.detail', $asset->asset_id)
                                    : route('inventory.detail', $asset->asset_id);
                            @endphp
                            <a href="{{ $detailRoute }}" target="_blank" class="asset-link">
                                View asset <i class="fa-solid fa-arrow-up-right-from-square icon-xs"></i>
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @elseif((Auth::user()->canProcessSupply() || Auth::user()->role === 'super_admin') && $inventoryMatches->isEmpty() && count($requisition->items ?? []) > 0)
    <div class="cmms-panel mt-18">
        <div class="cmms-panel-head">
            <h2><i class="fa-solid fa-warehouse icon-gray"></i>Inventory Availability Check</h2>
        </div>
        <div class="cmms-panel-body">
            <p class="info-text">
                <i class="fa-solid fa-circle-xmark text-gray-400 icon-mr-4"></i>
                No matching spare or in-use assets found in inventory for the requested items. May need to procure new parts.
            </p>
        </div>
    </div>
    @endif

    @if((Auth::user()->canProcessSupply() || Auth::user()->role === 'super_admin') && $partsStockMatches->isNotEmpty())
    {{-- ══ SUPPLY: Parts & Consumables Stock Availability ══ --}}
    <div class="cmms-panel mt-18" style="border-left:3px solid #0038A8;">
        <div class="cmms-panel-head">
            <h2><i class="fa-solid fa-boxes mr-6" style="color:#0038A8;"></i>Parts &amp; Consumables Stock</h2>
            <span class="badge-sm text-gray-400">Available inventory from the Parts Stock (quantity-based)</span>
        </div>
        <div class="cmms-panel-body panel-body-flush">
            @foreach($partsStockMatches as $lineIndex => $match)
            @php $reqLine = $match['requested']; $part = $match['part']; @endphp
            <div class="item-block">
                <div class="item-header">
                    <div>
                        <span class="item-label">Requested item #{{ $lineIndex + 1 }}</span>
                        <div class="item-title">
                            {{ $reqLine['quantity'] ?? 1 }} × {{ $reqLine['description'] ?? '' }}
                            @if($part->unit) <span class="text-gray-400">({{ $part->unit }})</span> @endif
                        </div>
                    </div>
                    @if($match['deficit'])
                        <span class="badge-short"><i class="fa-solid fa-triangle-exclamation" style="margin-right:5px;"></i>Short — needs PR</span>
                    @else
                        <span class="badge-stock-ok"><i class="fa-solid fa-circle-check" style="margin-right:5px;"></i>Available in stock</span>
                    @endif
                </div>
                <div class="asset-row" style="background:#f8fafc;border:1px solid #e2e8f0;">
                    <div>
                        <span class="asset-name">{{ $part->item_name }}</span>
                        @if($part->category) <span class="asset-model">{{ $part->category }}</span> @endif
                        <div class="asset-sn">On-hand: {{ $part->on_hand_qty }} {{ $part->unit }}</div>
                    </div>
                    <div class="asset-actions">
                        @if($match['deficit'])
                            <span class="badge-status">Needs {{ $reqLine['quantity'] - $part->on_hand_qty }} more — Create PR</span>
                        @else
                            <span class="badge-available">Sufficient</span>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
            @php $hasDeficit = $partsStockMatches->contains(fn($m) => !empty($m['deficit'])); @endphp
            @if(Auth::user()->canProcessSupply() && $hasDeficit)
            <div class="info-bar" style="background:#fff7ed;color:#9a3412;border-bottom:none;display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                <span><i class="fa-solid fa-boxes-stacked icon-mr-4"></i>May kulang sa Parts Stock — gumawa ng Purchase Request.</span>
                <button type="button" id="createPrBtn" class="cmms-btn-primary" style="padding:7px 14px;font-size:12px;">＋ Create Purchase Request</button>
            </div>
            @endif
            @if($canReview && in_array($status, ['approved'], true))
            <div class="info-bar" style="background:#eff6ff;color:#0038A8;border-bottom:none;">
                <i class="fa-solid fa-circle-info icon-mr-4"></i>
                Issuing will deduct these items from Parts Stock and assign their serialized units to the linked asset custodian.
            </div>
            @endif
        </div>
    </div>
    @endif

            <div class="cmms-toolbar">
                @if($ticket)
                    <a href="{{ route('ict.show', $ticket->id) }}" class="cmms-btn-secondary" target="_blank">Job order record</a>
                    @if($isIT)
                        <a href="{{ route('ict.ticket', $ticket->id) }}" class="cmms-btn-secondary">Parts register</a>
                    @endif
                @endif
                @if(Auth::user()->canProcessSupply())
                <a href="{{ route('requisitions.index', ['view' => 'queue']) }}" class="cmms-btn-secondary">Return to list</a>
                @else
                <a href="{{ route('requisitions.index') }}" class="cmms-btn-secondary">Return to list</a>
                @endif
            </div>
        </div>

        <aside class="cmms-sticky-side">
            @if($canReview && in_array($status, ['pending', 'approved'], true))
            {{-- Action-required banner --}}
            <div class="rq-banner rq-banner--{{ $status === 'pending' ? 'pending' : 'approved' }}">
                <i class="fa-solid {{ $status === 'pending' ? 'fa-triangle-exclamation' : 'fa-box-open' }} rq-banner-icon" aria-hidden="true"></i>
                <div>
                    <div class="rq-banner-title">Action required</div>
                    <div class="rq-banner-text">{{ $status === 'pending' ? 'Review and approve or disapprove this requisition.' : 'This requisition is approved — issue the parts to the custodian.' }}</div>
                </div>
            </div>
            <div class="cmms-panel" id="supplyReviewPanel">
                <div class="cmms-panel-head">
                    <h2>Official action</h2>
                </div>
                <div class="cmms-panel-body">
                    @if($status === 'pending')
                    <p class="info-text-sm">
                        Verify the particulars above. Record your decision and any document reference below.
                    </p>
                    @else
                    <p class="info-text-sm">
                        Confirm release of property. The job order will resume if it was awaiting parts.
                    </p>
                    @endif

                    <div class="cmms-form-field">
                        <label>Remarks / PAR or RIS reference</label>
                        <textarea id="supplyReviewRemarks" rows="4" placeholder="Enter reference number or remarks.">{{ $requisition->remarks }}</textarea>
                    </div>

                    <div class="cmms-action-bar action-bar-flush">
                        @if($status === 'pending')
                        <button type="button" class="cmms-btn-primary supply-action-btn" data-action="approve"><i class="fa-solid fa-check"></i> Approve</button>
                        <button type="button" class="cmms-btn-danger supply-action-btn" data-action="reject"><i class="fa-solid fa-xmark"></i> Disapprove</button>
                        @elseif($status === 'approved')
                            @if($issueContext['valid'])
                                <button type="button" class="cmms-btn-success supply-action-btn" data-action="issue"><i class="fa-solid fa-box-open"></i> Issue property</button>
                            @else
                                <button type="button" class="cmms-btn-success" disabled title="{{ $issueContext['message'] }}"><i class="fa-solid fa-box-open"></i> Issue property</button>
                            @endif
                        <button type="button" class="cmms-btn-danger supply-action-btn" data-action="reject"><i class="fa-solid fa-xmark"></i> Disapprove</button>
                        @endif
                    </div>
                    @if($status === 'approved' && ! $issueContext['valid'])
                    <p class="info-text" style="color:#991b1b;margin-top:8px;"><i class="fa-solid fa-circle-exclamation mr-6"></i>{{ $issueContext['message'] }}</p>
                    @endif
                </div>
            </div>
            @elseif($status === 'issued' || $status === 'rejected')
            <div class="cmms-panel">
                <div class="cmms-panel-head"><h2>Record closed</h2></div>
                <div class="cmms-panel-body">
                    <p class="info-text-sm">
                        This parts requisition is <strong>{{ $status }}</strong>. No further action is required.
                    </p>
                </div>
            </div>
            @else
            <div class="cmms-panel">
                <div class="cmms-panel-head"><h2>Next action</h2></div>
                <div class="cmms-panel-body">
                    <p class="info-text-sm">
                        Awaiting decision by the Property and Supply Office. See processing status above.
                    </p>
                </div>
            </div>
            @endif

            <div class="cmms-panel mt-18">
                <div class="cmms-panel-head"><h2>Particulars</h2></div>
                <div class="cmms-panel-body panel-body-compact">
                    <div class="cmms-pr-meta-row"><span class="k">REQ number</span><span class="v" style="font-weight:800; color:#0038A8;">REQ-{{ str_pad($requisition->id, 5, '0', STR_PAD_LEFT) }}</span></div>
                    <div class="cmms-pr-meta-row"><span class="k">Status</span><span class="v"><span class="cmms-status-badge cmms-status-{{ strtolower($status) }}">{{ ucfirst($status) }}</span></span></div>
                    <div class="cmms-pr-meta-row"><span class="k">Requester</span><span class="v">{{ $requisition->requester?->full_name ?? '&mdash;' }}</span></div>
                    @if($ticket)
                    <div class="cmms-pr-meta-row"><span class="k">Job order</span><span class="v">{{ $ticket->display_number ?? $ticket->request_number }}</span></div>
                    <div class="cmms-pr-meta-row"><span class="k">Ticket type</span><span class="v"><span class="cmms-req-tag {{ $ticket->type === 'Preventive Maintenance' ? 'cmms-req-tag--issue' : 'cmms-req-tag--review' }}">{{ $ticket->type === 'Preventive Maintenance' ? '[PM]' : '[ICT]' }}</span></span></div>
                    @endif
                    <div class="cmms-pr-meta-row"><span class="k">Line items</span><span class="v">{{ count($requisition->items ?? []) }}</span></div>
                    @if($requisition->reviewer && $requisition->reviewed_at)
                    <div class="cmms-pr-meta-row"><span class="k">Last action by</span><span class="v">{{ $requisition->reviewer->full_name }}</span></div>
                    <div class="cmms-pr-meta-row"><span class="k">Reviewed</span><span class="v">{{ $requisition->reviewed_at->format('d M Y, h:i A') }}</span></div>
                    @endif
                </div>
            </div>
        </aside>
    </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@if($canReview && in_array($status, ['pending', 'approved'], true))
<script nonce="{{ $cspNonce }}">
(function () {
    let busy = false;
    const remarksEl = () => document.getElementById('supplyReviewRemarks');

    document.querySelectorAll('.supply-action-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (busy) return;
            const action = btn.dataset.action;
            const titles = { approve: 'Approve requisition', reject: 'Reject requisition', issue: 'Issue parts to asset custodian' };

            // A disapproval must carry a documented reason (mirrors queue quick-actions).
            if (action === 'reject' && !(remarksEl()?.value.trim())) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Reason required',
                    text: 'Please provide a reason for disapproving this requisition.',
                    confirmButtonColor: '#0038A8',
                });
                remarksEl()?.focus();
                return;
            }

            const issueDestination = @json($issueContext['valid']
                ? ($issueContext['custodian']->full_name . ' for ' . $issueContext['asset']->item_name)
                : null);
            const confirm = await Swal.fire({
                icon: 'question',
                title: titles[action],
                text: action === 'issue'
                    ? 'Issued units will be assigned to ' + issueDestination + '.'
                    : 'This will be recorded under your Administrative supply admin account.',
                showCancelButton: true,
                confirmButtonColor: '#0038A8',
                confirmButtonText: 'Confirm',
            });
            if (!confirm.isConfirmed) return;
            busy = true;
            document.querySelectorAll('.supply-action-btn').forEach(b => b.disabled = true);
            try {
                const res = await fetch(@json(route('requisitions.review', $requisition->id)), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        action,
                        remarks: remarksEl()?.value || '',
                    }),
                });
                const data = await res.json().catch(() => ({}));

                // Validation errors (e.g. missing disapproval reason caught server-side).
                if (!res.ok && data.errors) {
                    const first = Object.values(data.errors)[0]?.[0] || data.message || 'Please check the form.';
                    Swal.fire({ icon: 'warning', title: 'Check the form', text: first, confirmButtonColor: '#0038A8' });
                    busy = false;
                    document.querySelectorAll('.supply-action-btn').forEach(b => b.disabled = false);
                    remarksEl()?.focus();
                    return;
                }

                if (data.success) {
                    await Swal.fire({ icon: 'success', title: 'Recorded', text: data.message, confirmButtonColor: '#0038A8' });
                    window.location.href = data.redirect || @json(route('requisitions.show', $requisition->id));
                    return;
                }

                // Stock shortage — show exactly which lines are short.
                if (Array.isArray(data.deficits) && data.deficits.length) {
                    const rows = data.deficits.map(d =>
                        '· <b>' + d.description + '</b> — requested <b>' + d.requested + '</b>, on-hand <b>' + d.available + '</b>'
                    ).join('<br>');
                    Swal.fire({
                        icon: 'error',
                        title: 'Insufficient parts stock',
                        html:
                            '<div style="text-align:left;font-size:13px;line-height:1.9;">' + rows + '</div>'
                            + '<div style="margin-top:10px;font-size:12px;color:#64748b;">Tip: use “Create Purchase Request” in the Parts Stock panel above to procure the shortage.</div>',
                        confirmButtonColor: '#0038A8',
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Could not process.', confirmButtonColor: '#0038A8' });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Network error', confirmButtonColor: '#0038A8' });
            }
            busy = false;
            document.querySelectorAll('.supply-action-btn').forEach(b => b.disabled = false);
        });
    });
})();
</script>
@endif
@php $canCreatePr = Auth::user()->canProcessSupply(); @endphp
@if($canCreatePr)
<script nonce="{{ $cspNonce }}">
(function () {
    const btn = document.getElementById('createPrBtn');
    if (!btn) return;
    btn.addEventListener('click', async () => {
        const confirm = await Swal.fire({
            icon: 'question',
            title: 'Create Purchase Request',
            text: 'Gagawa ng PR para sa kulang na Parts Stock lines ng requisition na ito.',
            showCancelButton: true,
            confirmButtonColor: '#0038A8',
            confirmButtonText: 'Create',
        });
        if (!confirm.isConfirmed) return;
        try {
            const res = await fetch(@json(route('purchase_requests.create', $requisition->id)), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                },
            });
            const data = await res.json();
            if (data.success) {
                await Swal.fire({ icon: 'success', title: 'Created', text: data.message, confirmButtonColor: '#0038A8' });
                window.location.href = data.purchase_request_id
                    ? '{{ url('purchase-requests') }}/' + data.purchase_request_id
                    : '{{ url('purchase-requests') }}';
                return;
            }
            Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Could not create.', confirmButtonColor: '#0038A8' });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Network error', confirmButtonColor: '#0038A8' });
        }
    });
})();
</script>
@endif
@endsection
