@extends('layouts.app')

@section('title', 'Edit '.$purchaseRequest->pr_number.' | NCMB CMMS')
@section('page-title', 'Edit Purchase Request')

@section('styles')
<style nonce="{{ $cspNonce }}">
    .a60-wrap { width:100%; margin-top:-10px; }
    .a60-toolbar { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px; }

    /* ── Document sheet — max-width keeps it document-like on wide screens ── */
    .a60-sheet { background:#fff; border:1px solid #94a3b8; padding:30px 36px; font-family:'Times New Roman', Times, serif; color:#111; font-size:13px; max-width:900px; margin:0 auto; }

    .a60-title { text-align:center; font-size:16px; font-weight:bold; letter-spacing:.5px; margin:6px 0 14px; }

    /* ── Header field grid ── */
    .a60-hdr-wrap { width:100%; }
    .a60-hdr { width:100%; border-collapse:collapse; margin-bottom:0; font-size:12.5px; }
    .a60-hdr td { padding:0; vertical-align:top; }
    .a60-field { border:1px solid #374151; padding:5px 9px 4px; }
    .a60-field + .a60-field { border-left:none; }
    /* Desktop border helpers (replaces inline styles) */
    .hdr-br-none { border-right:none; }
    .hdr-bl { border-left:1px solid #374151; }
    .hdr-bt-none { border-top:none; }
    .a60-field-lbl { font-size:9.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#6b7280; margin-bottom:3px; display:flex; justify-content:space-between; align-items:center; }
    .a60-field-val { font-weight:700; color:#111827; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .a60-field-val.muted { color:#9ca3af; font-weight:400; font-style:italic; }

    .a60-line { display:block; width:100%; border:none; background:transparent; font-family:inherit; font-size:13px; font-weight:700; color:#1e3a8a; padding:0; min-width:0; }
    .a60-line:focus { outline:none; background:#f0f6ff; border-radius:2px; }
    textarea.a60-line { resize:vertical; font-weight:400; }

    /* â”€â”€ Section dividers â”€â”€ */
    .a60-section { border-top:1px solid #d1d5db; margin:18px 0 14px; }

    /* â”€â”€ Items table â”€â”€ */
    .a60-table { width:100%; border-collapse:collapse; margin-top:0; }
    .a60-table th, .a60-table td { border:1px solid #111; padding:6px 7px; vertical-align:middle; font-size:12.5px; }
    .a60-table th { text-align:center; font-weight:bold; background:#f8fafc; }
    .a60-table .cell-input { width:100%; border:none; border-bottom:1px dotted #94a3b8; background:transparent; font-family:inherit; font-size:13px; padding:3px 4px; color:#1e3a8a; font-weight:bold; }
    .a60-table .cell-input:focus { outline:none; border-bottom:2px solid #0038A8; background:#f0f6ff; }
    .a60-table .cell-input.is-invalid { border-bottom:2px solid #dc2626; background:#fef2f2; }
    .a60-table td.num-cell { text-align:center; }
    .a60-table td.right-cell { text-align:right; }
    .a60-table tfoot td { border:1px solid #111; padding:6px 7px; font-weight:bold; }
    .a60-table tfoot .total-label { text-align:right; font-weight:800; font-size:13px; }
    .a60-table tfoot .total-val { text-align:right; font-weight:800; font-size:13px; }

    /* â”€â”€ Purpose & Remarks boxes â”€â”€ */
    .a60-purpose-box { border:1px solid #374151; padding:5px 9px 6px; }
    .a60-char-count { font-size:9px; color:#94a3b8; font-weight:400; letter-spacing:0; text-transform:none; }
    .a60-remarks-box { border:1px solid #cbd5e1; background:#fafafa; padding:5px 9px 6px; margin-top:10px; }
    .a60-remarks-lbl { color:#94a3b8; }

    /* â”€â”€ Signature section â”€â”€ */
    .a60-signs { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:6px; font-size:13px; }
    .a60-signs .who { font-weight:bold; margin-bottom:10px; font-size:13px; }
    .a60-signs .srow { display:flex; gap:6px; margin-bottom:9px; align-items:baseline; }
    .a60-signs .sline { flex:1; border:none; border-bottom:1px solid #111; background:transparent; font-family:inherit; font-size:13px; font-weight:bold; color:#1e3a8a; padding:0 3px; }
    .a60-signs .sline:focus { outline:none; border-bottom:2px solid #0038A8; background:#f0f6ff; }
    .a60-signs .sstatic { flex:1; border-bottom:1px solid #111; font-weight:bold; padding:0 3px; min-height:17px; display:block; }
    .a60-signs .sig-note { font-size:10px; color:#94a3b8; font-style:italic; margin-top:4px; }

    /* ── Print ── */
    @media print {
        .a60-toolbar, .error-banner { display:none !important; }
        .a60-sheet { max-width:none; border:none; box-shadow:none; padding:0; }
    }
    @media screen and (max-width:900px){
        .a60-sheet { padding:18px; }
        .a60-table { min-width:660px; }
        .prf-scroll { overflow-x:auto; }
    }

    /* ===== UX polish ===== */
    .a60-toolbar { position:sticky; top:10px; z-index:30; background:rgba(255,255,255,.94); backdrop-filter:blur(6px); padding:9px 12px; border-radius:10px; border:1px solid #e2e8f0; box-shadow:0 2px 12px rgba(15,23,42,.08); max-width:900px; margin-left:auto; margin-right:auto; }
    .a60-sheet { box-shadow:0 6px 24px rgba(15,23,42,.07); border-radius:6px; }
    .a60-title { color:#0038A8; }

    tr.pr-item-row { animation:prRowIn .25s ease-out; }
    @keyframes prRowIn { from { opacity:0; transform:translateY(-3px); } to { opacity:1; transform:none; } }
    tr.pr-item-row:hover td { background:#f0f6ff; }
    tr.pr-item-row td:last-child { position:relative; }
    tr.pr-item-row .pr-x { position:absolute; right:-22px; top:50%; transform:translateY(-50%); width:20px; height:20px; line-height:18px; text-align:center; border-radius:50%; border:none; background:#fee2e2; color:#b91c1c; cursor:pointer; font-size:12px; opacity:0; transition:opacity .12s; padding:0; }
    tr.pr-item-row:hover .pr-x { opacity:1; }
    .pr-x:hover { background:#dc2626; color:#fff; }

    .cell-input.pr-unit { transition:background .3s; }
    @keyframes prUnitFlash { 0% { background:#bfdbfe; } 100% { background:transparent; } }
    .cell-input.pr-unit.flash-now { animation:prUnitFlash .9s ease-out; }

    #prGrandTotal.grand-active { color:#0038A8; }

    /* Unified action buttons */
    .cmms-action-btn { display:inline-flex; align-items:center; gap:7px; padding:8px 16px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; transition:transform .12s, box-shadow .12s, background .15s; text-decoration:none; border:1px solid transparent; font-family:'Segoe UI',Arial,sans-serif; }
    .cmms-action-btn i { font-size:11px; }
    .cmms-action-btn--primary { background:#0038A8; color:#fff; box-shadow:0 2px 8px rgba(0,56,168,.25); }
    .cmms-action-btn--primary:hover { background:#002d87; transform:translateY(-1px); box-shadow:0 3px 12px rgba(0,56,168,.32); }

    /* Toolbar buttons */
    .a60-toolbar .cmms-btn-secondary,
    .a60-toolbar .cmms-btn-primary { display:inline-flex; align-items:center; padding:6px 13px; border-radius:7px; font-size:11.5px; font-weight:700; transition:transform .12s, box-shadow .12s; }
    .a60-toolbar .cmms-btn-primary { box-shadow:0 2px 8px rgba(0,56,168,.25); }
    .a60-toolbar .cmms-btn-primary:hover { transform:translateY(-1px); box-shadow:0 3px 12px rgba(0,56,168,.32); }
    .a60-toolbar .cmms-btn-secondary:hover { background:#eef2ff; border-color:#0038A8; color:#0038A8; }

    /* Add row button */
    .cmms-pr-add-row { display:inline-flex; align-items:center; gap:6px; width:auto; margin-top:8px; padding:6px 13px; border:1.5px dashed #94a3b8; border-radius:7px; background:#f8fafc; color:#475569; font-size:11.5px; font-weight:700; cursor:pointer; transition:all .15s; }
    .cmms-pr-add-row:hover { border-color:#0038A8; color:#0038A8; background:#eff6ff; }
    .cmms-pr-add-row:focus-visible { outline:none; border-style:solid; border-color:#0038A8; box-shadow:0 0 0 3px rgba(0,56,168,.18); }
    .pr-row-badge { display:inline-flex; align-items:center; justify-content:center; background:#e2e8f0; color:#475569; font-size:10px; font-weight:800; border-radius:10px; padding:1px 7px; margin-left:2px; transition:background .2s, color .2s; }
    .pr-row-badge.has-rows { background:#dbeafe; color:#1e40af; }

    /* Error banner — outside sheet, uses sans-serif */
    .error-banner { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:10px 14px; border-radius:8px; font-size:13px; font-family:'Segoe UI',Arial,sans-serif; max-width:900px; margin:0 auto 12px; }

    /* ===== MOBILE RESPONSIVE (≤ 768px) ===== */
    @media screen and (max-width: 768px) {
        .a60-wrap {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            overflow-x: hidden !important;
            box-sizing: border-box !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .a60-toolbar {
            position: static !important;
            display: flex !important;
            flex-direction: row !important;
            gap: 8px !important;
            padding: 10px 12px !important;
            margin-bottom: 12px !important;
            border-radius: 10px !important;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06) !important;
            box-sizing: border-box !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        .a60-toolbar .cmms-btn-secondary {
            flex: 0 0 auto !important;
            min-height: 44px !important;
            padding: 10px 16px !important;
            font-size: 13px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        .a60-toolbar .cmms-action-btn--primary {
            flex: 1 1 auto !important;
            min-height: 44px !important;
            padding: 10px 14px !important;
            font-size: 13px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            text-align: center !important;
        }
        .a60-sheet {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif !important;
            padding: 14px 10px !important;
            border-radius: 10px !important;
            border: 1px solid #cbd5e1 !important;
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            box-sizing: border-box !important;
            overflow: hidden !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05) !important;
        }
        .a60-title {
            font-size: 16px !important;
            font-weight: 800 !important;
            letter-spacing: 0.5px !important;
            margin: 6px 0 14px !important;
            color: #0038A8 !important;
            text-align: center !important;
        }

        /* Header fields: Mobile Grid with PR No. and Date side-by-side */
        .a60-hdr-wrap {
            width: 100% !important;
            max-width: 100% !important;
            border: 1.5px solid #374151 !important;
            border-radius: 8px !important;
            overflow: hidden !important;
            background: #fff !important;
            box-sizing: border-box !important;
            margin-bottom: 14px !important;
        }
        table.a60-hdr {
            display: block !important;
            width: 100% !important;
            max-width: 100% !important;
            border: none !important;
            border-radius: 0 !important;
            overflow: visible !important;
            margin-bottom: 0 !important;
            background: #fff !important;
            box-sizing: border-box !important;
        }
        table.a60-hdr colgroup,
        table.a60-hdr col { display: none !important; }
        table.a60-hdr tbody {
            display: block !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        table.a60-hdr tr {
            display: flex !important;
            flex-wrap: wrap !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        table.a60-hdr td,
        table.a60-hdr td.a60-field {
            display: block !important;
            box-sizing: border-box !important;
        }
        table.a60-hdr td.a60-cell-entity,
        table.a60-hdr td.a60-cell-fund,
        table.a60-hdr td.a60-cell-office,
        table.a60-hdr td.a60-cell-rcc {
            display: block !important;
            width: 100% !important;
            flex: 0 0 100% !important;
            max-width: 100% !important;
            border-top: 0 !important;
            border-right: 0 !important;
            border-left: 0 !important;
            border-bottom: 1px solid #cbd5e1 !important;
            padding: 7px 10px !important;
            box-sizing: border-box !important;
            background: #fff !important;
        }
        table.a60-hdr td.a60-cell-pr {
            display: block !important;
            width: 50% !important;
            flex: 0 0 50% !important;
            max-width: 50% !important;
            border-top: 0 !important;
            border-left: 0 !important;
            border-right: 1px solid #cbd5e1 !important;
            border-bottom: 1px solid #cbd5e1 !important;
            padding: 7px 10px !important;
            box-sizing: border-box !important;
            background: #fff !important;
        }
        table.a60-hdr td.a60-cell-date {
            display: block !important;
            width: 50% !important;
            flex: 0 0 50% !important;
            max-width: 50% !important;
            border-top: 0 !important;
            border-left: 0 !important;
            border-right: 0 !important;
            border-bottom: 1px solid #cbd5e1 !important;
            padding: 7px 10px !important;
            box-sizing: border-box !important;
            background: #fff !important;
        }
        table.a60-hdr td.a60-cell-rcc {
            border-bottom: 0 !important;
        }
        .a60-field-lbl {
            font-size: 10px !important;
            font-weight: 800 !important;
            color: #64748b !important;
            text-transform: uppercase !important;
            letter-spacing: .04em !important;
            margin-bottom: 2px !important;
        }
        .a60-field-val {
            font-size: 12.5px !important;
            font-weight: 700 !important;
            white-space: normal !important;
            word-break: break-word !important;
            overflow: visible !important;
            line-height: 1.3 !important;
        }
        .a60-cell-pr .a60-field-val,
        .a60-cell-date .a60-field-val {
            font-size: 12px !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }
        .a60-line {
            background: #f8fafc !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            padding: 8px 10px !important;
            min-height: 40px !important;
            font-size: 14px !important;
            color: #0f172a !important;
            font-family: inherit !important;
            margin-top: 4px !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        .a60-line:focus {
            background: #fff !important;
            border-color: #0038A8 !important;
            box-shadow: 0 0 0 3px rgba(0, 56, 168, 0.12) !important;
        }

        /* Items Table — strictly confined so it never pushes the page to the right */
        .prf-scroll {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            background: #fff !important;
            margin-bottom: 10px !important;
            box-sizing: border-box !important;
            display: block !important;
        }
        .a60-table {
            min-width: 620px !important;
            border-collapse: collapse !important;
            width: 100% !important;
        }
        .a60-table th {
            background: #f8fafc !important;
            padding: 10px 8px !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: .03em !important;
            color: #475569 !important;
            border: 1px solid #e2e8f0 !important;
        }
        .a60-table td {
            padding: 8px 6px !important;
            border: 1px solid #e2e8f0 !important;
            vertical-align: middle !important;
        }
        .a60-table .cell-input {
            background: #f8fafc !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            padding: 7px 8px !important;
            font-size: 13px !important;
            min-height: 38px !important;
            box-sizing: border-box !important;
            font-family: inherit !important;
            font-weight: 600 !important;
            color: #1e293b !important;
        }
        .a60-table .cell-input:focus {
            background: #fff !important;
            border-color: #0038A8 !important;
            box-shadow: 0 0 0 2px rgba(0, 56, 168, 0.15) !important;
        }
        tr.pr-item-row .pr-x {
            opacity: 1 !important;
            position: static !important;
            transform: none !important;
            margin-left: 6px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 28px !important;
            height: 28px !important;
            border-radius: 6px !important;
            background: #fee2e2 !important;
            color: #b91c1c !important;
            border: none !important;
            cursor: pointer !important;
            font-size: 14px !important;
            font-weight: 800 !important;
            flex-shrink: 0 !important;
        }
        .pr-amount {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-end !important;
            gap: 6px !important;
            min-height: 38px !important;
        }
        .cmms-pr-add-row {
            width: 100% !important;
            justify-content: center !important;
            min-height: 44px !important;
            font-size: 13px !important;
            border-radius: 8px !important;
        }

        /* Purpose & Remarks */
        .a60-purpose-box, .a60-remarks-box {
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            padding: 10px 12px !important;
            margin-top: 10px !important;
            background: #fff !important;
        }
        textarea.a60-line {
            background: #f8fafc !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            padding: 8px 10px !important;
            min-height: 75px !important;
            font-size: 13.5px !important;
            box-sizing: border-box !important;
            font-family: inherit !important;
        }

        /* Signatures */
        .a60-signs {
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
            margin-top: 14px !important;
        }
        .a60-signs > div {
            background: #f8fafc !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            padding: 12px 14px !important;
        }
        .a60-signs .who {
            font-size: 13px !important;
            font-weight: 800 !important;
            color: #0f172a !important;
            margin-bottom: 8px !important;
        }
        .a60-signs .srow {
            margin-bottom: 8px !important;
        }
        .a60-signs .sline {
            background: #fff !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            padding: 6px 8px !important;
            min-height: 36px !important;
            font-size: 13px !important;
        }
    }
</style>
@endsection

@section('content')
<div class="a60-wrap">
    <div class="a60-toolbar" role="toolbar" aria-label="Purchase request actions">
        <a href="{{ route('purchase_requests.show', $purchaseRequest->id) }}" class="cmms-btn-secondary" aria-label="Back to purchase request document"><i class="fa-solid fa-arrow-left" style="margin-right:6px;"></i>Back</a>
        <button type="submit" form="prForm" class="cmms-action-btn cmms-action-btn--primary" aria-label="Save changes to purchase request"><i class="fa-solid fa-floppy-disk"></i>Save changes</button>
    </div>

    @if($errors->any())
        <div class="error-banner" role="alert">
            <strong><i class="fa-solid fa-circle-exclamation" style="margin-right:6px;"></i>Please check the form:</strong>
            <ul style="margin:6px 0 0 18px;padding:0;">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('purchase_requests.update', $purchaseRequest->id) }}" id="prForm" novalidate aria-label="Appendix 60 purchase request edit form">
        @csrf

        <div class="a60-sheet">
            {{-- â”€â”€ Document header badge â”€â”€ --}}
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
                <div style="font-size:11px;color:#6b7280;">Republic of the Philippines</div>
                <div style="font-size:11px;font-weight:700;color:#374151;letter-spacing:.5px;">Appendix 60</div>
            </div>

            <div class="a60-title">PURCHASE REQUEST</div>

            {{-- ── Header field grid ── --}}
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
                        <div class="a60-field-val">
                            <input type="text" name="fund_cluster"
                                   value="{{ old('fund_cluster', $purchaseRequest->fund_cluster) }}"
                                   class="a60-line" maxlength="64"
                                   placeholder="— enter fund cluster —"
                                   aria-label="Fund cluster">
                        </div>
                    </td>
                </tr>
                {{-- Row 2: Office/Unit | PR No. | Date --}}
                <tr>
                    <td class="a60-field a60-cell-office hdr-bt-none hdr-br-none">
                        <div class="a60-field-lbl">Office / Unit</div>
                        <div class="a60-field-val">
                            <input type="text" name="office_unit"
                                   value="{{ old('office_unit', $purchaseRequest->office_unit) }}"
                                   class="a60-line" maxlength="160"
                                   placeholder="— office or unit —"
                                   aria-label="Office or unit">
                        </div>
                    </td>
                    <td class="a60-field a60-cell-pr hdr-bt-none hdr-bl hdr-br-none">
                        <div class="a60-field-lbl">PR No.</div>
                        <div class="a60-field-val" style="color:#0038A8;">{{ $purchaseRequest->pr_number }}</div>
                    </td>
                    <td class="a60-field a60-cell-date hdr-bt-none hdr-bl">
                        <div class="a60-field-lbl">Date</div>
                        <div class="a60-field-val">{{ $purchaseRequest->created_at?->format('F d, Y') }}</div>
                    </td>
                </tr>
                {{-- Row 3: Responsibility Center Code --}}
                <tr>
                    <td class="a60-field a60-cell-rcc hdr-bt-none" colspan="3">
                        <div class="a60-field-lbl">Responsibility Center Code</div>
                        <div class="a60-field-val">
                            <input type="text" name="responsibility_center"
                                   value="{{ old('responsibility_center', $purchaseRequest->responsibility_center) }}"
                                   class="a60-line" maxlength="64"
                                   placeholder="— responsibility center code —"
                                   aria-label="Responsibility center code">
                        </div>
                    </td>
                </tr>
            </table>
            </div>

            {{-- ── Section divider ── --}}
            <div class="a60-section"></div>

            <div class="prf-scroll">
                <table class="a60-table">
                    <thead>
                        <tr>
                            <th style="width:12%;">Stock/ Property No.</th>
                            <th style="width:9%;">Unit</th>
                            <th style="width:35%;">Item Description</th>
                            <th style="width:9%;">Quantity</th>
                            <th style="width:16%;">Unit Cost (₱)</th>
                            <th style="width:16%;">Total Cost (₱)</th>
                        </tr>
                    </thead>
                    <tbody id="prItemsList"></tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="total-label">TOTAL</td>
                            <td class="total-val">
                                <span id="prGrandTotal" role="status" aria-live="polite" aria-label="Grand total amount">₱0.00</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @error('items')
                <div style="color:#b91c1c;font-size:12px;margin-top:6px;">{{ $message }}</div>
            @enderror
            <div style="margin-top:8px;">
                <button type="button" id="prAddRow" class="cmms-pr-add-row" aria-label="Add item row">
                    <i class="fa-solid fa-plus"></i>Add row
                    <span id="prLineCount" class="pr-row-badge" aria-live="polite">0</span>
                </button>
            </div>

            {{-- â”€â”€ Section divider â”€â”€ --}}
            <div class="a60-section"></div>

            {{-- â”€â”€ Purpose â”€â”€ --}}
            <div class="a60-purpose-box">
                <div class="a60-field-lbl">
                    <span>Purpose / Justification</span>
                    <span class="a60-char-count" id="purposeCount">0 / 1000</span>
                </div>
                <textarea name="purpose" id="purposeTextarea" rows="3" class="a60-line" style="min-height:50px;" maxlength="1000" aria-label="Purpose of purchase" placeholder="Describe the purpose or justification for this purchase requestâ€¦">{{ old('purpose', $purchaseRequest->purpose) }}</textarea>
            </div>

            {{-- â”€â”€ Section divider â”€â”€ --}}
            <div class="a60-section"></div>

            {{-- â”€â”€ Signatures â”€â”€ --}}
            <div class="a60-signs">
                <div>
                    <div class="who">Requested by:</div>
                    <div class="srow"><span style="white-space:nowrap;">Signature&nbsp;:</span><span class="sstatic">&nbsp;</span></div>
                    <div class="srow"><span style="white-space:nowrap;">Printed Name:</span><input type="text" class="sline" value="{{ $purchaseRequest->requester?->full_name ?: Auth::user()->full_name }}" readonly tabindex="-1" aria-label="Printed name of requester"></div>
                    <div class="srow"><span style="white-space:nowrap;">Designation:</span><span class="sstatic">{{ $purchaseRequest->requester?->position ?: Auth::user()->position ?: '___________________' }}</span></div>
                    <div class="sig-note">To be physically signed before submission</div>
                </div>
                <div>
                    <div class="who">Approved by:</div>
                    <div class="srow"><span style="white-space:nowrap;">Signature&nbsp;:</span><span class="sstatic">&nbsp;</span></div>
                    <div class="srow"><span style="white-space:nowrap;">Printed Name:</span><span class="sstatic">&nbsp;</span></div>
                    <div class="srow"><span style="white-space:nowrap;">Designation:</span><span class="sstatic">&nbsp;</span></div>
                    <div class="sig-note">To be signed upon approval</div>
                </div>
            </div>

            {{-- â”€â”€ Section divider â”€â”€ --}}
            <div class="a60-section"></div>

            {{-- â”€â”€ Internal Remarks (not printed) â”€â”€ --}}
            <div class="a60-remarks-box">
                <div class="a60-field-lbl a60-remarks-lbl">
                    <span>Internal Remarks</span>
                    <span style="font-size:9px;color:#d1d5db;font-weight:400;text-transform:none;letter-spacing:0;">Not printed on the official form</span>
                </div>
                <textarea name="remarks" rows="2" class="a60-line" style="font-weight:400;color:#374151;" maxlength="1000" placeholder="Optional internal notes for Supply Officerâ€¦" aria-label="Internal remarks, not printed on the official form">{{ old('remarks', $purchaseRequest->remarks) }}</textarea>
            </div>

        </div>
    </form>
</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
(function () {
    const list    = document.getElementById('prItemsList');
    const addBtn  = document.getElementById('prAddRow');
    const totalEl = document.getElementById('prGrandTotal');
    const countEl = document.getElementById('prLineCount');
    const catalog = @json($parts->map(fn ($p) => ['id' => $p->id, 'name' => $p->item_name, 'unit' => $p->unit]));
    const prefillItems = @json(old('items') ?: ($purchaseRequest->items ?? []));

    // Purpose char counter
    const purposeTa    = document.getElementById('purposeTextarea');
    const purposeCount = document.getElementById('purposeCount');
    function updatePurposeCount() {
        const len = purposeTa.value.length;
        purposeCount.textContent = len + ' / 1000';
        purposeCount.style.color = len > 900 ? '#ef4444' : len > 700 ? '#f59e0b' : '#94a3b8';
    }
    purposeTa.addEventListener('input', updatePurposeCount);
    updatePurposeCount();

    let rowSeq = 0;
    function rowHtml(item) {
        item = item || {};
        // Explicit per-row index is REQUIRED: repeated items[][] names are parsed
        // by PHP into separate single-key elements, losing quantity/cost pairing.
        const idx = rowSeq++;
        const n = (f) => 'items[' + idx + '][' + f + ']';
        const tr = document.createElement('tr');
        tr.className = 'pr-item-row';
        tr.dataset.partId = item.part_id || '';
        tr.innerHTML =
            '<td class="num-cell"><input type="text" name="' + n('property_no') + '" class="cell-input" value="" tabindex="-1" title="Filled upon inspection/receipt" aria-label="Stock or property number" style="color:#94a3b8;font-weight:400;" placeholder="—"></td>' +
            '<td class="num-cell"><input type="text" name="' + n('unit') + '" class="cell-input pr-unit" value="' + (item.unit || '') + '" maxlength="32" aria-label="Unit of measure"></td>' +
            '<td><input type="text" name="' + n('description') + '" class="cell-input pr-desc" value="' + (item.description || '') + '" maxlength="255" aria-label="Item description and specification"></td>' +
            '<td class="num-cell"><input type="number" name="' + n('quantity') + '" class="cell-input pr-qty" min="1" step="1" value="' + (item.quantity ?? '') + '" style="text-align:center;" aria-label="Quantity"></td>' +
            '<td class="right-cell"><input type="number" name="' + n('unit_cost') + '" class="cell-input pr-cost" min="0" step="0.01" value="' + (item.unit_cost ?? '') + '" style="text-align:right;" aria-label="Unit cost in Philippine peso"></td>' +
            '<td class="right-cell pr-amount" style="font-weight:bold;position:relative;">—<button type="button" class="pr-x" title="Remove row" aria-label="Remove this item row" tabindex="-1">×</button></td>';
        return tr;
    }

    function recalc() {
        let grand = 0;
        const rows = list.querySelectorAll('.pr-item-row');
        rows.forEach((tr) => {
            const qty  = parseInt(tr.querySelector('.pr-qty').value, 10) || 0;
            const cost = parseFloat(tr.querySelector('.pr-cost').value) || 0;
            const amt  = qty * cost;
            grand += amt;
            tr.querySelector('.pr-amount').firstChild.textContent =
                (qty > 0 && cost > 0) ? amt.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';
        });
        totalEl.textContent = '₱' + grand.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        totalEl.classList.toggle('grand-active', grand > 0);
        const count = rows.length;
        countEl.textContent = count;
        countEl.classList.toggle('has-rows', count > 0);
    }

    function addItem(item) { list.appendChild(rowHtml(item)); recalc(); }

    addBtn.addEventListener('click', () => addItem({}));

    list.addEventListener('click', (e) => {
        const btn = e.target.closest('.pr-x');
        if (!btn) return;
        btn.closest('tr').remove();
        if (!list.querySelector('.pr-item-row')) addItem({});
        recalc();
    });

    list.addEventListener('input', (e) => {
        if (e.target.matches('.pr-qty, .pr-cost')) { recalc(); return; }
        if (e.target.matches('.pr-desc')) {
            const tr        = e.target.closest('.pr-item-row');
            const unitInput = tr?.querySelector('.pr-unit');
            if (tr && unitInput && unitInput.value.trim() === '') {
                const term = e.target.value.trim().toLowerCase();
                if (term.length >= 3) {
                    const hit = catalog.find((p) =>
                        p.name.toLowerCase().includes(term) || term.includes(p.name.toLowerCase())
                    );
                    if (hit && hit.unit) {
                        unitInput.value = hit.unit;
                        tr.dataset.partId = hit.id;
                        unitInput.classList.remove('flash-now');
                        void unitInput.offsetWidth;
                        unitInput.classList.add('flash-now');
                        setTimeout(() => unitInput.classList.remove('flash-now'), 950);
                    }
                }
                const qtyInput = tr?.querySelector('.pr-qty');
                if (qtyInput && (!qtyInput.value || parseInt(qtyInput.value, 10) < 1)) {
                    qtyInput.value = 1;
                    qtyInput.classList.remove('flash-now');
                    void qtyInput.offsetWidth;
                    qtyInput.classList.add('flash-now');
                    setTimeout(() => qtyInput.classList.remove('flash-now'), 950);
                }
            }
            recalc();
        }
    });

    prefillItems.filter((it) => String(it.description || '').trim() !== '').forEach(addItem);
    while (list.querySelectorAll('.pr-item-row').length < 4) addItem({});

    document.getElementById('prForm').addEventListener('submit', (e) => {
        let incomplete = false;
        list.querySelectorAll('.pr-item-row').forEach((tr) => {
            const descInput = tr.querySelector('.pr-desc');
            const qtyInput  = tr.querySelector('.pr-qty');
            const costInput = tr.querySelector('.pr-cost');
            const desc = descInput.value.trim();
            const cost = costInput.value;
            const qty  = qtyInput.value;
            const started = desc !== '' || cost !== '' || (qty !== '' && parseInt(qty, 10) > 0);
            if (!started) { tr.remove(); return; }
            if (desc === '') {
                descInput.classList.add('is-invalid');
                incomplete = true;
                return;
            }
            if (!qty || parseInt(qty, 10) < 1) qtyInput.value = 1;
            descInput.classList.remove('is-invalid');
        });
        const remaining = [...list.querySelectorAll('.pr-item-row')];
        if (remaining.length === 0 || incomplete === true) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: remaining.length === 0 ? 'No items' : 'Incomplete item',
                text: remaining.length === 0
                    ? 'Add at least one item with a description.'
                    : 'An item has a quantity/cost but no description. Fill in the highlighted description or remove the row.',
                confirmButtonColor: '#0038A8',
            });
            recalc();
            return;
        }
        recalc();
    });
})();
</script>
@endsection

