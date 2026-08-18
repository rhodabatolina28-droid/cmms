@extends('layouts.app')

@section('title', 'Parts & Consumables | NCMB CMMS')
@section('page-title', 'Parts & Consumables')

@section('styles')
<style nonce="{{ $cspNonce }}">
    .parts-container { width: 100%; margin-top: -10px; animation: fadeInSlide 0.4s ease-out; }
    .parts-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
    .parts-header { background: #f8fafc; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; align-items: center; }
    .parts-search { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .parts-search input, .parts-search select {
        border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 12px; font-size: 13px; background: #fff; color: #1e293b; outline: none;
    }
    .parts-search input:focus, .parts-search select:focus { border-color: #0038A8; box-shadow: 0 0 0 3px rgba(0,56,168,.12); }
    .btn-navy { background: #0038A8; color: #fff; border: none; padding: 9px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; }
    .btn-navy:hover { background: #002d8a; }
    .btn-ghost { background: #fff; color: #334155; border: 1px solid #cbd5e1; padding: 9px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
    .btn-ghost:hover { border-color: #0038A8; color: #0038A8; }

    .banner { display: flex; align-items: center; gap: 10px; padding: 12px 20px; border-radius: 10px; margin-bottom: 14px; font-size: 13px; font-weight: 600; }
    .banner-warn { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
    .banner-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        .parts-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .parts-table th { text-align: left; padding: 12px 16px; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #64748b; background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
    .parts-table td { padding: 13px 16px; font-size: 13.5px; border-bottom: 1px solid #f1f5f9; color: #1e293b; vertical-align: middle; }
    .parts-table tr:hover td { background: #f8fafc; }
    .row-low td { background: #fffbeb; }
    .row-critical td { background: #fef2f2; }

    .qty { font-weight: 800; }
    .qty-ok { color: #15803d; }
    .qty-low { color: #b45309; }
    .qty-critical { color: #b91c1c; }

    .badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 800; letter-spacing: .3px; }
    .badge-ok { background: #dcfce7; color: #166534; }
    .badge-low { background: #fef3c7; color: #92400e; }
    .badge-critical { background: #fee2e2; color: #991b1b; }
    .row-actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .mini-btn { padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; border: 1px solid #cbd5e1; background: #fff; color: #334155; cursor: pointer; }
    .mini-btn:hover { border-color: #0038A8; color: #0038A8; }
    .mini-btn.in { border-color: #bbf7d0; color: #15803d; }
    .mini-btn.out { border-color: #fecaca; color: #b91c1c; }
    .mini-btn:disabled { opacity: .45; cursor: not-allowed; }

    .modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.55); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 16px; }
    .modal-overlay.open { display: flex; }
    .modal-box { background: #fff; border-radius: 12px; width: 100%; max-width: 480px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 40px rgba(0,0,0,.2); }
    .modal-head { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; }
    .modal-head h3 { margin: 0; font-size: 16px; font-weight: 800; color: #0f172a; }
    .modal-close { background: none; border: none; font-size: 22px; line-height: 1; cursor: pointer; color: #64748b; }
    .modal-body { padding: 20px; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .form-field { display: flex; flex-direction: column; gap: 5px; }
    .form-field.full { grid-column: 1 / -1; }
    .form-field label { font-size: 12px; font-weight: 700; color: #475569; }
    .form-field input, .form-field select {
        border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 11px; font-size: 13px; color: #1e293b; outline: none; background: #fff;
    }
    .form-field input:focus, .form-field select:focus { border-color: #0038A8; box-shadow: 0 0 0 3px rgba(0,56,168,.12); }
    .form-err { color: #dc2626; font-size: 11.5px; font-weight: 600; min-height: 14px; }
    .current-hand { background: #f0f4ff; color: #0038A8; font-weight: 800; padding: 8px 12px; border-radius: 8px; font-size: 13px; margin-bottom: 12px; }
    .modal-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 14px 20px; border-top: 1px solid #e2e8f0; }

    .movement-item { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; align-items: flex-start; }
    .movement-item:last-child { border-bottom: none; }
    .movement-qty { min-width: 54px; font-weight: 800; }
    .mov-qty-in { color: #15803d; } .mov-qty-out { color: #b91c1c; }
    .movement-body { flex: 1; }
    .movement-reason { font-weight: 600; color: #1e293b; font-size: 13px; }
    .movement-meta { font-size: 11.5px; color: #64748b; margin-top: 2px; }

    .empty-state { text-align: center; padding: 60px 20px; color: #64748b; }
    .empty-state .big { font-size: 40px; margin-bottom: 8px; }
    .pagination-row { display: flex; justify-content: flex-end; padding: 14px 20px; }
    .toast { position: fixed; top: 18px; right: 18px; z-index: 2000; padding: 13px 18px; border-radius: 8px; color: #fff; font-size: 13px; font-weight: 700; box-shadow: 0 8px 20px rgba(0,0,0,.2); display: none; max-width: 340px; }
    .toast.ok { background: #15803d; } .toast.err { background: #b91c1c; }
    @media (max-width: 768px) {
        .form-grid { grid-template-columns: 1fr; }
        .parts-header { flex-direction: column; align-items: stretch; }
        .parts-search { flex-direction: column; align-items: stretch; }
        .parts-search input, .parts-search select { width: 100%; }
        .parts-table th, .parts-table td { padding: 10px 12px; }
    }

</style>
<style nonce="{{ $cspNonce }}">
    /* ═══ Parts & Consumables — Visual/UX refresh ═══ */
    .parts-container { width: 100%; }

    /* Page hero */
    .parts-hero { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin: 0 0 18px; }
    .parts-hero-title { display: flex; align-items: center; gap: 14px; }
    .parts-hero-icon { width: 52px; height: 52px; border-radius: 14px; background: linear-gradient(135deg, #0038A8, #0b5cd6); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 22px; box-shadow: 0 8px 18px rgba(0,56,168,.25); }
    .parts-hero h2 { margin: 0; font-size: 22px; font-weight: 900; color: #0f172a; letter-spacing: -.02em; }
    .parts-hero p { margin: 2px 0 0; font-size: 12.5px; color: #64748b; font-weight: 500; }

    .banner { border-radius: 10px; padding: 11px 16px; font-size: 12.5px; }

    /* Stats strip — plain white cards (gaya ng inventory), walang icons/colors */
    .parts-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 18px; }
    .stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; box-shadow: 0 2px 8px rgba(15,23,42,.04); transition: transform .15s ease, box-shadow .15s ease; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 22px rgba(15,23,42,.08); }
    .stat-val { font-size: 22px; font-weight: 800; line-height: 1.2; color: #1e293b; }
    .stat-label { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: .6px; margin-bottom: 4px; }

    /* Card + header */
    .parts-card { border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 6px 18px rgba(15,23,42,.05); }
    .parts-header { background: linear-gradient(180deg, #ffffff, #f8fafc); padding: 16px 20px; border: 0; border-bottom: 1px solid #eef2f6; }
    .parts-toolbar { display: flex; gap: 10px; flex-wrap: wrap; }
    .search-box { position: relative; flex: 1; min-width: 180px; }
    .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; }
    .search-box input { width: 100%; padding: 10px 12px 10px 34px; }
    .parts-search input, .parts-search select { border: 1px solid #cbd5e1; border-radius: 10px; padding: 10px 12px; font-size: 13px; background: #fff; color: #1e293b; outline: none; transition: border-color .15s, box-shadow .15s; }
    .parts-search input:focus, .parts-search select:focus { border-color: #0038A8; box-shadow: 0 0 0 3px rgba(0,56,168,.12); }

    /* Table */
        .parts-table th { padding: 13px 16px; font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #64748b; background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
    .parts-table td { padding: 13px 16px; font-size: 13.5px; }
    .item-name { font-weight: 700; color: #0f172a; font-size: 13.5px; }
    .item-sub { font-size: 11.5px; color: #94a3b8; margin-top: 2px; }
    .qty-pill { display: inline-flex; align-items: center; gap: 9px; }
    .qty-num { font-size: 16px; font-weight: 900; }
    .qty-ok { color: #15803d; } .qty-low { color: #b45309; } .qty-critical { color: #b91c1c; }
    .qty-track { width: 62px; height: 6px; background: #eef2f6; border-radius: 99px; overflow: hidden; }
    .qty-track > i { display: block; height: 100%; border-radius: 99px; }
    .t-ok { background: #22c55e; } .t-low { background: #f59e0b; } .t-critical { background: #ef4444; }
    .badge { font-size: 10.5px; padding: 4px 11px; letter-spacing: .4px; }

    /* Icon actions */
    .act-btn { width: 34px; height: 34px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0; background: #fff; color: #475569; cursor: pointer; font-size: 13px; transition: all .15s; }
    .act-btn:hover { border-color: #0038A8; color: #0038A8; background: #eff6ff; }
    .act-btn.in { border-color: #bbf7d0; color: #15803d; }
    .act-btn.in:hover { background: #f0fdf4; }
    .act-btn.out { border-color: #fecaca; color: #b91c1c; }
    .act-btn.out:hover { background: #fef2f2; }
    .act-btn:disabled { opacity: .4; cursor: not-allowed; }

    .btn-navy { border-radius: 10px; padding: 10px 18px; display: inline-flex; align-items: center; gap: 7px; }
    .btn-ghost { border-radius: 10px; padding: 10px 16px; }

    .modal-box { border-radius: 16px; overflow: hidden; }
    .modal-head { background: #f8fafc; padding: 16px 20px; }
    .modal-head h3 { font-size: 15px; }

    @media (max-width: 900px) { .parts-stats { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 480px) { .parts-stats { grid-template-columns: 1fr; } }
</style>
<style nonce="{{ $cspNonce }}">
    /* ═══ Card-only layout (mimic Inventory & Assets) ═══ */
    .parts-card-head { padding: 16px 20px; border-bottom: 1px solid #eef2f6; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; background: #f8fafc; }
    .parts-card-head h3 { margin: 0; font-size: 16px; font-weight: 800; color: #0f172a; }
    .parts-card-head h3 i { color: #0038A8; margin-right: 8px; }
    .parts-card-head p { margin: 3px 0 0; font-size: 12px; color: #64748b; font-weight: 500; }
    .parts-body { padding: 16px 20px; }

    /* Filter bar — gaya ng inventory filter-ribbon */
    .parts-header { margin-bottom: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; }

    /* Pagination — custom view (gaya ng inventory: pages 1 2 3, navy active) */
    .parts-pagination { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; padding: 12px 20px; border-top: 1px solid #eef2f6; }
    .parts-pag-info { font-size: 12px; color: #64748b; font-weight: 600; }
    .parts-pag-btns { display: flex; gap: 4px; flex-wrap: wrap; align-items: center; }
    .parts-pag-btns a, .parts-pag-btns button, .parts-pag-btns .active {
        padding: 5px 10px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff; color: #1e293b; font-size: 12px; font-weight: 700; text-decoration: none; cursor: pointer;
    }
    .parts-pag-btns a:hover { border-color: #0038A8; color: #0038A8; }
    .parts-pag-btns .active { background: #0038A8; color: #fff; border-color: #0038A8; cursor: default; }
    .parts-pag-btns button[disabled], .parts-pag-btns button:disabled { background: #f1f5f9; color: #94a3b8; cursor: default; }
    .parts-pag-btns .p-ellipsis { border: none; background: transparent; color: #94a3b8; cursor: default; padding: 5px 2px; }
    /* ===== Column alignment: justified layout + centered numerics ===== */
    .parts-table th, .parts-table td { vertical-align: middle; }
    .parts-table th:nth-child(1), .parts-table td:nth-child(1) { text-align: left; }
    .parts-table th:nth-child(2), .parts-table th:nth-child(3), .parts-table th:nth-child(4), .parts-table th:nth-child(5), .parts-table th:nth-child(6), .parts-table th:nth-child(7), .parts-table th:nth-child(8), .parts-table td:nth-child(2), .parts-table td:nth-child(3), .parts-table td:nth-child(4), .parts-table td:nth-child(5), .parts-table td:nth-child(6), .parts-table td:nth-child(7), .parts-table td:nth-child(8) { text-align: center; }
    /* Badges — colored, no emoji */
    .badge { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; font-weight: 700; letter-spacing: .3px; }
    .badge-critical { background: #fde8e8; color: #b91c1c; padding: 3px 11px; }
    .badge-low { background: #fff3e6; color: #b45309; padding: 3px 11px; }
    .badge-ok { background: #dcfce7; color: #15803d; padding: 3px 11px; }
    /* Row actions — centered */
    .row-actions { display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    /* Misc containers */
        .parts-container { max-width: 100%; margin: 0 auto; padding: 22px 16px; width: 100%; }
    .empty-state { text-align: center; padding: 42px 16px; color: #64748b; }
    .empty-state .big { font-size: 44px; margin-bottom: 10px; }
    .stat-val { font-variant-numeric: tabular-nums; }
</style>
<style nonce="{{ $cspNonce }}">
    /* ── Additions only — existing styles untouched ── */

    /* Fix: @keyframes was referenced but never defined */
    @keyframes fadeInSlide {
        0%   { opacity: 0; transform: translateY(10px); }
        100% { opacity: 1; transform: translateY(0); }
    }

    /* Fix search icon vertical alignment */
    .search-box {
        position: relative;
        display: flex;
        align-items: center;
    }
    .search-box i {
        position: absolute;
        left: 11px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 13px;
        pointer-events: none;
        line-height: 1;
    }
    .search-box input {
        width: 100%;
        padding: 9px 12px 9px 32px !important;
        line-height: 1.4;
    }

    /* Smooth transitions on filter inputs */
    .parts-search input, .parts-search select {
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    /* Table row hover — subtle left accent */
    .parts-table td:first-child { border-left: 3px solid transparent; }
    .parts-table tr:hover td:first-child { border-left-color: #0038A8; }

    /* ── Units Modal table — fix alignment + polish ── */

    /* Override: inside the units modal, all columns left-aligned */
    #unitsModal .parts-table th,
    #unitsModal .parts-table td { text-align: left !important; }

    /* Wider modal for the units table */
    #unitsModal .modal-box { max-width: 620px; }

    /* Units search bar */
    #unitsSearch {
        flex: 1;
        min-width: 160px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 9px 12px;
        font-size: 13px;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    #unitsSearch:focus { border-color: #0038A8; box-shadow: 0 0 0 3px rgba(0,56,168,.12); }

    /* Add unit inline form */
    #addUnitForm {
        border: 1px solid #e2e8f0 !important;
        border-radius: 10px !important;
        background: #f8fafc !important;
        padding: 14px !important;
        margin-bottom: 14px !important;
    }
    #addUnitForm input {
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 9px 11px;
        font-size: 13px;
        outline: none;
        transition: border-color 0.2s;
        width: 100%;
        box-sizing: border-box;
    }
    #addUnitForm input:focus { border-color: #0038A8; box-shadow: 0 0 0 3px rgba(0,56,168,.12); }

    /* Units body scroll area */
    #unitsBody { max-height: 46vh; overflow-y: auto; }

    /* Units table total row */
    #unitsModal .parts-table tr.units-total-row td {
        border-top: 2px solid #e2e8f0;
        font-weight: 800;
        color: #0f172a;
        background: #f8fafc;
        font-size: 13px;
    }
</style>
@endsection

@section('content')
<div class="parts-container">
    <div class="parts-card">
        <div class="parts-card-head">
            <div>
                <h3><i class="fa-solid fa-boxes-stacked"></i> Parts &amp; Consumables</h3>
                <p id="partsHeadSub">Supplies ledger — {{ $totalParts }} item(s) · {{ $totalOnHand }} total on-hand{!! !empty($isSuperAdminView) ? ' · Read-only' : '' !!}</p>
            </div>
            <div class="parts-toolbar">
                <a class="btn-ghost" href="#" onclick="event.preventDefault(); exportPartsCsv();"><i class="fa-solid fa-download"></i> Export CSV</a>
                @if($canWriteInventory)
                    <button type="button" class="btn-ghost" onclick="document.getElementById('partsCsvInput').click()"><i class="fa-solid fa-file-import"></i> Import CSV</button>
                    <input type="file" id="partsCsvInput" accept=".csv" style="display:none;">
                    <button class="btn-navy" onclick="openPartModal('add')">＋ Add Part</button>
                @endif
            </div>
        </div>
        <div class="parts-body">
            <div class="parts-stats">
                <div class="stat-card"><div class="stat-label">Total Parts</div><div class="stat-val" id="statTotalParts">{{ $totalParts }}</div></div>
                <div class="stat-card"><div class="stat-label">On-hand total</div><div class="stat-val" id="statTotalOnHand">{{ $totalOnHand }}</div></div>
                <div class="stat-card"><div class="stat-label">Low stock</div><div class="stat-val" id="statLowStock">{{ $lowStockCount }}</div></div>
                <div class="stat-card"><div class="stat-label">Critical</div><div class="stat-val" id="statCritical">{{ $criticalCount }}</div></div>
            </div>

            <div class="parts-header">
            <form method="GET" action="{{ route($isSuperAdminView ? 'super_admin.parts' : 'inventory.parts') }}" class="parts-search">
                <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" name="search" id="partsSearchInput" value="{{ $filters['search'] }}" placeholder="Search item / category..."></div>
                <select name="category" id="partsCatFilter">
                    <option value="">All categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" @selected($filters['category'] === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
                <select name="status" id="partsStatusFilter">
                    <option value="">All status</option>
                    <option value="ok" @selected($filters['status'] === 'ok')>OK</option>
                    <option value="low" @selected($filters['status'] === 'low')>Low</option>
                    <option value="critical" @selected($filters['status'] === 'critical')>Critical</option>
                </select>
            </form>
        </div>

        <div style="overflow-x:auto;">
                <table class="parts-table">
                                    <colgroup>
                    <col style="width:24%">
                    <col style="width:7%">
                    <col style="width:11%">
                    <col style="width:7%">
                    <col style="width:14%">
                    <col style="width:14%">
                    <col style="width:7%">
                    <col style="width:16%">
                </colgroup>
                <thead>
                        <tr>
                            <th>Item</th>
                            <th>Unit</th>
                            <th>On-hand</th>
                            <th>Reorder</th>
                            <th>Unit Value</th>
                            <th>Total Cost</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="partsTableBody">
                        <tr><td colspan="6" style="text-align:center;padding:30px 16px;color:#64748b;"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading parts...</td></tr>
                    </tbody>
                </table>
            </div>

            <div id="partsPagination" class="parts-pagination"></div>
        </div>
    </div>
</div>

{{-- ============ MODALS ============ --}}

<div class="modal-overlay" id="partModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="partModalTitle">Add Part</h3>
            <button class="modal-close" onclick="closeModal('partModal')">&times;</button>
        </div>
        <form id="partForm">
            <input type="hidden" name="_method" value="post" id="partMethod">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-field full">
                        <label>Item name *</label>
                        <input type="text" name="item_name" id="p_item_name" maxlength="190">
                        <div class="form-err" id="err_item_name"></div>
                    </div>
                                        <div class="form-field">
                        <label>Unit *</label>
                        <select name="unit" id="p_unit">
                            <option value="">Select unit</option>
                            <option value="pcs">pcs</option>
                            <option value="pc">pc</option>
                            <option value="pack">pack</option>
                            <option value="box">box</option>
                            <option value="tube">tube</option>
                            <option value="set">set</option>
                            <option value="bottle">bottle</option>
                            <option value="roll">roll</option>
                        </select>
                        <div class="form-err" id="err_unit"></div>
                    </div>
                                        <div class="form-field">
                        <label>Category</label>
                        <select name="category" id="p_category">
                            <option value="">Select category</option>
                            <option value="Memory">Memory</option>
                            <option value="Storage">Storage</option>
                            <option value="Print Consumable">Print Consumable</option>
                            <option value="Power">Power</option>
                            <option value="Peripheral">Peripheral</option>
                            <option value="Hardware">Hardware</option>
                            <option value="Network">Network</option>
                            <option value="Other">Other</option>
                        </select>
                        <div class="form-err" id="err_category"></div>
                    </div>
                    <div class="form-field" id="field_on_hand">
                        <label>On-hand (initial)</label>
                        <input type="number" name="on_hand_qty" id="p_on_hand" min="0" value="0">
                        <div class="form-err"></div>
                    </div>
                    <div class="form-field full" style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="requires_unit_tracking" id="p_requires_unit_tracking" value="1" style="width:auto;">
                        <label for="p_requires_unit_tracking" style="margin:0;">Track every unit (serial number, property number, and unit cost required)</label>
                    </div>
                                        <div class="form-field">
                        <label>Reorder level</label>
                        <input type="number" name="reorder_level" id="p_reorder" min="0" value="0">
                        <div class="form-err"></div>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-ghost" onclick="closeModal('partModal')">Cancel</button>
                <button type="submit" class="btn-navy"> Save</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="stockModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="stockModalTitle">Stock In</h3>
            <button class="modal-close" onclick="closeModal('stockModal')">&times;</button>
        </div>
        <form id="stockForm">
            <div class="modal-body">
                <div class="current-hand" id="stockCurrent">Current on-hand: —</div>
                <div class="form-grid">
                    <div class="form-field">
                        <label id="stockQtyLabel">Qty to add *</label>
                        <input type="number" name="qty" id="s_qty" min="1">
                        <div class="form-err" id="err_qty"></div>
                    </div>
                    <div class="form-field full" id="stockSerialWrap">
                        <label>Serial / Property numbers <span style="font-weight:400;color:#94a3b8;">(isa bawat linya: serial, property)</span></label>
                        <textarea name="units" id="s_units" rows="3" style="width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:9px 12px; font-size:13px; font-family:monospace;"></textarea>
                        <div class="form-err" id="err_units"></div>
                    </div>
                    <div class="form-field full" id="stockUnitsPickerWrap" style="display:none;">
                        <label>Pumili ng serial na ii-issue</label>
                        <div id="s_units_picker" style="border:1px solid #d1d5db;border-radius:8px;padding:8px 10px;max-height:150px;overflow:auto;background:#fff;">
                            <div class="empty-state" style="padding:12px;">No serials available.</div>
                        </div>
                    </div>
                    <div class="form-field full" id="stockOutTargetWrap" style="display:none;">
                        <label>Attach to asset (optional)</label>
                        <select id="s_asset_id" style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;font-size:13px;"><option value="">-- No asset (manual) --</option></select>
                    </div>
                    <div class="form-field full" id="stockOutTicketWrap" style="display:none;">
                        <label>Attach to ticket (optional)</label>
                        <select id="s_request_id" style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;font-size:13px;"><option value="">-- No ticket (manual) --</option></select>
                    </div>
                    <div class="form-field" id="stockIssuedToWrap" style="display:none;">
                        <label>Issued To (custodian)</label>
                        <select id="s_issued_to" style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;font-size:13px;"><option value="">-- Manual / none --</option></select>
                    </div>
                    <div class="form-field">
                        <label>Source / For</label>
                        <input type="text" name="reference_type" id="s_ref" maxlength="32" placeholder="Purchase / Requisition / adjustment">
                        <div class="form-err"></div>
                    </div>
                    <div class="form-field full">
                        <label>Reason / Remarks *</label>
                        <input type="text" name="reason" id="s_reason" maxlength="190">
                        <div class="form-err" id="err_reason"></div>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-ghost" onclick="closeModal('stockModal')">Cancel</button>
                <button type="submit" class="btn-navy" id="stockSubmit">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="historyModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="historyTitle">History</h3>
            <button class="modal-close" onclick="closeModal('historyModal')">&times;</button>
        </div>
        <div class="modal-body" id="historyBody" style="max-height:50vh;overflow-y:auto;">
            <div class="empty-state"><div class="big"></div>Loading...</div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="unitsModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="unitsModalTitle">Units</h3>
            <button class="modal-close" onclick="closeModal('unitsModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display:flex; gap:8px; align-items:center; margin-bottom:12px; flex-wrap:wrap;">
                <input type="text" id="unitsSearch" placeholder="Search serial / property..." style="flex:1; min-width:160px; border:1px solid #cbd5e1; border-radius:8px; padding:9px 12px; font-size:13px;">
                <button type="button" class="btn-ghost" onclick="openAddUnitModal()">＋ Add Unit</button>
            </div>
            <div id="addUnitForm" style="display:none; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:12px; background:#f8fafc;">
                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:8px;">
                    <input type="text" id="au_serial" placeholder="Serial number" style="border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;font-size:13px;">
                    <input type="text" id="au_property" placeholder="Property number" style="border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;font-size:13px;">
                    <input type="number" id="au_value" placeholder="Unit value (₱)" step="0.01" style="border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;font-size:13px;">
                </div>
                <div style="margin-top:8px; display:flex; gap:8px; justify-content:flex-end;">
                    <button type="button" class="btn-ghost" onclick="cancelAddUnit()">Cancel</button>
                    <button type="button" class="btn-navy" onclick="saveUnit()">Save Unit</button>
                </div>
            </div>
            <div id="unitsBody" style="max-height:46vh; overflow-y:auto;">
                <div class="empty-state"><div class="big">🔢</div><div>No units registered yet.</div></div>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn-ghost" onclick="closeModal('unitsModal')">Close</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
    const PARTS_CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const PARTS_CAN_WRITE = @json($canWriteInventory);
    const PARTS_STORE_URL = '{{ route('inventory.parts.store') }}';
    const PARTS_UPDATE_PREFIX = '{{ route('inventory.parts.update', ['part' => 'PART_ID']) }}';
    const PARTS_STOCK_IN_PREFIX = '{{ route('inventory.parts.stock-in', ['part' => 'PART_ID']) }}';
    const PARTS_STOCK_OUT_PREFIX = '{{ route('inventory.parts.stock-out', ['part' => 'PART_ID']) }}';
    const PARTS_MOVEMENTS_PREFIX =
        @if(!empty($isSuperAdminView))
        '{{ route('super_admin.parts.movements', ['part' => 'PART_ID']) }}';
        @else
        '{{ route('inventory.parts.movements', ['part' => 'PART_ID']) }}';
        @endif
    const PARTS_IS_READONLY = @json(!empty($isSuperAdminView));
    const PARTS_DATA_URL =
        @if(!empty($isSuperAdminView))
        '{{ route('super_admin.parts.data') }}';
        @else
        '{{ route('inventory.parts.data') }}';
        @endif
    const PARTS_EXPORT_URL =
        @if(!empty($isSuperAdminView))
        '{{ route('super_admin.parts.export') }}';
        @else
        '{{ route('inventory.parts.export') }}';
        @endif
    const PARTS_UNITS_PREFIX =
        @if(!empty($isSuperAdminView))
        '{{ route('super_admin.parts.units', ['part' => 'PART_ID']) }}';
        @else
        '{{ route('inventory.parts.units', ['part' => 'PART_ID']) }}';
        @endif
    const PARTS_UNITS_STORE_PREFIX = '{{ route('inventory.parts.units.store', ['part' => 'PART_ID']) }}';
    const PARTS_IMPORT_PREVIEW_URL = '{{ route('inventory.parts.import.preview') }}';
    const PARTS_IMPORT_COMMIT_URL = '{{ route('inventory.parts.import.commit') }}';
    const PARTS_STOCK_OUT_CONTEXT_URL = '{{ route('inventory.parts.stock-out-context') }}';

    // ── Live filters (Ajax) — gaya ng inventory, inu-update ang stats cards + table pag nag-filter ──
    (function () {
        const form = document.querySelector('.parts-search');
        if (!form) return;
        let t;
        const trigger = () => { clearTimeout(t); t = setTimeout(() => loadParts(1), 120); };
        form.addEventListener('submit', (e) => { e.preventDefault(); loadParts(1); });
        form.querySelectorAll('select').forEach((sel) => {
            sel.addEventListener('change', trigger);
        });
        const searchInput = form.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => loadParts(1), 400); });
            searchInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
        }
    })();

    // ── Data / rendering ──
    let partsPage = 1;
    let partsLastPage = 1;
    let allParts = [];
    let partsById = {};
    let partsReqSeq = 0;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function fmtMoney(v) {
        if (v === null || v === undefined || v === '') return '—';
        return '₱' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function loadParts(page) {
        const params = new URLSearchParams();
        const searchInput = document.getElementById('partsSearchInput');
        const catFilter = document.getElementById('partsCatFilter');
        const statusFilter = document.getElementById('partsStatusFilter');
        if (searchInput && searchInput.value.trim()) params.set('search', searchInput.value.trim());
        if (catFilter && catFilter.value) params.set('category', catFilter.value);
        if (statusFilter && statusFilter.value) params.set('status', statusFilter.value);
        if (page) params.set('page', page);
        params.set('per_page', 15);

        const mySeq = ++partsReqSeq;

        // Gaya ng inventory: direktang i-render pagkatapos ng fetch — walang loading/dim UI sa pagpapalit ng filter.
        fetch(PARTS_DATA_URL + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then((r) => r.json())
        .then((data) => {
            if (mySeq !== partsReqSeq) return; // huwag i-render ang stale response
            if (!data || !data.success) {
                const tbody = document.getElementById('partsTableBody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state">Unable to load parts.</div></td></tr>';
                return;
            }
            allParts = data.parts || [];
            partsById = {};
            allParts.forEach((p) => { partsById[p.id] = p; });
            partsPage = page || 1;
            partsLastPage = data.last_page || 1;
            renderPartsTable(allParts);
            renderPartsPagination(data.total, data.last_page);
            updatePartsSummary(data.stats);
        })
        .catch(() => {
            if (mySeq !== partsReqSeq) return;
            const tbody = document.getElementById('partsTableBody');
            if (tbody) tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state">Unable to load parts.</div></td></tr>';
        });
    }

    function toast(msg, ok) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast ' + (ok ? 'ok' : 'err');
        t.style.display = 'block';
        clearTimeout(t._h);
        t._h = setTimeout(() => { t.style.display = 'none'; }, 3200);
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    function api(url, method, body) {
        return fetch(url, {
            method: method,
            headers: {
                'X-CSRF-TOKEN': PARTS_CSRF,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: body ? JSON.stringify(body) : undefined
        }).then(async r => {
            const data = await r.json().catch(() => ({}));
            return { ok: r.ok, status: r.status, data };
        });
    }

    function exportPartsCsv() {
        const params = new URLSearchParams();
        const searchInput = document.getElementById('partsSearchInput');
        const catFilter = document.getElementById('partsCatFilter');
        const statusFilter = document.getElementById('partsStatusFilter');
        if (searchInput && searchInput.value.trim()) params.set('search', searchInput.value.trim());
        if (catFilter && catFilter.value) params.set('category', catFilter.value);
        if (statusFilter && statusFilter.value) params.set('status', statusFilter.value);
        window.location.href = PARTS_EXPORT_URL + '?' + params.toString();
    }

    function importPartsCsv() {
        const input = document.getElementById('partsCsvInput');
        if (!input || !input.files || !input.files[0]) return;
        const fd = new FormData();
        fd.append('file', input.files[0]);
        input.value = '';

        fetch(PARTS_IMPORT_PREVIEW_URL, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': PARTS_CSRF, 'Accept': 'application/json' },
            body: fd
        })
        .then(async (r) => { const d = await r.json().catch(() => ({})); return { ok: r.ok, data: d }; })
        .then(({ ok, data }) => {
            if (!ok || !data.success) { toast(data.message || 'Import preview failed.', false); return; }
            const s = data.summary || {};
            const okToProceed = window.confirm(
                'Parts import preview:\n\n' +
                '· ' + s.rows + ' row(s)\n' +
                '· ' + s.distinct_parts + ' part(s)\n' +
                '· ' + s.duplicate_serials + ' duplicate serial(s)\n\n' +
                'I-import na?'
            );
            if (!okToProceed) return;

            api(PARTS_IMPORT_COMMIT_URL, 'POST', { token: data.token }).then(({ ok: ok2, data: d2 }) => {
                if (ok2) { toast(d2.message || 'Imported', true); setTimeout(() => location.reload(), 600); }
                else toast(d2.message || 'Import failed.', false);
            });
        })
        .catch(() => toast('Import preview failed.', false));
    }

    function renderPartsTable(rows) {
        const tbody = document.getElementById('partsTableBody');
        if (!tbody) return;
        const canWrite = PARTS_CAN_WRITE;
        if (!rows || rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state"><div class="big"></div><div>No parts &amp; consumables yet.</div>' +
                (canWrite ? '<div style="margin-top:14px;"><button class="btn-navy" onclick="openPartModal(\'add\')">＋ Add Part</button></div>' : '') +
                '</div></td></tr>';
            return;
        }
        tbody.innerHTML = rows.map((p) => {
            const level = p.level;
            const rowCls = level === 'critical' ? 'row-critical' : (level === 'low' ? 'row-low' : '');
            const qtyCls = level === 'critical' ? 'qty-critical' : (level === 'low' ? 'qty-low' : 'qty-ok');
            const trackCls = level === 'critical' ? 't-critical' : (level === 'low' ? 't-low' : 't-ok');
            const trackW = (p.reorder_level > 0)
                ? Math.min(100, Math.round((p.on_hand_qty / p.reorder_level) * 100))
                : (p.on_hand_qty > 0 ? 100 : 0);
            const badge = level === 'critical'
                ? '<span class="badge badge-critical">CRITICAL</span>'
                : (level === 'low' ? '<span class="badge badge-low">LOW</span>' : '<span class="badge badge-ok">OK</span>');

            let actions = '<div class="row-actions">';
            if (canWrite) {
                actions += '<button class="act-btn" title="Edit part" onclick="openPartModal(\'edit\', partsById[' + p.id + '])"><i class="fa-solid fa-pen"></i></button>';
                actions += '<button class="act-btn in" title="Stock In" onclick="openStockModal(\'in\', partsById[' + p.id + '])"><i class="fa-solid fa-arrow-down"></i></button>';
                actions += '<button class="act-btn out' + (p.on_hand_qty <= 0 ? ' disabled' : '') + '" title="Stock Out"' + (p.on_hand_qty <= 0 ? ' disabled' : '') + ' onclick="openStockModal(\'out\', partsById[' + p.id + '])"><i class="fa-solid fa-arrow-up"></i></button>';
            }
            actions += '<button class="act-btn" title="View history" onclick="openHistory(' + p.id + ')"><i class="fa-solid fa-clock-rotate-left"></i></button>';
            actions += '<button class="act-btn" title="View units (serial / property)" onclick="openUnitsModal(' + p.id + ')"><i class="fa-solid fa-list-ul"></i></button>';
            actions += '</div>';

            return '<tr class="' + rowCls + '">'
                + '<td><div class="item-name">' + esc(p.item_name) + '</div><div class="item-sub">' + esc(p.category || 'Uncategorized') + '</div></td>'
                + '<td>' + esc(p.unit) + '</td>'
                + '<td><div class="qty-pill"><span class="qty-num ' + qtyCls + '">' + p.on_hand_qty + '</span><div class="qty-track"><i class="' + trackCls + '" style="width:' + trackW + '%"></i></div></div></td>'
                + '<td>' + p.reorder_level + '</td>'
                + '<td>' + fmtMoney(p.unit_value) + '</td>'
                + '<td>' + fmtMoney(p.total_cost) + '</td>'
                + '<td>' + badge + '</td>'
                + '<td>' + actions + '</td>'
                + '</tr>';
        }).join('');
    }

    function renderPartsPagination(total, lastPage) {
        const el = document.getElementById('partsPagination');
        if (!el) return;
        if (total === 0) { el.innerHTML = ''; return; }
        const first = (partsPage - 1) * 15 + 1;
        const last = Math.min(partsPage * 15, total);
        let html = '<span class="parts-pag-info">Showing ' + first + '–' + last + ' of ' + total + '</span><div class="parts-pag-btns">';
        html += '<button ' + (partsPage <= 1 ? 'disabled' : '') + ' onclick="loadParts(' + (partsPage - 1) + ')"><i class="fa-solid fa-chevron-left"></i> Prev</button>';
        for (let i = 1; i <= lastPage; i++) {
            html += (i === partsPage)
                ? '<span class="active">' + i + '</span>'
                : '<button onclick="loadParts(' + i + ')">' + i + '</button>';
        }
        html += '<button ' + (partsPage >= lastPage ? 'disabled' : '') + ' onclick="loadParts(' + (partsPage + 1) + ')">Next <i class="fa-solid fa-chevron-right"></i></button>';
        html += '</div>';
        el.innerHTML = html;
    }

    function updatePartsSummary(stats) {
        if (!stats) return;
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('statTotalParts', stats.totalParts);
        set('statTotalOnHand', stats.totalOnHand);
        set('statLowStock', stats.lowStockCount);
        set('statCritical', stats.criticalCount);
        const head = document.getElementById('partsHeadSub');
        if (head) {
            head.textContent = 'Supplies ledger — ' + stats.totalParts + ' item(s) · ' + stats.totalOnHand + ' total on-hand' + (PARTS_IS_READONLY ? ' · Read-only' : '');
        }
    }

    // Initial load (gaya ng inventory's loadInventory sa page load).
    loadParts(1);

    document.getElementById('partsCsvInput')?.addEventListener('change', importPartsCsv);

    let partMode = 'add', editingId = null;
    let stockMode = 'in', stockPart = null;

    function openPartModal(mode, part) {
        partMode = mode;
        editingId = part ? part.id : null;
        document.getElementById('partModalTitle').textContent = mode === 'add' ? 'Add Part' : 'Edit Part';
        document.getElementById('partMethod').value = mode === 'add' ? 'post' : 'put';
        document.getElementById('p_item_name').value = part ? part.item_name : '';
        document.getElementById('p_unit').value = part ? part.unit : '';
        document.getElementById('p_category').value = (part && part.category) ? part.category : '';
        document.getElementById('p_on_hand').value = part ? part.on_hand_qty : 0;
        document.getElementById('p_requires_unit_tracking').checked = !!(part && part.requires_unit_tracking);
                document.getElementById('p_reorder').value = part ? part.reorder_level : 0;
        document.getElementById('field_on_hand').style.display = mode === 'add' ? 'flex' : 'none';
        document.querySelectorAll('.form-err').forEach(e => e.textContent = '');
        document.getElementById('partModal').classList.add('open');
        document.getElementById('p_item_name').focus();
    }

    function openStockModal(mode, part) {
        stockMode = mode;
        stockPart = part;
        document.getElementById('stockModalTitle').textContent = mode === 'in' ? 'Stock In' : 'Stock Out (Issue)';
        document.getElementById('stockQtyLabel').textContent = mode === 'in' ? 'Qty to add *' : 'Qty to issue *';
        document.getElementById('stockSubmit').textContent = mode === 'in' ? '✓ Stock In' : '✓ Issue';
        document.getElementById('stockCurrent').textContent = 'Current on-hand: ' + part.on_hand_qty + ' ' + part.unit;
        document.getElementById('s_qty').value = '';
        document.getElementById('s_ref').value = '';
        document.getElementById('s_reason').value = '';
        // Serial UI — ipapakita ayon sa mode (Phase 1: visual; backend sa Phase 3).
        document.getElementById('s_units').value = '';
        document.getElementById('s_issued_to').value = '';
        document.getElementById('stockSerialWrap').style.display = mode === 'in' ? 'block' : 'none';
        document.getElementById('stockUnitsPickerWrap').style.display = mode === 'out' ? 'block' : 'none';
        document.getElementById('stockIssuedToWrap').style.display = mode === 'out' ? 'block' : 'none';
        document.getElementById('stockOutTargetWrap').style.display = mode === 'out' ? 'block' : 'none';
        document.getElementById('stockOutTicketWrap').style.display = mode === 'out' ? 'block' : 'none';
        document.getElementById('s_issued_to').innerHTML = '<option value="">-- Manual / none --</option>';
        document.getElementById('s_asset_id').value = '';
        document.getElementById('s_request_id').value = '';
        if (mode === 'out') {
            loadStockOutUnits(part.id);
            loadStockOutContext();
        }
        document.querySelectorAll('.form-err').forEach(e => e.textContent = '');
        document.getElementById('stockModal').classList.add('open');
        document.getElementById('s_qty').focus();
    }

    function loadStockOutUnits(id) {
        const picker = document.getElementById('s_units_picker');
        if (!picker) return;
        picker.innerHTML = '<div class="empty-state" style="padding:12px;">Loading serials...</div>';
        api(PARTS_UNITS_PREFIX.replace('PART_ID', id), 'GET').then(({ ok, data }) => {
            const inStock = ((data && data.units) || []).filter(u => u.status === 'in_stock');
            if (!ok || inStock.length === 0) {
                picker.innerHTML = '<div class="empty-state" style="padding:12px;">No serials available.</div>';
                return;
            }
            picker.innerHTML = inStock.map(u =>
                '<label style="display:block;padding:6px 4px;cursor:pointer;"><input type="checkbox" class="stock-unit-chk" value="' + u.id + '"> '
                + esc((u.serial_number || '—')) + (u.property_number ? ' · ' + esc(u.property_number) : '') + '</label>'
            ).join('');
            picker.addEventListener('change', function () {
                const sel = document.querySelectorAll('.stock-unit-chk:checked').length;
                if (sel > 0 && document.getElementById('s_qty')) document.getElementById('s_qty').value = sel;
            });
        });
    }

    // ── Units ──
    let currentUnitsPartId = null;

    // ── Stock Out auto-fill (Phase 5) ──
    let stockOutAssets = [];
    let stockOutTickets = [];

    function loadStockOutContext() {
        api(PARTS_STOCK_OUT_CONTEXT_URL, 'GET').then(({ ok, data }) => {
            if (!ok) return;
            stockOutAssets = (data && data.assets) || [];
            stockOutTickets = (data && data.tickets) || [];
            const custEl = document.getElementById('s_issued_to');
            if (custEl) {
                custEl.innerHTML = '<option value="">-- Manual / none --</option>';
                ((data && data.custodians) || []).forEach(cu => {
                    const o = document.createElement('option');
                    o.value = cu.id; o.textContent = cu.full_name; custEl.appendChild(o);
                });
            }
            const assetEl = document.getElementById('s_asset_id');
            if (assetEl) {
                assetEl.innerHTML = '<option value="">-- No asset (manual) --</option>';
                stockOutAssets.forEach(a => {
                    const o = document.createElement('option');
                    o.value = a.asset_id;
                    o.textContent = '#' + a.asset_id + ' · ' + a.item_name + (a.custodian_name ? ' · ' + a.custodian_name : '');
                    assetEl.appendChild(o);
                });
            }
            const ticketEl = document.getElementById('s_request_id');
            if (ticketEl) {
                ticketEl.innerHTML = '<option value="">-- No ticket (manual) --</option>';
                stockOutTickets.forEach(t => {
                    const o = document.createElement('option');
                    o.value = t.id;
                    o.textContent = '[' + (t.type === 'Preventive Maintenance' ? 'PM' : 'ICT') + '] ' + t.request_number + (t.asset_name ? ' · ' + t.asset_name : '');
                    ticketEl.appendChild(o);
                });
            }
        });
    }

    const soAssetEl = document.getElementById('s_asset_id');
    if (soAssetEl) soAssetEl.addEventListener('change', function () {
        const asset = stockOutAssets.find(a => String(a.asset_id) === String(this.value));
        const custEl = document.getElementById('s_issued_to');
        if (custEl) custEl.value = asset ? String(asset.custodian_id || '') : '';
    });
    const soTicketEl = document.getElementById('s_request_id');
    if (soTicketEl) soTicketEl.addEventListener('change', function () {
        const ticket = stockOutTickets.find(t => String(t.id) === String(this.value));
        const assetEl = document.getElementById('s_asset_id');
        const custEl = document.getElementById('s_issued_to');
        if (ticket) {
            if (assetEl) assetEl.value = String(ticket.asset_id || '');
            if (custEl) custEl.value = String(ticket.custodian_id || '');
        }
    });

    function openUnitsModal(id) {
        const part = partsById[id] || {};
        currentUnitsPartId = id;
        document.getElementById('unitsModalTitle').textContent = (part.item_name || 'Part') + ' · Units';
        document.getElementById('unitsSearch').value = '';
        document.getElementById('addUnitForm').style.display = 'none';
        const body = document.getElementById('unitsBody');
        body.innerHTML = '<div class="empty-state"><div class="big">⏳</div>Loading units...</div>';
        document.getElementById('unitsModal').classList.add('open');
        api(PARTS_UNITS_PREFIX.replace('PART_ID', id), 'GET').then(({ ok, data }) => {
            if (!ok || !data.units) { body.innerHTML = '<div class="empty-state">Unable to load units.</div>'; return; }
            renderUnits(data.units);
        });
    }

    function renderUnits(units) {
        const body = document.getElementById('unitsBody');
        if (units.length === 0) {
            body.innerHTML = '<div class="empty-state"><div class="big">🔢</div><div>No units registered yet.</div></div>';
            return;
        }
        const pill = (text, bg, fg) => '<span style="background:' + bg + ';color:' + fg + ';padding:2px 9px;border-radius:6px;font-weight:700;font-size:11px;">' + text + '</span>';
        const total = units.reduce((s, u) => s + (Number(u.unit_value) || 0), 0);
        body.innerHTML = '<div style="overflow-x:auto;"><table class="parts-table" style="table-layout:auto;width:100%;">'
            + '<thead><tr><th>Serial</th><th>Property No</th><th>Unit Value</th><th>Status</th><th>Custodian</th></tr></thead><tbody>'
            + units.map(u => {
                const st = u.status === 'issued'
                    ? pill('Issued', '#dbeafe', '#1e40af')
                    : (u.status === 'scrapped' ? pill('Scrapped', '#fee2e2', '#991b1b') : pill('In stock', '#dcfce7', '#166534'));
                return '<tr>'
                    + '<td>' + esc(u.serial_number || '—') + '</td>'
                    + '<td>' + esc(u.property_number || '—') + '</td>'
                    + '<td>' + fmtMoney(u.unit_value) + '</td>'
                    + '<td>' + st + '</td>'
                    + '<td>' + esc(u.issued_to || '—') + '</td>'
                    + '</tr>';
            }).join('')
            + '<tr class="units-total-row"><td colspan="2">TOTAL</td><td>' + fmtMoney(total) + '</td><td colspan="2"></td></tr>'
            + '</tbody></table></div>';
    }

    function openAddUnitModal() {
        document.getElementById('addUnitForm').style.display = 'block';
        document.getElementById('au_serial').value = '';
        document.getElementById('au_property').value = '';
        document.getElementById('au_value').value = '';
        document.getElementById('au_serial').focus();
    }

    function cancelAddUnit() {
        document.getElementById('addUnitForm').style.display = 'none';
    }

    function saveUnit() {
        const serial = document.getElementById('au_serial').value.trim();
        const property = document.getElementById('au_property').value.trim();
        const value = document.getElementById('au_value').value;
        if (!serial && !property) { toast('Maglagay ng serial o property number.', false); return; }
        api(PARTS_UNITS_STORE_PREFIX.replace('PART_ID', currentUnitsPartId), 'POST', {
            serial_number: serial, property_number: property, unit_value: value
        }).then(({ ok, data }) => {
            if (ok) {
                toast(data.message || 'Unit added', true);
                openUnitsModal(currentUnitsPartId);
                loadParts(partsPage || 1);
            } else {
                toast(data.message || 'Something went wrong.', false);
            }
        });
    }

    document.getElementById('partForm').addEventListener('submit', function (e) {
        e.preventDefault();
                const payload = {
            item_name: document.getElementById('p_item_name').value,
            unit: document.getElementById('p_unit').value,
            category: document.getElementById('p_category').value,
            on_hand_qty: document.getElementById('p_on_hand').value,
            reorder_level: document.getElementById('p_reorder').value,
            requires_unit_tracking: document.getElementById('p_requires_unit_tracking').checked
        };
        if (partMode === 'add') {
            api(PARTS_STORE_URL, 'POST', payload).then(({ ok, data }) => {
                if (ok) { toast(data.message || 'Saved', true); closeModal('partModal'); setTimeout(() => location.reload(), 400); }
                else showPartErrors(data);
            });
        } else {
            api(PARTS_UPDATE_PREFIX.replace('PART_ID', editingId), 'PUT', payload).then(({ ok, data }) => {
                if (ok) { toast(data.message || 'Saved', true); closeModal('partModal'); setTimeout(() => location.reload(), 400); }
                else showPartErrors(data);
            });
        }
    });

    function showPartErrors(data) {
        if (data.errors) {
                        ['item_name','unit','on_hand_qty','reorder_level','category'].forEach(k => {
                const el = document.getElementById('err_' + k);
                if (el && data.errors[k]) el.textContent = data.errors[k][0];
            });
            toast('There are validation errors.', false);
        } else {
            toast(data.message || 'Something went wrong.', false);
        }
    }

    document.getElementById('stockForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const url = (stockMode === 'in' ? PARTS_STOCK_IN_PREFIX : PARTS_STOCK_OUT_PREFIX).replace('PART_ID', stockPart.id);
        const payload = {
            qty: document.getElementById('s_qty').value,
            reason: document.getElementById('s_reason').value,
            reference_type: document.getElementById('s_ref').value
        };
        if (stockMode === 'in') {
            const lines = (document.getElementById('s_units').value || '').split('\n').map(l => l.trim()).filter(l => l !== '');
            if (lines.length) {
                payload.units = lines.map(line => {
                    const parts = line.split(',').map(x => x.trim());
                    return { serial_number: parts[0] || '', property_number: parts[1] || '' };
                });
            }
        } else {
            const sel = Array.from(document.querySelectorAll('.stock-unit-chk:checked')).map(c => parseInt(c.value, 10));
            if (sel.length) payload.unit_ids = sel;
            const iTo = document.getElementById('s_issued_to')?.value;
            const aId = document.getElementById('s_asset_id')?.value;
            const rId = document.getElementById('s_request_id')?.value;
            if (iTo) payload.issued_to = iTo;
            if (aId) payload.asset_id = aId;
            if (rId) payload.request_id = rId;
        }
        api(url, 'POST', payload).then(({ ok, data }) => {
            if (ok) { toast(data.message || 'Saved', true); closeModal('stockModal'); setTimeout(() => location.reload(), 400); }
            else if (data.errors) {
                ['qty','reason'].forEach(k => {
                    const el = document.getElementById('err_' + k);
                    if (el && data.errors[k]) el.textContent = data.errors[k][0];
                });
                toast('There are validation errors.', false);
            } else {
                toast(data.message || 'Something went wrong.', false);
            }
        });
    });

    function openHistory(id) {
        const name = partsById[id] ? partsById[id].item_name : '';
        document.getElementById('historyTitle').textContent = name + ' · History';
        const body = document.getElementById('historyBody');
        body.innerHTML = '<div class="empty-state"><div class="big">⏳</div>Loading...</div>';
        document.getElementById('historyModal').classList.add('open');
        api(PARTS_MOVEMENTS_PREFIX.replace('PART_ID', id), 'GET').then(({ ok, data }) => {
            if (!ok || !data.movements) { body.innerHTML = '<div class="empty-state">Unable to load.</div>'; return; }
            if (data.movements.length === 0) { body.innerHTML = '<div class="empty-state">No stock movements yet.</div>'; return; }
            body.innerHTML = data.movements.map(m => `
                <div class="movement-item">
                    <div class="movement-qty ${m.qty_change >= 0 ? 'mov-qty-in' : 'mov-qty-out'}">${m.qty_change >= 0 ? '+' : ''}${m.qty_change}</div>
                    <div class="movement-body">
                        <div class="movement-reason">${m.reason}</div>
                        <div class="movement-meta">${m.performed_by} · ${m.created_at}${m.reference_type ? ' · ' + m.reference_type : ''}</div>
                    </div>
                </div>`).join('');
        });
    }

</script>
@endsection
