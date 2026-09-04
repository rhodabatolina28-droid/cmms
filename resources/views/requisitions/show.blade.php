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

    /* Desktop defaults for custom components */
    .mobile-table-hint { display: none; }
    .info-bar-deficit {
        background: #fff7ed;
        color: #9a3412;
        border-bottom: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
    }
    .deficit-pr-btn {
        padding: 7px 14px;
        font-size: 12px;
        text-decoration: none;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
    }
    .info-bar-issue-notice {
        background: #eff6ff;
        color: #0038A8;
        border-bottom: none;
        padding: 10px 16px;
    }

    /* Container containment */
    .cmms-official,
    .cmms-official-page,
    .cmms-page-card,
    .cmms-page-card-body,
    .cmms-layout-split,
    .cmms-panel,
    .cmms-panel-body {
        box-sizing: border-box !important;
        max-width: 100% !important;
    }

    @media screen and (max-width: 768px) {
        html, body {
            overflow-x: hidden !important;
        }
        .cmms-official,
        .cmms-official-page {
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: hidden !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .cmms-page-card {
            border-radius: 8px !important;
            margin-bottom: 16px !important;
            overflow: hidden !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        .cmms-page-card-head {
            padding: 14px 14px !important;
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 10px !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .cmms-page-card-head h2 {
            font-size: 16px !important;
            line-height: 1.3 !important;
            word-break: break-word !important;
        }
        .cmms-page-card-head .sub {
            font-size: 12px !important;
            line-height: 1.4 !important;
        }
        .cmms-page-card-head .cmms-btn-secondary {
            width: 100% !important;
            justify-content: center !important;
            text-align: center !important;
            min-height: 44px !important;
            display: flex !important;
            align-items: center !important;
            box-sizing: border-box !important;
            font-weight: 700 !important;
        }
        .cmms-page-card-body {
            padding: 12px 10px !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        .cmms-layout-split {
            display: flex !important;
            flex-direction: column !important;
            gap: 16px !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        .cmms-layout-split > div,
        .cmms-sticky-side {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            position: static !important;
            box-sizing: border-box !important;
        }
        .cmms-panel {
            border-radius: 8px !important;
            margin-bottom: 14px !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        .cmms-panel-body {
            padding: 12px 12px !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        .panel-body-flush {
            padding: 0 !important;
        }
        /* Accountable property */
        .cmms-meta-grid {
            display: flex !important;
            flex-direction: column !important;
            gap: 8px !important;
            width: 100% !important;
        }
        .cmms-meta-card {
            display: flex !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 4px !important;
            padding: 10px 12px !important;
            background: #f8fafc !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .cmms-meta-card .label {
            font-size: 10.5px !important;
            font-weight: 800 !important;
            color: #64748b !important;
            text-transform: uppercase !important;
            letter-spacing: 0.04em !important;
            margin-bottom: 0 !important;
        }
        .cmms-meta-card .value {
            font-size: 13px !important;
            font-weight: 700 !important;
            color: #0f172a !important;
            word-break: break-word !important;
        }
        /* Mobile table swipe hint */
        .mobile-table-hint {
            display: flex !important;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 7px 10px;
            background: #eff6ff;
            color: #1e40af;
            font-size: 11px;
            font-weight: 700;
            border-bottom: 1px solid #dbeafe;
            width: 100%;
            box-sizing: border-box;
        }
        .table-wrap {
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch !important;
            box-sizing: border-box !important;
        }
        .desktop-colgroup {
            display: none !important;
        }
        .cmms-req-items-table {
            min-width: 500px !important;
            width: 500px !important;
            table-layout: auto !important;
        }
        .cmms-req-items-table th,
        .cmms-req-items-table td {
            padding: 9px 10px !important;
            font-size: 12px !important;
            white-space: nowrap !important;
        }
        .cmms-req-items-table td:nth-child(2) {
            white-space: normal !important;
            min-width: 150px !important;
            max-width: 220px !important;
            word-break: break-word !important;
        }
        /* Stock & inventory cards */
        .item-block {
            padding: 12px 12px !important;
        }
        .item-header {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 8px !important;
        }
        .item-header .badge-spare,
        .item-header .badge-stock-ok,
        .item-header .badge-short {
            align-self: flex-start !important;
        }
        .item-title {
            font-size: 13.5px !important;
            line-height: 1.4 !important;
            word-break: break-word !important;
        }
        .asset-row {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 8px !important;
            padding: 10px 12px !important;
            border-radius: 8px !important;
        }
        .asset-actions {
            width: 100% !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            flex-wrap: wrap !important;
            gap: 6px !important;
            border-top: 1px dashed #cbd5e1 !important;
            padding-top: 8px !important;
            margin-top: 4px !important;
        }
        /* Deficit alert bar */
        .info-bar-deficit {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 8px !important;
            padding: 10px 12px !important;
        }
        .deficit-pr-btn {
            width: 100% !important;
            min-height: 42px !important;
            justify-content: center !important;
            text-align: center !important;
            box-sizing: border-box !important;
            font-size: 13px !important;
            font-weight: 700 !important;
        }
        /* Bottom toolbar */
        .cmms-toolbar {
            display: flex !important;
            flex-direction: column !important;
            gap: 8px !important;
            width: 100% !important;
            margin-top: 14px !important;
        }
        .cmms-toolbar a,
        .cmms-toolbar .cmms-btn-secondary {
            width: 100% !important;
            min-height: 44px !important;
            justify-content: center !important;
            text-align: center !important;
            display: flex !important;
            align-items: center !important;
            box-sizing: border-box !important;
            font-size: 13px !important;
            font-weight: 700 !important;
        }
        /* Particulars */
        .cmms-pr-meta-row {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 10px 0 !important;
            border-bottom: 1px dashed #e2e8f0 !important;
        }
        .cmms-pr-meta-row:last-child {
            border-bottom: none !important;
        }
        .cmms-pr-meta-row .k {
            font-size: 11px !important;
            font-weight: 700 !important;
            color: #64748b !important;
            text-transform: uppercase !important;
        }
        .cmms-pr-meta-row .v {
            font-size: 13px !important;
            font-weight: 700 !important;
            text-align: right !important;
        }
        /* Official action review panel */
        #supplyReviewPanel {
            border: 2px solid #0038A8 !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 14px rgba(0,56,168,0.12) !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        #supplyReviewRemarks {
            width: 100% !important;
            min-height: 80px !important;
            box-sizing: border-box !important;
            font-size: 14px !important;
            padding: 10px 12px !important;
            border-radius: 8px !important;
        }
        .action-bar-flush {
            display: flex !important;
            flex-direction: column !important;
            gap: 10px !important;
            width: 100% !important;
        }
        .action-bar-flush button,
        .action-bar-flush .cmms-btn-primary,
        .action-bar-flush .cmms-btn-success,
        .action-bar-flush .cmms-btn-danger {
            width: 100% !important;
            min-height: 48px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            font-size: 14px !important;
            font-weight: 700 !important;
            border-radius: 8px !important;
            box-sizing: border-box !important;
        }
        /* SweetAlert mobile */
        .swal2-popup {
            width: 92% !important;
            max-width: 380px !important;
            padding: 18px 14px !important;
            border-radius: 14px !important;
        }
        .swal2-title {
            font-size: 17px !important;
        }
        .swal2-actions {
            width: 100% !important;
            gap: 8px !important;
            margin-top: 16px !important;
        }
        .swal2-actions button {
            min-height: 44px !important;
            border-radius: 8px !important;
            flex: 1 !important;
            margin: 0 !important;
        }
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

            {{-- ── Requested Items ── --}}
            <div class="cmms-panel mt-18">
                <div class="cmms-panel-head">
                    <h2><i class="fa-solid fa-list-check mr-6" style="color:#0038A8;"></i>Requested Items</h2>
                    <span class="badge-sm text-gray-400">{{ count($requisition->items ?? []) }} line item(s)</span>
                </div>
                <div class="cmms-panel-body panel-body-flush">
                    <div class="mobile-table-hint"><i class="fa-solid fa-arrows-left-right"></i> Swipe table horizontally to view all columns</div>
                    <div class="table-wrap">
                        <table class="cmms-official-table cmms-req-items-table">
                            <colgroup class="desktop-colgroup">
                                <col style="width:5%">
                                <col>
                                <col style="width:8%">
                                <col style="width:10%">
                                <col style="width:14%">
                                <col style="width:14%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="col-num">No.</th>
                                    <th style="text-align:left;">Description</th>
                                    <th class="col-qty">Qty</th>
                                    <th>Unit</th>
                                    <th class="col-price">Unit Cost</th>
                                    <th class="col-price">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($requisition->items ?? [] as $i => $line)
                                @php
                                    $stockMatch = $partsStockMatches->get($i);
                                    $matchPart = $stockMatch['part'] ?? null;
                                    // Get unit cost from the part's latest tracked unit record
                                    $unitCost = null;
                                    if ($matchPart && $matchPart->requires_unit_tracking) {
                                        $latestUnit = $matchPart->units()->whereNotNull('unit_value')->orderByDesc('created_at')->first();
                                        $unitCost = $latestUnit?->unit_value;
                                    }
                                    $amount = $unitCost !== null ? ($line['quantity'] ?? 1) * (float)$unitCost : null;
                                @endphp
                                <tr>
                                    <td class="col-num">{{ $i + 1 }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($line['description'] ?? '', 80) }}</td>
                                    <td class="col-qty">{{ $line['quantity'] ?? 1 }}</td>
                                    <td>{{ $matchPart?->unit ?? '—' }}</td>
                                    <td class="col-price">{{ $unitCost !== null ? '₱' . number_format((float) $unitCost, 2) : '—' }}</td>
                                    <td class="col-price">{{ $amount !== null ? '₱' . number_format($amount, 2) : '—' }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:20px;">No line items.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($requisition->remarks)
                    <div style="padding:14px 16px;border-top:1px solid #e2e8f0;">
                        <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;">Purpose / Justification</div>
                        <div style="font-size:13px;color:#1e293b;margin-top:4px;">{{ $requisition->remarks }}</div>
                    </div>
                    @endif
                </div>
            </div>

    @php
        // Only Spare units are releasable - Active/in-use copies never appear here,
        // and the whole panel stays hidden when no spares match the request.
        $spareInventoryMatches = $inventoryMatches->map(function ($m) {
            return ['requested' => $m['requested'], 'assets' => $m['assets']->where('status', 'Spare')->values()];
        })->filter(fn ($m) => $m['assets']->isNotEmpty())->values();
    @endphp
    @if((Auth::user()->canProcessSupply() || Auth::user()->role === 'super_admin') && $spareInventoryMatches->isNotEmpty())
    {{-- ══ SUPPLY: Inventory Availability Check ══ --}}
    <div class="cmms-panel mt-18">
        <div class="cmms-panel-head">
            <h2><i class="fa-solid fa-warehouse mr-6" style="color:#0038A8;"></i>Inventory Availability Check</h2>
            <span class="badge-sm text-green-800">
                {{ $spareInventoryMatches->count() }} of {{ count($requisition->items ?? []) }} item(s) have spare stock ready for issue
            </span>
        </div>
        <div class="cmms-panel-body panel-body-flush">
            <div class="info-bar">
                <i class="fa-solid fa-circle-info icon-mr-4"></i>
                These are possible matches from the current inventory. Verify physically before deciding to issue from stock or procure new.
            </div>
            @foreach($spareInventoryMatches as $lineIndex => $match)
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
    @endif

    @if((Auth::user()->canProcessSupply() || Auth::user()->role === 'super_admin') && $partsStockMatches->isNotEmpty())
    {{-- ══ SUPPLY: Parts & Consumables Stock Availability ══ --}}
    <div class="cmms-panel mt-18">
        <div class="cmms-panel-head">
            <h2><i class="fa-solid fa-boxes mr-6" style="color:#0038A8;"></i>Parts &amp; Consumables Stock</h2>
            @php $okLines = $partsStockMatches->filter(fn ($m) => empty($m['deficit']))->count(); @endphp
            <span class="badge-sm text-green-800">{{ $okLines }} of {{ $partsStockMatches->count() }} line(s) available in stock</span>
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
            <div class="info-bar info-bar-deficit">
                <span><i class="fa-solid fa-boxes-stacked icon-mr-4"></i>May kulang sa Parts Stock — gumawa ng Purchase Request.</span>
                <a href="{{ route('purchase_requests.create', ['requisition_id' => $requisition->id]) }}" class="cmms-btn-primary deficit-pr-btn">Create Purchase Request</a>
            </div>
            @endif
            @if($canReview && in_array($status, ['approved'], true))
            <div class="info-bar info-bar-issue-notice">
                <i class="fa-solid fa-circle-info icon-mr-4"></i>
                Issuing will deduct these items from Parts Stock and assign their serialized units to the linked asset custodian.
            </div>
            @endif
        </div>
    </div>
    @endif

            <div class="cmms-toolbar">
                @if($ticket)
                    <a href="{{ $ticket->type === 'Preventive Maintenance' ? route('maintenance.show', $ticket->id) : route('ict.show', $ticket->id) }}" class="cmms-btn-secondary" target="_blank">Job order record</a>
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
@endsection
