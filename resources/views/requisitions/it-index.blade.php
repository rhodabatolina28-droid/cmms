@extends('layouts.app')

@section('title', 'My Parts Requisitions')
@section('page-title', 'My Parts Requisitions')

@section('styles')
@include('requisitions.partials.official-assets')
<style nonce="{{ $cspNonce }}">
    .btn-back-top { margin-top:14px; }
    .pr-select { border:1px solid #e2e8f0; background:#fff; }
    .col-th-action { width:44px; }
    .input-qty-center { text-align:center; }
    .ticket-req-source { margin-top:6px; width:100%; border:1px solid #e2e8f0; background:#fff; color:#334155; padding:7px 10px; font-size:12px; border-radius:6px; }
    .ticket-req-source:focus { border-color:#0038A8; outline:none; }
    .col-price-muted { color:#cbd5e1; }
    .req-list-flush { padding:0; background:transparent; }
    .paginator-compact { padding:8px 0; }
    .pr-page-wrap { width:100%; }
    .cell-trim { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .cmms-official-table td, .cmms-official-table th { vertical-align:middle; }
    /* Table polish - matched to Parts & Consumables */
    .cmms-official-table { border-collapse:collapse; }
    .cmms-official-table th {
        padding:12px 14px; font-size:11px; font-weight:700; text-transform:uppercase;
        letter-spacing:.5px; color:#64748b; background:#f8fafc;
        border-bottom:2px solid #e2e8f0; text-align:center;
    }
    .cmms-official-table th:first-child { text-align:left; }
    .cmms-official-table td {
        padding:12px 14px; font-size:13px; color:#1e293b;
        border-bottom:1px solid #f1f5f9; text-align:center;
    }
    .cmms-official-table td:first-child { text-align:left; }
    .cmms-official-table tbody tr:hover td { background:#f8fafc; }
    .cmms-official-table tbody tr:hover td:first-child { box-shadow:inset 3px 0 0 #0038A8; }
    .cmms-status-badge { display:inline-block; padding:3px 10px; border-radius:99px; font-size:11px; font-weight:800; white-space:nowrap; }
    .cmms-history-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; margin-bottom:14px; }
    .cmms-history-toolbar form { display:flex; align-items:center; gap:9px; flex:1; min-width:220px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:8px 12px; }
    .cmms-history-toolbar form > i { color:#94a3b8; font-size:13px; }
    .cmms-history-toolbar input[type="text"] { border:none !important; outline:none; box-shadow:none; flex:1; min-width:120px; font-size:13px; background:transparent; padding:0; margin:0; }
    .chips { display:inline-flex; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:#fff; }
    .chips a { padding:8px 13px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#64748b; text-decoration:none; transition:all .15s; }
    .chips a + a { border-left:1px solid #e2e8f0; }
    .chips a:hover { color:#0038A8; background:#f8fafc; }
    .chips a.active { background:#0038A8; color:#fff; }
    @media (max-width:767px) {
        .cmms-history-toolbar { flex-direction:column; align-items:stretch; }
        .chips a { flex:1; text-align:center; display:inline-block; }
        /* Tabs: all 3 fit in one row — equal width, compact */
        .cmms-tabs { overflow:visible; }
        .cmms-tab { flex:1 1 0 !important; text-align:center !important; padding:12px 4px !important; font-size:11.5px !important; min-height:44px !important; white-space:normal !important; line-height:1.2 !important; }
        /* Tables: contain overscroll so the page doesn't shift on swipe */
        .table-wrap { overscroll-behavior-x:contain !important; }
        /* My PRs table — natural widths, scroll instead of squishing */
        .tab-myprs-scroll .cmms-req-table { min-width:560px !important; width:560px !important; table-layout:auto !important; }
        .tab-myprs-scroll .cmms-req-table th,
        .tab-myprs-scroll .cmms-req-table td { white-space:nowrap !important; }
        /* History table — contain overscroll (min-width 860px already inline) */
        #tab-history .table-wrap { overscroll-behavior-x:contain !important; }
        /* History table — natural widths like the other fixed tables */
        .tab-history-scroll .cmms-official-table {
            table-layout:auto !important;
            min-width:920px !important;
            width:920px !important;
        }
        .tab-history-scroll .cmms-official-table th,
        .tab-history-scroll .cmms-official-table td { white-space:nowrap !important; }
        /* Awaiting Parts checkbox — override the global 100%-width/44px input rule */
        .cmms-pr-option {
            display:flex !important;
            align-items:flex-start !important;
            gap:10px !important;
            padding:10px 12px !important;
            background:#f8fafc !important;
            border:1px solid #e2e8f0 !important;
            border-radius:8px !important;
            font-size:12.5px !important;
            line-height:1.4 !important;
        }
        .cmms-pr-option input[type="checkbox"],
        #ticketReqAwaitingParts {
            width:18px !important;
            height:18px !important;
            min-height:18px !important;
            min-width:18px !important;
            max-width:18px !important;
            margin:0 !important;
            padding:0 !important;
            flex-shrink:0 !important;
            font-size:16px !important;
            accent-color:#0038A8 !important;
        }
        .cmms-pr-option-hint { font-size:11px !important; line-height:1.35 !important; padding:0 !important; }
        /* History search + status chips — compact, wrapping */
        .cmms-history-toolbar .search-input { min-height:44px !important; font-size:14px !important; padding-left:38px !important; }
        .chips { display:flex !important; flex-wrap:wrap !important; width:100% !important; gap:6px !important; background:transparent !important; border:none !important; border-radius:8px !important; overflow:visible !important; }
        .chips a {
            flex:1 1 auto !important;
            text-align:center !important;
            display:inline-block !important;
            padding:11px 10px !important;
            font-size:10.5px !important;
            min-height:44px !important;
            line-height:1.2 !important;
            border:1px solid #e2e8f0 !important;
            border-radius:8px !important;
            background:#fff !important;
        }
        .chips a + a { border-left:1px solid #e2e8f0 !important; }
        .chips a.active { background:#0038A8 !important; color:#fff !important; border-color:#0038A8 !important; }
    }
    .col-num { width:44px; text-align:center; color:#64748b; font-weight:700; font-size:12px; }
    .cmms-pr-add-row-bar { display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin-top:12px; }
    .cmms-item-count { font-size:12px; color:#94a3b8; font-weight:700; }
    .cmms-pr-footer { align-items:flex-start; }
    .cmms-pr-option-wrap { display:flex; flex-direction:column; gap:4px; }
    .cmms-pr-option-hint { font-size:11px; color:#94a3b8; font-weight:500; }
    /* Requisition card hover effect */
    .req-card-hover { transition: all 0.2s ease; }
    .req-card-hover:hover { background: #f8fafc; }
    /* Status badge glow — already in cmms-official.css */
    /* Mobile swipe-table hint — hidden on desktop, shown ≤767px */
    .mobile-table-hint { display: none; }
    /* MOBILE RESPONSIVE */
    @media (max-width: 767px) {
        .cmms-official-hero h1,
        .cmms-official-hero,
        .cmms-hero-badge,
        .cmms-official-hero .ref,
        .cmms-official-hero .sub {
            display: none !important;
        }
        .cmms-tab { padding: 12px 16px !important; font-size: 13px !important; min-height: 44px !important; }
        .cmms-pr-sheet { padding: 0 !important; }
        .cmms-pr-sheet-head { flex-direction: column !important; gap: 8px !important; padding: 16px !important; }
        .cmms-pr-sheet-head .doc-title { font-size: 16px !important; }
        .cmms-pr-select { min-height: 48px !important; font-size: 14px !important; }
        .cmms-pr-input { min-height: 44px !important; font-size: 14px !important; }
        .cmms-pr-textarea { min-height: 80px !important; font-size: 14px !important; }
        .cmms-pr-footer { flex-direction: column !important; gap: 12px !important; padding: 16px !important; }
        .cmms-pr-footer .cmms-btn-primary { width: 100% !important; justify-content: center !important; }
        .cmms-pr-add-row { width: 100% !important; justify-content: center !important; }
        .cmms-official-table { min-width: 480px !important; }
        .cmms-official-table th { font-size: 10px !important; padding: 8px !important; }
        .cmms-official-table td { padding: 8px !important; }
        .cmms-pr-items-wrap { overflow-x: auto !important; }
        .cmms-pr-justification { padding: 16px !important; }
        .ticket-req-qty { max-width: 60px !important; }
    }
</style>
@endsection

@section('content')
<div class="pr-page-wrap">
    <div class="cmms-official cmms-official-page">
        <div class="cmms-page-card">
            <div class="cmms-page-card-head">
                <div>
                    <h2>My Parts Requisitions</h2>
                    <div class="sub">Request and track parts tied to your assigned ICT or PM job orders.</div>
                </div>
                @if($activeTickets->isEmpty())
                <a href="{{ route('dashboard.it') }}" class="cmms-btn-secondary">Back to IT dashboard</a>
                @endif
            </div>
            <div class="cmms-page-card-body">
                @php $isHistory = request('tab') === 'history' || request()->has('history_status') || request()->has('history_q'); $isMyPrs = request('tab') === 'myprs'; @endphp
            <div class="cmms-tabs" role="tablist">
            <button type="button" class="cmms-tab {{ ($isHistory || $isMyPrs) ? '' : 'active' }}" data-target="tab-new" role="tab">Request Parts</button>
            <button type="button" class="cmms-tab {{ $isMyPrs ? 'active' : '' }}" data-target="tab-myprs" role="tab">Purchase Requests</button>
            <button type="button" class="cmms-tab {{ $isHistory ? 'active' : '' }}" data-target="tab-history" role="tab">History</button>
        </div>

        <div id="tab-new" class="cmms-tab-content {{ ($isHistory || $isMyPrs) ? '' : 'active' }}" role="tabpanel">
            @if($activeTickets->isEmpty())
                <div class="cmms-panel">
                    <div class="cmms-panel-body cmms-empty">
                        <i class="fa-solid fa-clipboard-list cmms-empty-icon"></i>
                        <h3>No open ICT or PM job order</h3>
                        <p>Parts can only be requested from an assigned ICT or manual PM job order that is still active.</p>
                        <a href="{{ route('dashboard.it') }}" class="cmms-btn-secondary btn-back-top">Back to IT dashboard</a>
                    </div>
                </div>
            @else
                <form id="ticketReqForm">
                    <div class="cmms-pr-sheet">
                        <div class="cmms-pr-sheet-head">
                            <div>
                                <h2 class="doc-title">Parts requisition</h2>
                                <p class="doc-org">Select the ICT or PM job order that needs parts.</p>
                            </div>
                            <div class="cmms-pr-number-badge muted">Draft</div>
                        </div>

                        <div class="cmms-pr-meta-grid">
                            <div class="cmms-pr-meta-block">
                                <div class="cmms-pr-meta-row"><span class="k">Unit</span><span class="v">ICT / PM</span></div>
                                <div class="cmms-pr-meta-row"><span class="k">Requester</span><span class="v">{{ Auth::user()->full_name }}</span></div>
                                <div class="cmms-pr-meta-row">
                                    <span class="k">Job order no.</span>
                                    <span class="v">
                                        <select id="request_id" class="cmms-pr-select pr-select" style="min-height:36px; padding:6px 10px; font-size:13px; font-weight:600; width:100%; border-color:#cbd5e1; border-radius:6px; outline:none; transition:border-color 0.15s ease;" onfocus="this.style.borderColor='#0038A8'" onblur="this.style.borderColor='#cbd5e1'" required>
                                            <option value="" disabled {{ !$selectedTicketId ? 'selected' : '' }}>Select job order</option>
                                            @foreach($activeTickets as $ticket)
                                                <option value="{{ $ticket->id }}" {{ (string) $ticket->id === (string) ($selectedTicketId ?? '') ? 'selected' : '' }}
                                                    data-type="{{ $ticket->type }}"
                                                    data-asset="{{ $ticket->linkedAsset?->item_name ?? '' }}"
                                                    data-serial="{{ $ticket->linkedAsset?->serial_number ?? '' }}"
                                                    data-custodian="{{ $ticket->linkedAsset?->assignedUser?->full_name ?? '' }}"
                                                    data-request="{{ $ticket->display_number ?? $ticket->request_number }}">
                                                    {{ $ticket->type === 'Preventive Maintenance' ? '[PM] ' : '[ICT] ' }}{{ $ticket->display_number ?? $ticket->request_number }} &middot; {{ $ticket->status }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </span>
                                </div>
                                <div id="dynamicAssetRow" class="cmms-pr-meta-row" style="display:none; background:#f8fafc; padding:8px 10px; border-radius:6px; border-left:3px solid #0038A8; margin-top:6px;">
                                    <span class="k" style="color:#0038A8; font-size:10px;">For Asset &amp; Custodian</span>
                                    <span class="v" id="dynamicAssetInfo" style="font-size:12px; color:#1e293b;"></span>
                                </div>
                            </div>
                            <div class="cmms-pr-meta-block">
                                <div class="cmms-pr-meta-row"><span class="k">Date prepared</span><span class="v">{{ date('F d, Y') }}</span></div>
                                <div class="cmms-pr-meta-row"><span class="k">Supply action</span><span class="v">Pending review</span></div>
                                <div class="cmms-pr-meta-row"><span class="k">Status after submit</span><span class="v">Awaiting Parts</span></div>
                            </div>
                        </div>

                        <div class="cmms-pr-items-wrap">
                            <table class="cmms-official-table">
                                <thead>
                                    <tr>
                                        <th class="col-num">#</th>
                                        <th class="col-qty">Qty</th>
                                        <th>Description and specifications</th>
                                        <th class="col-th-action"></th>
                                    </tr>
                                </thead>
                                <tbody id="ticketReqItemsList">
                                    <tr class="ticket-req-row">
                                        <td class="col-num input-qty-center"></td>
                                        <td class="col-qty"><input type="number" class="cmms-pr-input ticket-req-qty input-qty-center" min="1" value="1" required></td>
                                        <td class="col-desc">
                                            <input type="text" class="cmms-pr-input ticket-req-desc" placeholder="e.g. NVMe SSD 1TB, DDR4 8GB RAM" required>
                                            <select class="ticket-req-source">
                                                <option value="">Source: type manually / spare asset</option>
                                                @foreach($partsStock as $ps)
                                                    <option value="{{ $ps->id }}" data-name="{{ $ps->item_name }}" data-unit="{{ $ps->unit }}" data-onhand="{{ $ps->on_hand_qty }}">From Parts Stock: {{ $ps->item_name }} ({{ $ps->on_hand_qty }} {{ $ps->unit }})</option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" class="ticket-req-part-id" value="">
                                            <input type="hidden" class="ticket-req-source-val" value="other">
                                        </td>
                                        <td class="col-action"><button type="button" class="cmms-row-remove remove-item-btn" tabindex="-1" title="Remove row">&times;</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="cmms-pr-add-row-bar">
                            <button type="button" id="addTicketReqLine" class="cmms-pr-add-row">Add line item</button>
                            <span id="ticketReqItemCount" class="cmms-item-count" aria-live="polite">1 line item</span>
                        </div>

                        <div id="manualPrHint" style="display:none;margin-bottom:12px;padding:10px 14px;border:1px solid #fde68a;background:#fffbeb;border-radius:8px;align-items:center;gap:14px;flex-wrap:wrap;">
                            <div style="flex:1;min-width:200px;font-size:11.5px;color:#92400e;">
                                <span id="manualPrHintText">This item is not in Parts Stock — it can't be issued from existing inventory.</span>
                            </div>
                            <a id="manualPrLink" href="#" class="cmms-action-btn cmms-action-btn--primary" aria-label="Create Purchase Request for this manual item">
                                Create Purchase Request<i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>

                        <div class="cmms-pr-justification">
                            <div class="k">Purpose / justification</div>
                            <textarea id="ticketReqRemarks" class="cmms-pr-textarea" rows="3" placeholder="State the purpose of the request and relevant technical particulars."></textarea>
                        </div>

                        <div id="openReqWarning" style="display:none;margin-bottom:12px;padding:10px 14px;border:1px solid #fed7aa;border-left:3px solid #d97706;background:#fff7ed;border-radius:8px;font-size:12.5px;color:#92400e;">
                            <i class="fa-solid fa-triangle-exclamation" style="margin-right:6px;"></i>This ticket already has an <strong>ongoing</strong> parts request for the same items. Wait for it to be issued or rejected before requesting the same parts again.
                        </div>

                        <div class="cmms-pr-footer">
                            <div class="cmms-pr-option-wrap">
                                <label class="cmms-pr-option">
                                    <input type="checkbox" id="ticketReqAwaitingParts" checked>
                                    Mark job order as <strong>Awaiting Parts</strong>
                                </label>
                                <span class="cmms-pr-option-hint">Sets the job order status to "Awaiting Parts" once submitted.</span>
                            </div>
                            <button type="button" id="ticketPartsSubmitBtn" class="cmms-btn-primary">Submit to Supply</button>
                        </div>
                    </div>
                </form>
            @endif
        </div>

        <div id="tab-myprs" class="cmms-tab-content {{ $isMyPrs ? 'active' : '' }}" role="tabpanel">
            {{-- Purchase Requests — own PR documents status (PR document flow) --}}
            <div class="cmms-panel">
                <div class="cmms-panel-body flush">
                    @if(isset($myPrs) && $myPrs->isNotEmpty())
                        <div class="mobile-table-hint"><i class="fa-solid fa-arrow-right-arrow-left"></i> Swipe table horizontally to view all columns</div>
                        <div class="table-wrap tab-myprs-scroll">
                            <table class="cmms-official-table cmms-req-table">
                                <thead>
                                    <tr>
                                        <th style="text-align:left;">PR #</th><th>Total</th><th>Date submitted</th><th>Status</th><th style="text-align:left;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="myPrsTableBody">
                                    @include('requisitions.partials.my-pr-rows')
                                </tbody>
                            </table>
                        </div>
                        <div id="myPrsPagination" class="paginator-compact">{{ $myPrs->links() }}</div>
                    @else
                        <div class="cmms-empty">No purchase requests yet.</div>
                    @endif
                </div>
            </div>
        </div>

        <div id="tab-history" class="cmms-tab-content {{ $isHistory ? 'active' : '' }}" role="tabpanel">
            <div class="cmms-history-toolbar">
                <form method="GET" action="{{ route('requisitions.index') }}" role="search">
                    <input type="hidden" name="tab" value="history">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="history_q" value="{{ $historyQ }}" placeholder="Search REQ no / item / remarks..." maxlength="100" aria-label="Search requisition history">
                    @if($historyQ !== '')
                        <a href="{{ route('requisitions.index', ['tab' => 'history', 'history_status' => $historyStatus]) }}" class="pr-select" style="text-decoration:none;padding:6px 10px;" title="Clear search">&times; Clear</a>
                    @endif
                </form>
                <div class="chips" role="group" aria-label="Filter by status">
                    @foreach([['all','All'],['pending','Pending'],['approved','Approved'],['issued','Issued'],['rejected','Rejected']] as [$k,$lbl])
                    <a href="{{ route('requisitions.index', array_filter(['tab'=>'history','history_status'=>$k,'history_q'=>$historyQ], fn ($v) => $v !== '' && $v !== null)) }}" class="{{ $historyStatus === $k ? 'active' : '' }}">{{ $lbl }}</a>
                    @endforeach
                </div>
            </div>
            @if($requisitions->isEmpty())
                <div class="cmms-panel">
                    <div class="cmms-panel-body cmms-empty">
                        <i class="fa-solid fa-box-archive cmms-empty-icon"></i>
                        <h3>No requisition history</h3>
                        <p>Parts requests submitted to Supply will appear here.</p>
                    </div>
                </div>
            @else
                <div class="mobile-table-hint"><i class="fa-solid fa-arrow-right-arrow-left"></i> Swipe table horizontally to view all columns</div>
                <div class="table-wrap tab-history-scroll" style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
                    <table class="cmms-official-table" style="table-layout:fixed;min-width:860px;">
                        <colgroup>
                            <col style="width:12%">
                            <col style="width:7%">
                            <col style="width:13%">
                            <col style="width:15%">
                            <col>
                            <col style="width:14%">
                            <col style="width:14%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th style="text-align:left;">REQ #</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Job order</th>
                                <th>Items</th>
                                <th>Date</th>
                                <th>Completed</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                            @include('requisitions.partials.history-rows')
                        </tbody>
                    </table>
                </div>
                <div id="historyPagination" class="paginator-compact">{{ $requisitions->links() }}</div>
            @endif
        </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
(function () {
    const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');

    // Open (pending/approved) requisitions per ticket — for the duplicate-parts guard.
    const OPEN_REQS = @json($openRequisitions ?? collect());
    const openByTicket = {};
    (OPEN_REQS || []).forEach(r => { (openByTicket[r.request_id] = openByTicket[r.request_id] || []).push(r); });

    // Mirror of RequisitionSupport::sameParts (part_id or trimmed description, order-independent).
    function partsFingerprint(items) {
        const keys = [];
        (items || []).forEach(it => {
            const source = it.source || '';
            const pid = it.part_id ?? null;
            if ((source === 'parts-stock' || source === 'spare') && pid !== null && pid !== '') {
                keys.push('pid:' + String(pid));
            } else {
                keys.push('dsc:' + String(it.description || '').trim().toLowerCase());
            }
        });
        keys.sort();
        return keys.join('|');
    }
    function duplicatesOpenParts(ticketId, items) {
        const open = openByTicket[ticketId] || [];
        const fp = partsFingerprint(items);
        if (!fp) return null;
        const hit = open.find(r => partsFingerprint(r.items) === fp);
        if (!hit) return null;
        return {
            status: hit.status === 'approved' ? 'approved and awaiting release' : 'pending with Supply',
            requisitionId: hit.id,
        };
    }
    const duplicateWarningEl = () => document.getElementById('openReqWarning');
    function updateDuplicateGuard() {
        const list = document.querySelectorAll('.ticket-req-row');
        const items = [];
        list.forEach(row => {
            const desc = row.querySelector('.ticket-req-desc')?.value?.trim();
            if (desc) items.push({
                description: desc,
                source: row.querySelector('.ticket-req-source-val')?.value || 'other',
                part_id: row.querySelector('.ticket-req-part-id')?.value || null,
            });
        });
        const reqId = document.getElementById('request_id')?.value;
        const hit = reqId ? duplicatesOpenParts(reqId, items) : null;
        const warn = duplicateWarningEl();
        const btn = document.getElementById('ticketPartsSubmitBtn');
        if (hit) {
            if (warn) { warn.style.display = 'block'; }
            if (btn) { btn.disabled = true; }
        } else {
            if (warn) { warn.style.display = 'none'; }
            if (btn) { btn.disabled = false; }
        }
    }
    const reqSelect = document.getElementById('request_id');
    if (reqSelect) reqSelect.addEventListener('change', () => { updateTicketContext(); updateDuplicateGuard(); });
    const reqItemsList = document.getElementById('ticketReqItemsList');
    if (reqItemsList) {
        reqItemsList.addEventListener('input', updateDuplicateGuard);
        reqItemsList.addEventListener('change', updateDuplicateGuard);
    }

    document.querySelectorAll('.cmms-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.cmms-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.cmms-tab-content').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            document.getElementById(this.dataset.target).classList.add('active');
        });
    });

    const requestIdSelect = document.getElementById('request_id');
    const dynamicAssetRow = document.getElementById('dynamicAssetRow');
    const dynamicAssetInfo = document.getElementById('dynamicAssetInfo');
    const updateTicketContext = () => {
        if (!dynamicAssetInfo) return;
        const opt = requestIdSelect && requestIdSelect.options[requestIdSelect.selectedIndex];
        if (!opt || !opt.value) {
            if (dynamicAssetRow) dynamicAssetRow.style.display = 'none';
            return;
        }
        const asset = opt.dataset.asset || 'N/A';
        const serial = opt.dataset.serial && opt.dataset.serial !== '-' ? `(SN: ${opt.dataset.serial})` : '';
        const custodian = opt.dataset.custodian || 'Unassigned';
        
        dynamicAssetInfo.innerHTML = `<strong>${esc(asset)}</strong> <span style="color:#64748b; font-size:11px;">${esc(serial)}</span> <br><span style="color:#64748b;">issued to</span> <i class="fa-solid fa-user-check" style="color:#0038A8; margin-left:4px; font-size:10px;"></i> <strong>${esc(custodian)}</strong>`;
        
        if (dynamicAssetRow) dynamicAssetRow.style.display = 'grid';
    };
    if (requestIdSelect) requestIdSelect.addEventListener('change', updateTicketContext);
    updateTicketContext();
    updateDuplicateGuard();

    const ticketReqItemsList = document.getElementById('ticketReqItemsList');
    if (!ticketReqItemsList) return;

    let submitting = false;

    const rowHtml = () => `
        <tr class="ticket-req-row">
            <td class="col-num input-qty-center"></td>
            <td class="col-qty"><input type="number" class="cmms-pr-input ticket-req-qty input-qty-center" min="1" value="1" required></td>
            <td class="col-desc">
                <input type="text" class="cmms-pr-input ticket-req-desc" placeholder="Item description" required>
                <select class="ticket-req-source">
                    <option value="">Source: type manually / spare asset</option>
                    @foreach($partsStock as $ps)
                        <option value="{{ $ps->id }}" data-name="{{ $ps->item_name }}" data-unit="{{ $ps->unit }}" data-onhand="{{ $ps->on_hand_qty }}">From Parts Stock: {{ $ps->item_name }} ({{ $ps->on_hand_qty }} {{ $ps->unit }})</option>
                    @endforeach
                </select>
                <input type="hidden" class="ticket-req-part-id" value="">
                <input type="hidden" class="ticket-req-source-val" value="other">
            </td>
            <td class="col-action"><button type="button" class="cmms-row-remove remove-item-btn" tabindex="-1">&times;</button></td>
        </tr>`;

    const renumberTicketRows = () => {
        const rows = ticketReqItemsList.querySelectorAll('.ticket-req-row');
        rows.forEach((row, idx) => {
            const num = row.querySelector('.col-num');
            if (num) num.textContent = (idx + 1);
        });
        const countEl = document.getElementById('ticketReqItemCount');
        if (countEl) countEl.textContent = rows.length + (rows.length === 1 ? ' line item' : ' line items');
    };

    document.getElementById('addTicketReqLine')?.addEventListener('click', () => {
        ticketReqItemsList.insertAdjacentHTML('beforeend', rowHtml());
        renumberTicketRows();
        const rows = ticketReqItemsList.querySelectorAll('.ticket-req-row');
        const lastDesc = rows[rows.length - 1]?.querySelector('.ticket-req-desc');
        if (lastDesc) lastDesc.focus();
    });

    ticketReqItemsList.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-item-btn')) {
            if (ticketReqItemsList.querySelectorAll('.ticket-req-row').length > 1) {
                e.target.closest('tr').remove();
                renumberTicketRows();
            } else {
                Swal.fire({ icon: 'warning', title: 'Cannot remove', text: 'At least one item is required.', confirmButtonColor: '#0038A8' });
            }
        }
    });

    // When IT picks a part from Parts Stock, prefill the description + part_id/source.
    ticketReqItemsList.addEventListener('change', (e) => {
        if (!e.target.classList.contains('ticket-req-source')) return;
        const row = e.target.closest('tr');
        const opt = e.target.selectedOptions[0];
        const partIdInput = row.querySelector('.ticket-req-part-id');
        const sourceValInput = row.querySelector('.ticket-req-source-val');
        const descInput = row.querySelector('.ticket-req-desc');
        const qtyInput = row.querySelector('.ticket-req-qty');

        if (opt && opt.value) {
            partIdInput.value = opt.value;
            sourceValInput.value = 'parts-stock';
            descInput.value = opt.dataset.name || '';
            if (!qtyInput.value || parseInt(qtyInput.value, 10) < 1) qtyInput.value = 1;
        } else {
            partIdInput.value = '';
            sourceValInput.value = 'other';
        }
    });

    renumberTicketRows();

    document.getElementById('ticketPartsSubmitBtn')?.addEventListener('click', async () => {
        if (submitting) return;

        const reqId = document.getElementById('request_id').value;
        if (!reqId) {
            Swal.fire({ icon: 'warning', title: 'Select job order', text: 'Choose the ICT or PM job order this request is for.', confirmButtonColor: '#0038A8' });
            return;
        }

        const items = [];
        let hasEmptyDesc = false;
        document.querySelectorAll('.ticket-req-row').forEach(row => {
            const desc = row.querySelector('.ticket-req-desc')?.value?.trim();
            const qty = parseInt(row.querySelector('.ticket-req-qty')?.value || '1', 10);
            if (desc) items.push({
                description: desc,
                quantity: qty,
                source: row.querySelector('.ticket-req-source-val')?.value || 'other',
                part_id: row.querySelector('.ticket-req-part-id')?.value || null,
            });
            else hasEmptyDesc = true;
        });

        if (items.length === 0 || hasEmptyDesc) {
            Swal.fire({ icon: 'warning', title: 'Incomplete items', text: 'Fill in every item description.', confirmButtonColor: '#0038A8' });
            return;
        }

        // Final duplicate-parts safety (also enforced server-side in StoreRequisitionAction).
        const dup = duplicatesOpenParts(reqId, items);
        if (dup) {
            Swal.fire({
                icon: 'warning',
                title: 'Ongoing request found',
                text: 'This ticket already has an ongoing parts request for the same items. Wait for it to be issued or rejected before requesting again.',
                confirmButtonColor: '#0038A8',
            });
            return;
        }

        submitting = true;
        const btn = document.getElementById('ticketPartsSubmitBtn');
        const originalLabel = btn.textContent;
        btn.textContent = 'Submitting...';
        btn.disabled = true;

        let url = @json(route('requisitions.store', 'REPLACE_ID'));
        url = url.replace('REPLACE_ID', reqId);

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    items,
                    remarks: document.getElementById('ticketReqRemarks')?.value || '',
                    set_awaiting_parts: document.getElementById('ticketReqAwaitingParts')?.checked,
                }),
            });
            const data = await res.json();

            if (res.ok && data.success) {
                await Swal.fire({ icon: 'success', title: 'Submitted', text: data.message, confirmButtonColor: '#0038A8' });
                window.location.href = '{{ url('requisitions') }}/' + data.requisition_id;
                return;
            }

            Swal.fire({ icon: 'error', title: 'Failed', text: data.message || 'Could not submit.', confirmButtonColor: '#0038A8' });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Network error', text: 'Check your connection and try again.', confirmButtonColor: '#0038A8' });
        }

        submitting = false;
        btn.textContent = originalLabel;
        btn.disabled = false;
    });
})();

// Contextual "Create Purchase Request" hint for manual items (PR document flow)
(function () {
    const hint = document.getElementById('manualPrHint');
    const link = document.getElementById('manualPrLink');
    const list = document.getElementById('ticketReqItemsList');
    if (!hint || !link || !list) return;

    const createRoute = @json(route('purchase_requests.create'));

    function firstManualRow() {
        for (const row of list.querySelectorAll('.ticket-req-row')) {
            const desc = row.querySelector('.ticket-req-desc')?.value.trim() || '';
            const src = row.querySelector('.ticket-req-source')?.value || '';
            if (desc !== '' && src === '') return row;
        }
        return null;
    }

    // Collect rows that have a typed description but no Parts Stock source.
    function manualRows() {
        return [...list.querySelectorAll('.ticket-req-row')].filter((row) => {
            const desc = row.querySelector('.ticket-req-desc')?.value.trim() || '';
            const src  = row.querySelector('.ticket-req-source')?.value || '';
            return desc !== '' && src === '';
        });
    }

    let hintTimer = null;
    function scheduleHint() {
        clearTimeout(hintTimer);
        hintTimer = setTimeout(updateHint, 500); // wait for a typing pause
    }

    function updateHint() {
        const rows = manualRows();
        if (rows.length === 0) { hint.style.display = 'none'; return; }

        // Prefill the Create Purchase Request link with the first manual item,
        // carrying the selected job order ticket so the PR silently links to
        // the asset (Phase A invisible linkage).
        const row = rows[0];
        const qty = parseInt(row.querySelector('.ticket-req-qty')?.value, 10) || 1;
        const desc = encodeURIComponent(row.querySelector('.ticket-req-desc').value.trim());
        const sel = document.getElementById('request_id');
        const tid = sel && sel.value ? '&ticket=' + encodeURIComponent(sel.value) : '';
        link.href = createRoute + '?description=' + desc + '&quantity=' + qty + tid;

        const hintText = document.getElementById('manualPrHintText');
        if (hintText) {
            hintText.textContent = rows.length === 1
                ? 'This item is not in Parts Stock — it can\'t be issued from existing inventory. Create a Purchase Request instead.'
                : rows.length + ' items are not in Parts Stock — they can\'t be issued from existing inventory. Create a Purchase Request instead.';
        }
        hint.style.display = 'flex';
    }

    list.addEventListener('input', (e) => {
        if (e.target.matches('.ticket-req-desc, .ticket-req-source')) scheduleHint();
    });
    list.addEventListener('change', (e) => {
        if (e.target.matches('.ticket-req-source')) updateHint();
    });

    // Initial state (rows may be re-rendered with values after validation errors)
    list.querySelectorAll('.ticket-req-row').forEach(() => updateHint());
})();

// ── History tab AJAX (no reload) ──
(function () {
    const historyTableBody = document.getElementById('historyTableBody');
    const historyPagination = document.getElementById('historyPagination');
    if (!historyTableBody) return;

    function loadHistory(status, q, page) {
        const params = new URLSearchParams();
        params.set('tab', 'history');
        params.set('history_status', status || 'all');
        if (q) params.set('history_q', q);
        if (page) params.set('page', page);

        fetch(@json(route('requisitions.history.data')) + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            historyTableBody.innerHTML = data.rows;
            if (historyPagination) historyPagination.innerHTML = data.pagination;
            bindHistoryPagination();
        })
        .catch(() => {});
    }

    function bindHistoryPagination() {
        if (!historyPagination) return;
        historyPagination.querySelectorAll('a').forEach(a => {
            if (a._bound) return;
            a._bound = true;
            a.addEventListener('click', (e) => {
                e.preventDefault();
                const sp = new URLSearchParams(a.getAttribute('href').split('?')[1] || '');
                loadHistory(sp.get('history_status') || 'all', sp.get('history_q') || '', sp.get('page'));
            });
        });
    }

    const historyForm = document.querySelector('.cmms-history-toolbar form');
    if (historyForm) {
        historyForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const status = new URLSearchParams(window.location.search).get('history_status') || 'all';
            loadHistory(status, historyForm.querySelector('input[name="history_q"]').value.trim(), 1);
        });
        historyForm.querySelector('input[name="history_q"]').addEventListener('input', () => {
            clearTimeout(window._hQTimer);
            window._hQTimer = setTimeout(() => {
                const status = new URLSearchParams(window.location.search).get('history_status') || 'all';
                loadHistory(status, historyForm.querySelector('input[name="history_q"]').value.trim(), 1);
            }, 400);
        });
    }
    document.querySelectorAll('.cmms-history-toolbar .chips a').forEach(chip => {
        chip.addEventListener('click', (e) => {
            e.preventDefault();
            const sp = new URLSearchParams(chip.getAttribute('href').split('?')[1] || '');
            loadHistory(sp.get('history_status') || 'all', sp.get('history_q') || '', 1);
        });
    });
    bindHistoryPagination();
})();

// ── Purchase Requests tab AJAX (no reload) ──
(function () {
    const myPrsTableBody = document.getElementById('myPrsTableBody');
    const myPrsPagination = document.getElementById('myPrsPagination');
    if (!myPrsTableBody) return;

    function loadMyPrs(page) {
        const params = new URLSearchParams();
        params.set('tab', 'myprs');
        if (page) params.set('page', page);

        fetch(@json(route('requisitions.myprs.data')) + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            myPrsTableBody.innerHTML = data.rows;
            if (myPrsPagination) myPrsPagination.innerHTML = data.pagination;
            bindMyPrsPagination();
        })
        .catch(() => {});
    }

    function bindMyPrsPagination() {
        if (!myPrsPagination) return;
        myPrsPagination.querySelectorAll('a').forEach(a => {
            if (a._bound) return;
            a._bound = true;
            a.addEventListener('click', (e) => {
                e.preventDefault();
                const page = new URLSearchParams(a.getAttribute('href').split('?')[1] || '').get('page');
                loadMyPrs(page);
            });
        });
    }
    bindMyPrsPagination();
})();
</script>
@endsection