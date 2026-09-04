@extends('layouts.app')

@section('title', 'Purchase Request '.$purchaseRequest->pr_number)
@section('page-title', 'Purchase Request')

@section('styles')
<style nonce="{{ $cspNonce }}">
    .prd-wrap { width:100%; margin-top:-10px; }
    .prd-toolbar { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:11px 16px; }

    /* ── Action buttons (screen only) ── */
    .prd-actions { margin-left:auto; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .prd-action-btn { display:inline-flex; align-items:center; gap:7px; padding:8px 16px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; transition:transform .12s, box-shadow .12s, background .15s; text-decoration:none; border:1px solid transparent; }
    .prd-action-btn i { font-size:11px; }
    .prd-action-btn--primary { background:#0038A8; color:#fff; box-shadow:0 2px 8px rgba(0,56,168,.25); }
    .prd-action-btn--primary:hover { background:#002d87; transform:translateY(-1px); box-shadow:0 3px 12px rgba(0,56,168,.32); }
    .prd-action-btn--success { background:#15803d; color:#fff; box-shadow:0 2px 8px rgba(21,128,61,.28); }
    .prd-action-btn--success:hover { background:#166534; transform:translateY(-1px); box-shadow:0 3px 12px rgba(21,128,61,.35); }
    .prd-action-btn--secondary { background:#fff; color:#374151; border-color:#cbd5e1; }
    .prd-action-btn--secondary:hover { background:#eef2ff; border-color:#0038A8; color:#0038A8; }
    .prd-action-btn--locked { background:#f8fafc; color:#94a3b8; border-color:#e2e8f0; cursor:not-allowed; }
    .prd-print-tip { flex-basis:100%; font-size:10px; color:#94a3b8; text-align:right; line-height:1.45; }

    /* ── Status explainer pill (compact) ── */
    .prd-status-box { display:inline-flex; align-items:center; gap:8px; padding:5px 13px; border-radius:20px; font-size:11.5px; border:1px solid transparent; }
    .prd-status-box .dot { width:6px; height:6px; border-radius:50%; background:currentColor; flex:none; }
    .prd-status-box .st-title { font-weight:800; font-size:10.5px; letter-spacing:.4px; text-transform:uppercase; }
    .prd-status-box .st-desc { font-size:11.5px; opacity:.85; }
    .prd-status-box.submitted { background:#fefce8; border-color:#fde68a; color:#854d0e; }
    .prd-status-box.submitted .dot { animation:prdPulse 1.6s ease-in-out infinite; }
    .prd-status-box.finalized { background:#f0fdf4; border-color:#bbf7d0; color:#166534; }
    .prd-status-box.draft { background:#f8fafc; border-color:#e2e8f0; color:#475569; }
    @keyframes prdPulse { 0%,100% { opacity:1; } 50% { opacity:.3; } }
    .prd-status-pill { display:inline-block; padding:3px 12px; border-radius:20px; font-size:11.5px; font-weight:800; }
    .st-submitted { background:#fef3c7; color:#92400e; }
    .st-finalized { background:#dcfce7; color:#166534; }
    .st-draft { background:#e2e8f0; color:#334155; }
    .prd-sheet { background:#fff; border:1px solid #cbd5e1; border-radius:8px; padding:28px 32px; max-width:830px; margin:0 auto; font-family:Arial, Helvetica, sans-serif; color:#111827; }
    .prd-title { text-align:center; font-size:14px; font-weight:700; letter-spacing:2px; margin:2px 0 10px; }

    /* ── Header field grid (first section of the sheet) ── */
    .a60-hdr { width:100%; border-collapse:collapse; margin-bottom:12px; font-size:12px; }
    .a60-hdr td { padding:0; vertical-align:top; }
    .a60-field { border:1px solid #374151; padding:4px 8px 3px; }
    .a60-field-lbl { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#6b7280; margin-bottom:2px; }
    .a60-field-val { font-weight:700; color:#111827; font-size:12.5px; }
    .a60-field-val.accent { color:#0038A8; }
    .a60-field-val.muted { color:#9ca3af; font-weight:400; font-style:italic; }

    .prd-meta { display:grid; grid-template-columns:1fr 1fr; gap:6px 26px; font-size:12.5px; margin-bottom:16px; }
    .prd-meta .k { color:#4b5563; }
    .prd-meta .v { font-weight:600; }
    .prd-table { width:100%; border-collapse:collapse; font-size:11px; margin-bottom:10px; table-layout:fixed; }
    .prd-table th, .prd-table td { border:1px solid #374151; padding:4px 6px; text-align:left; vertical-align:top; word-break:break-word; }
    .prd-table th { background:#f3f4f6; font-family:Arial, sans-serif; font-size:9.5px; text-transform:uppercase; letter-spacing:.4px; text-align:center; }
    .prd-table .num { text-align:center; }
    .prd-table .right { text-align:right; white-space:nowrap; }
    .prd-total-row td { font-weight:800; background:#f9fafb; text-align:right; }
    .prd-purpose { font-size:11.5px; margin-bottom:14px; padding:7px 10px; border:1px solid #374151; min-height:38px; }
    .prd-purpose .k { font-weight:700; margin-right:6px; }

    /* Signatures — one aligned grid: Requested | Approved side by side */
    table.prd-signs { width:100%; border-collapse:collapse; margin-top:64px; font-size:11.5px; page-break-inside:avoid; }
    .prd-signs td { padding:13px 8px; vertical-align:bottom; }
    .prd-signs td.lbl { white-space:nowrap; width:16%; color:#374151; padding-left:0; }
    .prd-signs td.val { border-bottom:1px solid #111827; text-align:center; font-weight:700; height:24px; width:26%; }
    .prd-signs td.sig-space { height:54px; }
    .prd-signs td.who { font-weight:800; padding-bottom:16px; padding-left:0; font-size:12px; }

    /* Phase C - delivered status + receive panel + receipts (screen only) */
    .prd-status-box.sp-status-delivered { background:#ecfdf5; border-color:#10b981; }
    .prd-status-box.sp-status-delivered .dot { background:#059669; }
    .rx-panel { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:16px 18px; margin-top:12px; }
    .rx-panel h3 { margin:0 0 4px; font-size:15px; font-weight:800; color:#111827; }
    .rx-panel .rx-hint { font-size:12px; color:#6b7280; margin-bottom:12px; }
    .rx-line { border:1px solid #f3f4f6; border-radius:8px; padding:10px 12px; margin-bottom:10px; }
    .rx-line-head { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .rx-line-desc { flex:1 1 220px; font-size:13px; font-weight:600; color:#111827; }
    .rx-line select, .rx-line input[type=text] { padding:6px 8px; border:1px solid #d1d5db; border-radius:6px; font-size:12.5px; }
    .rx-unit-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px 10px; margin-top:8px; }
    .rx-unit-grid input[type=text] { width:100%; }
    .rx-unit-grid label { font-size:11px; color:#374151; display:block; margin-bottom:2px; }
    @media print { .rx-panel, .pr-attach-card { display:none !important; } }

    .pr-attach-card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px 18px; margin-top:14px; }
    .pr-attach-card h3 { margin:0 0 8px; font-size:14px; font-weight:800; color:#111827; }
    .pr-attach-row { display:flex; align-items:center; gap:10px; padding:7px 0; border-bottom:1px solid #f3f4f6; font-size:12.5px; flex-wrap:wrap; }
    .pr-attach-row:last-of-type { border-bottom:none; }
    .pr-attach-meta { color:#6b7280; font-size:11.5px; }

    .prd-table-responsive { width:100%; }

    @media screen and (max-width: 768px) {
        .prd-wrap { padding:12px 8px !important; margin-top:0 !important; }
        .prd-toolbar {
            flex-direction:column !important;
            align-items:stretch !important;
            gap:12px !important;
            padding:12px 14px !important;
        }
        .prd-status-box {
            width:100% !important;
            box-sizing:border-box !important;
            flex-direction:column !important;
            align-items:flex-start !important;
            gap:6px !important;
            padding:10px 14px !important;
            border-radius:8px !important;
        }
        .prd-actions {
            width:100% !important;
            margin-left:0 !important;
            display:grid !important;
            grid-template-columns:1fr 1fr !important;
            gap:8px !important;
        }
        .prd-actions form {
            display:contents !important;
        }
        .prd-action-btn {
            width:100% !important;
            min-height:44px !important;
            justify-content:center !important;
            text-align:center !important;
            padding:10px 12px !important;
            box-sizing:border-box !important;
            font-size:13px !important;
        }
        .prd-print-tip {
            text-align:center !important;
        }
        .prd-sheet {
            padding:14px 10px !important;
            border-radius:10px !important;
            margin:0 !important;
            width:100% !important;
            max-width:100% !important;
            min-width:0 !important;
            box-sizing:border-box !important;
            overflow:hidden !important;
        }
        .prd-table-responsive {
            width:100% !important;
            max-width:100% !important;
            min-width:0 !important;
            overflow-x:auto !important;
            -webkit-overflow-scrolling:touch !important;
            margin-bottom:12px !important;
            box-sizing:border-box !important;
            display:block !important;
        }
        .prd-table {
            min-width:620px !important;
        }
        /* Header fields: Mobile Grid with PR No. and Date side-by-side */
        .a60-hdr-wrap {
            width:100% !important;
            max-width:100% !important;
            border:1.5px solid #374151 !important;
            border-radius:8px !important;
            overflow:hidden !important;
            box-sizing:border-box !important;
            margin-bottom:14px !important;
            background:#fff !important;
        }
        table.a60-hdr {
            display:block !important;
            width:100% !important;
            max-width:100% !important;
            border:none !important;
            border-radius:0 !important;
            overflow:visible !important;
            box-sizing:border-box !important;
            margin-bottom:0 !important;
            background:#fff !important;
        }
        table.a60-hdr colgroup {
            display:none !important;
        }
        table.a60-hdr tbody {
            display:block !important;
            width:100% !important;
            box-sizing:border-box !important;
        }
        table.a60-hdr tr {
            display:flex !important;
            flex-wrap:wrap !important;
            width:100% !important;
            box-sizing:border-box !important;
        }
        table.a60-hdr td,
        table.a60-hdr td.a60-field {
            display:block !important;
            box-sizing:border-box !important;
        }
        table.a60-hdr td.a60-cell-entity,
        table.a60-hdr td.a60-cell-fund,
        table.a60-hdr td.a60-cell-office,
        table.a60-hdr td.a60-cell-rcc {
            display:block !important;
            width:100% !important;
            flex:0 0 100% !important;
            max-width:100% !important;
            border-top:0 !important;
            border-right:0 !important;
            border-left:0 !important;
            border-bottom:1px solid #cbd5e1 !important;
            padding:7px 10px !important;
            box-sizing:border-box !important;
            background:#fff !important;
        }
        table.a60-hdr td.a60-cell-pr {
            display:block !important;
            width:50% !important;
            flex:0 0 50% !important;
            max-width:50% !important;
            border-top:0 !important;
            border-left:0 !important;
            border-right:1px solid #cbd5e1 !important;
            border-bottom:1px solid #cbd5e1 !important;
            padding:7px 10px !important;
            box-sizing:border-box !important;
            background:#fff !important;
        }
        table.a60-hdr td.a60-cell-date {
            display:block !important;
            width:50% !important;
            flex:0 0 50% !important;
            max-width:50% !important;
            border-top:0 !important;
            border-left:0 !important;
            border-right:0 !important;
            border-bottom:1px solid #cbd5e1 !important;
            padding:7px 10px !important;
            box-sizing:border-box !important;
            background:#fff !important;
        }
        table.a60-hdr td.a60-cell-rcc {
            border-bottom:0 !important;
        }
        .a60-field-val {
            font-size:12.5px !important;
            font-weight:700 !important;
            white-space:normal !important;
            word-break:break-word !important;
            overflow:visible !important;
            line-height:1.3 !important;
        }
        .a60-cell-pr .a60-field-val,
        .a60-cell-date .a60-field-val {
            font-size:12px !important;
            white-space:nowrap !important;
            overflow:hidden !important;
            text-overflow:ellipsis !important;
        }
        .prd-signs {
            display:block !important;
            margin-top:28px !important;
        }
        .prd-signs tr {
            display:flex !important;
            flex-direction:column !important;
            margin-bottom:16px !important;
        }
        .prd-signs td {
            display:block !important;
            width:100% !important;
            padding:6px 0 !important;
            text-align:left !important;
        }
        .prd-signs td.val {
            text-align:left !important;
            border-bottom:1px solid #111827 !important;
        }
        .prd-signs td.sig-space {
            height:36px !important;
        }
        .rx-panel {
            padding:14px 12px !important;
        }
        .rx-line-head {
            flex-direction:column !important;
            align-items:stretch !important;
        }
        .rx-unit-grid {
            grid-template-columns:1fr !important;
        }
        .pr-attach-card {
            padding:12px 14px !important;
        }
        .pr-attach-row {
            flex-direction:column !important;
            align-items:flex-start !important;
        }
    }

    @media print {
        @page { size:A4 portrait; margin:12mm; }
        body * { visibility:hidden; }
        .prd-sheet, .prd-sheet * { visibility:visible; }
        .prd-sheet { position:absolute; left:0; top:0; width:100%; border:none; border-radius:0; padding:0; max-width:none; }
        .no-print { display:none !important; }
        .prd-table-responsive { overflow:visible !important; }
        .prd-table { min-width:0 !important; }
        .a60-hdr colgroup { display:table-column-group !important; }
        .a60-hdr tr { display:table-row !important; }
        .a60-hdr td { display:table-cell !important; }
        .prd-table tr { page-break-inside:avoid; }
        .prd-signs { display:table !important; page-break-inside:avoid; }
        .prd-signs tr { display:table-row !important; }
        .prd-signs td { display:table-cell !important; }
    }
</style>
@endsection

@section('content')
<div class="prd-wrap">
    <div class="prd-toolbar no-print">
        <a href="{{ route('requisitions.index') }}" class="cmms-btn-secondary" aria-label="Back to requisitions page"><i class="fa-solid fa-arrow-left" style="margin-right:6px;"></i>Back</a>

        @php
            $stClass = $purchaseRequest->status === 'finalized' || $purchaseRequest->status === 'delivered' ? 'finalized' : ($purchaseRequest->status === 'submitted' && ! $purchaseRequest->isLegacyStatus() ? 'submitted' : 'draft');
            if ($purchaseRequest->status === 'delivered') { $stClass = 'sp-status-delivered'; }
        @endphp
        <div class="prd-status-box {{ $stClass }}">
            <span class="dot"></span>
            <span class="st-title">
                @if($purchaseRequest->isLegacyStatus())
                    {{ ucfirst($purchaseRequest->status) }} (legacy)
                @else
                    {{ ucfirst($purchaseRequest->status) }}
                @endif
            </span>
            <span class="st-desc">
                @if($purchaseRequest->status === 'delivered')
                    — Goods received {{ optional($purchaseRequest->delivered_at)->format('M d, Y') }} by {{ $purchaseRequest->deliverer?->full_name ?? 'Supply Officer' }}.
                @elseif($purchaseRequest->status === 'finalized')
                    — Ready to print and submit to Procurement.
                @elseif($purchaseRequest->status === 'submitted')
                    — Waiting for Supply Officer review · printing unlocks once finalized.
                @else
                    — Not yet submitted · printing unavailable until it reaches Supply.
                @endif
            </span>
        </div>

        <div class="prd-actions">
            @php
                $canEditPr = $purchaseRequest->status === \App\Models\PurchaseRequest::STATUS_SUBMITTED
                    && (Auth::user()->canProcessSupply()
                        || Auth::user()->role === 'super_admin'
                        || (Auth::user()->role === 'it' && ($purchaseRequest->requested_by === Auth::id() || $purchaseRequest->created_by === Auth::id())));
            @endphp
            @if($canEditPr)
                <a href="{{ route('purchase_requests.edit', $purchaseRequest->id) }}" class="prd-action-btn prd-action-btn--secondary" aria-label="Edit purchase request" title="Correct items, quantities, or header details">
                    <i class="fa-solid fa-pen-to-square"></i>Edit
                </a>
            @endif
            @if($purchaseRequest->status === 'submitted' && Auth::user()->canProcessSupply())
                <form method="POST" action="{{ route('purchase_requests.finalize', $purchaseRequest->id) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="prd-action-btn prd-action-btn--success pr-finalize-btn" data-pr="{{ $purchaseRequest->pr_number }}" title="Lock this document and unlock official printing">
                        <i class="fa-solid fa-stamp"></i>Finalize &amp; Print
                    </button>
                </form>
            @elseif($purchaseRequest->status === 'finalized')
                <button type="button" onclick="window.print()" class="prd-action-btn prd-action-btn--primary" aria-label="Print purchase request document">
                    <i class="fa-solid fa-print"></i>Print document
                </button>
                @if(!empty($canReceive))
                    <a href="{{ route('purchase_requests.receiveForm', $purchaseRequest->id) }}" class="prd-action-btn prd-action-btn--success" aria-label="Record the delivery of purchased goods" title="Goods arrived? Record what arrived and where it went">
                        <i class="fa-solid fa-box-open"></i>Record delivery
                    </a>
                @endif
            @elseif($purchaseRequest->status === 'delivered')
                <a href="{{ route('purchase_requests.receiveForm', $purchaseRequest->id) }}" class="prd-action-btn prd-action-btn--secondary" aria-label="View the delivery record and proof of purchase" title="View delivery record, recorded items, and proof of purchase">
                    <i class="fa-solid fa-receipt"></i>View delivery
                </a>
                <span class="prd-action-btn prd-action-btn--locked"><i class="fa-solid fa-circle-check"></i>Delivered</span>
            @else
                <span class="prd-action-btn prd-action-btn--locked"><i class="fa-solid fa-lock"></i>Print locked</span>
            @endif
        </div>
    </div>


    <div class="prd-sheet">
        <div class="prd-title">PURCHASE REQUEST</div>

        <div class="a60-hdr-wrap">
        <table class="a60-hdr" role="presentation" aria-label="Purchase request header fields">
            <colgroup>
                <col style="width:50%;">
                <col style="width:25%;">
                <col style="width:25%;">
            </colgroup>
            {{-- Row 1: Entity Name | Fund Cluster --}}
            <tr>
                <td class="a60-field a60-cell-entity hdr-br-none">
                    <div class="a60-field-lbl">Entity Name</div>
                    <div class="a60-field-val">National Conciliation and Mediation Board</div>
                </td>
                <td class="a60-field a60-cell-fund hdr-bl" colspan="2">
                    <div class="a60-field-lbl">Fund Cluster</div>
                    <div class="a60-field-val {{ $purchaseRequest->fund_cluster ? '' : 'muted' }}">
                        {{ $purchaseRequest->fund_cluster ?: '— not specified —' }}
                    </div>
                </td>
            </tr>
            {{-- Row 2: Office/Unit | PR No. | Date --}}
            <tr>
                <td class="a60-field a60-cell-office hdr-bt-none hdr-br-none">
                    <div class="a60-field-lbl">Office / Unit</div>
                    <div class="a60-field-val {{ $purchaseRequest->office_unit ? '' : 'muted' }}">
                        {{ $purchaseRequest->office_unit ?: '— not specified —' }}
                    </div>
                </td>
                <td class="a60-field a60-cell-pr hdr-bt-none hdr-bl hdr-br-none">
                    <div class="a60-field-lbl">PR No.</div>
                    <div class="a60-field-val accent">{{ $purchaseRequest->pr_number }}</div>
                </td>
                <td class="a60-field a60-cell-date hdr-bt-none hdr-bl">
                    <div class="a60-field-lbl">Date</div>
                    <div class="a60-field-val">{{ $purchaseRequest->created_at?->format('F d, Y') }}</div>
                </td>
            </tr>
            {{-- Row 3: Responsibility Center Code (full width) --}}
            <tr>
                <td class="a60-field a60-cell-rcc hdr-bt-none" colspan="3">
                    <div class="a60-field-lbl">Responsibility Center Code</div>
                    <div class="a60-field-val {{ $purchaseRequest->responsibility_center ? '' : 'muted' }}">
                        {{ $purchaseRequest->responsibility_center ?: '— not specified —' }}
                    </div>
                </td>
            </tr>
        </table>
        </div>

        <div class="prd-table-responsive">
        <table class="prd-table">
            <thead>
                <tr>
                    <th style="width:14%;">Stock/Property No.</th>
                    <th style="width:9%;">Unit</th>
                    <th>Description / specification</th>
                    <th style="width:8%;">Qty</th>
                    <th style="width:15%;">Unit Cost</th>
                    <th style="width:16%;">Total Cost</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchaseRequest->items ?? [] as $i => $line)
                    @php
                        $qty = (int) ($line['quantity'] ?? 0);
                        $cost = isset($line['unit_cost']) && $line['unit_cost'] !== null ? (float) $line['unit_cost'] : null;
                        $amount = $cost !== null ? $qty * $cost : null;
                    @endphp
                    <tr>
                        <td class="num">&nbsp;</td>
                        <td class="num">@if(!empty($line['unit'])){{ $line['unit'] }}@else&nbsp;@endif</td>
                        <td>@if(!empty($line['description'])){{ $line['description'] }}@else&nbsp;@endif</td>
                        <td class="num">@if($qty > 0){{ $qty }}@else&nbsp;@endif</td>
                        <td class="right">@if($cost !== null){{ number_format($cost, 2) }}@else&nbsp;@endif</td>
                        <td class="right">@if($amount !== null){{ number_format($amount, 2) }}@else&nbsp;@endif</td>
                    </tr>
                @endforeach
                {{-- Pad with blank rows so the printed sheet shows a full official grid --}}
                @php $blankRows = max(0, 8 - count($purchaseRequest->items ?? [])); @endphp
                @for($b = 0; $b < $blankRows; $b++)
                    <tr>
                        <td class="num">&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td class="num">&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                @endfor
                <tr class="prd-total-row">
                    <td colspan="5" style="text-align:right;">TOTAL</td>
                    <td>@if($purchaseRequest->total_amount !== null)&#8369; {{ number_format((float) $purchaseRequest->total_amount, 2) }}@else&nbsp;@endif</td>
                </tr>
            </tbody>
        </table>
        </div>

        <div class="prd-purpose">
            <span class="k">Purpose / justification:</span>
            @php $purposeText = trim((string) ($purchaseRequest->purpose ?: $purchaseRequest->remarks)); @endphp
            @if($purposeText !== '')
                {{ $purposeText }}
            @else
                &nbsp;
            @endif
        </div>

        @if($purchaseRequest->requisition)
            <div style="font-size:11.5px;color:#4b5563;margin-bottom:20px;">
                Requisition reference: {{ $purchaseRequest->requisition->display_number }}
                @if($purchaseRequest->finalized_at)
                    &middot; Finalized {{ $purchaseRequest->finalized_at->format('F d, Y') }}
                    @if($purchaseRequest->finalizer) by {{ $purchaseRequest->finalizer->full_name }}@endif
                @endif
            </div>
        @endif

        <table class="prd-signs">
            <tr>
                <td class="who" colspan="2">Requested by:</td>
                <td class="who" colspan="2">Approved by:</td>
            </tr>
            <tr>
                <td class="lbl">Signature&nbsp;:</td>
                <td class="val sig-space">&nbsp;</td>
                <td class="lbl">&nbsp;</td>
                <td class="val sig-space">&nbsp;</td>
            </tr>
            <tr>
                <td class="lbl">Printed Name&nbsp;:</td>
                <td class="val">@if(!empty($purchaseRequest->requester?->full_name)){{ $purchaseRequest->requester->full_name }}@else&nbsp;@endif</td>
                <td class="lbl">&nbsp;</td>
                <td class="val">@if(!empty($purchaseRequest->finalizer?->full_name)){{ $purchaseRequest->finalizer->full_name }}@else&nbsp;@endif</td>
            </tr>
            <tr>
                <td class="lbl">Designation&nbsp;:</td>
                <td class="val">@if(!empty($purchaseRequest->requester?->position)){{ $purchaseRequest->requester->position }}@else&nbsp;@endif</td>
                <td class="lbl">&nbsp;</td>
                <td class="val">@if(!empty($purchaseRequest->finalizer?->position)){{ $purchaseRequest->finalizer->position }}@else&nbsp;@endif</td>
            </tr>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
    // Finalize confirmation (Supply only; button renders for submitted PRs)
    document.querySelectorAll('.pr-finalize-btn').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const form = btn.closest('form');
            Swal.fire({
                icon: 'question',
                title: 'Finalize this Purchase Request?',
                text: (btn.dataset.pr || '') + ' will be marked finalized — printing unlocks and it is ready for physical submission to Procurement.',
                showCancelButton: true,
                confirmButtonText: 'Finalize',
                confirmButtonColor: '#0038A8',
                cancelButtonColor: '#64748b',
            }).then((r) => { if (r.isConfirmed) form.submit(); });
        });
    });
</script>
@endsection
